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

    public function draftEmail(?int $contactId, ?int $dealId, string $intent = ''): array
    {
        $context = '';

        if ($contactId) {
            $contact    = Contact::with(['companies', 'owner', 'deals'])->findOrFail($contactId);
            $activities = Activity::where('subject_type', Contact::class)->where('subject_id', $contactId)->latest()->limit(5)->get();
            $context    = $this->contactContext($contact, $activities->toArray());
        } elseif ($dealId) {
            $deal       = Deal::with(['companies', 'contacts', 'stage', 'pipeline', 'owner'])->findOrFail($dealId);
            $activities = Activity::where('subject_type', Deal::class)->where('subject_id', $dealId)->latest()->limit(5)->get();
            $context    = $this->dealContext($deal, $activities->toArray(), []);
        }

        $intentLine = $intent ? "\nObjectif de l'email : {$intent}" : '';
        $prompt     = $context.$intentLine."\n\nRédige un email professionnel B2B en français (maximum 200 mots). Objet accrocheur. Signature générique (prénom seulement). Réponds UNIQUEMENT en JSON valide : {\"subject\": \"Objet de l'email\", \"body\": \"Corps de l'email\"}";

        $raw = $this->llm->complete(
            'Tu es un assistant rédacteur B2B expert. Tu rédiges des emails commerciaux professionnels, concis et percutants en français.',
            $prompt,
            ['max_tokens' => 600]
        );

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed) && isset($parsed['subject'], $parsed['body'])) {
                return $parsed;
            }
        }

        return ['subject' => 'Email généré', 'body' => $raw];
    }

    public function analyzeSentiment(string $text): array
    {
        $raw = $this->llm->complete(
            'Tu es un analyseur de sentiment d\'emails commerciaux. Réponds uniquement en JSON valide.',
            "Analyse le sentiment de cette réponse email :\n\"{$text}\"\n\nRéponds UNIQUEMENT en JSON valide : {\"sentiment\": \"positif\", \"score\": 0.8, \"summary\": \"Résumé en 1 phrase.\"}",
            ['max_tokens' => 150]
        );

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed) && isset($parsed['sentiment'])) {
                return $parsed;
            }
        }

        return ['sentiment' => 'neutre', 'score' => 0.0, 'summary' => ''];
    }

    public function scoreContact(int $id, bool $fresh = false): array
    {
        return $this->resolve('score-contact', 'contact', $id, $fresh, function () use ($id) {
            $contact    = Contact::with(['companies', 'owner', 'deals'])->findOrFail($id);
            $activities = Activity::where('subject_type', Contact::class)->where('subject_id', $id)->latest()->limit(10)->get();

            return [
                'context' => $this->contactContext($contact, $activities->toArray()),
                'hash'    => md5('score-contact'.(string) $contact->updated_at.$activities->count()),
                'prompt'  => 'score-contact',
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

        $stagnantDeals = Deal::with('stage')
            ->where('status', 'open')
            ->where('updated_at', '<', now()->subDays(7))
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        $closingSoon = Deal::with('stage')
            ->where('status', 'open')
            ->whereBetween('close_date', [now(), now()->addDays(7)])
            ->orderBy('close_date')
            ->limit(5)
            ->get();

        $overdueTasks = Activity::where('type', Activity::TYPE_TASK)
            ->where('status', 'open')
            ->where('owner_id', $user->id)
            ->where('due_at', '<', now())
            ->limit(5)
            ->get();

        $recentReplies = Activity::where('type', Activity::TYPE_EMAIL_REPLIED)
            ->where('created_at', '>', now()->subHours(48))
            ->with('subject')
            ->limit(5)
            ->get();

        $contextParts = [];

        if ($stagnantDeals->isNotEmpty()) {
            $lines = $stagnantDeals->map(fn ($d) =>
                '- '.$d->name.' ('.$d->stage?->name.') : '.number_format($d->amount, 0, ',', ' ').' € — stagnant depuis '.now()->diffInDays($d->updated_at).' j'
            )->join("\n");
            $contextParts[] = "Deals stagnants (>7j sans modification) :\n{$lines}";
        }

        if ($closingSoon->isNotEmpty()) {
            $lines = $closingSoon->map(fn ($d) =>
                '- '.$d->name.' : '.number_format($d->amount, 0, ',', ' ').' € — close le '.$d->close_date?->format('d/m/Y')
            )->join("\n");
            $contextParts[] = "Deals qui closent dans les 7 prochains jours :\n{$lines}";
        }

        if ($overdueTasks->isNotEmpty()) {
            $lines = $overdueTasks->map(fn ($t) =>
                '- '.$t->title.' (due le '.($t->due_at?->format('d/m/Y') ?? '?').')'
            )->join("\n");
            $contextParts[] = "Tâches en retard (overdue) :\n{$lines}";
        }

        if ($recentReplies->isNotEmpty()) {
            $lines = $recentReplies->map(function ($a) {
                $subject = $a->subject;
                $name = $subject ? (($subject->first_name ?? '').' '.($subject->last_name ?? $subject->name ?? '')): 'contact inconnu';
                return '- '.trim($name).' a répondu à une campagne Emelia';
            })->join("\n");
            $contextParts[] = "Contacts ayant répondu à Emelia dans les 48h (à relancer) :\n{$lines}";
        }

        if (empty($contextParts)) {
            $data = ['suggestions' => ['Aucune action urgente détectée. Bonne journée !'], 'alerts' => [], 'priorities' => []];
            Cache::put($cacheKey, $data, now()->addHours(12));
            return ['data' => $data, 'cached' => false];
        }

        $context = implode("\n\n", $contextParts);
        $prompt = "Tu es un assistant CRM commercial B2B. Voici l'état du pipeline aujourd'hui :\n\n{$context}\n\nPropose des suggestions d'actions concrètes, des alertes urgentes et les priorités du jour. Réponds UNIQUEMENT en JSON valide : {\"suggestions\": [\"action 1\"], \"alerts\": [\"alerte urgente 1\"], \"priorities\": [\"priorité 1\"]}";

        try {
            $raw = $this->llm->complete(
                'Tu es un assistant CRM commercial B2B expert. Réponds en français, de façon concise et actionnable.',
                $prompt,
                ['max_tokens' => 800]
            );
        } catch (RuntimeException $e) {
            throw $e;
        }

        $parsed = null;
        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $parsed = json_decode($matches[0], true);
        }
        $data = $parsed ?? ['suggestions' => [$raw], 'alerts' => [], 'priorities' => []];

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
            'summarize'    => $context."\n\nGénère un brief synthétique de 4 à 6 lignes pour aider le commercial à reprendre ce dossier.",
            'next-action'  => $context."\n\nSuggère la prochaine action commerciale concrète. Réponds UNIQUEMENT en JSON valide : {\"action\": \"...\", \"rationale\": \"...\", \"priority\": \"high\"}",
            'score'        => $context."\n\nÉvalue la santé de ce deal. Donne un score sur 100, une tendance ('warming', 'cooling', 'stable'), des raisons clés, des points forts (green_flags), des risques ou points de vigilance (red_flags) et des recommandations d'action concrètes pour le commercial. Réponds UNIQUEMENT en JSON valide : {\"score\": 75, \"trend\": \"warming\", \"reasons\": [\"raison 1\"], \"green_flags\": [\"point fort 1\"], \"red_flags\": [\"point de vigilance 1\"], \"recommendations\": [\"recommandation 1\"]}",
            'score-contact' => $context."\n\nÉvalue l'engagement commercial de ce contact sur 100. Tiens compte du lifecycle, des interactions Emelia (ouvertures, réponses) et de l'activité CRM récente. Réponds UNIQUEMENT en JSON valide : {\"score\": 75, \"rationale\": \"Justification en 1-2 phrases.\"}",
            default        => $context,
        };

        $options = match ($promptType) {
            'summarize'    => [],
            'next-action'  => ['max_tokens' => 300],
            'score'        => ['max_tokens' => 600],
            'score-contact' => ['max_tokens' => 250],
            default        => [],
        };
        $raw = $this->llm->complete($system, $user, $options);

        if (in_array($promptType, ['next-action', 'score', 'score-contact'])) {
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
            'Lifecycle : '.($contact->lifecycle_stage ?? 'N/A'),
        ];

        if (count($activities) > 0) {
            $lines[] = "\nActivités récentes :";
            foreach (array_slice($activities, 0, 5) as $a) {
                $lines[] = "- [{$a['type']}] {$a['title']} ({$a['status']})";
            }
        } else {
            $lines[] = "\nAucune activité enregistrée.";
        }

        if ($contact->emelia_campaign_id) {
            $emeliaStats = Activity::where('subject_type', Contact::class)
                ->where('subject_id', $contact->id)
                ->where('source', 'emelia')
                ->selectRaw('type, COUNT(*) as cnt, MAX(occurred_at) as last_at')
                ->groupBy('type')
                ->get();

            if ($emeliaStats->isNotEmpty()) {
                $lines[] = "\nEngagement email (Emelia) :";
                foreach ($emeliaStats as $ea) {
                    $lastAt = $ea->last_at ? \Carbon\Carbon::parse($ea->last_at)->format('d/m/Y') : 'N/A';
                    $lines[] = "- {$ea->type} : {$ea->cnt}x (dernier : {$lastAt})";
                }
            }
            $campaignLabel = $contact->emelia_campaign_name ?? $contact->emelia_campaign_id;
            $lines[] = "Campagne Emelia : {$campaignLabel}";
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
