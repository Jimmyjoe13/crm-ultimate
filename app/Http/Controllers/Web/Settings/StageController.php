<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\Request;

class StageController extends Controller
{
    public function index()
    {
        $pipeline = Pipeline::where('is_default', true)->first() ?? Pipeline::first();
        $stages   = PipelineStage::where('pipeline_id', $pipeline?->id)->orderBy('position')->get();
        return view('pages.settings.stages', compact('stages', 'pipeline'));
    }

    public function store(Request $request)
    {
        $pipeline = Pipeline::where('is_default', true)->first() ?? Pipeline::first();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $max = PipelineStage::where('pipeline_id', $pipeline?->id)->max('position') ?? 0;

        PipelineStage::create([
            'pipeline_id' => $pipeline?->id,
            'name'        => $data['name'],
            'probability' => $data['probability'] ?? 50,
            'position'    => $max + 1,
        ]);

        return back()->with('success', 'Étape créée.');
    }

    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($data['ids'] as $position => $id) {
            PipelineStage::where('id', $id)->update(['position' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, PipelineStage $stage)
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'position'    => ['sometimes', 'integer', 'min:0'],
        ]);

        $stage->update($data);

        return back()->with('success', 'Étape mise à jour.');
    }
}
