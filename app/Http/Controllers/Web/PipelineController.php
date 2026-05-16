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
        $pipeline = Pipeline::where('is_default', true)->first()
            ?? Pipeline::first();

        $stages = PipelineStage::where('pipeline_id', $pipeline?->id)
            ->orderBy('position')
            ->get();

        $dealsByStage = Deal::where('status', 'open')
            ->with('companies', 'owner')
            ->get()
            ->groupBy('pipeline_stage_id');

        $stagesWithDeals = $stages->map(fn($stage) => [
            'stage'  => $stage,
            'deals'  => $dealsByStage->get($stage->id, collect()),
        ]);

        $total = Deal::where('status', 'open')->sum('amount');

        return view('pages.pipeline.index', compact('stagesWithDeals', 'total', 'pipeline'));
    }
}
