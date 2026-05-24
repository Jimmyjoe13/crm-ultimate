<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $now          = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOf30d   = $now->copy()->subDays(30);

        // Lot 2 — P1 : cache 5 min + fix N+1 sur stagesData
        $data = Cache::remember('dashboard.data', 300, function () use ($startOfMonth, $startOf30d) {
            $wonThisMonth  = Deal::where('status', 'won')
                ->where('updated_at', '>=', $startOfMonth)->get(['name', 'amount']);
            $lostThisMonth = Deal::where('status', 'lost')
                ->where('updated_at', '>=', $startOfMonth)->get(['name', 'amount']);

            $closed30   = Deal::whereIn('status', ['won', 'lost'])
                ->where('updated_at', '>=', $startOf30d)->count();
            $won30      = Deal::where('status', 'won')
                ->where('updated_at', '>=', $startOf30d)->count();
            $conversion = $closed30 > 0 ? round(($won30 / $closed30) * 100) : 0;

            // 1 seule requête pour tous les deals ouverts → plus de N+1
            $openDeals = Deal::where('status', 'open')->get(['pipeline_stage_id', 'amount']);
            $stages    = PipelineStage::orderBy('position')
                ->where('is_won', false)->where('is_lost', false)->get();

            $stagesData = $stages->map(fn($s) => [
                'name'  => $s->name,
                'count' => $openDeals->where('pipeline_stage_id', $s->id)->count(),
                'total' => (float) $openDeals->where('pipeline_stage_id', $s->id)->sum('amount'),
            ]);

            return [
                'kpis' => [
                    'pipeline_total' => (float) $openDeals->sum('amount'),
                    'conversion'     => $conversion,
                    'won_count'      => $wonThisMonth->count(),
                    'won_amount'     => $wonThisMonth->sum('amount'),
                    'won_names'      => $wonThisMonth->take(3)->pluck('name'),
                    'lost_count'     => $lostThisMonth->count(),
                    'lost_amount'    => $lostThisMonth->sum('amount'),
                    'lost_names'     => $lostThisMonth->take(3)->pluck('name'),
                    'ca_total'       => (float) Deal::where('status', 'won')->sum('amount'),
                    'ca_lost'        => (float) Deal::where('status', 'lost')->sum('amount'),
                ],
                'stagesData' => $stagesData,
                'maxTotal'   => $stagesData->max('total') ?: 1,
            ];
        });

        $activities = Activity::with(['subject'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('pages.dashboard', array_merge($data, compact('activities')));
    }
}
