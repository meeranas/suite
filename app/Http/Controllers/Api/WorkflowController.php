<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentWorkflow;
use App\Models\Suite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function index(Request $request, Suite $suite): JsonResponse
    {
        $workflows = $suite->workflows()->active()->get();

        return response()->json($workflows);
    }

    public function indexAll(Request $request): JsonResponse
    {
        $workflows = AgentWorkflow::with('suite')
            ->active()
            ->get();

        return response()->json($workflows);
    }

    public function store(Request $request, Suite $suite): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agent_sequence' => 'required|array',
            'agent_sequence.*' => 'exists:agents,id',
            'workflow_config' => 'nullable|array',
        ]);

        $workflow = AgentWorkflow::create([
            'suite_id' => $suite->id,
            'name' => $request->name,
            'description' => $request->description,
            'agent_sequence' => $request->agent_sequence,
            'workflow_config' => $request->workflow_config,
        ]);

        return response()->json($workflow, 201);
    }

    public function update(Request $request, AgentWorkflow $workflow): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'agent_sequence' => 'sometimes|array',
            'agent_sequence.*' => 'exists:agents,id',
            'workflow_config' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $workflow->update($request->only([
            'name',
            'description',
            'agent_sequence',
            'workflow_config',
            'is_active',
        ]));

        return response()->json($workflow);
    }

    public function destroy(AgentWorkflow $workflow): JsonResponse
    {
        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted']);
    }
}
