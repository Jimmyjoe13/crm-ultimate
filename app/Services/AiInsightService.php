<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AiInsightService
{
    public function __construct(private readonly LlmService $llm) {}

    public function summarizeDeal(int $id, bool $fresh = false): array
    {
        return $this->resolve('summarize-deal', 'deal', $id, $fresh, function () use ($id) {
            $deal = Deal::with(['companies', 'contacts', 'pipeline', 'stage', 'owner'])->findOrFail($id);
            $activities = Activity::where('subject_type', Deal::class)->where('subject_id', $id)->latest()->limit(10)->get();
            $auditLogs  = AuditLog::where('auditable_type', Deal::class)->where('auditable_id', $id)->latest()->limit(5)->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), $auditLogs->toArray()),
                'hash'    => md5((string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'summarize',
            ];
        });
    }

    public function nextActionDeal(int $id, bool $fresh = false): array
    {
        return $this->resolve('next-action-deal', 'deal', $id, $fresh, function () use ($id) {
            $deal = Deal::with(['companies', 'contacts', 'pipeline', 'stage', 'owner'])->findOrFail($id);
            $activities = Activity::where('subject_type', Deal::class)->where('subject_id', $id)->latest()->limit(10)->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), []),
                'hash'    => md5('next-action'.(string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'next-action',
            ];
        });
    }

    public function scoreDeal(int $id, bool $fresh = false): array
    {
        return $this->resolve('score-deal', 'deal', $id, $fresh, function () use ($id) {
            $deal = Deal::with(['stage'])->findOrFail($id);
            $activities = Activity::where('subject_type', Deal::class)->where('subject_id', $id)->latest()->limit(5)->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), []),
                'hash'    => md5('score'.(string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'score',
            ];
        });
    }

    public function summarizeContact(int $id, bool $fresh = false): array
    {
        return $this->resolve('summarize-contact', 'contact', $id, $fresh, function () use ($id) {
            $contact = Contact::with(['companies', 'owner'])->findOrFail($id);
            $activities = Activity::where('subject_type', Contact::class)->where('subject_id', $id)->latest()->limit(10)->get();

            return [
                'context' => $this->contactContext($contact, $activities->toArray()),
                'hash'    => md5((string) $contact->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'summarize',
            ];
        });
    }

    public function summarizeCompany(int $id, bool $fresh = false): array
    {
        return $this->resolve('summarize-company', 'company', $id, $fresh, function () use ($id) {
            $company = Company::with(['contacts', 'deals'])->findOrFail($id);
            $activities = Activity::where('subject_type', Company::class)->where('subject_id', $id)->latest()->limit(10)->get();

            return [
                'context' => $this->companyContext($company, $activities->toArray()),
                'hash'    => md5((string) $company->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'summarize',
            ];
        });
    }

    public function dailySuggestions(User $user, bool $fresh = false): array
    {
        $cacheKey = 'ai:daily-suggestions:user:'.$user->id.':'.now()->format('Y-m-d');

        if (!$fresh && Cache::has($cacheKey)) {
            return ['data' => Cache::get($cacheKey), 'cached' => true];
        }

        $stagnantDeals = Deal::with(['stage'])
            ->where('status', 'open')
            ->where('updated_at', '<', now()->subDays(7))
            ->orderBy('amount', 'desc')
            ->limit(5)
            ->get();

        if ($stagnantDeals->isEmpty()) {
            return ['data' => ['suggestions' => ['Aucun deal stagnant détecté. Bonne journée !']], 'cached' => false];
        }

        $dealLines = $stagnantDeals->map(fn ($d) =>
            "- {$d->name} ({$d->stage?->name}) : ".number_format($d->amount, 0, ',', ' ').' € — stagnant depuis '.now()->diffInDays($d->updated_at).' j'
        )->join("\n");

        $prompt = "Tu es un assistant CRM. Voici les deals stagnants d'un commercial :\n{$dealLines}\n\nPropose 3 à 5 suggestions d'actions concrètes pour débloquer le pipeline. Réponds UNIQUEMENT en JSON valide : {\"suggestions\": [\"action 1\", \"action 2\"]}";

        try {
            $raw = $this->llm->complete(
                'Tu es un assistant CRM commercial B2B expert. Réponds en français, de façon concise et actionnable.',
                $prompt,
                ['max_tokens' => 600]
            );
        } catch (RuntimeException $e) {
            throw $e;
        }

        $parsed = null;
        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $parsed = json_decode($matches[0], true);
        }
        $data = $parsed ?? ['suggestions' => [$raw]];

        Cache::put($cacheKey, $data, now()->addHours(12));

        return ['data' => $data, 'cached' => false];
    }

    private function resolve(string $endpoint, string $type, int $id, bool $fresh, callable $builder): array
    {
        $built    = $builder();
        $cacheKey = "ai:{$endpoint}:{$type}:{$id}:{$built['hash']}";

        if (!$fresh && Cache::has($cacheKey)) {
            return ['data' => Cache::get($cacheKey), 'cached' => true];
        }

        $content = $this->generate($built['context'], $built['prompt']);
        Cache::put($cacheKey, $content, now()->addHours(24));

        return ['data' => $content, 'cached' => false];
    }

    private function generate(string $context, string $promptType): mixed
    {
        $system = 'Tu es un assistant CRM commercial B2B expert. Tu analyses les données CRM et génères des insights actionnables en français. Sois concis et précis.';

        $user = match ($promptType) {
            'summarize'   => $context."\n\nGénère un brief synthétique de 4 à 6 lignes pour aider le commercial à reprendre ce dossier.",
            'next-action' => $context."\n\nSuggère la prochaine action commerciale concrète. Réponds UNIQUEMENT en JSON valide : {\"action\": \"...\", \"rationale\": \"...\", \"priority\": \"high\"}",
            'score'       => $context."\n\nScore ce deal sur 100. Réponds UNIQUEMENT en JSON valide : {\"score\": 75, \"trend\": \"warming\", \"reasons\": [\"raison 1\"]}",
            default       => $context,
        };

        $options = $promptType !== 'summarize' ? ['max_tokens' => 300] : [];
        $raw = $this->llm->complete($system, $user, $options);

        if (in_array($promptType, ['next-action', 'score'])) {
            if (preg_match('/\{.*\}/s', $raw, $matches)) {
                $parsed = json_decode($matches[0], true);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return $raw;
    }

    private function dealContext(Deal $deal, array $activities, array $auditLogs): string
    {
        $daysOld = $deal->updated_at ? now()->diffInDays($deal->updated_at) : '?';
        $lines   = [
            "=== DEAL : {$deal->name} ===",
            'Montant : '.number_format((float) $deal->amount, 2, ',', ' ').' €',
            "Statut : {$deal->status}",
            'Étape : '.($deal->stage?->name ?? 'N/A').' (probabilité : '.($deal->stage?->probability ?? 0).'%)',
            'Pipeline : '.($deal->pipeline?->name ?? 'N/A'),
            'Entreprise : '.($deal->companies->first()?->name ?? 'Non renseignée'),
            'Contact : '.($deal->contacts->first() ? "{$deal->contacts->first()->first_name} {$deal->contacts->first()->last_name}" : 'Non renseigné'),
            'Responsable : '.($deal->owner?->name ?? 'Non assigné'),
            'Date de clôture : '.($deal->close_date?->format('d/m/Y') ?? 'Non définie'),
            "Dernière modif : il y a {$daysOld} jour(s)",
        ];

        if (count($activities) > 0) {
            $lines[] = "\nActivités récentes :";
            foreach (array_slice($activities, 0, 5) as $a) {
                $lines[] = "- [{$a['type']}] {$a['title']} ({$a['status']})";
            }
        } else {
            $lines[] = "\nAucune activité enregistrée.";
        }

        if (count($auditLogs) > 0) {
            $lines[] = "\nDernières modifications :";
            foreach (array_slice($auditLogs, 0, 3) as $log) {
                $vals = is_array($log['new_values']) ? implode(', ', array_map(fn ($k, $v) => "$k: $v", array_keys($log['new_values']), $log['new_values'])) : '';
                $lines[] = '- '.$log['event'].($vals ? " ($vals)" : '');
            }
        }

        return implode("\n", $lines);
    }

    private function contactContext(Contact $contact, array $activities): string
    {
        $lines = [
            "=== CONTACT : {$contact->first_name} {$contact->last_name} ===",
            'Email : '.($contact->email ?? 'Non renseigné'),
            'Téléphone : '.($contact->phone ?? 'Non renseigné'),
            'Poste : '.($contact->job_title ?? 'Non renseigné'),
            'Entreprise : '.($contact->companies->first()?->name ?? 'Non renseignée'),
            'Responsable : '.($contact->owner?->name ?? 'Non assigné'),
        ];

        if (count($activities) > 0) {
            $lines[] = "\nActivités récentes :";
            foreach (array_slice($activities, 0, 5) as $a) {
                $lines[] = "- [{$a['type']}] {$a['title']} ({$a['status']})";
            }
        } else {
            $lines[] = "\nAucune activité enregistrée.";
        }

        return implode("\n", $lines);
    }

    private function companyContext(Company $company, array $activities): string
    {
        $lines = [
            "=== ENTREPRISE : {$company->name} ===",
            'Industrie : '.($company->industry ?? 'Non renseignée'),
            'Site web : '.($company->website ?? 'Non renseigné'),
            'Contacts : '.$company->contacts->count(),
            'Deals ouverts : '.$company->deals->where('status', 'open')->count(),
            'CA deals : '.number_format($company->deals->sum('amount'), 0, ',', ' ').' €',
        ];

        if (count($activities) > 0) {
            $lines[] = "\nActivités récentes :";
            foreach (array_slice($activities, 0, 5) as $a) {
                $lines[] = "- [{$a['type']}] {$a['title']} ({$a['status']})";
            }
        } else {
            $lines[] = "\nAucune activité enregistrée.";
        }

        return implode("\n", $lines);
    }
}
