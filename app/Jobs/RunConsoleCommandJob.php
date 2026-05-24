<?php

namespace App\Jobs;

use App\Models\ConsoleRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class RunConsoleCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        private readonly ConsoleRun $run,
        private readonly string $command,
        private readonly array $args = [],
    ) {}

    public function handle(): void
    {
        $this->run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $exitCode = Artisan::call($this->command, $this->args);
            $output   = Artisan::output();
        } catch (\Throwable $e) {
            $this->run->update([
                'status'      => 'failed',
                'output'      => $e->getMessage(),
                'exit_code'   => 1,
                'finished_at' => now(),
            ]);
            return;
        }

        $this->run->update([
            'status'      => $exitCode === 0 ? 'done' : 'failed',
            'output'      => $output ?: '(aucun output)',
            'exit_code'   => $exitCode,
            'finished_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->run->update([
            'status'      => 'failed',
            'output'      => 'Job échoué : ' . $e->getMessage(),
            'exit_code'   => 1,
            'finished_at' => now(),
        ]);
    }
}
