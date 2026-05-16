<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = Contact::with('companies')
            ->when($search, fn($q) => $q->where('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%"));

        $contacts = $query->orderBy('last_name')->paginate(25)->withQueryString();

        return view('pages.contacts.index', compact('contacts', 'search'));
    }

    public function show(Contact $contact)
    {
        $contact->load('companies', 'deals.stage');
        return view('pages.contacts.show', compact('contact'));
    }
}
