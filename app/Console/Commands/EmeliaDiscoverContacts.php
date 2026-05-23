<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\EmeliaService;
use Illuminate\Console\Command;

class EmeliaDiscoverContacts extends Command
{
    protected $signature = 'emelia:discover-contacts
                            {campaign_id : ID de la campagne Emelia}
                            {--new-only : Seulement les contacts sans emelia_contact_id (nouvellement importés)}
                            {--dry-run : Affiche ce qui serait fait sans modifier la BDD}';

    protected $description = 'Détecte les contacts CRM déjà présents dans une campagne Emelia et les lie — sans jamais ajouter de contacts à la campagne';

    public function handle(EmeliaService $emelia): int
    {
        $campaignId = $this->argument('campaign_id');
        $newOnly    = $this->option('new-only');
        $dryRun     = $this->option('dry-run');

        $this->info("Recherche de la campagne $campaignId...");
        try {
            $campaign = $emelia->findCampaign($campaignId);
        } catch (\RuntimeException $e) {
            $this->error('Emelia API : ' . $e->getMessage());
            return 1;
        }

        if (! $campaign) {
            $this->error("Campagne $campaignId introuvable.");
            return 1;
        }

        $campaignName = $campaign['name'] ?? $campaignId;
        $this->info("Campagne : \"$campaignName\" ({$campaign['contactsCount']} contacts Emelia)");

        if ($dryRun) {
            $this->warn('[DRY-RUN] Aucune modification ne sera effectuée.');
        }

        $query = Contact::whereNotNull('email')
            ->where(function ($q) use ($campaignId) {
                // Exclure les contacts déjà liés à CETTE campagne
                $q->whereNull('emelia_campaign_id')
                  ->orWhere('emelia_campaign_id', '!=', $campaignId);
            });

        if ($newOnly) {
            $query->whereNull('emelia_contact_id');
        }

        $total   = $query->count();
        $linked  = 0;
        $skipped = 0;
        $errors  = 0;

        $this->info("Contacts CRM à inspecter : $total" . ($newOnly ? ' (nouveaux seulement)' : ''));
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select(['id', 'email', 'emelia_contact_id', 'emelia_campaign_id'])
            ->chunk(50, function ($contacts) use (
                $emelia, $campaignId, $campaignName, $dryRun,
                &$linked, &$skipped, &$errors, $bar
            ) {
                foreach ($contacts as $contact) {
                    try {
                        // Requête Emelia par email — filtre par nom de campagne
                        $emeliData = $emelia->getContactByEmail($contact->email, $campaignName);

                        if (! $emeliData || empty($emeliData['_id'])) {
                            $skipped++;
                        } else {
                            if (! $dryRun) {
                                $contact->update([
                                    'emelia_contact_id'    => $emeliData['_id'],
                                    'emelia_campaign_id'   => $campaignId,
                                    'emelia_campaign_name' => $campaignName,
                                ]);
                            }
                            $linked++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                    }

                    usleep(220_000);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Statut', 'Nb'],
            [
                [$dryRun ? 'Seraient liés' : 'Liés à Emelia', $linked],
                ['Non trouvés dans cette campagne', $skipped],
                ['Erreurs', $errors],
            ]
        );

        if (! $dryRun && $linked > 0) {
            $this->info("$linked contacts liés → campagne \"$campaignName\". Lance emelia:sync-contact-events pour récupérer leurs activités.");
        }

        return $errors > 0 ? 1 : 0;
    }
}
