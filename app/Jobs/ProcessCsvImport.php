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

    /**
     * Maps common/French CSV header names to DB column names, per entity type.
     * Keys are lowercase+trimmed. Applied after BOM strip and basic normalization.
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

    public function __construct(private readonly int $importJobId, private readonly string $path) {}

    public function handle(): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);
        $job->update(['status' => 'processing']);

        $handle = fopen(Storage::path($this->path), 'rb');
        $rawHeaders = fgetcsv($handle) ?: [];
        $headers = $this->normalizeHeaders($rawHeaders, $job->entity_type);

        $model = $this->modelFor($job->entity_type);
        $fillable = array_flip((new $model)->getFillable());

        $errors = [];
        $processed = 0;
        $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $processed++;

            if (count($row) !== count($headers)) {
                $failed++;
                if (count($errors) < 100) {
                    $errors[] = ['row' => $processed, 'message' => 'Nombre de colonnes incorrect.'];
                }
                continue;
            }

            // Keep only fillable fields and drop empty strings
            $payload = array_filter(
                array_intersect_key(array_combine($headers, $row), $fillable),
                fn ($v) => $v !== '' && $v !== null
            );

            if (empty($payload)) {
                $failed++;
                if (count($errors) < 100) {
                    $errors[] = ['row' => $processed, 'message' => 'Aucun champ reconnu — vérifiez les en-têtes CSV.'];
                }
                continue;
            }

            // Default owner to the user who triggered the import
            if (! isset($payload['owner_id']) && isset($fillable['owner_id'])) {
                $payload['owner_id'] = $job->user_id;
            }

            try {
                $model::query()->create($payload);
            } catch (\Throwable $exception) {
                $failed++;
                if (count($errors) < 100) {
                    $errors[] = ['row' => $processed, 'message' => $exception->getMessage()];
                }
            }
        }

        fclose($handle);

        $job->update([
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'total_rows' => $processed,
            'processed_rows' => $processed - $failed,
            'failed_rows' => $failed,
            'errors' => $errors,
        ]);
    }

    private function normalizeHeaders(array $rawHeaders, string $entityType): array
    {
        $map = self::COLUMN_MAPS[$entityType] ?? [];

        return array_map(function (string $header) use ($map): string {
            // Strip UTF-8 BOM present on first column of Excel-exported CSVs
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
            $lower = mb_strtolower(trim($header));

            // Direct alias match (handles accented characters)
            if (isset($map[$lower])) {
                return $map[$lower];
            }

            // Normalize separators then try alias map again
            $snake = preg_replace('/[\s\-\.]+/', '_', $lower);
            if (isset($map[$snake])) {
                return $map[$snake];
            }

            // Return snake_case version for direct DB column name match
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
