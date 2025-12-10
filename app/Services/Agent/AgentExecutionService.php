<?php

namespace App\Services\Agent;

use App\Models\Agent;
use App\Models\Chat;
use App\Models\Message;
use App\Services\AI\AiModelService;
use App\Services\RAG\RagService;
use App\Services\Search\SearchService;
use App\Services\ExternalApi\ExternalApiService;
use App\Services\Tool\ToolDefinitionService;
use App\Services\Tool\ToolExecutionService;
use Illuminate\Support\Facades\Log;

class AgentExecutionService
{
    protected AiModelService $aiService;
    protected RagService $ragService;
    protected SearchService $searchService;
    protected ExternalApiService $externalApiService;
    protected ToolDefinitionService $toolDefinitionService;
    protected ToolExecutionService $toolExecutionService;

    public function __construct(
        AiModelService $aiService,
        RagService $ragService,
        SearchService $searchService,
        ExternalApiService $externalApiService,
        ToolDefinitionService $toolDefinitionService,
        ToolExecutionService $toolExecutionService
    ) {
        $this->aiService = $aiService;
        $this->ragService = $ragService;
        $this->searchService = $searchService;
        $this->externalApiService = $externalApiService;
        $this->toolDefinitionService = $toolDefinitionService;
        $this->toolExecutionService = $toolExecutionService;
    }

    /**
     * Execute single agent with chat history context
     */
    public function execute(Agent $agent, Chat $chat, string $userMessage): array
    {
        // Get chat history (last 3 turns for context)
        $chatHistory = $this->getChatHistory($chat, 3);

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

        // Perform web search if enabled (only if tools are NOT being used)
        // When using tools, web search should be a tool that AI calls on-demand
        // For now, we'll skip automatic web search when tools are available
        $useTools = $agent->enable_external_apis && !empty($agent->external_api_configs);

        if ($agent->enable_web_search && !$useTools) {
            try {
                $searchResults = $this->searchService->search($userMessage);
                $webSearchResults = $searchResults['results'] ?? [];
            } catch (\Exception $e) {
                Log::warning('Web search failed', [
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Generate tools from external API configs if enabled
        $tools = null;
        $availableApiConfigs = null;
        if ($agent->enable_external_apis && !empty($agent->external_api_configs)) {
            try {
                $tools = $this->toolDefinitionService->generateTools($agent->external_api_configs);
                $availableApiConfigs = $agent->external_api_configs;

                // If tools are available, use tool-based approach (AI will call tools as needed)
                // Otherwise, fall back to old approach (fetch all data upfront)
                if (empty($tools)) {
                    // Fallback: fetch external API data upfront
                    $externalData = $this->externalApiService->fetchData(
                        $agent->external_api_configs,
                        $userMessage
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Tool generation failed, falling back to direct API calls', [
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
                // Fallback: fetch external API data upfront
                $externalData = $this->externalApiService->fetchData(
                    $agent->external_api_configs,
                    $userMessage
                );
            }
        } else {
            // External APIs not enabled, but might have been called directly before
            $externalData = [];
        }

        // Build context for AI (include chat history)
        // Pass full RAG context with metadata (not just content) so filenames are preserved
        $aiContext = [
            'rag' => $ragContext, // Pass full context array with metadata
            'web_search' => $webSearchResults,
            'external_data' => $externalData ?? [],
            'chat_history' => $chatHistory,
        ];

        // Generate AI response with tools if available
        // Inject ToolExecutionService into AiModelService if needed
        if ($tools !== null && $this->toolExecutionService) {
            // Use reflection or dependency injection to set toolExecutionService
            $reflection = new \ReflectionClass($this->aiService);
            $property = $reflection->getProperty('toolExecutionService');
            $property->setAccessible(true);
            $property->setValue($this->aiService, $this->toolExecutionService);
        }

        $response = $this->aiService->generateResponse(
            $agent,
            $userMessage,
            $aiContext,
            null,
            $tools,
            $availableApiConfigs
        );

        // Store assistant message
        Message::create([
            'chat_id' => $chat->id,
            'agent_id' => $agent->id,
            'role' => 'assistant',
            'content' => $response['content'],
            'metadata' => [
                'tokens_used' => $response['tokens_used'] ?? [],
                'model' => $response['model'] ?? $agent->model_name,
            ],
            'rag_context' => $ragContext,
            'external_data' => $externalData,
            'order' => $chat->messages()->count() + 1,
        ]);

        // Log usage
        $this->aiService->logUsage(
            $agent,
            $response['tokens_used'] ?? [],
            $chat->user_id,
            $chat->id
        );

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





