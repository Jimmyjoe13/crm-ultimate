<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\EmeliaCampaign;
use App\Services\EmeliaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class EmeliaBackfillContacts extends Command
{
    protected $signature = 'emelia:backfill-contacts
                            {--limit=500 : Nombre max de contacts CRM à vérifier}
                            {--dry-run   : Affiche les changements sans modifier la BDD}
                            {--all       : Vérifie aussi les contacts déjà liés (re-sync complète)}';

    protected $description = 'Reverse-lookup Emelia : lie les contacts CRM existants à leurs campagnes Emelia (backfill pour events antérieurs au webhook)';

    public function handle(EmeliaService $emelia): int
    {
        $limit  = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $all    = (bool) $this->option('all');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Aucune modification en base.');
        }

        // 1. Sync du registre de campagnes
        $this->info('Synchronisation du registre de campagnes Emelia...');
        try {
            $synced = $emelia->syncCampaignRegistry();
            $this->line("  {$synced} campagnes dans le registre.");
        } catch (\Throwable $e) {
            $this->warn("  syncCampaignRegistry() failed: {$e->getMessage()}");
        }

        // 2. Construction de la requête de sélection
        $query = Contact::whereNotNull('email');
        if (! $all) {
            $query->where(function ($q) {
                $q->whereNull('emelia_contact_id')
                  ->orWhereDoesntHave('emeliaCampaigns');
            });
        }

        // Snapshot des IDs AVANT modification — évite le row-skip de chunk()
        // (chunk() pagine par OFFSET et le WHERE dynamique ferait sauter des lignes)
        $ids   = $query->limit($limit)->pluck('id')->toArray();
        $total = count($ids);

        $this->info("Contacts à inspecter : {$total}" . ($all ? '' : ' (sans lien Emelia)'));

        if ($total === 0) {
            $this->info('Rien à faire.');
            return 0;
        }

        $linked  = 0;
        $skipped = 0;
        $errors  = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach (array_chunk($ids, 50) as $batch) {
            $contacts = Contact::whereIn('id', $batch)
                ->select(['id', 'email', 'emelia_contact_id'])
                ->get();

            foreach ($contacts as $contact) {
                try {
                    $emeliData = $emelia->getContactByEmail($contact->email);

                    if (! $emeliData || empty($emeliData['_id'])) {
                        $skipped++;
                        $bar->advance();
                        usleep(150_000);
                        continue;
                    }

                    $emeliId       = $emeliData['_id'];
                    $campaignNames = $emeliData['campaigns'] ?? [];

                    if ($dryRun) {
                        $this->newLine();
                        $this->line("  FOUND {$contact->email} | Emelia:{$emeliId} | campaigns: " . implode(', ', $campaignNames));
                        $linked++;
                        $bar->advance();
                        usleep(150_000);
                        continue;
                    }

                    // Mettre à jour emelia_contact_id si manquant
                    if (! $contact->emelia_contact_id) {
                        $contact->update(['emelia_contact_id' => $emeliId]);
                    }

                    // Lier à chaque campagne Emelia retournée
                    foreach ($campaignNames as $campaignName) {
                        $campaign = EmeliaCampaign::where('name', $campaignName)->first();
                        if (! $campaign) {
                            continue;
                        }

                        $fromMs = fn (?string $ms) => $ms ? Carbon::createFromTimestampMs((int) $ms) : null;
                        $timestamps = array_filter([
                            $fromMs($emeliData['lastReplied']   ?? null),
                            $fromMs($emeliData['lastOpen']      ?? null),
                            $fromMs($emeliData['lastContacted'] ?? null),
                        ]);
                        $lastEventAt = count($timestamps) ? max($timestamps) : now();

                        $pivotData = [
                            'emelia_contact_id' => $emeliId,
                            'status'            => $emeliData['status'] ?? null,
                            'last_event_at'     => $lastEventAt,
                        ];

                        try {
                            $existing = $contact->emeliaCampaigns()->whereKey($campaign->id)->first();
                            if ($existing) {
                                $contact->emeliaCampaigns()->updateExistingPivot($campaign->id, $pivotData);
                            } else {
                                $pivotData['first_event_at'] = $lastEventAt;
                                $contact->emeliaCampaigns()->attach($campaign->id, $pivotData);
                            }

                            $contact->update([
                                'emelia_campaign_id'   => $campaign->emelia_id,
                                'emelia_campaign_name' => $campaignName,
                            ]);
                        } catch (UniqueConstraintViolationException) {
                            $contact->emeliaCampaigns()->updateExistingPivot($campaign->id, $pivotData);
                        }
                    }

                    $linked++;
                } catch (\Throwable $e) {
                    $errors++;
                }

                $bar->advance();
                usleep(150_000);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Statut', 'Nb'],
            [
                [$dryRun ? 'Seraient liés' : 'Liés à Emelia', $linked],
                ['Non trouvés dans Emelia',                    $skipped],
                ['Erreurs',                                    $errors],
            ]
        );

        return $errors > 0 ? 1 : 0;
    }
}
