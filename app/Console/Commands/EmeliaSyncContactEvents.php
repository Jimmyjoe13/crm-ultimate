<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\EmeliaService;
use App\Support\EmeliaEventDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EmeliaSyncContactEvents extends Command
{
    protected $signature = 'emelia:sync-contact-events
                            {--only-linked : Traite seulement les contacts avec emelia_contact_id}
                            {--contact= : ID CRM d\'un contact spécifique}
                            {--dry-run : Affiche sans modifier la BDD}';

    protected $description = 'Récupère les events Emelia (ouvertures, réponses…) et les crée comme activités dans les fiches contact';

    public function handle(EmeliaService $emelia): int
    {
        $onlyLinked = $this->option('only-linked');
        $contactId  = $this->option('contact');
        $dryRun     = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Aucune modification ne sera effectuée.');
        }

        // Inclut les contacts avec ID Emelia connu OU ceux avec campagne mais ID manquant
        if ($onlyLinked) {
            $query = Contact::whereNotNull('emelia_contact_id')
                ->whereNotNull('emelia_campaign_id');
        } else {
            $query = Contact::where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('emelia_contact_id')->whereNotNull('emelia_campaign_id');
                })->orWhereNotNull('emelia_campaign_name');
            });
        }

        if ($contactId) {
            $query->where('id', $contactId);
        }

        $total    = $query->count();
        $created  = 0;
        $skipped  = 0;
        $errors   = 0;
        $resolved = 0;

        $this->info("Contacts à traiter : {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select(['id', 'email', 'first_name', 'last_name', 'owner_id',
                        'emelia_contact_id', 'emelia_campaign_id', 'emelia_campaign_name',
                        'lifecycle_stage'])
            ->chunk(50, function ($contacts) use (
                $emelia, $dryRun, &$created, &$skipped, &$errors, &$resolved, $bar
            ) {
                foreach ($contacts as $contact) {
                    try {
                        // Résoudre l'ID Emelia si absent (contact "already included" à la sync push)
                        if (! $contact->emelia_contact_id) {
                            $emeliData = $emelia->getContactByEmail($contact->email, $contact->emelia_campaign_name);
                            $resolvedId = $emeliData['_id'] ?? null;

                            if (! $resolvedId) {
                                $skipped++;
                                $bar->advance();
                                continue;
                            }

                            if (! $dryRun) {
                                $contact->update([
                                    'emelia_contact_id' => $resolvedId,
                                    'emelia_campaign_id' => $contact->emelia_campaign_id
                                        ?? ($emeliData['campaigns'][0] ?? null),
                                ]);
                                $contact->refresh();
                            } else {
                                $contact->emelia_contact_id = $resolvedId;
                            }

                            $resolved++;
                            usleep(220_000);
                        }

                        $events = $emelia->getContactEvents(
                            $contact->emelia_contact_id,
                            $contact->emelia_campaign_id,
                            $contact->email,
                            $contact->emelia_campaign_name,
                        );

                        foreach ($events as $event) {
                            $type = EmeliaEventDispatcher::typeFromEmeliaEvent($event['type']);
                            if (! $type) {
                                continue;
                            }

                            /** @var Carbon $date */
                            $date       = $event['date'];
                            $externalId = 'emelia:' . hash('sha256', "{$contact->emelia_contact_id}:{$type}:{$date->toIso8601String()}");

                            if ($dryRun) {
                                $this->newLine();
                                $this->line("  [dry] {$contact->email}: {$type} @ {$date->format('Y-m-d H:i')}");
                                $created++;
                                continue;
                            }

                            $activity = EmeliaEventDispatcher::dispatch(
                                contact:    $contact,
                                type:       $type,
                                payload:    ['synthetic' => true, 'source_contact_id' => $contact->emelia_contact_id],
                                occurredAt: $date,
                                externalId: $externalId,
                            );

                            if ($activity !== null) {
                                $created++;
                            } else {
                                $skipped++;
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->newLine();
                        $this->warn("  Erreur {$contact->email}: " . $e->getMessage());
                        $errors++;
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
                [$dryRun ? 'Seraient créées' : 'Activités créées', $created],
                ['IDs Emelia résolus', $resolved],
                ['Doublons ignorés (idempotence)', $skipped],
                ['Erreurs API', $errors],
            ]
        );

        return $errors > 0 ? 1 : 0;
    }
}
