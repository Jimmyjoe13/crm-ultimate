<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvImport;
use App\Models\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(ImportJob::query()->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:company,contact,deal'],
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $data['file']->store('imports');

        $job = ImportJob::query()->create([
            'user_id' => $request->user()->id,
            'entity_type' => $data['entity_type'],
            'filename' => $data['file']->getClientOriginalName(),
            'status' => 'pending',
        ]);

        ProcessCsvImport::dispatch($job->id, $path);

        return response()->json(['data' => $job], 202);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ImportJob::query()->findOrFail($id)]);
    }
}
