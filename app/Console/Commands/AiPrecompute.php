<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Deal;
use App\Services\AiInsightService;
use Illuminate\Console\Command;

class AiPrecompute extends Command
{
    protected $signature = 'ai:precompute
                            {--limit=50  : Nombre max de deals à précharger}
                            {--contacts  : Précharger aussi les résumés des contacts liés à Emelia}
                            {--dry-run   : Simuler sans appeler le LLM ni écrire en cache}';

    protected $description = 'Pré-calcule les insights IA des deals actifs pour éliminer la latence LLM au premier affichage';

    public function handle(AiInsightService $ai): int
    {
        $limit  = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        // ─── Deals ────────────────────────────────────────────────────────────
        $deals = Deal::where('status', 'open')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get();

        $this->info(sprintf(
            'Precompute deals : %d deal(s)%s',
            $deals->count(),
            $dryRun ? ' [dry-run]' : ''
        ));

        $dealHits   = 0;
        $dealErrors = 0;
        $bar        = $this->output->createProgressBar($deals->count() * 3);

        foreach ($deals as $deal) {
            $calls = [
                'summarize'   => fn () => $ai->summarizeDeal($deal->id, fresh: false),
                'score'       => fn () => $ai->scoreDeal($deal->id, fresh: false),
                'next-action' => fn () => $ai->nextActionDeal($deal->id, fresh: false),
            ];

            foreach ($calls as $label => $call) {
                try {
                    if (!$dryRun) {
                        $result = $call();
                        if (!($result['cached'] ?? false)) {
                            $dealHits++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->line("<comment>Deal #{$deal->id} [{$label}] : {$e->getMessage()}</comment>");
                    $dealErrors++;
                }

                $bar->advance();
                usleep(500_000); // respect OpenRouter ~2 req/s
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Deals : {$dealHits} nouveau(x) en cache, {$dealErrors} erreur(s).");

        // ─── Contacts (optionnel) ─────────────────────────────────────────────
        if ($this->option('contacts')) {
            $contacts = Contact::whereNotNull('emelia_campaign_id')
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get();

            $this->info(sprintf(
                'Precompute contacts : %d contact(s)%s',
                $contacts->count(),
                $dryRun ? ' [dry-run]' : ''
            ));

            $contactHits   = 0;
            $contactErrors = 0;
            $bar2          = $this->output->createProgressBar($contacts->count());

            foreach ($contacts as $contact) {
                try {
                    if (!$dryRun) {
                        $result = $ai->summarizeContact($contact->id, fresh: false);
                        if (!($result['cached'] ?? false)) {
                            $contactHits++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->line("<comment>Contact #{$contact->id} : {$e->getMessage()}</comment>");
                    $contactErrors++;
                }

                $bar2->advance();
                usleep(500_000);
            }

            $bar2->finish();
            $this->newLine();
            $this->info("Contacts : {$contactHits} nouveau(x) en cache, {$contactErrors} erreur(s).");
        }

        return Command::SUCCESS;
    }
}
