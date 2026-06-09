<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\AiInsightService;
use Illuminate\Console\Command;

class AiScoreContacts extends Command
{
    protected $signature = 'ai:score-contacts
                            {--limit=50 : Nombre maximum de contacts à scorer}
                            {--all : Inclure tous les contacts, pas seulement ceux liés à Emelia}
                            {--dry-run : Simuler sans écrire en base}';

    protected $description = 'Calcule et persiste un score IA (0-100) pour chaque contact actif (batch)';

    public function handle(AiInsightService $ai): int
    {
        $limit  = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $query = Contact::whereNull('deleted_at')
                        ->with('deals')
                        ->orderByDesc('updated_at');

        if (!$this->option('all')) {
            $query->whereNotNull('emelia_campaign_id');
        }

        $contacts = $query->limit($limit)->get();

        if ($contacts->isEmpty()) {
            $this->info('Aucun contact à scorer.');
            return Command::SUCCESS;
        }

        $this->info(sprintf(
            'Scoring %d contact(s) en batch (10 par appel LLM)%s…',
            $contacts->count(),
            $dryRun ? ' [dry-run]' : ''
        ));

        $results = $ai->batchScoreContacts($contacts);

        if (empty($results)) {
            $this->error('Aucun résultat retourné par le batch LLM.');
            return Command::FAILURE;
        }

        $scored = 0;
        $errors = 0;

        foreach ($contacts as $contact) {
            $item = $results[$contact->id] ?? null;

            if ($item && isset($item['score'])) {
                if (!$dryRun) {
                    $contact->update([
                        'ai_score'            => (int) min(100, max(0, $item['score'])),
                        'ai_score_updated_at' => now(),
                    ]);
                }
                $scored++;
            } else {
                $this->line("<error>Contact #{$contact->id}: aucun score retourné</error>");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Terminé : {$scored} scorés, {$errors} erreur(s).");

        return Command::SUCCESS;
    }
}