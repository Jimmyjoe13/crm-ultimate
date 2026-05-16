<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Services\SegmentQueryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SegmentController extends Controller
{
    use CrudActions;

    protected string $modelClass = Segment::class;

    protected array $searchable = ['name', 'description'];

    public function __construct(private readonly SegmentQueryEngine $engine) {}

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'name'        => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_type' => [$required, 'in:contact,company,deal'],
            'rules'       => [$required, 'array'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules('store'));
        $this->validateRules($data['rules'], $data['entity_type']);

        $data['created_by'] = $request->user()?->id;

        $segment = Segment::query()->create($data);
        $segment->refresh();

        return response()->json(['data' => $segment], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);
        $data = $request->validate($this->rules('update'));

        if (isset($data['rules'])) {
            $entityType = $data['entity_type'] ?? $segment->entity_type;
            $this->validateRules($data['rules'], $entityType);
        }

        $segment->fill($data)->save();

        return response()->json(['data' => $segment]);
    }

    /** GET /segments/{id}/members */
    public function members(Request $request, int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);
        $perPage = (int) $request->query('per_page', 25);
        $page    = (int) $request->query('page', 1);

        $query = $this->engine->buildQuery($segment);
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        if ($page === 1) {
            $segment->update([
                'last_count'       => $result->total(),
                'last_computed_at' => now(),
            ]);
        }

        return response()->json($result);
    }

    /** POST /segments/{id}/refresh */
    public function refreshCount(int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);
        $count   = $this->engine->buildQuery($segment)->count();

        $segment->update([
            'last_count'       => $count,
            'last_computed_at' => now(),
        ]);

        return response()->json([
            'count'       => $count,
            'computed_at' => $segment->last_computed_at,
        ]);
    }

    /** POST /segments/preview — calcule count + échantillon sans persister */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:contact,company,deal'],
            'rules'       => ['required', 'array'],
        ]);

        $this->validateRules($data['rules'], $data['entity_type']);

        $segment = new Segment(['entity_type' => $data['entity_type'], 'rules' => $data['rules']]);
        $query   = $this->engine->buildQuery($segment);

        $count  = $query->count();
        $sample = (clone $query)->limit(20)->get();

        return response()->json([
            'count'  => $count,
            'sample' => $sample,
        ]);
    }

    /** GET /segments/fields/{entityType} */
    public function fields(string $entityType): JsonResponse
    {
        try {
            $fields = $this->engine->availableFields($entityType);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $fields]);
    }

    private function validateRules(array $rules, string $entityType): void
    {
        try {
            $this->engine->validateTree($rules, $entityType);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }
}
