<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q', '');
        $results = ['deals' => [], 'contacts' => [], 'companies' => []];

        if (strlen($q) >= 2) {
            $results['deals'] = Deal::where('name', 'ilike', "%{$q}%")->limit(5)->get();
            $results['contacts'] = Contact::where('first_name', 'ilike', "%{$q}%")
                ->orWhere('last_name', 'ilike', "%{$q}%")
                ->limit(5)->get();
            $results['companies'] = Company::where('name', 'ilike', "%{$q}%")->limit(5)->get();
        }

        return view('pages.search', compact('q', 'results'));
    }

    public function quick(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['contacts' => [], 'companies' => [], 'deals' => []]);
        }

        $contacts = Contact::where('first_name', 'ilike', "%{$q}%")
            ->orWhere('last_name', 'ilike', "%{$q}%")
            ->orWhere('email', 'ilike', "%{$q}%")
            ->limit(5)->get(['id', 'first_name', 'last_name', 'email', 'job_title']);

        $companies = Company::where('name', 'ilike', "%{$q}%")
            ->orWhere('industry', 'ilike', "%{$q}%")
            ->limit(5)->get(['id', 'name', 'industry', 'city']);

        $deals = Deal::where('name', 'ilike', "%{$q}%")
            ->limit(5)->get(['id', 'name', 'amount', 'status']);

        return response()->json([
            'contacts'  => $contacts->map(fn($c) => [
                'id'       => $c->id,
                'label'    => trim("{$c->first_name} {$c->last_name}"),
                'sub'      => $c->job_title ?: $c->email,
                'url'      => "/contacts/{$c->id}",
            ]),
            'companies' => $companies->map(fn($c) => [
                'id'    => $c->id,
                'label' => $c->name,
                'sub'   => implode(', ', array_filter([$c->industry, $c->city])),
                'url'   => "/companies/{$c->id}",
            ]),
            'deals' => $deals->map(fn($d) => [
                'id'    => $d->id,
                'label' => $d->name,
                'sub'   => $d->status,
                'url'   => "/deals/{$d->id}",
            ]),
        ]);
    }
}
