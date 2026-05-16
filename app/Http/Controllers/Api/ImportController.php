<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvImport;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Deal;
use App\Models\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    private const CORE_FIELD_LABELS = [
        'contact' => [
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'email' => 'Email',
            'phone' => 'Téléphone',
            'job_title' => 'Poste',
            'company_id' => 'Entreprise (ID)',
            'owner_id' => 'Propriétaire (ID)',
        ],
        'company' => [
            'name' => 'Nom',
            'domain' => 'Domaine',
            'website' => 'Site web',
            'phone' => 'Téléphone',
            'industry' => 'Secteur',
            'city' => 'Ville',
            'country' => 'Pays',
            'owner_id' => 'Propriétaire (ID)',
        ],
        'deal' => [
            'name' => 'Nom',
            'amount' => 'Montant',
            'currency' => 'Devise',
            'close_date' => 'Date de clôture',
            'status' => 'Statut',
            'owner_id' => 'Propriétaire (ID)',
            'company_id' => 'Entreprise (ID)',
            'pipeline_id' => 'Pipeline (ID)',
            'pipeline_stage_id' => 'Étape (ID)',
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        return response()->json(ImportJob::query()->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:company,contact,deal'],
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = Storage::disk('local')->putFile('imports/preview', $data['file']);

        $handle = fopen(Storage::disk('local')->path($path), 'rb');
        $rawHeaders = fgetcsv($handle) ?: [];

        // Strip BOM on first header
        if (! empty($rawHeaders[0])) {
            $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeaders[0]);
        }

        $sampleRows = [];
        for ($i = 0; $i < 5 && ($row = fgetcsv($handle)) !== false; $i++) {
            $sampleRows[] = $row;
        }
        fclose($handle);

        $autoMapping = $this->buildAutoMapping($rawHeaders, $data['entity_type']);
        $availableFields = $this->buildAvailableFields($data['entity_type']);

        return response()->json([
            'preview_token' => $path,
            'headers' => $rawHeaders,
            'auto_mapping' => $autoMapping,
            'available_fields' => $availableFields,
            'sample_rows' => $sampleRows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Two modes: direct upload (legacy) or via preview_token + mapping
        if ($request->hasFile('file')) {
            $data = $request->validate([
                'entity_type' => ['required', 'in:company,contact,deal'],
                'file' => ['required', 'file', 'mimes:csv,txt'],
            ]);
            $path = $data['file']->store('imports');
            $mapping = null;
        } else {
            $data = $request->validate([
                'entity_type' => ['required', 'in:company,contact,deal'],
                'preview_token' => ['required', 'string'],
                'mapping' => ['required', 'array'],
                'mapping.*' => ['nullable', 'string'],
            ]);

            $token = $data['preview_token'];
            if (! str_starts_with($token, 'imports/preview/') || ! Storage::disk('local')->exists($token)) {
                return response()->json(['message' => 'Invalid preview token.'], 422);
            }

            // Move the preview file to the permanent imports directory
            $newPath = 'imports/'.basename($token);
            Storage::disk('local')->move($token, $newPath);
            $path = $newPath;
            $mapping = $data['mapping'];
        }

        $job = ImportJob::query()->create([
            'user_id' => $request->user()->id,
            'entity_type' => $data['entity_type'],
            'filename' => basename($path),
            'status' => 'pending',
            'mapping' => $mapping,
        ]);

        ProcessCsvImport::dispatch($job->id, $path);

        return response()->json(['data' => $job], 202);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ImportJob::query()->findOrFail($id)]);
    }

    private function buildAutoMapping(array $rawHeaders, string $entityType): array
    {
        $columnMaps = ProcessCsvImport::getColumnMaps($entityType);
        $customFields = CustomField::query()
            ->where('entity_type', $entityType)
            ->pluck('key', 'key')
            ->toArray();

        $mapping = [];
        foreach ($rawHeaders as $header) {
            $lower = mb_strtolower(trim($header));
            $snake = preg_replace('/[\s\-\.]+/', '_', $lower);

            if (isset($columnMaps[$lower])) {
                $mapping[$header] = $columnMaps[$lower];
            } elseif (isset($columnMaps[$snake])) {
                $mapping[$header] = $columnMaps[$snake];
            } elseif (isset($customFields[$snake])) {
                $mapping[$header] = $snake;
            } else {
                $mapping[$header] = null;
            }
        }

        return $mapping;
    }

    private function buildAvailableFields(string $entityType): array
    {
        $coreLabels = self::CORE_FIELD_LABELS[$entityType] ?? [];
        $fields = [];

        foreach ($coreLabels as $key => $label) {
            $fields[] = ['key' => $key, 'label' => $label, 'type' => 'core'];
        }

        $customFields = CustomField::query()
            ->where('entity_type', $entityType)
            ->orderBy('position')
            ->get(['key', 'label']);

        foreach ($customFields as $cf) {
            $fields[] = ['key' => $cf->key, 'label' => $cf->label, 'type' => 'custom'];
        }

        return $fields;
    }

    private function modelFor(string $entityType): string
    {
        return match ($entityType) {
            'company' => Company::class,
            'contact' => Contact::class,
            'deal' => Deal::class,
        };
    }
}
