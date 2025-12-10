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
        ]);

        $updateData = $request->only([
            'name',
            'description',
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
        ]);

        $agent->update($updateData);

        return response()->json($agent);
    }

    public function destroy(Agent $agent): JsonResponse
    {
        $agent->delete();

        return response()->json(['message' => 'Agent deleted']);
    }
}

