<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Suite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index(Request $request, Suite $suite): JsonResponse
    {
        $agents = $suite->agents()->ordered()->get();

        return response()->json($agents);
    }

    public function indexAll(Request $request): JsonResponse
    {
        $agents = Agent::with('suite')
            ->whereHas('suite', function ($query) use ($request) {
                // Filter by user's accessible suites
            })
            ->get();

        // Add can_delete and days_remaining info for each agent
        $agents->each(function ($agent) {
            $agent->can_delete = $agent->canBeDeleted();
            if ($agent->is_active && !$agent->can_delete) {
                $agent->days_remaining = max(0, 60 - $agent->created_at->diffInDays(now()));
            }
        });

        return response()->json($agents);
    }

    public function store(Request $request, Suite $suite): JsonResponse
    {
        // Convert external_api_configs to integers before validation
        if ($request->has('external_api_configs') && is_array($request->external_api_configs)) {
            $request->merge([
                'external_api_configs' => array_values(
                    array_filter(
                        array_map(function ($id) {
                            return is_numeric($id) ? (int) $id : null;
                        }, $request->external_api_configs),
                        function ($id) {
                            return $id !== null;
                        }
                    )
                )
            ]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'model_provider' => 'required|in:openai,gemini,mistral,claude,anthropic',
            'model_name' => 'required|string',
            'system_prompt' => 'nullable|string',
            'prompt_template' => 'nullable|array',
            'model_config' => 'nullable|array',
            'external_api_configs' => 'nullable|array',
            'external_api_configs.*' => 'integer|exists:external_api_configs,id',
            'enable_rag' => 'boolean',
            'enable_web_search' => 'boolean',
            'enable_external_apis' => 'boolean',
            'order' => 'integer',
            'metadata' => 'nullable|array',
        ]);

        $agent = Agent::create([
            'suite_id' => $suite->id,
            'name' => $request->name,
            'description' => $request->description,
            'slug' => Str::slug($request->name),
            'model_provider' => $request->model_provider,
            'model_name' => $request->model_name,
            'system_prompt' => $request->system_prompt,
            'prompt_template' => $request->prompt_template,
            'model_config' => $request->model_config,
            'external_api_configs' => $request->external_api_configs ?? [],
            'enable_rag' => $request->boolean('enable_rag', false),
            'enable_web_search' => $request->boolean('enable_web_search', false),
            'metadata' => $request->metadata ?? [],
            'order' => $request->order ?? 0,
        ]);

        return response()->json($agent, 201);
    }

    public function show(Agent $agent): JsonResponse
    {
        return response()->json($agent);
    }

    public function update(Request $request, Agent $agent): JsonResponse
    {
        // Convert external_api_configs to integers before validation
        if ($request->has('external_api_configs') && is_array($request->external_api_configs)) {
            $request->merge([
                'external_api_configs' => array_values(
                    array_filter(
                        array_map(function ($id) {
                            return is_numeric($id) ? (int) $id : null;
                        }, $request->external_api_configs),
                        function ($id) {
                            return $id !== null;
                        }
                    )
                )
            ]);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'suite_id' => 'sometimes|integer|exists:suites,id',
            'model_provider' => 'sometimes|in:openai,gemini,mistral,claude,anthropic',
            'model_name' => 'sometimes|string',
            'system_prompt' => 'nullable|string',
            'prompt_template' => 'nullable|array',
            'model_config' => 'nullable|array',
            'external_api_configs' => 'nullable|array',
            'external_api_configs.*' => 'integer|exists:external_api_configs,id',
            'enable_rag' => 'boolean',
            'enable_web_search' => 'boolean',
            'is_active' => 'boolean',
            'order' => 'integer',
            'metadata' => 'nullable|array',
        ]);

        $updateData = $request->only([
            'name',
            'description',
            'suite_id',
            'model_provider',
            'model_name',
            'system_prompt',
            'prompt_template',
            'model_config',
            'external_api_configs',
            'enable_rag',
            'enable_web_search',
            'enable_external_apis',
            'is_active',
            'order',
            'metadata',
        ]);

        // Merge metadata if it exists, to preserve existing metadata fields
        if ($request->has('metadata') && is_array($request->metadata)) {
            $existingMetadata = $agent->metadata ?? [];
            $updateData['metadata'] = array_merge($existingMetadata, $request->metadata);
        }

        $agent->update($updateData);

        return response()->json($agent);
    }

    public function archive(Request $request, Agent $agent): JsonResponse
    {
        if ($agent->isArchived()) {
            return response()->json(['message' => 'Agent is already archived'], 400);
        }

        $agent->update([
            'archived_at' => now(),
            'is_active' => false,
        ]);

        return response()->json(['message' => 'Agent archived successfully', 'agent' => $agent->fresh()]);
    }

    public function destroy(Agent $agent): JsonResponse
    {
        // Check if agent can be deleted
        if (!$agent->canBeDeleted()) {
            $daysRemaining = 60 - $agent->created_at->diffInDays(now());
            return response()->json([
                'error' => 'Cannot delete active agent',
                'message' => "Active agents can only be deleted after 60 days. {$daysRemaining} days remaining.",
                'can_delete' => false,
                'days_remaining' => $daysRemaining,
            ], 403);
        }

        $agent->delete();

        return response()->json(['message' => 'Agent deleted successfully']);
    }

    public function getFiles(Agent $agent): JsonResponse
    {
        // Get all active files for this agent (exclude soft-deleted)
        $files = $agent->files()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($files);
    }
}

