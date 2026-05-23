<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\CustomFieldRenderer;
use App\Support\CustomValueValidator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $search  = $request->get('search');
        $allowed = ['last_name', 'email', 'created_at'];
        $sort    = in_array($request->get('sort'), $allowed) ? $request->get('sort') : 'last_name';
        $dir     = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        $page    = (int) $request->get('page', 1);

        $cacheKey = 'contacts.index.p' . $page . '.s' . $sort . '.d' . $dir . '.' . md5((string) $search);

        $cached = Cache::tags(['contacts.index'])->remember($cacheKey, 30, function () use ($search, $sort, $dir, $page) {
            $pag = Contact::with(['companies:id,name', 'owner:id,name'])
                ->when($search, fn($q) => $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%"))
                ->orderBy($sort, $dir)
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

        return view('pages.contacts.index', compact('contacts', 'search', 'sort', 'dir'));
    }

    public function create()
    {
        $companies = \App\Models\Company::orderBy('name')->get(['id', 'name']);
        return view('pages.contacts.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'first_name'      => ['required', 'string', 'max:255'],
            'last_name'       => ['nullable', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:255'],
            'job_title'       => ['nullable', 'string', 'max:255'],
            'lifecycle_stage' => ['nullable', 'in:lead,mql,sql,opportunity,customer,evangelist,other'],
            'lead_status'     => ['nullable', 'in:new,open,in_progress,connected,unqualified,bad_fit'],
            'company_id'      => ['nullable', 'exists:companies,id'],
        ], CustomValueValidator::validationRules('contact')));

        $companyId = $data['company_id'] ?? null;
        unset($data['company_id']);

        $data['lifecycle_stage'] = $data['lifecycle_stage'] ?? 'lead';
        $data['custom_values']   = CustomValueValidator::cast('contact', $data['custom_values'] ?? []);

        $contact = Contact::create($data);

        if ($companyId) {
            $contact->companies()->attach($companyId);
        }

        return redirect('/contacts/' . $contact->id)->with('flash_toast', [
            'message' => 'Contact créé.',
            'type'    => 'success',
        ]);
    }

    public function show(Contact $contact)
    {
        $contact->load('companies', 'deals.stage', 'owner');
        $activities = \App\Models\Activity::where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $stages = \App\Models\PipelineStage::orderBy('position')->get();

        return view('pages.contacts.show', compact('contact', 'activities', 'stages'));
    }

    public function edit(Contact $contact)
    {
        return view('pages.contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate(array_merge([
            'first_name'      => ['required', 'string', 'max:255'],
            'last_name'       => ['nullable', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:255'],
            'job_title'       => ['nullable', 'string', 'max:255'],
            'lifecycle_stage' => ['nullable', 'in:lead,mql,sql,opportunity,customer,evangelist,other'],
            'lead_status'     => ['nullable', 'in:new,open,in_progress,connected,unqualified,bad_fit'],
        ], CustomValueValidator::validationRules('contact')));

        $data['custom_values'] = CustomValueValidator::cast('contact', $data['custom_values'] ?? []);

        $contact->update($data);

        return redirect('/contacts/' . $contact->id)->with('flash_toast', [
            'message' => 'Contact mis à jour.',
            'type'    => 'success',
        ]);
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return redirect('/contacts')->with('flash_toast', [
            'message' => 'Contact supprimé.',
            'type'    => 'success',
        ]);
    }

    public function export(Request $request)
    {
        $search       = $request->get('search');
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
                ->when($search, fn($q) => $q->where('first_name', 'ilike', "%{$search}%")
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
                            $raw      = $c->custom_values[$field->key] ?? null;
                            $display  = CustomFieldRenderer::displayValue($field, $raw);
                            $row[]    = $display === '—' ? '' : $display;
                        }
                        fputcsv($out, $row);
                    }
                });

            fclose($out);
        }, 'contacts_' . date('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        if ($request->boolean('select_all')) {
            $count = Contact::query()->count();
            Contact::query()->delete();

            return redirect('/contacts')->with('flash_toast', [
                'message' => "{$count} contacts supprimés.",
                'type'    => 'success',
            ]);
        }

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:contacts,id'],
        ]);

        $count = Contact::whereIn('id', $data['ids'])->delete();

        return redirect('/contacts')->with('flash_toast', [
            'message' => "{$count} contact(s) supprimé(s).",
            'type'    => 'success',
        ]);
    }
}
