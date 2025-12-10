<?php

namespace App\Services\Workflow;

use App\Models\AgentWorkflow;
use App\Models\Chat;
use App\Models\Message;
use App\Services\AI\AiModelService;
use App\Services\RAG\RagService;
use App\Services\Search\SearchService;
use App\Services\ExternalApi\ExternalApiService;
use Illuminate\Support\Facades\Log;

class WorkflowOrchestrator
{
    protected AiModelService $aiService;
    protected RagService $ragService;
    protected SearchService $searchService;
    protected ExternalApiService $externalApiService;

    public function __construct(
        AiModelService $aiService,
        RagService $ragService,
        SearchService $searchService,
        ExternalApiService $externalApiService
    ) {
        $this->aiService = $aiService;
        $this->ragService = $ragService;
        $this->searchService = $searchService;
        $this->externalApiService = $externalApiService;
    }

    /**
     * Execute workflow chain
     */
    public function execute(AgentWorkflow $workflow, Chat $chat, string $userMessage): array
    {
        $agents = $workflow->agents;
        $context = [
            'chat_id' => $chat->id,
            'user_id' => $chat->user_id,
            'messages' => [],
        ];

        $finalResponse = '';

        $previousAgentOutput = null;
        foreach ($agents as $agent) {
            if (!$agent->is_active) {
                continue;
            }

            try {
                // Pass previous agent output to current agent
                $response = $this->executeAgent($agent, $userMessage, $context, $chat, $previousAgentOutput);

                $context['messages'][] = [
                    'agent' => $agent->name,
                    'response' => $response['content'],
                ];

                $finalResponse = $response['content'];
                $previousAgentOutput = $response['content']; // Pass to next agent

                // Store message
                Message::create([
                    'chat_id' => $chat->id,
                    'agent_id' => $agent->id,
                    'role' => 'assistant',
                    'content' => $response['content'],
                    'metadata' => [
                        'tokens_used' => $response['tokens_used'] ?? [],
                        'model' => $response['model'] ?? $agent->model_name,
                    ],
                    'rag_context' => $response['rag_context'] ?? null,
                    'external_data' => $response['external_data'] ?? null,
                    'order' => $chat->messages()->count() + 1,
                ]);

                // Log usage
                $this->aiService->logUsage(
                    $agent,
                    $response['tokens_used'] ?? [],
                    $chat->user_id,
                    $chat->id
                );
            } catch (\Exception $e) {
                Log::error('Agent execution failed', [
                    'agent_id' => $agent->id,
                    'workflow_id' => $workflow->id,
                    'error' => $e->getMessage(),
                ]);

                // Continue with next agent or break based on workflow config
                if ($workflow->workflow_config['stop_on_error'] ?? false) {
                    throw $e;
                }
            }
        }

        return [
            'response' => $finalResponse,
            'context' => $context,
        ];
    }

    /**
     * Execute single agent
     */
    protected function executeAgent($agent, string $userMessage, array $context, Chat $chat, ?string $previousAgentOutput = null): array
    {
        $ragContext = [];
        $webSearchResults = [];
        $externalData = [];

        // Get RAG context if enabled (scoped to this chat's documents only)
        if ($agent->enable_rag) {
            $ragContext = $this->ragService->searchContext(
                $userMessage,
                $chat->user,
                $chat->id, // Pass chat_id to filter documents
                5
            );
        }

        // Perform web search if enabled
        if ($agent->enable_web_search) {
            try {
                $searchResults = $this->searchService->search($userMessage);
                $webSearchResults = $searchResults['results'] ?? [];
            } catch (\Exception $e) {
                Log::warning('Web search failed', ['error' => $e->getMessage()]);
            }
        }

        // Get external API data if configured
        if (!empty($agent->external_api_configs)) {
            try {
                $externalData = $this->externalApiService->fetchData(
                    $agent->external_api_configs,
                    $userMessage
                );
            } catch (\Exception $e) {
                Log::warning('External API fetch failed', [
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Get chat history (last 3 turns for context continuity)
        $chatHistory = $this->getChatHistory($chat, 3);

        // Build context for AI
        $aiContext = [
            'rag' => array_column($ragContext, 'content'),
            'web_search' => $webSearchResults,
            'external_data' => $externalData,
            'chat_history' => $chatHistory,
        ];

        // Generate AI response (pass previous agent output for workflow chains)
        $response = $this->aiService->generateResponse($agent, $userMessage, $aiContext, $previousAgentOutput);

        return array_merge($response, [
            'rag_context' => $ragContext,
            'external_data' => $externalData,
        ]);
    }

    /**
     * Get chat history for context (last N turns)
     */
    protected function getChatHistory(Chat $chat, int $turns = 3): array
    {
        // Get last N user-assistant pairs
        $messages = $chat->messages()
            ->orderBy('order', 'desc')
            ->limit($turns * 2) // N turns = N user + N assistant messages
            ->get()
            ->reverse()
            ->values();

        $history = [];
        foreach ($messages as $message) {
            $history[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $history;
    }

}

