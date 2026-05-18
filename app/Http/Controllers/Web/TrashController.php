<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;

class TrashController extends Controller
{
    public function index()
    {
        $contacts  = Contact::onlyTrashed()->orderByDesc('deleted_at')->get();
        $companies = Company::onlyTrashed()->orderByDesc('deleted_at')->get();
        $deals     = Deal::onlyTrashed()->orderByDesc('deleted_at')->get();

        return view('pages.trash.index', compact('contacts', 'companies', 'deals'));
    }

    public function restoreContact(int $id)
    {
        Contact::withTrashed()->findOrFail($id)->restore();

        return redirect('/trash')->with('flash_toast', [
            'message' => 'Contact restauré.',
            'type'    => 'success',
        ]);
    }

    public function restoreCompany(int $id)
    {
        Company::withTrashed()->findOrFail($id)->restore();

        return redirect('/trash')->with('flash_toast', [
            'message' => 'Entreprise restaurée.',
            'type'    => 'success',
        ]);
    }

    public function restoreDeal(int $id)
    {
        Deal::withTrashed()->findOrFail($id)->restore();

        return redirect('/trash')->with('flash_toast', [
            'message' => 'Deal restauré.',
            'type'    => 'success',
        ]);
    }
}
