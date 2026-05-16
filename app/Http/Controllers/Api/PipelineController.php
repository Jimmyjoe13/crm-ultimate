<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Pipeline;
use Illuminate\Http\JsonResponse;

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

    public function show(int $id): JsonResponse
    {
        $pipeline = Pipeline::query()->with(['stages' => fn ($q) => $q->orderBy('position')])->findOrFail($id);

        return response()->json(['data' => $pipeline]);
    }
}
