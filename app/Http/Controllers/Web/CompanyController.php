<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesOwnerAccess;
use App\Models\Activity;
use App\Models\Company;
use App\Support\CustomFieldRenderer;
use App\Support\CustomValueValidator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CompanyController extends Controller
{
    use AuthorizesOwnerAccess;

    public function index(Request $request)
    {
        $search = $request->get('search');
        $allowed = ['name', 'industry', 'city', 'created_at'];
        $sort = in_array($request->get('sort'), $allowed) ? $request->get('sort') : 'name';
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        $page = (int) $request->get('page', 1);

        // Filtres
        $industry = $request->get('industry');
        $lifecycle = $request->get('lifecycle_stage');
        $ownerId = $request->get('owner_id');

        // Cloisonnement par owner : la clé de cache intègre le périmètre + chaque filtre.
        $user = $request->user();
        $scopeKey = md5(json_encode($user->accessibleOwnerIds() ?? 'all'));
        $cacheKey = 'companies.index.p'.$page.'.s'.$sort.'.d'.$dir.'.'.md5((string) $search)
            .'.in'.md5((string) $industry).'.lc'.$lifecycle.'.o'.$ownerId
            .'.sc'.$scopeKey;

        $cached = Cache::tags(['companies.index'])->remember($cacheKey, 30, function () use ($search, $sort, $dir, $page, $user, $industry, $lifecycle, $ownerId) {
            $pag = Company::with(['contacts:id,first_name,last_name', 'owner:id,name'])
                ->visibleTo($user)
                ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', "%{$search}%")
                    ->orWhere('industry', 'ilike', "%{$search}%")))
                ->when($industry, fn ($q) => $q->where('industry', $industry))
                ->when($lifecycle, fn ($q) => $q->where('lifecycle_stage', $lifecycle))
                ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
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

        // Liste des industries présentes (pour le filtre), bornée au périmètre owner.
        $industries = Company::visibleTo($user)
            ->whereNotNull('industry')->where('industry', '!=', '')
            ->distinct()->orderBy('industry')->pluck('industry');

        $owners = $this->visibleOwners($user);

        return view('pages.companies.index', compact('companies', 'search', 'sort', 'dir', 'industry', 'lifecycle', 'ownerId', 'industries', 'owners'));
    }

    public function create()
    {
        return view('pages.companies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ], CustomValueValidator::validationRules('company')));

        // owner_id auto-assigné à l'utilisateur connecté
        $data['owner_id'] = $request->user()->id;

        $data['custom_values'] = CustomValueValidator::cast('company', $data['custom_values'] ?? []);

        $company = Company::create($data);

        return redirect('/companies/'.$company->id)->with('flash_toast', [
            'message' => 'Entreprise créée.',
            'type' => 'success',
        ]);
    }

    public function show(Request $request, Company $company)
    {
        // Cloisonnement par owner : 404 si l'entreprise est hors du périmètre du commercial.
        $this->ensureVisible($company, $request->user());

        $company->load('contacts', 'deals.stage');
        $activities = Activity::where('subject_type', Company::class)
            ->where('subject_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('pages.companies.show', compact('company', 'activities'));
    }

    public function edit(Request $request, Company $company)
    {
        // Cloisonnement par owner : 404 si l'entreprise est hors du périmètre du commercial.
        $this->ensureVisible($company, $request->user());

        return view('pages.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        // Cloisonnement par owner : 404 si l'entreprise est hors du périmètre du commercial.
        $this->ensureVisible($company, $request->user());

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ], CustomValueValidator::validationRules('company')));

        if (array_key_exists('custom_values', $data)) {
            $data['custom_values'] = array_merge(
                $company->custom_values ?? [],
                CustomValueValidator::cast('company', is_array($data['custom_values']) ? $data['custom_values'] : [])
            );
        }

        $company->update($data);

        return redirect('/companies/'.$company->id)->with('flash_toast', [
            'message' => 'Entreprise mise à jour.',
            'type' => 'success',
        ]);
    }

    public function destroy(Request $request, Company $company)
    {
        // Cloisonnement par owner : 404 si l'entreprise est hors du périmètre du commercial.
        $this->ensureVisible($company, $request->user());

        $company->delete();

        return redirect('/companies')->with('flash_toast', [
            'message' => 'Entreprise supprimée.',
            'type' => 'success',
        ]);
    }

    public function export(Request $request)
    {
        $search = $request->get('search');
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
                ->when($search, fn ($q) => $q->where('name', 'ilike', "%{$search}%")
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
                            $raw = $c->custom_values[$field->key] ?? null;
                            $display = CustomFieldRenderer::displayValue($field, $raw);
                            $row[] = $display === '—' ? '' : $display;
                        }
                        fputcsv($out, $row);
                    }
                });

            fclose($out);
        }, 'entreprises_'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:companies,id'],
        ]);

        $count = Company::whereIn('id', $data['ids'])
            ->visibleTo($user)
            ->delete();

        return redirect('/companies')->with('flash_toast', [
            'message' => "{$count} entreprise(s) supprimée(s).",
            'type' => 'success',
        ]);
    }
}
