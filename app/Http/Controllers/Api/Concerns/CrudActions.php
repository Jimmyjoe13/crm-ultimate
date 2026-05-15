<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Support\CrmQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CrudActions
{
    public function index(Request $request): JsonResponse
    {
        $query = CrmQuery::apply(($this->modelClass)::query(), $request, $this->searchable);

        return response()->json($query->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules('store'));
        $record = ($this->modelClass)::query()->create($data);
        $record->refresh();

        return response()->json(['data' => $record], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ($this->modelClass)::query()->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = ($this->modelClass)::query()->findOrFail($id);
        $record->fill($request->validate($this->rules('update')));
        $record->save();

        return response()->json(['data' => $record]);
    }

    public function destroy(int $id): JsonResponse
    {
        ($this->modelClass)::query()->findOrFail($id)->delete();

        return response()->json(status: 204);
    }

    abstract protected function rules(string $operation): array;
}
