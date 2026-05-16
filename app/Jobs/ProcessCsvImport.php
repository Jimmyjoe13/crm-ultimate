<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\ImportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessCsvImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /**
     * Maps common/French CSV header names to DB column names, per entity type.
     * Keys are lowercase+trimmed.
     */
    private const COLUMN_MAPS = [
        'contact' => [
            'prenom' => 'first_name', 'prénom' => 'first_name',
            'firstname' => 'first_name', 'first name' => 'first_name',
            'givenname' => 'first_name', 'given name' => 'first_name',
            'nom' => 'last_name', 'lastname' => 'last_name', 'last name' => 'last_name',
            'surname' => 'last_name', 'nom de famille' => 'last_name',
            'mail' => 'email', 'courriel' => 'email',
            'e-mail' => 'email', 'adresse email' => 'email', 'adresse mail' => 'email',
            'tel' => 'phone', 'tél' => 'phone', 'téléphone' => 'phone',
            'telephone' => 'phone', 'mobile' => 'phone', 'portable' => 'phone',
            'phone number' => 'phone',
            'poste' => 'job_title', 'titre' => 'job_title', 'fonction' => 'job_title',
            'function' => 'job_title', 'title' => 'job_title', 'position' => 'job_title',
            'job title' => 'job_title', 'intitulé' => 'job_title', 'intitule' => 'job_title',
            'metier' => 'job_title', 'métier' => 'job_title',
            'proprietaire' => 'owner_id', 'propriétaire' => 'owner_id',
            'cycle de vie' => 'lifecycle_stage', 'lifecycle' => 'lifecycle_stage',
            'statut lead' => 'lead_status', 'lead status' => 'lead_status',
        ],
        'company' => [
            'nom' => 'name', 'company' => 'name', 'entreprise' => 'name',
            'societe' => 'name', 'société' => 'name',
            'raison sociale' => 'name', 'company name' => 'name',
            'nom entreprise' => 'name',
            'domaine' => 'domain', 'site' => 'domain', 'site web' => 'website',
            'url' => 'website',
            'secteur' => 'industry', 'sector' => 'industry',
            "secteur d'activite" => 'industry', "secteur d'activité" => 'industry',
            'tel' => 'phone', 'tél' => 'phone', 'téléphone' => 'phone', 'telephone' => 'phone',
            'ville' => 'city', 'pays' => 'country',
            'proprietaire' => 'owner_id', 'propriétaire' => 'owner_id',
            'cycle de vie' => 'lifecycle_stage', 'lifecycle' => 'lifecycle_stage',
            'statut lead' => 'lead_status',
        ],
        'deal' => [
            'nom' => 'name', 'opportunite' => 'name', 'opportunité' => 'name',
            'montant' => 'amount', 'valeur' => 'amount', 'value' => 'amount',
            'devise' => 'currency',
            'date de cloture' => 'close_date', 'date cloture' => 'close_date',
            'statut' => 'status',
            'proprietaire' => 'owner_id', 'propriétaire' => 'owner_id',
        ],
    ];

    private const DUPE_KEYS = [
        'contact' => 'email',
        'company' => 'domain',
        'deal' => null,
    ];

    // Virtual __company_* field prefixes extracted before fillable filter
    private const COMPANY_VIRTUAL_FIELDS = [
        '__company_name',
        '__company_domain',
        '__company_industry',
        '__company_phone',
        '__company_website',
        '__company_city',
        '__company_country',
        '__company_lifecycle',
    ];

    public static function getColumnMaps(string $entityType): array
    {
        return self::COLUMN_MAPS[$entityType] ?? [];
    }

    public function __construct(private readonly int $importJobId, private readonly string $path) {}

    public function handle(): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);
        $job->update(['status' => 'processing']);

        $handle = fopen(Storage::path($this->path), 'rb');
        $rawHeaders = fgetcsv($handle) ?: [];

        $model = $this->modelFor($job->entity_type);
        $fillable = array_flip((new $model)->getFillable());

        if (! empty($job->mapping)) {
            $headers = $this->applyUserMapping($rawHeaders, $job->mapping);
        } else {
            $headers = $this->normalizeHeaders($rawHeaders, $job->entity_type);
        }

        $existing = $this->loadExistingKeys($job->entity_type);

        $errors = [];
        $processed = 0;
        $failed = 0;
        $duplicatesSkipped = 0;
        $buffer = [];

        while (($row = fgetcsv($handle)) !== false) {
            $processed++;

            if (count($row) !== count($headers)) {
                $failed++;
                if (count($errors) < 100) {
                    $errors[] = ['row' => $processed, 'message' => 'Nombre de colonnes incorrect.'];
                }

                continue;
            }

            $combined = array_combine($headers, $row);

            // Extract all virtual __company_* fields before fillable filter
            $companyPayload = [];
            foreach (self::COMPANY_VIRTUAL_FIELDS as $vf) {
                if (isset($combined[$vf]) && $combined[$vf] !== '') {
                    $companyPayload[$vf] = $combined[$vf];
                }
            }

            $payload = array_filter(
                array_intersect_key($combined, $fillable),
                fn ($v) => $v !== '' && $v !== null
            );

            if (empty($payload) && empty($companyPayload)) {
                $failed++;
                if (count($errors) < 100) {
                    $errors[] = ['row' => $processed, 'message' => 'Aucun champ reconnu — vérifiez les en-têtes CSV.'];
                }

                continue;
            }

            if (! isset($payload['owner_id']) && isset($fillable['owner_id'])) {
                $payload['owner_id'] = $job->user_id;
            }

            // Resolve __company_* → company record, then attach via pivot
            $attachCompanyId = null;
            if (! empty($companyPayload) && $job->entity_type === 'contact') {
                $attachCompanyId = $this->resolveCompany($companyPayload);
            }

            // Duplicate detection
            $dupeKey = $this->duplicateKey($payload, $job->entity_type);
            if ($dupeKey !== null && isset($existing[$dupeKey])) {
                $duplicatesSkipped++;

                continue;
            }

            $buffer[] = [
                'row' => $processed,
                'payload' => $payload,
                'attachCompanyId' => $attachCompanyId,
            ];

            if ($dupeKey !== null) {
                $existing[$dupeKey] = true;
            }

            if (count($buffer) >= 500) {
                $this->flushBuffer($buffer, $model, $failed, $errors);
                $buffer = [];
            }
        }

        if (! empty($buffer)) {
            $this->flushBuffer($buffer, $model, $failed, $errors);
        }

        fclose($handle);

        $job->update([
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'total_rows' => $processed,
            'processed_rows' => $processed - $failed - $duplicatesSkipped,
            'failed_rows' => $failed,
            'duplicates_skipped' => $duplicatesSkipped,
            'errors' => $errors,
        ]);
    }

    private function resolveCompany(array $companyPayload): int
    {
        // Build the company core payload (strip __ prefix)
        $coreFields = [
            '__company_name' => 'name',
            '__company_domain' => 'domain',
            '__company_industry' => 'industry',
            '__company_phone' => 'phone',
            '__company_website' => 'website',
            '__company_city' => 'city',
            '__company_country' => 'country',
            '__company_lifecycle' => 'lifecycle_stage',
        ];

        $mapped = [];
        foreach ($coreFields as $virtual => $col) {
            if (isset($companyPayload[$virtual]) && $companyPayload[$virtual] !== '') {
                $mapped[$col] = $companyPayload[$virtual];
            }
        }

        if (empty($mapped)) {
            return 0;
        }

        // Prefer domain as unique match key, fall back to name
        $matchKey = isset($mapped['domain'])
            ? ['domain' => $mapped['domain']]
            : ['name' => $mapped['name'] ?? 'Unknown'];

        $company = Company::query()->firstOrCreate($matchKey, $mapped);

        // Enrich with any newly discovered fields
        $toUpdate = array_filter($mapped, fn ($v, $k) => ! isset($matchKey[$k]) && $v !== '', ARRAY_FILTER_USE_BOTH);
        if (! empty($toUpdate)) {
            $company->fill($toUpdate)->save();
        }

        return $company->id;
    }

    private function flushBuffer(array $buffer, string $model, int &$failed, array &$errors): void
    {
        // Each create is independent — no wrapping transaction.
        // PostgreSQL aborts the whole transaction on any error, so wrapping
        // a batch in one transaction would silently kill all rows after the first failure.
        foreach ($buffer as ['row' => $row, 'payload' => $payload, 'attachCompanyId' => $attachCompanyId]) {
            try {
                $record = $model::query()->create($payload);

                if ($attachCompanyId && $model === Contact::class) {
                    $record->companies()->attach($attachCompanyId, [
                        'role' => 'employee',
                        'is_primary' => true,
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                if (count($errors) < 100) {
                    $errors[] = ['row' => $row, 'message' => $e->getMessage()];
                }
            }
        }
    }

    private function loadExistingKeys(string $entityType): array
    {
        return match ($entityType) {
            'contact' => Contact::query()->whereNotNull('email')->pluck('email')->flip()->toArray(),
            'company' => Company::query()->whereNotNull('domain')->pluck('domain')->flip()->toArray(),
            'deal' => Deal::query()->pluck('name')->flip()->toArray(),
            default => [],
        };
    }

    private function duplicateKey(array $payload, string $entityType): ?string
    {
        return match ($entityType) {
            'contact' => isset($payload['email']) && $payload['email'] !== '' ? $payload['email'] : null,
            'company' => isset($payload['domain']) && $payload['domain'] !== '' ? $payload['domain'] : null,
            'deal' => $payload['name'] ?? null,
            default => null,
        };
    }

    private function applyUserMapping(array $rawHeaders, array $mapping): array
    {
        return array_map(function (string $header) use ($mapping): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

            if (array_key_exists($header, $mapping)) {
                return $mapping[$header] ?? '__ignored_'.md5($header);
            }

            $lower = mb_strtolower(trim($header));

            return preg_replace('/[\s\-\.]+/', '_', $lower);
        }, $rawHeaders);
    }

    private function normalizeHeaders(array $rawHeaders, string $entityType): array
    {
        $map = self::COLUMN_MAPS[$entityType] ?? [];

        return array_map(function (string $header) use ($map): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
            $lower = mb_strtolower(trim($header));

            if (isset($map[$lower])) {
                return $map[$lower];
            }

            $snake = preg_replace('/[\s\-\.]+/', '_', $lower);
            if (isset($map[$snake])) {
                return $map[$snake];
            }

            return $snake;
        }, $rawHeaders);
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
