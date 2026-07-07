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

class ContactController extends Controller
{
    use CrudActions;

    protected string $modelClass = Contact::class;

    protected array $searchable = ['first_name', 'last_name', 'email', 'phone', 'job_title'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'first_name' => [$required, 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'lifecycle_stage' => ['nullable', 'in:lead,mql,sql,opportunity,customer,evangelist,other'],
            'lead_status' => ['nullable', 'in:new,open,in_progress,connected,unqualified,bad_fit'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'custom_values' => ['array'],
        ];
    }

    /**
     * Statistiques agrégées sur les contacts visibles par l'utilisateur courant.
     *
     * Cet endpoint DOIT être déclaré avant la route paramétrique `contacts/{contact}`
     * (cf. routes/api.php). Sinon `GET /contacts/stats` est capturé par `show()` qui
     * attend un int → TypeError 500 (bug observé en prod). La contrainte whereNumber
     * sur l'apiResource sert de garde-fou supplémentaire.
     */
    public function stats(Request $request): JsonResponse
    {
        $base = Contact::query()->visibleTo($request->user());

        return response()->json([
            'data' => [
                'total' => (clone $base)->count(),
                'contactable' => (clone $base)->contactable()->count(),
                'blacklisted' => (clone $base)->blacklisted()->count(),
                'by_lifecycle' => (clone $base)
                    ->selectRaw('lifecycle_stage, COUNT(*) as count')
                    ->groupBy('lifecycle_stage')
                    ->pluck('count', 'lifecycle_stage'),
                'by_lead_status' => (clone $base)
                    ->selectRaw('lead_status, COUNT(*) as count')
                    ->groupBy('lead_status')
                    ->pluck('count', 'lead_status'),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Scope par owner : 404 si le contact est hors du périmètre de l'utilisateur.
        $contact = Contact::query()
            ->visibleTo($request->user())
            ->with(['companies', 'deals.stage', 'owner'])
            ->findOrFail($id);

        return response()->json([
            'data' => $contact,
            'activities' => Activity::query()
                ->where('subject_type', Contact::class)
                ->where('subject_id', $contact->id)
                ->latest()
                ->get(),
            'audit_logs' => AuditLog::query()
                ->where('auditable_type', Contact::class)
                ->where('auditable_id', $contact->id)
                ->latest('created_at')
                ->limit(25)
                ->get(),
        ]);
    }

    // ── Association endpoints ────────────────────────────────────────────────

    public function attachCompany(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'role' => ['in:employee,decision_maker,influencer,former'],
            'is_primary' => ['boolean'],
        ]);

        $contact = Contact::query()->visibleTo($request->user())->findOrFail($id);
        $company = Company::query()->findOrFail($data['company_id']);

        $pivot = [
            'role' => $data['role'] ?? 'employee',
            'is_primary' => $data['is_primary'] ?? false,
        ];

        $contact->companies()->syncWithoutDetaching([$company->id => $pivot]);

        AssociationAuditor::recordAttach($contact, 'companies', $company->id, Company::class, $pivot);

        return response()->json(['data' => $contact->companies()->withPivot('role', 'is_primary')->get()]);
    }

    public function detachCompany(Request $request, int $id, int $companyId): JsonResponse
    {
        $contact = Contact::query()->visibleTo($request->user())->findOrFail($id);
        $contact->companies()->detach($companyId);

        AssociationAuditor::recordDetach($contact, 'companies', $companyId, Company::class);

        return response()->json(null, 204);
    }

    public function updateCompanyAssoc(Request $request, int $id, int $companyId): JsonResponse
    {
        $data = $request->validate([
            'role' => ['in:employee,decision_maker,influencer,former'],
            'is_primary' => ['boolean'],
        ]);

        $contact = Contact::query()->visibleTo($request->user())->findOrFail($id);
        $contact->companies()->updateExistingPivot($companyId, $data);

        return response()->json(['data' => $contact->companies()->withPivot('role', 'is_primary')->get()]);
    }
}
