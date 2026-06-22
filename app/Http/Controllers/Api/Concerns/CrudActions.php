<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Support\CrmQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CrudActions
{
    public function index(Request $request): JsonResponse
    {
        $query = CrmQuery::apply($this->scopedQuery($request), $request, $this->searchable);

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

    public function show(Request $request, int $id): JsonResponse
    {
        // findOrFail sur la requête scopée → 404 si hors périmètre (ne leak pas l'existence).
        return response()->json(['data' => $this->scopedQuery($request)->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->scopedQuery($request)->findOrFail($id);
        $data = $this->authorizeOwnerAssignment($request, $request->validate($this->rules('update')));
        $record->fill($data);
        $record->save();

        return response()->json(['data' => $record]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->scopedQuery($request)->findOrFail($id)->delete();

        return response()->json(status: 204);
    }

    /**
     * Requête de base du modèle, restreinte au périmètre de l'utilisateur courant
     * (cloisonnement par owner_id) lorsque le modèle expose le scope `visibleTo`.
     *
     * Les modèles sans owner_id (config partagée : pipelines, champs custom…) ne
     * portent pas le trait ScopesToOwner et restent donc non filtrés.
     */
    protected function scopedQuery(Request $request): Builder
    {
        $query = ($this->modelClass)::query();

        if (method_exists($this->modelClass, 'scopeVisibleTo')) {
            $query->visibleTo($request->user());
        }

        return $query;
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
