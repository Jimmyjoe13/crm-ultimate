<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\ExportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessCsvExport implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $exportJobId) {}

    public function handle(): void
    {
        $job = ExportJob::query()->findOrFail($this->exportJobId);
        $query = $this->modelFor($job->entity_type)::query();

        foreach ($job->filters ?? [] as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        $records = $query->get();
        $path = 'exports/'.$job->entity_type.'-'.$job->id.'.csv';

        Storage::makeDirectory('exports');
        $handle = fopen(Storage::path($path), 'wb');

        if ($records->isNotEmpty()) {
            fputcsv($handle, array_keys($records->first()->getAttributes()));
        }

        foreach ($records as $record) {
            fputcsv($handle, array_map(
                fn ($value) => is_array($value) ? json_encode($value) : $value,
                $record->getAttributes(),
            ));
        }

        fclose($handle);

        $job->update([
            'status' => 'completed',
            'file_path' => $path,
            'total_rows' => $records->count(),
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
