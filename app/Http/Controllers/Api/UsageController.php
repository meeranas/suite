<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin can see all usage, regular users see only their own
        $query = UsageLog::query();

        if (!$user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by suite if provided
        if ($request->has('suite_id')) {
            $query->where('suite_id', $request->suite_id);
        }

        // Filter by agent if provided
        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        $usage = $query->with(['user', 'suite', 'agent'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        // Calculate summary statistics
        $summary = UsageLog::query();
        if (!$user->hasRole('admin')) {
            $summary->where('user_id', $user->id);
        }
        if ($request->has('start_date')) {
            $summary->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $summary->whereDate('created_at', '<=', $request->end_date);
        }

        $summaryData = [
            'total_requests' => $summary->count(),
            'total_input_tokens' => $summary->sum('input_tokens'),
            'total_output_tokens' => $summary->sum('output_tokens'),
            'total_cost' => $summary->sum('cost_usd'),
        ];

        // Calculate suite-level breakdown (only for admin)
        $suiteBreakdown = [];
        if ($user->hasRole('admin')) {
            $suiteBreakdownQuery = UsageLog::query();

            // Apply same filters as summary (except suite_id - we want all suites in breakdown)
            if ($request->has('start_date')) {
                $suiteBreakdownQuery->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $suiteBreakdownQuery->whereDate('created_at', '<=', $request->end_date);
            }

            $suiteBreakdown = $suiteBreakdownQuery
                ->selectRaw('suite_id, 
                    COUNT(*) as total_requests,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(cost_usd) as total_cost')
                ->whereNotNull('suite_id')
                ->groupBy('suite_id')
                ->with('suite:id,name')
                ->get()
                ->map(function ($item) {
                    return [
                        'suite_id' => $item->suite_id,
                        'suite_name' => $item->suite->name ?? 'Unknown Suite',
                        'total_requests' => (int) $item->total_requests,
                        'total_input_tokens' => (int) $item->total_input_tokens,
                        'total_output_tokens' => (int) $item->total_output_tokens,
                        'total_cost' => (float) $item->total_cost,
                    ];
                })
                ->sortByDesc('total_cost')
                ->values();
        }

        return response()->json([
            'data' => $usage->items(),
            'pagination' => [
                'current_page' => $usage->currentPage(),
                'last_page' => $usage->lastPage(),
                'per_page' => $usage->perPage(),
                'total' => $usage->total(),
            ],
            'summary' => $summaryData,
            'suite_breakdown' => $suiteBreakdown,
        ]);
    }
}

