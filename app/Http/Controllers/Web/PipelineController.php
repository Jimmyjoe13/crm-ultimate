<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;

class PipelineController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $pipeline = Pipeline::where('is_default', true)->first()
            ?? Pipeline::first();

        $stages = PipelineStage::where('pipeline_id', $pipeline?->id)
            ->orderBy('position')
            ->get();

        // Cartes : uniquement les deals ouverts, cloisonnés par owner (visibleTo).
        $openDeals = Deal::where('pipeline_id', $pipeline?->id)
            ->where('status', 'open')
            ->visibleTo($user)
            ->with('companies', 'owner')
            ->get();

        $dealsByStage = $openDeals->groupBy('pipeline_stage_id');

        $stagesWithDeals = $stages->map(fn ($stage) => [
            'stage' => $stage,
            'deals' => $dealsByStage->get($stage->id, collect()),
        ]);

        $total = $openDeals->sum('amount');

        // Seuil "hot deal" : 80 % du plus gros deal ouvert visible (montant élevé).
        $maxAmount = (float) $openDeals->max('amount');
        $hotThreshold = $maxAmount > 0 ? $maxAmount * 0.8 : null;

        return view('pages.pipeline.index', compact('stagesWithDeals', 'total', 'pipeline', 'hotThreshold'));
    }
}
