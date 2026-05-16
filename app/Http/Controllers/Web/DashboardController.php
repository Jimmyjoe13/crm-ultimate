<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOf30d   = $now->copy()->subDays(30);

        $pipelineTotal = Deal::where('status', 'open')->sum('amount');

        $wonThisMonth  = Deal::where('status', 'won')->where('updated_at', '>=', $startOfMonth)->get();
        $lostThisMonth = Deal::where('status', 'lost')->where('updated_at', '>=', $startOfMonth)->get();

        $closed30   = Deal::whereIn('status', ['won', 'lost'])->where('updated_at', '>=', $startOf30d)->count();
        $won30      = Deal::where('status', 'won')->where('updated_at', '>=', $startOf30d)->count();
        $conversion = $closed30 > 0 ? round(($won30 / $closed30) * 100) : 0;

        $stages = PipelineStage::orderBy('position')->where('is_won', false)->where('is_lost', false)->get();
        $stagesData = $stages->map(function ($stage) {
            $deals = Deal::where('pipeline_stage_id', $stage->id)->where('status', 'open')->get();
            return [
                'name'   => $stage->name,
                'count'  => $deals->count(),
                'total'  => $deals->sum('amount'),
            ];
        });
        $maxTotal = $stagesData->max('total') ?: 1;

        $activities = Activity::with(['subject'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('pages.dashboard', [
            'kpis' => [
                'pipeline_total' => $pipelineTotal,
                'conversion'     => $conversion,
                'won_count'      => $wonThisMonth->count(),
                'won_amount'     => $wonThisMonth->sum('amount'),
                'won_names'      => $wonThisMonth->take(3)->pluck('name'),
                'lost_count'     => $lostThisMonth->count(),
                'lost_amount'    => $lostThisMonth->sum('amount'),
                'lost_names'     => $lostThisMonth->take(3)->pluck('name'),
            ],
            'stagesData'  => $stagesData,
            'maxTotal'    => $maxTotal,
            'activities'  => $activities,
        ]);
    }
}
