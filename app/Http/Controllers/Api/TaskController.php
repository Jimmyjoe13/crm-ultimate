<?php

namespace App\Http\Controllers\Api;

use App\Models\Activity;
use App\Support\CrmQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ActivityController
{
    public function index(Request $request): JsonResponse
    {
        $query = CrmQuery::apply(Activity::query()->where('type', Activity::TYPE_TASK), $request, $this->searchable);

        return response()->json($query->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['type' => Activity::TYPE_TASK]);

        return parent::store($request);
    }
}
