<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\AssociationAuditor;
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
            ->with(['companies', 'contacts', 'pipeline', 'stage', 'owner'])
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
            ->with(['companies', 'contacts', 'stage'])
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

    // ── Association endpoints ────────────────────────────────────────────────

    public function attachContact(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'role' => ['in:primary,technical,billing,other'],
        ]);

        $deal = Deal::query()->findOrFail($id);
        $contact = Contact::query()->findOrFail($data['contact_id']);

        $pivot = ['role' => $data['role'] ?? 'primary'];

        $deal->contacts()->syncWithoutDetaching([$contact->id => $pivot]);

        AssociationAuditor::recordAttach($deal, 'contacts', $contact->id, Contact::class, $pivot);

        return response()->json(['data' => $deal->contacts()->withPivot('role')->get()]);
    }

    public function detachContact(Request $request, int $id, int $contactId): JsonResponse
    {
        $deal = Deal::query()->findOrFail($id);
        $deal->contacts()->detach($contactId);

        AssociationAuditor::recordDetach($deal, 'contacts', $contactId, Contact::class);

        return response()->json(null, 204);
    }

    public function updateContactAssoc(Request $request, int $id, int $contactId): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:primary,technical,billing,other'],
        ]);

        $deal = Deal::query()->findOrFail($id);
        $deal->contacts()->updateExistingPivot($contactId, $data);

        return response()->json(['data' => $deal->contacts()->withPivot('role')->get()]);
    }

    public function attachCompany(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'role' => ['in:customer,partner,reseller'],
            'is_primary' => ['boolean'],
        ]);

        $deal = Deal::query()->findOrFail($id);
        $company = Company::query()->findOrFail($data['company_id']);

        $pivot = [
            'role' => $data['role'] ?? 'customer',
            'is_primary' => $data['is_primary'] ?? false,
        ];

        $deal->companies()->syncWithoutDetaching([$company->id => $pivot]);

        AssociationAuditor::recordAttach($deal, 'companies', $company->id, Company::class, $pivot);

        return response()->json(['data' => $deal->companies()->withPivot('role', 'is_primary')->get()]);
    }

    public function detachCompany(Request $request, int $id, int $companyId): JsonResponse
    {
        $deal = Deal::query()->findOrFail($id);
        $deal->companies()->detach($companyId);

        AssociationAuditor::recordDetach($deal, 'companies', $companyId, Company::class);

        return response()->json(null, 204);
    }

    public function updateCompanyAssoc(Request $request, int $id, int $companyId): JsonResponse
    {
        $data = $request->validate([
            'role' => ['in:customer,partner,reseller'],
            'is_primary' => ['boolean'],
        ]);

        $deal = Deal::query()->findOrFail($id);
        $deal->companies()->updateExistingPivot($companyId, $data);

        return response()->json(['data' => $deal->companies()->withPivot('role', 'is_primary')->get()]);
    }
}
