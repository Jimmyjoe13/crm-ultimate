<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\CustomField;

class CustomFieldController extends Controller
{
    use CrudActions;

    protected string $modelClass = CustomField::class;

    protected array $searchable = ['entity_type', 'key', 'label', 'field_type'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'entity_type' => [$required, 'in:company,contact,deal'],
            'key' => [$required, 'alpha_dash:ascii', 'max:100'],
            'label' => [$required, 'string', 'max:255'],
            'field_type' => [$required, 'in:text,number,date,boolean,select'],
            'options' => ['nullable', 'array'],
            'is_required' => ['bool'],
            'position' => ['integer', 'min:0'],
        ];
    }
}
