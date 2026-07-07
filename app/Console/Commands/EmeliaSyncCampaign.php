<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\EmeliaService;
use Illuminate\Console\Command;

class EmeliaSyncCampaign extends Command
{
    protected $signature = 'emelia:sync-campaign
                            {campaign_id : ID de la campagne Emelia}
                            {--dry-run : Affiche ce qui serait fait sans modifier la BDD}
                            {--only-linked : Traite seulement les contacts déjà liés à Emelia}';

    protected $description = 'Synchronise les contacts CRM avec une campagne Emelia';

    public function handle(EmeliaService $emelia): int
    {
        $campaignId = $this->argument('campaign_id');
        $dryRun = $this->option('dry-run');
        $onlyLinked = $this->option('only-linked');

        // 1. Vérifier la campagne
        $this->info("Recherche de la campagne $campaignId...");
        try {
            $campaign = $emelia->findCampaign($campaignId);
        } catch (\RuntimeException $e) {
            $this->error('Emelia API : '.$e->getMessage());

            return 1;
        }

        if (! $campaign) {
            $this->error("Campagne $campaignId introuvable dans Emelia.");

            return 1;
        }

        $campaignName = $campaign['name'] ?? $campaignId;
        $this->info("Campagne trouvée : \"$campaignName\" (statut: {$campaign['status']}, {$campaign['contactsCount']} contacts Emelia)");

        if ($dryRun) {
            $this->warn('[DRY-RUN] Aucune modification ne sera effectuée.');
        }

        // 2. Requête contacts CRM (exclut les blacklistés)
        $query = Contact::whereNotNull('email')->contactable();
        if ($onlyLinked) {
            $query->whereNotNull('emelia_contact_id');
        }

        $total = $query->count();
        $linked = 0;
        $errors = 0;
        $skip = 0;

        $this->info("Contacts CRM à traiter : $total");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select(['id', 'email', 'first_name', 'last_name', 'emelia_contact_id', 'emelia_campaign_id'])
            ->chunk(50, function ($contacts) use (
                $emelia, $campaignId, $campaignName, $dryRun,
                &$linked, &$errors, &$skip, $bar
            ) {
                foreach ($contacts as $contact) {
                    // Déjà dans cette campagne
                    if ($contact->emelia_campaign_id === $campaignId) {
                        $skip++;
                        $bar->advance();

                        continue;
                    }

                    if ($dryRun) {
                        $linked++;
                        $bar->advance();

                        continue;
                    }

                    try {
                        $result = $emelia->addContactToCampaign($campaignId, [
                            'email' => $contact->email,
                            'firstName' => $contact->first_name ?? '',
                            'lastName' => $contact->last_name ?? '',
                        ]);

                        $contact->update([
                            'emelia_contact_id' => $result['id'] ?? $contact->emelia_contact_id,
                            'emelia_campaign_id' => $campaignId,
                            'emelia_campaign_name' => $campaignName,
                        ]);

                        $linked++;
                    } catch (\Throwable $e) {
                        if (str_contains($e->getMessage(), 'already included')) {
                            // Contact déjà dans la campagne — résoudre l'ID Emelia manquant si besoin
                            $updates = [
                                'emelia_campaign_id' => $campaignId,
                                'emelia_campaign_name' => $campaignName,
                            ];
                            if (! $contact->emelia_contact_id) {
                                $emeliData = $emelia->getContactByEmail($contact->email, $campaignName);
                                if ($emeliData && isset($emeliData['_id'])) {
                                    $updates['emelia_contact_id'] = $emeliData['_id'];
                                }
                                usleep(220_000);
                            }
                            $contact->update($updates);
                            $skip++;
                        } else {
                            $this->newLine();
                            $this->warn("  Erreur {$contact->email}: ".$e->getMessage());
                            $errors++;
                        }
                    }

                    // Respect du rate-limit Emelia (~5 req/s)
                    usleep(220_000);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Statut', 'Nb'],
            [
                [$dryRun ? 'Seraient liés' : 'Liés', $linked],
                ['Déjà dans cette campagne (skip)', $skip],
                ['Erreurs', $errors],
            ]
        );

        if (! $dryRun && $linked > 0) {
            $this->info("$linked contacts mis à jour → campagne \"$campaignName\".");
        }

        return $errors > 0 ? 1 : 0;
    }
}
