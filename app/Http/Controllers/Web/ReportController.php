<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $data = Cache::remember('reports.data', 1800, function () {
            return [
                'ca_mensuel'     => $this->caMensuel(),
                'entonnoir'      => $this->entonnoir(),
                'classement'     => $this->classementCommerciaux(),
                'activite_hebdo' => $this->activiteHebdomadaire(),
            ];
        });

        return view('pages.reports.index', $data);
    }

    private function caMensuel(): array
    {
        $rows = Deal::selectRaw("
            to_char(date_trunc('month', updated_at), 'YYYY-MM') AS mois,
            SUM(CASE WHEN status = 'won'  THEN amount ELSE 0 END) AS ca_gagne,
            SUM(CASE WHEN status = 'open' THEN amount ELSE 0 END) AS pipeline
        ")
        ->where('updated_at', '>=', now()->subMonths(12)->startOfMonth())
        ->groupByRaw("date_trunc('month', updated_at)")
        ->orderByRaw("date_trunc('month', updated_at)")
        ->get();

        return $rows->map(fn($r) => [
            'mois'     => $r->mois,
            'ca_gagne' => (float) $r->ca_gagne,
            'pipeline' => (float) $r->pipeline,
        ])->values()->all();
    }

    private function entonnoir(): array
    {
        $stages = PipelineStage::where('is_won', false)->where('is_lost', false)
            ->orderBy('position')->get();

        $counts = Deal::where('status', 'open')
            ->selectRaw('pipeline_stage_id, COUNT(*) as total')
            ->groupBy('pipeline_stage_id')
            ->pluck('total', 'pipeline_stage_id');

        $won   = Deal::where('status', 'won')->count();
        $total = Deal::count() ?: 1;

        return [
            'stages' => $stages->map(fn($s) => [
                'name'  => $s->name,
                'count' => (int) ($counts[$s->id] ?? 0),
            ])->values()->all(),
            'taux_conversion_global' => round($won / $total * 100, 1),
        ];
    }

    private function classementCommerciaux(): array
    {
        $debut = now()->startOfMonth();

        return Deal::where('status', 'won')
            ->where('updated_at', '>=', $debut)
            ->selectRaw('owner_id, COUNT(*) as nb_deals, SUM(amount) as ca')
            ->with('owner:id,name')
            ->groupBy('owner_id')
            ->orderByDesc('ca')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'commercial' => $r->owner?->name ?? 'N/A',
                'nb_deals'   => (int) $r->nb_deals,
                'ca'         => (float) $r->ca,
            ])->values()->all();
    }

    private function activiteHebdomadaire(): array
    {
        $rows = Activity::selectRaw("
            to_char(date_trunc('week', created_at), 'YYYY-MM-DD') AS semaine,
            type,
            COUNT(*) AS total
        ")
        ->where('created_at', '>=', now()->subWeeks(8)->startOfWeek())
        ->whereIn('type', ['call', 'email', 'task', 'note',
                           'email_sent', 'email_opened', 'email_replied'])
        ->groupByRaw("date_trunc('week', created_at), type")
        ->orderByRaw("date_trunc('week', created_at)")
        ->get();

        return $rows->groupBy('semaine')->map(fn($items, $sem) => [
            'semaine' => $sem,
            'detail'  => $items->pluck('total', 'type')->all(),
            'total'   => $items->sum('total'),
        ])->values()->all();
    }
}
