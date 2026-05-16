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

    public function show(Company $company)
    {
        $company->load('contacts', 'deals.stage');
        return view('pages.companies.show', compact('company'));
    }
}
