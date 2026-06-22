<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesOwnerAccess;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Support\CustomValueValidator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class DealController extends Controller
{
    use AuthorizesOwnerAccess;

    public function index(Request $request)
    {
        $filter  = $request->get('filter', 'all');
        $search  = $request->get('search');
        $allowed = ['name', 'amount', 'close_date', 'created_at'];
        $sort    = in_array($request->get('sort'), $allowed) ? $request->get('sort') : 'close_date';
        $dir     = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        $page    = (int) $request->get('page', 1);

        // Cloisonnement par owner : la clé de cache intègre le périmètre de l'utilisateur.
        $user      = $request->user();
        $scopeKey  = md5(json_encode($user->accessibleOwnerIds() ?? 'all'));
        $cacheKey  = 'deals.index.p' . $page . '.s' . $sort . '.d' . $dir . '.' . md5((string) $search) . '.sc' . $scopeKey;

        $cached = Cache::tags(['deals.index'])->remember($cacheKey, 30, function () use ($search, $sort, $dir, $page, $user) {
            $base = Deal::with([
                'stage:id,name',
                'companies:id,name',
                'contacts:id,first_name,last_name',
                'owner:id,name',
            ])->visibleTo($user)->where('status', 'open');

            $allCount = (clone $base)->count();
            $total    = (clone $base)->sum('amount');

            $pag = (clone $base)
                ->when($search, fn($q) => $q->where('name', 'ilike', "%{$search}%"))
                ->orderBy($sort, $dir)
                ->paginate(20, ['*'], 'page', $page);

            return [
                'items'    => $pag->items(),
                'total'    => $pag->total(),
                'allCount' => $allCount,
                'amount'   => $total,
            ];
        });

        $deals = new LengthAwarePaginator(
            $cached['items'],
            $cached['total'],
            20,
            $page,
            ['path' => url('/deals'), 'query' => $request->query()]
        );

        $allCount = $cached['allCount'];
        $total    = $cached['amount'];

        $stages = $this->allStages();

        return view('pages.deals.index', compact('deals', 'filter', 'sort', 'dir', 'search', 'allCount', 'total', 'stages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'name'              => ['required', 'string', 'max:255'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'close_date'        => ['nullable', 'date'],
            'contact_id'        => ['required', 'exists:contacts,id'],
        ], CustomValueValidator::validationRules('deal')));

        $contact = \App\Models\Contact::findOrFail($data['contact_id']);
        $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);

        $deal = Deal::create([
            'name'              => $data['name'],
            'amount'            => $data['amount'],
            'pipeline_id'       => $stage->pipeline_id,
            'pipeline_stage_id' => $data['pipeline_stage_id'],
            'close_date'        => $data['close_date'] ?? null,
            'status'            => 'open',
            'owner_id'          => auth()->id(),
            'custom_values'     => CustomValueValidator::cast('deal', $data['custom_values'] ?? []),
        ]);

        // Auto-associate contact
        $contactPivot = ['role' => 'primary'];
        $deal->contacts()->attach($contact->id, $contactPivot);
        \App\Services\AssociationAuditor::recordAttach($deal, 'contacts', $contact->id, \App\Models\Contact::class, $contactPivot);

        // Auto-associate contact's company
        $company = $contact->companies()->first();
        if ($company) {
            $companyPivot = [
                'role'       => 'customer',
                'is_primary' => true,
            ];
            $deal->companies()->attach($company->id, $companyPivot);
            \App\Services\AssociationAuditor::recordAttach($deal, 'companies', $company->id, \App\Models\Company::class, $companyPivot);
        }

        return redirect('/pipeline')
            ->with('flash_toast', ['message' => "Deal « {$deal->name} » créé.", 'type' => 'success']);
    }

    public function show(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $deal->load('stage', 'companies', 'contacts', 'owner');

        $stages = $this->activeStages();

        $activities = Activity::where('subject_type', Deal::class)
            ->where('subject_id', $deal->id)
            ->with('owner')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Background deals list (dimmed behind drawer)
        $bgDeals = Deal::with('stage', 'companies', 'owner')
            ->where('status', 'open')
            ->orderBy('close_date')
            ->limit(10)
            ->get();

        $allContacts = Cache::tags(['contacts.index'])->remember('contacts.dropdown', 60, fn() =>
            \App\Models\Contact::select('id', 'first_name', 'last_name')->orderBy('last_name')->get()
        );
        $allCompanies = Cache::tags(['companies.index'])->remember('companies.dropdown', 60, fn() =>
            \App\Models\Company::select('id', 'name')->orderBy('name')->get()
        );

        return view('pages.deals.show', compact('deal', 'stages', 'activities', 'bgDeals', 'allContacts', 'allCompanies'));
    }

    public function edit(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $stages = $this->activeStages();

        return view('pages.deals.edit', compact('deal', 'stages'));
    }

    public function update(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $data = $request->validate(array_merge([
            'name'              => ['required', 'string', 'max:255'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'close_date'        => ['nullable', 'date'],
            'status'            => ['nullable', 'in:open,won,lost'],
        ], CustomValueValidator::validationRules('deal')));

        $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
        $data['pipeline_id']    = $stage->pipeline_id;
        
        if (array_key_exists('custom_values', $data)) {
            $data['custom_values'] = array_merge(
                $deal->custom_values ?? [],
                CustomValueValidator::cast('deal', is_array($data['custom_values']) ? $data['custom_values'] : [])
            );
        }

        $deal->update($data);

        return redirect('/deals/' . $deal->id)->with('flash_toast', [
            'message' => "Deal « {$deal->name} » mis à jour.",
            'type'    => 'success',
        ]);
    }

    public function destroy(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $deal->delete();

        return redirect('/deals')->with('flash_toast', [
            'message' => 'Deal supprimé.',
            'type'    => 'success',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:deals,id'],
        ]);

        $count = Deal::whereIn('id', $data['ids'])->delete();

        return redirect('/deals')->with('flash_toast', [
            'message' => "{$count} deal(s) supprimé(s).",
            'type'    => 'success',
        ]);
    }

    public function markWon(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $wonStage = $this->wonStage();
        $deal->update([
            'status'            => 'won',
            'pipeline_stage_id' => $wonStage?->id ?? $deal->pipeline_stage_id,
        ]);

        return redirect('/deals')
            ->with('flash_toast', ['message' => "Deal « {$deal->name} » marqué gagné ✓", 'type' => 'success']);
    }

    public function markLost(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $lostStage = $this->lostStage();
        $deal->update([
            'status'            => 'lost',
            'pipeline_stage_id' => $lostStage?->id ?? $deal->pipeline_stage_id,
        ]);

        return redirect('/deals')
            ->with('flash_toast', ['message' => "Deal « {$deal->name} » marqué perdu.", 'type' => 'warning']);
    }

    public function attachContact(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $data = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'role'       => ['nullable', 'in:primary,technical,billing,other'],
        ]);

        $contactId = (int)$data['contact_id'];
        $pivot = ['role' => $data['role'] ?? 'primary'];

        $deal->contacts()->syncWithoutDetaching([$contactId => $pivot]);
        \App\Services\AssociationAuditor::recordAttach($deal, 'contacts', $contactId, \App\Models\Contact::class, $pivot);

        // Auto-associate contact's company if not already linked to the deal
        $contact = \App\Models\Contact::findOrFail($contactId);
        $company = $contact->companies()->first();
        if ($company && ! $deal->companies()->where('companies.id', $company->id)->exists()) {
            $companyPivot = ['role' => 'customer', 'is_primary' => true];
            $deal->companies()->attach($company->id, $companyPivot);
            \App\Services\AssociationAuditor::recordAttach($deal, 'companies', $company->id, \App\Models\Company::class, $companyPivot);
        }

        return redirect('/deals/' . $deal->id)
            ->with('flash_toast', ['message' => 'Contact associé au deal.', 'type' => 'success']);
    }

    public function detachContact(Request $request, Deal $deal, \App\Models\Contact $contact)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $deal->contacts()->detach($contact->id);

        \App\Services\AssociationAuditor::recordDetach($deal, 'contacts', $contact->id, \App\Models\Contact::class);

        return redirect('/deals/' . $deal->id)
            ->with('flash_toast', ['message' => 'Contact dissocié.', 'type' => 'warning']);
    }

    public function attachCompany(Request $request, Deal $deal)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'role'       => ['nullable', 'in:customer,partner,reseller'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $companyId = (int)$data['company_id'];
        $pivot = [
            'role'       => $data['role'] ?? 'customer',
            'is_primary' => filter_var($data['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        $deal->companies()->syncWithoutDetaching([$companyId => $pivot]);

        \App\Services\AssociationAuditor::recordAttach($deal, 'companies', $companyId, \App\Models\Company::class, $pivot);

        return redirect('/deals/' . $deal->id)
            ->with('flash_toast', ['message' => 'Entreprise associée au deal.', 'type' => 'success']);
    }

    public function detachCompany(Request $request, Deal $deal, \App\Models\Company $company)
    {
        // Cloisonnement par owner : 404 si le deal est hors du périmètre du commercial.
        $this->ensureVisible($deal, $request->user());

        $deal->companies()->detach($company->id);

        \App\Services\AssociationAuditor::recordDetach($deal, 'companies', $company->id, \App\Models\Company::class);

        return redirect('/deals/' . $deal->id)
            ->with('flash_toast', ['message' => 'Entreprise dissociée.', 'type' => 'warning']);
    }

    // Lot 4 — P2 : cache 1h sur les stages quasi-statiques
    private function activeStages(): \Illuminate\Support\Collection
    {
        return Cache::remember('pipeline.stages.active', 3600,
            fn() => PipelineStage::where('is_won', false)->where('is_lost', false)->orderBy('position')->get()
        );
    }

    private function allStages(): \Illuminate\Support\Collection
    {
        return Cache::remember('pipeline.stages.all', 3600,
            fn() => PipelineStage::orderBy('position')->get()
        );
    }

    private function wonStage(): ?PipelineStage
    {
        return Cache::remember('pipeline.stage.won', 86400,
            fn() => PipelineStage::where('is_won', true)->first()
        );
    }

    private function lostStage(): ?PipelineStage
    {
        return Cache::remember('pipeline.stage.lost', 86400,
            fn() => PipelineStage::where('is_lost', true)->first()
        );
    }
}
