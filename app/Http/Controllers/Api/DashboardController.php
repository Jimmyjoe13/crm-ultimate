<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $openDeals = Deal::query()->where('status', 'open');

        $openDealsCount = (clone $openDeals)->count();
        $openDealsValue = (clone $openDeals)->sum('amount');

        $wonThisMonth = Deal::query()
            ->where('status', 'won')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $lostThisMonth = Deal::query()
            ->where('status', 'lost')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $closedLast30 = Deal::query()
            ->whereIn('status', ['won', 'lost'])
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $wonLast30 = Deal::query()
            ->where('status', 'won')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $conversionRate = $closedLast30 > 0 ? round($wonLast30 / $closedLast30, 2) : 0;

        $now = now();
        $activitiesDueCount = Activity::query()
            ->where('type', 'task')
            ->where('status', 'open')
            ->where('due_at', '>=', $now)
            ->count();

        $activitiesOverdueCount = Activity::query()
            ->where('type', 'task')
            ->where('status', 'open')
            ->where('due_at', '<', $now)
            ->whereNotNull('due_at')
            ->count();

        $dealsByStage = PipelineStage::query()
            ->select([
                'pipeline_stages.name as stage',
                DB::raw('COUNT(deals.id) as count'),
                DB::raw('COALESCE(SUM(deals.amount), 0) as value'),
            ])
            ->leftJoin('deals', function ($join) {
                $join->on('deals.pipeline_stage_id', '=', 'pipeline_stages.id')
                    ->where('deals.status', '=', 'open')
                    ->whereNull('deals.deleted_at');
            })
            ->groupBy('pipeline_stages.id', 'pipeline_stages.name', 'pipeline_stages.position')
            ->orderBy('pipeline_stages.position')
            ->get();

        return response()->json([
            'open_deals_count' => $openDealsCount,
            'open_deals_value' => (float) $openDealsValue,
            'won_this_month' => $wonThisMonth,
            'lost_this_month' => $lostThisMonth,
            'conversion_rate_30d' => $conversionRate,
            'activities_due_count' => $activitiesDueCount,
            'activities_overdue_count' => $activitiesOverdueCount,
            'deals_by_stage' => $dealsByStage,
        ]);
    }
}
