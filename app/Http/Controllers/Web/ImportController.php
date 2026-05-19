<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvImport;
use App\Models\CustomField;
use App\Models\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    private const CORE_FIELD_LABELS = [
        'contact' => [
            'first_name'        => 'Prénom',
            'last_name'         => 'Nom',
            'email'             => 'Email',
            'phone'             => 'Téléphone',
            'job_title'         => 'Poste',
            'lifecycle_stage'   => 'Cycle de vie',
            'lead_status'       => 'Statut lead',
            'owner_id'          => 'Propriétaire (ID)',
            '__company_name'    => 'Société (créer/lier par nom)',
            '__company_domain'  => 'Société — Domaine',
            '__company_industry'=> 'Société — Secteur',
            '__company_phone'   => 'Société — Téléphone',
            '__company_website' => 'Société — Site web',
            '__company_city'    => 'Société — Ville',
            '__company_country' => 'Société — Pays',
            '__company_lifecycle'=> 'Société — Cycle de vie',
        ],
        'company' => [
            'name'            => 'Nom',
            'domain'          => 'Domaine',
            'website'         => 'Site web',
            'phone'           => 'Téléphone',
            'industry'        => 'Secteur',
            'city'            => 'Ville',
            'country'         => 'Pays',
            'lifecycle_stage' => 'Cycle de vie',
            'lead_status'     => 'Statut lead',
            'owner_id'        => 'Propriétaire (ID)',
        ],
        'deal' => [
            'name'              => 'Nom',
            'amount'            => 'Montant',
            'currency'          => 'Devise',
            'close_date'        => 'Date de clôture',
            'status'            => 'Statut',
            'owner_id'          => 'Propriétaire (ID)',
            'pipeline_id'       => 'Pipeline (ID)',
            'pipeline_stage_id' => 'Étape (ID)',
        ],
    ];

    private const REQUIRED_FIELDS = [
        'contact' => ['email'],
        'company' => ['name'],
        'deal'    => ['name'],
    ];

    private const COMPANY_NAME_ALIASES = [
        'societe', 'société', 'entreprise', 'company', 'company_name',
        'nom_entreprise', 'nom_societe', 'organization', 'organisation',
        'account', 'account_name',
    ];

    private const COMPANY_FIELD_ALIASES = [
        'domaine'                => '__company_domain',
        'domain'                 => '__company_domain',
        'secteur'                => '__company_industry',
        'secteur_activite'       => '__company_industry',
        'industry'               => '__company_industry',
        'tel_entreprise'         => '__company_phone',
        'company_phone'          => '__company_phone',
        'site_web'               => '__company_website',
        'site'                   => '__company_website',
        'ville_entreprise'       => '__company_city',
        'pays_entreprise'        => '__company_country',
        'lifecycle_entreprise'   => '__company_lifecycle',
    ];

    public function create(string $entityType = 'contact')
    {
        if (! in_array($entityType, ['contact', 'company', 'deal'])) {
            abort(404);
        }
        return view('pages.imports.create', compact('entityType'));
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:contact,company,deal'],
            'file'        => ['required', 'file', 'mimes:csv,txt'],
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

        return response()->json([
            'preview_token'   => $path,
            'headers'         => $rawHeaders,
            'auto_mapping'    => $this->buildAutoMapping($rawHeaders, $data['entity_type']),
            'available_fields'=> $this->buildAvailableFields($data['entity_type']),
            'required_fields' => self::REQUIRED_FIELDS[$data['entity_type']],
            'sample_rows'     => $sampleRows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type'        => ['required', 'in:contact,company,deal'],
            'preview_token'      => ['required', 'string'],
            'mapping'            => ['required', 'array'],
            'mapping.*'          => ['nullable', 'string'],
            'duplicate_strategy' => ['nullable', Rule::in(['skip', 'update', 'create'])],
        ]);

        $token = $data['preview_token'];
        if (! str_starts_with($token, 'imports/preview/') || ! Storage::disk('local')->exists($token)) {
            return response()->json(['message' => 'Token invalide.'], 422);
        }

        // Server-side validation of required fields
        $entity = $data['entity_type'];
        $mappedTargets = array_values(array_filter($data['mapping']));
        $missing = array_values(array_diff(self::REQUIRED_FIELDS[$entity], $mappedTargets));
        if (! empty($missing)) {
            return response()->json(['message' => 'Champs requis non mappés.', 'missing' => $missing], 422);
        }

        $newPath = 'imports/' . basename($token);
        Storage::disk('local')->move($token, $newPath);

        $job = ImportJob::query()->create([
            'user_id'            => auth()->id(),
            'entity_type'        => $entity,
            'filename'           => basename($newPath),
            'status'             => 'pending',
            'mapping'            => $data['mapping'],
            'duplicate_strategy' => $data['duplicate_strategy'] ?? 'skip',
        ]);

        ProcessCsvImport::dispatch($job->id, $newPath);

        return response()->json(['id' => $job->id, 'status' => 'pending'], 202);
    }

    public function status(int $id): JsonResponse
    {
        $job = ImportJob::query()->findOrFail($id);
        return response()->json([
            'id'                 => $job->id,
            'status'             => $job->status,
            'total_rows'         => $job->total_rows,
            'processed_rows'     => $job->processed_rows,
            'failed_rows'        => $job->failed_rows,
            'duplicates_skipped' => $job->duplicates_skipped,
            'errors'             => $job->errors ?? [],
        ]);
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
            $ascii = Str::ascii($lower);
            $snake = preg_replace('/[\s\-\.]+/', '_', $lower);
            $asciiSnake = preg_replace('/[\s\-\.]+/', '_', $ascii);

            if ($entityType === 'contact') {
                if (in_array($snake, self::COMPANY_NAME_ALIASES, true) || in_array($asciiSnake, self::COMPANY_NAME_ALIASES, true)) {
                    $mapping[$header] = '__company_name';
                    continue;
                }
                if (isset(self::COMPANY_FIELD_ALIASES[$lower])) {
                    $mapping[$header] = self::COMPANY_FIELD_ALIASES[$lower];
                    continue;
                }
                if (isset(self::COMPANY_FIELD_ALIASES[$snake])) {
                    $mapping[$header] = self::COMPANY_FIELD_ALIASES[$snake];
                    continue;
                }
                if (isset(self::COMPANY_FIELD_ALIASES[$asciiSnake])) {
                    $mapping[$header] = self::COMPANY_FIELD_ALIASES[$asciiSnake];
                    continue;
                }
            }

            $coreKeys = array_keys(self::CORE_FIELD_LABELS[$entityType] ?? []);

            if (isset($columnMaps[$lower])) {
                $mapping[$header] = $columnMaps[$lower];
            } elseif (isset($columnMaps[$snake])) {
                $mapping[$header] = $columnMaps[$snake];
            } elseif (isset($columnMaps[$ascii])) {
                $mapping[$header] = $columnMaps[$ascii];
            } elseif (isset($columnMaps[$asciiSnake])) {
                $mapping[$header] = $columnMaps[$asciiSnake];
            } elseif (in_array($lower, $coreKeys, true)) {
                $mapping[$header] = $lower;
            } elseif (in_array($snake, $coreKeys, true)) {
                $mapping[$header] = $snake;
            } elseif (isset($customFields[$snake])) {
                $mapping[$header] = $snake;
            } elseif (isset($customFields[$lower])) {
                $mapping[$header] = $lower;
            } else {
                $mapping[$header] = null;
            }
        }

        return $mapping;
    }

    private function buildAvailableFields(string $entityType): array
    {
        $required = self::REQUIRED_FIELDS[$entityType];
        $fields = [];
        foreach (self::CORE_FIELD_LABELS[$entityType] ?? [] as $key => $label) {
            $fields[] = [
                'key'      => $key,
                'label'    => $label,
                'type'     => 'core',
                'required' => in_array($key, $required, true),
            ];
        }
        foreach (CustomField::query()->where('entity_type', $entityType)->orderBy('position')->get(['key', 'label', 'is_required']) as $cf) {
            $fields[] = [
                'key'      => $cf->key,
                'label'    => $cf->label,
                'type'     => 'custom',
                'required' => (bool) $cf->is_required,
            ];
        }
        return $fields;
    }
}
