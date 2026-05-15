<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Pipeline;

class PipelineController extends Controller
{
    use CrudActions;

    protected string $modelClass = Pipeline::class;

    protected array $searchable = ['name'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'is_default' => ['bool'],
        ];
    }
}
