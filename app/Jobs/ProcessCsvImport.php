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

    public function __construct(private readonly int $importJobId, private readonly string $path) {}

    public function handle(): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);
        $job->update(['status' => 'processing']);

        $handle = fopen(Storage::path($this->path), 'rb');
        $headers = fgetcsv($handle) ?: [];
        $errors = [];
        $processed = 0;
        $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $processed++;
            $payload = array_combine($headers, $row);

            try {
                $this->modelFor($job->entity_type)::query()->create($payload);
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = [
                    'row' => $processed,
                    'message' => $exception->getMessage(),
                ];
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

    private function modelFor(string $entityType): string
    {
        return match ($entityType) {
            'company' => Company::class,
            'contact' => Contact::class,
            'deal' => Deal::class,
        };
    }
}
