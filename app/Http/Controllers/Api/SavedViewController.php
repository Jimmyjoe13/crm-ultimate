<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\SavedView;

class SavedViewController extends Controller
{
    use CrudActions;

    protected string $modelClass = SavedView::class;

    protected array $searchable = ['entity_type', 'name'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'user_id' => [$required, 'exists:users,id'],
            'entity_type' => [$required, 'in:company,contact,deal,activity'],
            'name' => [$required, 'string', 'max:255'],
            'filters' => ['array'],
            'sort' => ['array'],
            'columns' => ['array'],
        ];
    }
}
