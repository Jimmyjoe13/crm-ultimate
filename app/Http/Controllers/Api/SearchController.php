<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['companies' => [], 'contacts' => [], 'deals' => []]);
        }

        $term = '%'.$q.'%';

        $companies = Company::query()
            ->where(fn ($query) => $query
                ->where('name', 'ilike', $term)
                ->orWhere('domain', 'ilike', $term)
                ->orWhere('industry', 'ilike', $term)
            )
            ->limit(10)
            ->get(['id', 'name', 'domain', 'industry']);

        $contacts = Contact::query()
            ->where(fn ($query) => $query
                ->where('first_name', 'ilike', $term)
                ->orWhere('last_name', 'ilike', $term)
                ->orWhere('email', 'ilike', $term)
            )
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'job_title']);

        $deals = Deal::query()
            ->where('name', 'ilike', $term)
            ->limit(10)
            ->get(['id', 'name', 'amount', 'currency', 'status']);

        return response()->json([
            'companies' => $companies,
            'contacts' => $contacts,
            'deals' => $deals,
        ]);
    }
}
