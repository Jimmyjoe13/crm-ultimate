<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = Company::with('contacts')
            ->when($search, fn($q) => $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('industry', 'ilike', "%{$search}%"));

        $companies = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('pages.companies.index', compact('companies', 'search'));
    }

    public function create()
    {
        return view('pages.companies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'domain'        => ['nullable', 'string', 'max:255'],
            'industry'      => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:255'],
            'website'       => ['nullable', 'url', 'max:255'],
            'city'          => ['nullable', 'string', 'max:255'],
            'country'       => ['nullable', 'string', 'max:255'],
            'custom_values' => ['nullable', 'array'],
        ]);

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
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'domain'        => ['nullable', 'string', 'max:255'],
            'industry'      => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:255'],
            'website'       => ['nullable', 'url', 'max:255'],
            'city'          => ['nullable', 'string', 'max:255'],
            'country'       => ['nullable', 'string', 'max:255'],
            'custom_values' => ['nullable', 'array'],
        ]);

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
