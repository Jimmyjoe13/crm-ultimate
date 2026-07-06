<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesOwnerAccess;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Support\CustomFieldRenderer;
use App\Support\CustomValueValidator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ContactController extends Controller
{
    use AuthorizesOwnerAccess;

    public function index(Request $request)
    {
        $search = $request->get('search');
        $allowed = ['last_name', 'email', 'created_at', 'ai_score', 'last_activity'];
        $sort = in_array($request->get('sort'), $allowed) ? $request->get('sort') : 'last_name';
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        $page = (int) $request->get('page', 1);

        // Filtres
        $lifecycle = $request->get('lifecycle_stage');
        $leadStatus = $request->get('lead_status');
        $ownerId = $request->get('owner_id');
        $hideBlacklisted = $request->get('hide_blacklisted', '1') === '1';
        $hasDeal = $request->get('has_deal');
        $isHot = $request->get('is_hot') === '1';

        // Cloisonnement par owner : la clé de cache intègre le périmètre de l'utilisateur
        // pour ne jamais servir à un commercial le cache d'un admin/manager.
        // Chaque filtre doit aussi entrer dans la clé, sinon cache empoisonné.
        $user = $request->user();
        $scopeKey = md5(json_encode($user->accessibleOwnerIds() ?? 'all'));
        $cacheKey = 'contacts.index.p'.$page.'.s'.$sort.'.d'.$dir.'.'.md5((string) $search)
            .'.lc'.$lifecycle.'.ls'.$leadStatus.'.o'.$ownerId.'.bl'.($hideBlacklisted ? 1 : 0)
            .'.hd'.$hasDeal.'.hot'.($isHot ? 1 : 0)
            .'.sc'.$scopeKey;

        $cached = Cache::tags(['contacts.index'])->remember($cacheKey, 30, function () use ($search, $sort, $dir, $page, $user, $lifecycle, $leadStatus, $ownerId, $hideBlacklisted, $hasDeal, $isHot) {
            $pag = Contact::with(['companies:id,name', 'owner:id,name'])
                ->visibleTo($user)
                ->withMax('activities', 'created_at')
                ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")))
                ->when($lifecycle, fn ($q) => $q->where('lifecycle_stage', $lifecycle))
                ->when($leadStatus, fn ($q) => $q->where('lead_status', $leadStatus))
                ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
                ->when($hideBlacklisted, fn ($q) => $q->contactable())
                ->when($hasDeal === 'yes', fn ($q) => $q->has('deals'))
                ->when($hasDeal === 'no', fn ($q) => $q->doesntHave('deals'))
                ->when($isHot, fn ($q) => $q->where('ai_score', '>=', 70))
                ->when($sort === 'last_activity',
                    fn ($q) => $q->orderByRaw("activities_max_created_at {$dir} NULLS LAST"),
                    fn ($q) => $q->when($sort === 'ai_score',
                        fn ($q) => $q->orderByRaw("ai_score {$dir} NULLS LAST"),
                        fn ($q) => $q->orderBy($sort, $dir)
                    )
                )
                ->paginate(25, ['*'], 'page', $page);

            return ['items' => $pag->items(), 'total' => $pag->total()];
        });

        $contacts = new LengthAwarePaginator(
            $cached['items'],
            $cached['total'],
            25,
            $page,
            ['path' => url('/contacts'), 'query' => $request->query()]
        );

        $owners = $this->visibleOwners($user);

        return view('pages.contacts.index', compact('contacts', 'search', 'sort', 'dir', 'lifecycle', 'leadStatus', 'ownerId', 'hideBlacklisted', 'owners', 'hasDeal', 'isHot'));
    }

    public function create()
    {
        $companies = Company::orderBy('name')->get(['id', 'name']);

        return view('pages.contacts.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'lifecycle_stage' => ['nullable', 'in:lead,mql,sql,opportunity,customer,evangelist,other'],
            'lead_status' => ['nullable', 'in:new,open,in_progress,connected,unqualified,bad_fit'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ], CustomValueValidator::validationRules('contact')));

        // owner_id auto-assigné à l'utilisateur connecté (sécurité : pas de injection via form)
        $data['owner_id'] = $request->user()->id;

        $companyId = $data['company_id'] ?? null;
        unset($data['company_id']);

        $data['lifecycle_stage'] = $data['lifecycle_stage'] ?? 'lead';
        $data['custom_values'] = CustomValueValidator::cast('contact', $data['custom_values'] ?? []);

        $contact = Contact::create($data);

        if ($companyId) {
            $contact->companies()->attach($companyId);
        }

        return redirect('/contacts/'.$contact->id)->with('flash_toast', [
            'message' => 'Contact créé.',
            'type' => 'success',
        ]);
    }

    public function show(Request $request, Contact $contact)
    {
        $this->ensureVisible($contact, $request->user());
        $contact->load('companies', 'deals.stage', 'owner');
        $activities = Activity::where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $auditLogs = \App\Models\AuditLog::where('auditable_type', Contact::class)
            ->where('auditable_id', $contact->id)
            ->with('user')
            ->orderByDesc('id')
            ->get();

        $stages = PipelineStage::orderBy('position')->get();

        return view('pages.contacts.show', compact('contact', 'activities', 'stages', 'auditLogs'));
    }

    public function edit(Request $request, Contact $contact)
    {
        $this->ensureVisible($contact, $request->user());

        return view('pages.contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $this->ensureVisible($contact, $request->user());

        \Log::info('[Contact.update] input', [
            'contact_id' => $contact->id,
            'raw_input' => $request->except('_token', '_method', 'password'),
            'has_cv' => $request->has('custom_values'),
            'cv_input' => $request->input('custom_values'),
            'existing_cv' => $contact->custom_values,
        ]);

        $data = $request->validate(array_merge([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lifecycle_stage' => ['sometimes', 'nullable', 'in:lead,mql,sql,opportunity,customer,evangelist,other'],
            'lead_status' => ['sometimes', 'nullable', 'in:new,open,in_progress,connected,unqualified,bad_fit'],
        ], CustomValueValidator::validationRules('contact')));

        \Log::info('[Contact.update] after validate', [
            'contact_id' => $contact->id,
            'data_keys' => array_keys($data),
            'cv_in_data' => array_key_exists('custom_values', $data),
            'validated_cv' => $data['custom_values'] ?? 'NOT_PRESENT',
        ]);

        if (array_key_exists('custom_values', $data)) {
            $cast = CustomValueValidator::cast('contact', is_array($data['custom_values']) ? $data['custom_values'] : []);
            \Log::info('[Contact.update] cast result', ['contact_id' => $contact->id, 'cast' => $cast]);
            $data['custom_values'] = array_merge(
                $contact->custom_values ?? [],
                $cast
            );
            \Log::info('[Contact.update] final cv', ['contact_id' => $contact->id, 'final_cv' => $data['custom_values']]);
        }

        $contact->update($data);

        \Log::info('[Contact.update] saved', ['contact_id' => $contact->id, 'fresh_cv' => $contact->fresh()->custom_values]);

        return redirect('/contacts/'.$contact->id)->with('flash_toast', [
            'message' => 'Contact mis à jour.',
            'type' => 'success',
        ]);
    }

    public function destroy(Request $request, Contact $contact)
    {
        $this->ensureVisible($contact, $request->user());
        $contact->delete();

        return redirect('/contacts')->with('flash_toast', [
            'message' => 'Contact supprimé.',
            'type' => 'success',
        ]);
    }

    public function export(Request $request)
    {
        $search = $request->get('search');
        $customFields = CustomFieldRenderer::forEntity('contact');

        $headers = array_merge(
            ['id', 'prénom', 'nom', 'email', 'téléphone', 'poste', 'étape_lifecycle', 'statut_lead', 'propriétaire', 'entreprises', 'créé_le'],
            $customFields->pluck('label')->toArray()
        );

        return response()->streamDownload(function () use ($search, $customFields, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, $headers);

            Contact::with(['owner', 'companies'])
                ->when($search, fn ($q) => $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%"))
                ->orderBy('last_name')
                ->chunk(200, function ($contacts) use ($out, $customFields) {
                    foreach ($contacts as $c) {
                        $row = [
                            $c->id,
                            $c->first_name,
                            $c->last_name,
                            $c->email,
                            $c->phone,
                            $c->job_title,
                            $c->lifecycle_stage,
                            $c->lead_status,
                            $c->owner?->name,
                            $c->companies->pluck('name')->implode(', '),
                            $c->created_at?->format('d/m/Y'),
                        ];
                        foreach ($customFields as $field) {
                            $raw = $c->custom_values[$field->key] ?? null;
                            $display = CustomFieldRenderer::displayValue($field, $raw);
                            $row[] = $display === '—' ? '' : $display;
                        }
                        fputcsv($out, $row);
                    }
                });

            fclose($out);
        }, 'contacts_'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $user = $request->user();

        if ($request->boolean('select_all')) {
            $query = Contact::query()->visibleTo($user);
            $count = $query->count();
            $query->delete();

            return redirect('/contacts')->with('flash_toast', [
                'message' => "{$count} contacts supprimés.",
                'type' => 'success',
            ]);
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:contacts,id'],
        ]);

        $count = Contact::whereIn('id', $data['ids'])
            ->visibleTo($user)
            ->delete();

        return redirect('/contacts')->with('flash_toast', [
            'message' => "{$count} contact(s) supprimé(s).",
            'type' => 'success',
        ]);
    }
}
