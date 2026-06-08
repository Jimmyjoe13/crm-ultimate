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
        $data = $this->authorizeOwnerAssignment($request, $data);

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
        $data = $this->authorizeOwnerAssignment($request, $request->validate($this->rules('update')));
        $record->fill($data);
        $record->save();

        return response()->json(['data' => $record]);
    }

    public function destroy(int $id): JsonResponse
    {
        ($this->modelClass)::query()->findOrFail($id)->delete();

        return response()->json(status: 204);
    }

    protected function authorizeOwnerAssignment(Request $request, array $data): array
    {
        if (! array_key_exists('owner_id', $data) || $data['owner_id'] === null) {
            return $data;
        }

        $user = $request->user();

        if ($user?->isAdmin() || $user?->isManager()) {
            return $data;
        }

        if ((int) $data['owner_id'] !== (int) $user?->id) {
            abort(403, 'Forbidden owner assignment.');
        }

        return $data;
    }

    abstract protected function rules(string $operation): array;
}
