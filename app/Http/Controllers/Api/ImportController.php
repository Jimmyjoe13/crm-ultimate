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
            'lifecycle_stage' => 'Cycle de vie',
            'lead_status' => 'Statut lead',
            'owner_id' => 'Propriétaire (ID)',
            // Virtual company fields — resolved in ProcessCsvImport
            '__company_name' => 'Société (créer/lier par nom)',
            '__company_domain' => 'Société — Domaine',
            '__company_industry' => 'Société — Secteur',
            '__company_phone' => 'Société — Téléphone',
            '__company_website' => 'Société — Site web',
            '__company_city' => 'Société — Ville',
            '__company_country' => 'Société — Pays',
            '__company_lifecycle' => 'Société — Cycle de vie',
        ],
        'company' => [
            'name' => 'Nom',
            'domain' => 'Domaine',
            'website' => 'Site web',
            'phone' => 'Téléphone',
            'industry' => 'Secteur',
            'city' => 'Ville',
            'country' => 'Pays',
            'lifecycle_stage' => 'Cycle de vie',
            'lead_status' => 'Statut lead',
            'owner_id' => 'Propriétaire (ID)',
        ],
        'deal' => [
            'name' => 'Nom',
            'amount' => 'Montant',
            'currency' => 'Devise',
            'close_date' => 'Date de clôture',
            'status' => 'Statut',
            'owner_id' => 'Propriétaire (ID)',
            'pipeline_id' => 'Pipeline (ID)',
            'pipeline_stage_id' => 'Étape (ID)',
        ],
    ];

    // Company-name column aliases → __company_name (contact import)
    private const COMPANY_NAME_ALIASES = [
        'societe', 'société', 'entreprise', 'company', 'company_name',
        'nom_entreprise', 'nom_societe', 'organization', 'organisation',
        'account', 'account_name',
    ];

    // Additional company field aliases for contact CSV enrichment
    private const COMPANY_FIELD_ALIASES = [
        // domain
        'domaine' => '__company_domain',
        'domain' => '__company_domain',
        'website_domain' => '__company_domain',
        // industry
        'secteur' => '__company_industry',
        'secteur_activite' => '__company_industry',
        'secteur_d_activite' => '__company_industry',
        'industry' => '__company_industry',
        'sector' => '__company_industry',
        // phone (company)
        'tel_entreprise' => '__company_phone',
        'telephone_entreprise' => '__company_phone',
        'company_phone' => '__company_phone',
        // website
        'site_web' => '__company_website',
        'site' => '__company_website',
        'website_entreprise' => '__company_website',
        // city / country
        'ville_entreprise' => '__company_city',
        'pays_entreprise' => '__company_country',
        // lifecycle
        'lifecycle_entreprise' => '__company_lifecycle',
        'cycle_vie_entreprise' => '__company_lifecycle',
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

            if ($entityType === 'contact') {
                // Company-name aliases
                if (in_array($snake, self::COMPANY_NAME_ALIASES, true)) {
                    $mapping[$header] = '__company_name';

                    continue;
                }
                // Company-field aliases
                if (isset(self::COMPANY_FIELD_ALIASES[$lower])) {
                    $mapping[$header] = self::COMPANY_FIELD_ALIASES[$lower];

                    continue;
                }
                if (isset(self::COMPANY_FIELD_ALIASES[$snake])) {
                    $mapping[$header] = self::COMPANY_FIELD_ALIASES[$snake];

                    continue;
                }
            }

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
