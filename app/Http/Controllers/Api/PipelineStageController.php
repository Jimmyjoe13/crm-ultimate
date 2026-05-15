<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\PipelineStage;

class PipelineStageController extends Controller
{
    use CrudActions;

    protected string $modelClass = PipelineStage::class;

    protected array $searchable = ['name'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'pipeline_id' => [$required, 'exists:pipelines,id'],
            'name' => [$required, 'string', 'max:255'],
            'position' => ['integer', 'min:0'],
            'probability' => ['integer', 'min:0', 'max:100'],
            'is_won' => ['bool'],
            'is_lost' => ['bool'],
        ];
    }
}
