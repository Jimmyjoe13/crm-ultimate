<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    use CrudActions;

    protected string $modelClass = Deal::class;

    protected array $searchable = ['name', 'currency', 'status'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'amount' => ['numeric', 'min:0'],
            'currency' => ['string', 'size:3'],
            'close_date' => ['nullable', 'date'],
            'status' => ['in:open,won,lost'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'pipeline_id' => [$required, 'exists:pipelines,id'],
            'pipeline_stage_id' => [$required, 'exists:pipeline_stages,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'custom_values' => ['array'],
        ];
    }

    public function move(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
        ]);

        $deal = Deal::query()->findOrFail($id);
        $stage = PipelineStage::query()->findOrFail($data['pipeline_stage_id']);

        $deal->fill([
            'pipeline_id' => $stage->pipeline_id,
            'pipeline_stage_id' => $stage->id,
            'status' => $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open'),
        ])->save();

        return response()->json(['data' => $deal->fresh()]);
    }

    public function show(int $id): JsonResponse
    {
        $deal = Deal::query()
            ->with(['company', 'contact', 'pipeline', 'stage', 'owner'])
            ->findOrFail($id);

        return response()->json([
            'data' => $deal,
            'activities' => Activity::query()
                ->where('subject_type', Deal::class)
                ->where('subject_id', $deal->id)
                ->latest()
                ->get(),
            'audit_logs' => AuditLog::query()
                ->where('auditable_type', Deal::class)
                ->where('auditable_id', $deal->id)
                ->latest('created_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function board(): JsonResponse
    {
        $pipeline = Pipeline::query()
            ->with('stages')
            ->where('is_default', true)
            ->first() ?? Pipeline::query()->with('stages')->firstOrFail();

        $deals = Deal::query()
            ->with(['company', 'contact', 'stage'])
            ->where('pipeline_id', $pipeline->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('pipeline_stage_id');

        return response()->json([
            'pipeline' => $pipeline,
            'columns' => $pipeline->stages->map(fn (PipelineStage $stage) => [
                'stage' => $stage,
                'deals' => $deals->get($stage->id, collect())->values(),
            ])->values(),
        ]);
    }
}
