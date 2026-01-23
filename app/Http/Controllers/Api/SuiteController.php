<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Suite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SuiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tier = $user->subscription_tier[0] ?? null;

        $query = Suite::query();

        // Filter by subscription tier

        if (!$user->hasRole('admin') && $tier) {
            $query->forTier($tier);
        }

        // Admin can see all, users see only active
        if (!$user->hasRole('admin')) {
            $query->active();
        }

        $suites = $query->with([
            'agents' => function ($q) {
                $q->active()->ordered();
            }
        ])->get();

        // Add can_delete and days_remaining info for each suite
        $suites->each(function ($suite) {
            $suite->can_delete = $suite->canBeDeleted();
            if ($suite->status === 'active' && !$suite->can_delete) {
                $suite->days_remaining = max(0, 60 - $suite->created_at->diffInDays(now()));
            }
        });

        return response()->json($suites);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in(['active', 'hidden', 'archived'])],
            'subscription_tiers' => 'nullable|array',
        ]);

        $suite = Suite::create([
            'name' => $request->name,
            'description' => $request->description,
            'slug' => Str::slug($request->name),
            'status' => $request->status ?? 'hidden',
            'subscription_tiers' => $request->subscription_tiers,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($suite, 201);
    }

    public function show(Suite $suite): JsonResponse
    {
        $suite->load(['agents', 'workflows']);

        return response()->json($suite);
    }

    public function update(Request $request, Suite $suite): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => ['sometimes', Rule::in(['active', 'hidden', 'archived'])],
            'subscription_tiers' => 'nullable|array',
        ]);

        $suite->update($request->only(['name', 'description', 'status', 'subscription_tiers']));

        return response()->json($suite);
    }

    public function archive(Request $request, Suite $suite): JsonResponse
    {
        if ($suite->isArchived()) {
            return response()->json(['message' => 'Suite is already archived'], 400);
        }

        $suite->update([
            'archived_at' => now(),
            'status' => 'archived',
        ]);

        return response()->json(['message' => 'Suite archived successfully', 'suite' => $suite->fresh()]);
    }

    public function destroy(Suite $suite): JsonResponse
    {
        // Check if suite can be deleted
        if (!$suite->canBeDeleted()) {
            $daysRemaining = 60 - $suite->created_at->diffInDays(now());
            return response()->json([
                'error' => 'Cannot delete active suite',
                'message' => "Active suites can only be deleted after 60 days. {$daysRemaining} days remaining.",
                'can_delete' => false,
                'days_remaining' => $daysRemaining,
            ], 403);
        }

        $suite->delete();

        return response()->json(['message' => 'Suite deleted successfully']);
    }
}

