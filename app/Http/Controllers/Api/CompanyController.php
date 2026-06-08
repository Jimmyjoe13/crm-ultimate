<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Services\AssociationAuditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use CrudActions;

    protected string $modelClass = Company::class;

    protected array $searchable = ['name', 'domain', 'industry', 'city', 'country'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'lifecycle_stage' => ['nullable', 'in:lead,mql,sql,opportunity,customer,evangelist,other'],
            'lead_status' => ['nullable', 'in:new,open,in_progress,connected,unqualified,bad_fit'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'custom_values' => ['array'],
        ];
    }

    public function show(int $id): JsonResponse
    {
        $company = Company::query()
            ->with(['contacts', 'deals.stage', 'owner'])
            ->findOrFail($id);

        return response()->json([
            'data' => $company,
            'activities' => Activity::query()
                ->where('subject_type', Company::class)
                ->where('subject_id', $company->id)
                ->latest()
                ->get(),
            'audit_logs' => AuditLog::query()
                ->where('auditable_type', Company::class)
                ->where('auditable_id', $company->id)
                ->latest('created_at')
                ->limit(25)
                ->get(),
        ]);
    }

    // ── Association endpoints ────────────────────────────────────────────────

    public function attachContact(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'role' => ['in:employee,decision_maker,influencer,former'],
            'is_primary' => ['boolean'],
        ]);

        $company = Company::query()->findOrFail($id);
        $contact = Contact::query()->findOrFail($data['contact_id']);

        $pivot = [
            'role' => $data['role'] ?? 'employee',
            'is_primary' => $data['is_primary'] ?? false,
        ];

        $company->contacts()->syncWithoutDetaching([$contact->id => $pivot]);

        AssociationAuditor::recordAttach($company, 'contacts', $contact->id, Contact::class, $pivot);

        return response()->json(['data' => $company->contacts()->withPivot('role', 'is_primary')->get()]);
    }

    public function detachContact(Request $request, int $id, int $contactId): JsonResponse
    {
        $company = Company::query()->findOrFail($id);
        $company->contacts()->detach($contactId);

        AssociationAuditor::recordDetach($company, 'contacts', $contactId, Contact::class);

        return response()->json(null, 204);
    }

    public function updateContactAssoc(Request $request, int $id, int $contactId): JsonResponse
    {
        $data = $request->validate([
            'role' => ['in:employee,decision_maker,influencer,former'],
            'is_primary' => ['boolean'],
        ]);

        $company = Company::query()->findOrFail($id);
        $company->contacts()->updateExistingPivot($contactId, $data);

        return response()->json(['data' => $company->contacts()->withPivot('role', 'is_primary')->get()]);
    }
}
