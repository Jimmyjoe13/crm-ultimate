<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Support\CustomValueValidator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $filter  = $request->get('filter', 'all');
        $search  = $request->get('search');
        $allowed = ['name', 'amount', 'close_date', 'created_at'];
        $sort    = in_array($request->get('sort'), $allowed) ? $request->get('sort') : 'close_date';
        $dir     = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        $page    = (int) $request->get('page', 1);

        $cacheKey = 'deals.index.p' . $page . '.s' . $sort . '.d' . $dir . '.' . md5((string) $search);

        $cached = Cache::tags(['deals.index'])->remember($cacheKey, 30, function () use ($search, $sort, $dir, $page) {
            $base = Deal::with([
                'stage:id,name',
                'companies:id,name',
                'contacts:id,first_name,last_name',
                'owner:id,name',
            ])->where('status', 'open');

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

        $stages = PipelineStage::orderBy('position')->get();

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

    public function show(Deal $deal)
    {
        $deal->load('stage', 'companies', 'contacts', 'owner');

        $stages = PipelineStage::orderBy('position')
            ->where('is_won', false)
            ->where('is_lost', false)
            ->get();

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

        $allContacts = \App\Models\Contact::orderBy('last_name')->get();
        $allCompanies = \App\Models\Company::orderBy('name')->get();

        return view('pages.deals.show', compact('deal', 'stages', 'activities', 'bgDeals', 'allContacts', 'allCompanies'));
    }

    public function edit(Deal $deal)
    {
        $stages = PipelineStage::where('is_won', false)->where('is_lost', false)->orderBy('position')->get();

        return view('pages.deals.edit', compact('deal', 'stages'));
    }

    public function update(Request $request, Deal $deal)
    {
        $data = $request->validate(array_merge([
            'name'              => ['required', 'string', 'max:255'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'close_date'        => ['nullable', 'date'],
            'status'            => ['nullable', 'in:open,won,lost'],
        ], CustomValueValidator::validationRules('deal')));

        $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
        $data['pipeline_id']    = $stage->pipeline_id;
        $data['custom_values']  = CustomValueValidator::cast('deal', $data['custom_values'] ?? []);

        $deal->update($data);

        return redirect('/deals/' . $deal->id)->with('flash_toast', [
            'message' => "Deal « {$deal->name} » mis à jour.",
            'type'    => 'success',
        ]);
    }

    public function destroy(Deal $deal)
    {
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

    public function markWon(Deal $deal)
    {
        $wonStage = PipelineStage::where('is_won', true)->first();
        $deal->update([
            'status'            => 'won',
            'pipeline_stage_id' => $wonStage?->id ?? $deal->pipeline_stage_id,
        ]);

        return redirect('/deals')
            ->with('flash_toast', ['message' => "Deal « {$deal->name} » marqué gagné ✓", 'type' => 'success']);
    }

    public function markLost(Deal $deal)
    {
        $lostStage = PipelineStage::where('is_lost', true)->first();
        $deal->update([
            'status'            => 'lost',
            'pipeline_stage_id' => $lostStage?->id ?? $deal->pipeline_stage_id,
        ]);

        return redirect('/deals')
            ->with('flash_toast', ['message' => "Deal « {$deal->name} » marqué perdu.", 'type' => 'warning']);
    }

    public function attachContact(Request $request, Deal $deal)
    {
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

    public function detachContact(Deal $deal, \App\Models\Contact $contact)
    {
        $deal->contacts()->detach($contact->id);

        \App\Services\AssociationAuditor::recordDetach($deal, 'contacts', $contact->id, \App\Models\Contact::class);

        return redirect('/deals/' . $deal->id)
            ->with('flash_toast', ['message' => 'Contact dissocié.', 'type' => 'warning']);
    }

    public function attachCompany(Request $request, Deal $deal)
    {
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

    public function detachCompany(Deal $deal, \App\Models\Company $company)
    {
        $deal->companies()->detach($company->id);

        \App\Services\AssociationAuditor::recordDetach($deal, 'companies', $company->id, \App\Models\Company::class);

        return redirect('/deals/' . $deal->id)
            ->with('flash_toast', ['message' => 'Entreprise dissociée.', 'type' => 'warning']);
    }
}
