<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvExport;
use App\Models\ExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(ExportJob::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:company,contact,deal'],
            'filters' => ['array'],
        ]);

        $job = ExportJob::query()->create([
            'user_id' => $request->user()->id,
            'entity_type' => $data['entity_type'],
            'status' => 'pending',
            'filters' => $data['filters'] ?? [],
        ]);

        ProcessCsvExport::dispatch($job->id);

        return response()->json(['data' => $job], 202);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->ownedJobOrFail($request, $id)]);
    }

    public function download(Request $request, int $id)
    {
        $job = $this->ownedJobOrFail($request, $id);

        abort_unless($job->file_path && Storage::exists($job->file_path), 404);

        return Storage::download($job->file_path);
    }

    private function ownedJobOrFail(Request $request, int $id): ExportJob
    {
        return ExportJob::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }
}
