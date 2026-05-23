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

    protected $description = 'Calcule et persiste un score IA (0-100) pour chaque contact actif';

    public function handle(AiInsightService $ai): int
    {
        $limit  = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $query = Contact::whereNull('deleted_at')
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
            'Scoring %d contact(s)%s…',
            $contacts->count(),
            $dryRun ? ' [dry-run]' : ''
        ));

        $bar    = $this->output->createProgressBar($contacts->count());
        $scored = 0;
        $errors = 0;

        foreach ($contacts as $contact) {
            try {
                $result = $ai->scoreContact($contact->id, fresh: true);
                $data   = $result['data'];
                $score  = is_array($data) ? ($data['score'] ?? null) : null;

                if ($score !== null && !$dryRun) {
                    $contact->update([
                        'ai_score'            => (int) min(100, max(0, $score)),
                        'ai_score_updated_at' => now(),
                    ]);
                }

                if ($score !== null) {
                    $scored++;
                }

                usleep(500_000); // respect OpenRouter ~2 req/s
            } catch (\Exception $e) {
                $this->newLine();
                $this->line("<error>Contact #{$contact->id}: {$e->getMessage()}</error>");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Terminé : {$scored} scorés, {$errors} erreur(s).");

        return Command::SUCCESS;
    }
}
