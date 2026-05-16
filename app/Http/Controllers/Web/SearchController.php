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
}
