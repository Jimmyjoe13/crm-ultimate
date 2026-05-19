<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\CustomValueValidator;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $search  = $request->get('search');
        $allowed = ['last_name', 'email', 'created_at'];
        $sort    = in_array($request->get('sort'), $allowed) ? $request->get('sort') : 'last_name';
        $dir     = $request->get('dir') === 'desc' ? 'desc' : 'asc';

        $contacts = Contact::with('companies')
            ->when($search, fn($q) => $q->where('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%"))
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('pages.contacts.index', compact('contacts', 'search', 'sort', 'dir'));
    }

    public function create()
    {
        return view('pages.contacts.create');
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
        ], CustomValueValidator::validationRules('contact')));

        $data['custom_values'] = CustomValueValidator::cast('contact', $data['custom_values'] ?? []);

        $contact = Contact::create($data);

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

        return view('pages.contacts.show', compact('contact', 'activities'));
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
