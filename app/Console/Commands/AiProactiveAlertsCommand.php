<?php

namespace App\Console\Commands;

use App\Jobs\AiProactiveAlertsJob;
use Illuminate\Console\Command;

class AiProactiveAlertsCommand extends Command
{
    protected $signature = 'ai:proactive-alerts
                            {--sync : Exécuter synchronement (pas en queue)}';

    protected $description = 'Analyse le pipeline et génère des alertes IA proactives';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Génération des alertes proactives (sync)…');
            (new AiProactiveAlertsJob())->handle();
        } else {
            $this->info('Dispatch du job AiProactiveAlertsJob en queue…');
            AiProactiveAlertsJob::dispatch();
        }

        $this->info('Fait.');
        return Command::SUCCESS;
    }
}
