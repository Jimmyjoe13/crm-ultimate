<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function index()
    {
        $fields = CustomField::orderBy('entity_type')->orderBy('label')->get();
        return view('pages.settings.fields', compact('fields'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:contact,company,deal'],
            'label'       => ['required', 'string', 'max:100'],
            'key'         => ['required', 'string', 'max:60', 'alpha_dash'],
            'field_type'  => ['required', 'in:text,number,date,boolean,select'],
            'options'     => ['nullable', 'array'],
        ]);

        CustomField::create($data);

        return back()->with('success', 'Champ créé.');
    }
}
