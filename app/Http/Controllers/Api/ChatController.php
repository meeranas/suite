<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Suite;
use App\Models\Agent;
use App\Services\Workflow\WorkflowOrchestrator;
use App\Services\Agent\AgentExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected WorkflowOrchestrator $workflowOrchestrator;
    protected AgentExecutionService $agentExecutionService;

    public function __construct(
        WorkflowOrchestrator $workflowOrchestrator,
        AgentExecutionService $agentExecutionService
    ) {
        $this->workflowOrchestrator = $workflowOrchestrator;
        $this->agentExecutionService = $agentExecutionService;
    }

    public function index(Request $request): JsonResponse
    {
        // dd("index");
        $chats = Chat::where('user_id', $request->user()->id)
            ->with(['suite', 'workflow'])
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);

        return response()->json($chats);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'suite_id' => 'required|exists:suites,id',
            'workflow_id' => 'nullable|exists:agent_workflows,id',
            'agent_id' => 'nullable|exists:agents,id',
            'title' => 'nullable|string|max:255',
        ]);

        // Ensure either workflow_id or agent_id is provided, but not both
        if (!$request->workflow_id && !$request->agent_id) {
            return response()->json([
                'error' => 'Either workflow_id or agent_id must be provided',
            ], 422);
        }

        if ($request->workflow_id && $request->agent_id) {
            return response()->json([
                'error' => 'Cannot specify both workflow_id and agent_id',
            ], 422);
        }

        $chat = Chat::create([
            'user_id' => $request->user()->id,
            'suite_id' => $request->suite_id,
            'workflow_id' => $request->workflow_id,
            'agent_id' => $request->agent_id,
            'title' => $request->title,
        ]);

        $chat->load(['suite', 'workflow', 'agent']);

        return response()->json($chat, 201);
    }

    public function show(Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $chat->load(['messages.agent', 'suite', 'workflow', 'agent']);

        // Determine allowed data sources based on suite's agents
        $allowedDataSources = $this->getAllowedDataSources($chat->suite);

        // Add allowed data sources to chat response
        $chatData = $chat->toArray();
        $chatData['allowed_data_sources'] = $allowedDataSources;

        return response()->json($chatData);
    }

    /**
     * Get allowed data sources for a suite based on its agents
     */
    protected function getAllowedDataSources(?Suite $suite): array
    {
        if (!$suite) {
            return [
                'allow_rag' => false,
                'allow_web_search' => false,
                'allow_external_apis' => false,
                'external_api_configs' => [],
            ];
        }

        // Load agents for the suite
        $agents = $suite->agents()->where('is_active', true)->get();

        $allowRag = $agents->contains(function ($agent) {
            return $agent->enable_rag === true;
        });

        $allowWebSearch = $agents->contains(function ($agent) {
            return $agent->enable_web_search === true;
        });

        // Check if any agent has external APIs enabled
        $allowExternalApis = $agents->contains(function ($agent) {
            return $agent->enable_external_apis === true;
        });

        // Collect all external API configs from agents that have external APIs enabled
        $externalApiConfigs = [];
        foreach ($agents as $agent) {
            if ($agent->enable_external_apis && !empty($agent->external_api_configs) && is_array($agent->external_api_configs)) {
                $externalApiConfigs = array_merge($externalApiConfigs, $agent->external_api_configs);
            }
        }
        $externalApiConfigs = array_unique($externalApiConfigs);

        return [
            'allow_rag' => $allowRag,
            'allow_web_search' => $allowWebSearch,
            'allow_external_apis' => $allowExternalApis,
            'external_api_configs' => array_values($externalApiConfigs),
        ];
    }

    public function sendMessage(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $request->validate([
            'message' => 'required|string',
        ]);

        // Store user message
        $userMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $request->message,
            'order' => $chat->messages()->count() + 1,
        ]);

        // Update chat
        $chat->update([
            'last_message_at' => now(),
            'title' => $chat->title ?? substr($request->message, 0, 50),
        ]);

        // Execute workflow if available
        if ($chat->workflow) {
            try {

                logger("executing workflow");
                logger($chat->workflow);
                $result = $this->workflowOrchestrator->execute(
                    $chat->workflow,
                    $chat,
                    $request->message
                );

                return response()->json([
                    'message' => $userMessage,
                    'response' => $result['response'],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Workflow execution failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Single agent response
        if ($chat->agent) {
            try {
                $result = $this->agentExecutionService->execute(
                    $chat->agent,
                    $chat,
                    $request->message
                );

                return response()->json([
                    'message' => $userMessage,
                    'response' => $result['content'],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Agent execution failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'error' => 'No workflow or agent configured for this chat',
        ], 422);
    }

    public function getFiles(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $files = $chat->files()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($files);
    }

    public function destroy(Chat $chat): JsonResponse
    {
        $this->authorize('delete', $chat);

        $chat->delete();

        return response()->json(['message' => 'Chat deleted']);
    }
}

