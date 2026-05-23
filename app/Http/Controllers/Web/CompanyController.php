<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\CustomFieldRenderer;
use App\Support\CustomValueValidator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search  = $request->get('search');
        $allowed = ['name', 'industry', 'city', 'created_at'];
        $sort    = in_array($request->get('sort'), $allowed) ? $request->get('sort') : 'name';
        $dir     = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        $page    = (int) $request->get('page', 1);

        $cacheKey = 'companies.index.p' . $page . '.s' . $sort . '.d' . $dir . '.' . md5((string) $search);

        $cached = Cache::tags(['companies.index'])->remember($cacheKey, 30, function () use ($search, $sort, $dir, $page) {
            $pag = Company::with(['contacts:id,first_name,last_name', 'owner:id,name'])
                ->when($search, fn($q) => $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('industry', 'ilike', "%{$search}%"))
                ->orderBy($sort, $dir)
                ->paginate(25, ['*'], 'page', $page);

            return ['items' => $pag->items(), 'total' => $pag->total()];
        });

        $companies = new LengthAwarePaginator(
            $cached['items'],
            $cached['total'],
            25,
            $page,
            ['path' => url('/companies'), 'query' => $request->query()]
        );

        return view('pages.companies.index', compact('companies', 'search', 'sort', 'dir'));
    }

    public function create()
    {
        return view('pages.companies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'name'     => ['required', 'string', 'max:255'],
            'domain'   => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:255'],
            'website'  => ['nullable', 'url', 'max:255'],
            'city'     => ['nullable', 'string', 'max:255'],
            'country'  => ['nullable', 'string', 'max:255'],
        ], CustomValueValidator::validationRules('company')));

        $data['custom_values'] = CustomValueValidator::cast('company', $data['custom_values'] ?? []);

        $company = Company::create($data);

        return redirect('/companies/' . $company->id)->with('flash_toast', [
            'message' => 'Entreprise créée.',
            'type'    => 'success',
        ]);
    }

    public function show(Company $company)
    {
        $company->load('contacts', 'deals.stage');
        $activities = \App\Models\Activity::where('subject_type', Company::class)
            ->where('subject_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('pages.companies.show', compact('company', 'activities'));
    }

    public function edit(Company $company)
    {
        return view('pages.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate(array_merge([
            'name'     => ['required', 'string', 'max:255'],
            'domain'   => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:255'],
            'website'  => ['nullable', 'url', 'max:255'],
            'city'     => ['nullable', 'string', 'max:255'],
            'country'  => ['nullable', 'string', 'max:255'],
        ], CustomValueValidator::validationRules('company')));

        if ($request->has('custom_values')) {
            $data['custom_values'] = array_merge(
                $company->custom_values ?? [],
                CustomValueValidator::cast('company', $data['custom_values'] ?? [])
            );
        } else {
            unset($data['custom_values']);
        }

        $company->update($data);

        return redirect('/companies/' . $company->id)->with('flash_toast', [
            'message' => 'Entreprise mise à jour.',
            'type'    => 'success',
        ]);
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect('/companies')->with('flash_toast', [
            'message' => 'Entreprise supprimée.',
            'type'    => 'success',
        ]);
    }

    public function export(Request $request)
    {
        $search       = $request->get('search');
        $customFields = CustomFieldRenderer::forEntity('company');

        $headers = array_merge(
            ['id', 'nom', 'domaine', 'industrie', 'téléphone', 'site_web', 'ville', 'pays', 'étape_lifecycle', 'statut_lead', 'propriétaire', 'créé_le'],
            $customFields->pluck('label')->toArray()
        );

        return response()->streamDownload(function () use ($search, $customFields, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, $headers);

            Company::with(['owner'])
                ->when($search, fn($q) => $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('industry', 'ilike', "%{$search}%"))
                ->orderBy('name')
                ->chunk(200, function ($companies) use ($out, $customFields) {
                    foreach ($companies as $c) {
                        $row = [
                            $c->id,
                            $c->name,
                            $c->domain,
                            $c->industry,
                            $c->phone,
                            $c->website,
                            $c->city,
                            $c->country,
                            $c->lifecycle_stage,
                            $c->lead_status,
                            $c->owner?->name,
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
        }, 'entreprises_' . date('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:companies,id'],
        ]);

        $count = Company::whereIn('id', $data['ids'])->delete();

        return redirect('/companies')->with('flash_toast', [
            'message' => "{$count} entreprise(s) supprimée(s).",
            'type'    => 'success',
        ]);
    }
}
