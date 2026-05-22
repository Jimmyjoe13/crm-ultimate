<?php

namespace App\Console\Commands;

use App\Services\EmeliaService;
use Illuminate\Console\Command;

class EmeliaSyncAllCampaigns extends Command
{
    protected $signature = 'emelia:sync-all-campaigns
                            {--only-linked : Traite seulement les contacts déjà liés à Emelia}
                            {--dry-run : Affiche ce qui serait fait sans modifier la BDD}';

    protected $description = 'Synchronise les contacts CRM avec toutes les campagnes Emelia actives';

    public function handle(EmeliaService $emelia): int
    {
        $this->info('Récupération des campagnes Emelia...');

        try {
            $raw = $emelia->listCampaigns();
        } catch (\RuntimeException $e) {
            $this->error('Emelia API : '.$e->getMessage());
            return 1;
        }

        $campaigns = $raw['campaigns'] ?? $raw;

        if (empty($campaigns)) {
            $this->warn('Aucune campagne trouvée dans Emelia.');
            return 0;
        }

        $this->info(count($campaigns).' campagne(s) trouvée(s).');

        $hasErrors = false;

        foreach ($campaigns as $campaign) {
            $id   = $campaign['_id'] ?? $campaign['id'] ?? null;
            $name = $campaign['name'] ?? $id;

            if (! $id) {
                continue;
            }

            $this->newLine();
            $this->line("→ Sync campagne : \"$name\" ($id)");

            $args = ['campaign_id' => $id];
            if ($this->option('only-linked')) {
                $args['--only-linked'] = true;
            }
            if ($this->option('dry-run')) {
                $args['--dry-run'] = true;
            }

            $result = $this->call('emelia:sync-campaign', $args);

            if ($result !== 0) {
                $hasErrors = true;
            }
        }

        $this->newLine();
        $this->info('Sync toutes campagnes terminée.');

        // Récupération des events (ouvertures, réponses…) → activités fiches contact
        $this->newLine();
        $this->line('→ Sync events contacts (polling activités Emelia)...');
        $eventsArgs = ['--only-linked' => true];
        if ($this->option('dry-run')) {
            $eventsArgs['--dry-run'] = true;
        }
        $eventsResult = $this->call('emelia:sync-contact-events', $eventsArgs);
        if ($eventsResult !== 0) {
            $hasErrors = true;
        }

        return $hasErrors ? 1 : 0;
    }
}
