<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiInsightService
{
    public function __construct(private readonly LlmService $llm) {}

    // ──────────────────────────────────────────────
    //  API PUBLIQUE
    // ──────────────────────────────────────────────

    public function summarizeDeal(int $id, bool $fresh = false): array
    {
        $deal = Deal::with(['companies', 'contacts', 'pipeline', 'stage', 'owner'])->findOrFail($id);
        return $this->resolve('summarize-deal', 'deal', $id, $fresh, function () use ($deal) {
            $activities = Activity::where('subject_type', Deal::class)->where('subject_id', $deal->id)->latest()->limit(10)->get();
            $auditLogs  = AuditLog::where('auditable_type', Deal::class)->where('auditable_id', $deal->id)->latest()->limit(5)->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), $auditLogs->toArray()),
                'hash'    => md5((string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'summarize',
            ];
        }, $this->ttlFor('summarize-deal', $deal));
    }

    public function nextActionDeal(int $id, bool $fresh = false): array
    {
        $deal = Deal::with(['companies', 'contacts', 'pipeline', 'stage', 'owner'])->findOrFail($id);
        return $this->resolve('next-action-deal', 'deal', $id, $fresh, function () use ($deal) {
            $activities = Activity::where('subject_type', Deal::class)->where('subject_id', $deal->id)->latest()->limit(10)->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), []),
                'hash'    => md5('next-action'.(string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'next-action',
            ];
        }, $this->ttlFor('next-action-deal', $deal));
    }

    public function scoreDeal(int $id, bool $fresh = false): array
    {
        $deal = Deal::with(['stage'])->findOrFail($id);
        return $this->resolve('score-deal', 'deal', $id, $fresh, function () use ($deal) {
            $activities = Activity::where('subject_type', Deal::class)->where('subject_id', $deal->id)->latest()->limit(5)->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), []),
                'hash'    => md5('score'.(string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'score',
            ];
        }, $this->ttlFor('score-deal', $deal));
    }

    public function summarizeContact(int $id, bool $fresh = false): array
    {
        $contact = Contact::with(['companies', 'owner'])->findOrFail($id);
        return $this->resolve('summarize-contact', 'contact', $id, $fresh, function () use ($contact) {
            $activities = Activity::where('subject_type', Contact::class)->where('subject_id', $contact->id)->latest()->limit(10)->get();

            return [
                'context' => $this->contactContext($contact, $activities->toArray()),
                'hash'    => md5((string) $contact->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'summarize',
            ];
        }, $this->ttlFor('summarize-contact'));
    }

    public function summarizeCompany(int $id, bool $fresh = false): array
    {
        $company = Company::with(['contacts', 'deals'])->findOrFail($id);
        return $this->resolve('summarize-company', 'company', $id, $fresh, function () use ($company) {
            $activities = Activity::where('subject_type', Company::class)->where('subject_id', $company->id)->latest()->limit(10)->get();

            return [
                'context' => $this->companyContext($company, $activities->toArray()),
                'hash'    => md5((string) $company->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt'  => 'summarize',
            ];
        }, $this->ttlFor('summarize-company'));
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
        }, $this->ttlFor('score-contact'));
    }

    // ──────────────────────────────────────────────
    //  RÉDACTION EMAIL
    // ──────────────────────────────────────────────

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
        $prompt     = $context.$intentLine."\n\nRédige un email professionnel B2B en français (maximum 200 mots). Objet accrocheur. Signature générique (prénom seulement). Réponds UNIQUEMENT en JSON valide : {\"subject\": \"Objet\", \"body\": \"Corps\"}";

        $raw = $this->llm->complete(
            $this->systemPrompt('draft'),
            $prompt,
            ['max_tokens' => 600]
        );

        return $this->parseJsonStrict($raw) ?? ['subject' => 'Email généré', 'body' => $raw];
    }

    // ──────────────────────────────────────────────
    //  ANALYSE SENTIMENT
    // ──────────────────────────────────────────────

    public function analyzeSentiment(string $text): array
    {
        $raw = $this->llm->complete(
            $this->systemPrompt('sentiment'),
            "Analyse le sentiment de cette réponse email :\n\"{$text}\"\n\nRéponds UNIQUEMENT en JSON valide : {\"sentiment\": \"positif\", \"score\": 0.8, \"summary\": \"Résumé en 1 phrase.\"}",
            ['max_tokens' => 150]
        );

        return $this->parseJsonStrict($raw) ?? ['sentiment' => 'neutre', 'score' => 0.0, 'summary' => ''];
    }

    // ──────────────────────────────────────────────
    //  SUGGESTIONS QUOTIDIENNES
    // ──────────────────────────────────────────────

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
        $prompt = "Tu es un directeur commercial qui prépare le brief du matin pour son équipe. Voici l'état du pipeline aujourd'hui :\n\n{$context}\n\nPropose des suggestions d'actions concrètes, des alertes urgentes et les priorités du jour. Réponds UNIQUEMENT en JSON valide : {\"suggestions\": [\"action 1\"], \"alerts\": [\"alerte urgente 1\"], \"priorities\": [\"priorité 1\"]}";

        try {
            $raw = $this->llm->complete(
                $this->systemPrompt('suggestions'),
                $prompt,
                ['max_tokens' => 800]
            );
        } catch (RuntimeException $e) {
            throw $e;
        }

        $parsed = $this->parseJsonStrict($raw);
        $data = $parsed ?? ['suggestions' => [$raw], 'alerts' => [], 'priorities' => []];

        Cache::put($cacheKey, $data, now()->addHours(6));

        return ['data' => $data, 'cached' => false];
    }

    // ──────────────────────────────────────────────
    //  ANALYSE RAPPORTS
    // ──────────────────────────────────────────────

    public function analyzeReports(array $reportData, bool $fresh = false): array
    {
        $cacheKey = 'ai:report-insights';

        if (!$fresh && Cache::has($cacheKey)) {
            return ['data' => Cache::get($cacheKey), 'cached' => true];
        }

        $lines = [];

        // CA mensuel — dernier mois vs mois précédent
        $caMensuel = $reportData['ca_mensuel'] ?? [];
        if (count($caMensuel) >= 2) {
            $last    = end($caMensuel);
            $prev    = $caMensuel[count($caMensuel) - 2];
            $delta   = $prev['ca_gagne'] > 0 ? round(($last['ca_gagne'] - $prev['ca_gagne']) / $prev['ca_gagne'] * 100, 1) : null;
            $lines[] = "CA gagné ce mois ({$last['mois']}) : " . number_format($last['ca_gagne'], 0, ',', ' ') . ' €'
                       . ($delta !== null ? " ({$delta}% vs mois précédent)" : '');
            $lines[] = "Pipeline ouvert ce mois : " . number_format($last['pipeline'], 0, ',', ' ') . ' €';
        }

        // Entonnoir
        $entonnoir = $reportData['entonnoir'] ?? [];
        if (!empty($entonnoir['stages'])) {
            $lines[] = "Taux de conversion global : " . ($entonnoir['taux_conversion_global'] ?? '?') . '%';
            $stagesStr = collect($entonnoir['stages'])->map(fn($s) => "{$s['name']} : {$s['count']} deal(s)")->join(', ');
            $lines[] = "Entonnoir : {$stagesStr}";
        }

        // Classement commerciaux
        $classement = $reportData['classement'] ?? [];
        if (!empty($classement)) {
            $top = $classement[0];
            $lines[] = "Top commercial ce mois : {$top['commercial']} — {$top['nb_deals']} deal(s), " . number_format($top['ca'], 0, ',', ' ') . ' €';
            if (count($classement) > 1) {
                $bottom = end($classement);
                $lines[] = "Dernier classé : {$bottom['commercial']} — {$bottom['nb_deals']} deal(s)";
            }
        }

        // Activité hebdo
        $activiteHebdo = $reportData['activite_hebdo'] ?? [];
        if (!empty($activiteHebdo)) {
            $lastWeek = end($activiteHebdo);
            $lines[]  = "Activité semaine du {$lastWeek['semaine']} : {$lastWeek['total']} action(s) enregistrée(s)";
        }

        if (empty($lines)) {
            $data = ['insights' => ['Pas encore de données suffisantes pour générer des insights.'], 'alerts' => [], 'recommendations' => []];
            Cache::put($cacheKey, $data, now()->addHour());
            return ['data' => $data, 'cached' => false];
        }

        $context = implode("\n", $lines);
        $prompt  = "Tu es un analyste CRM qui détecte les tendances, anomalies et opportunités. Voici les métriques clés du CRM :\n\n{$context}\n\n"
                   . "Génère 3 à 5 insights actionnables basés sur ces données. Détecte les tendances, anomalies et opportunités. "
                   . "Réponds UNIQUEMENT en JSON valide : {\"insights\": [\"insight 1\"], \"alerts\": [\"alerte urgente 1\"], \"recommendations\": [\"recommandation 1\"]}";

        try {
            $raw = $this->llm->complete(
                $this->systemPrompt('analyze'),
                $prompt,
                ['max_tokens' => 700]
            );
        } catch (RuntimeException $e) {
            throw $e;
        }

        $parsed = $this->parseJsonStrict($raw);
        $data = $parsed ?? ['insights' => [$raw], 'alerts' => [], 'recommendations' => []];

        Cache::put($cacheKey, $data, now()->addHour());

        return ['data' => $data, 'cached' => false];
    }

    // ──────────────────────────────────────────────
    //  BATCH SCORING — 1 appel LLM pour N contacts
    // ──────────────────────────────────────────────

    /**
     * Score N contacts en batch — 1 appel LLM pour 10 contacts.
     * Utilisé par ai:score-contacts pour remplacer 50 appels individuels.
     *
     * @param \Illuminate\Support\Collection<int, Contact> $contacts
     * @return array<int, array{score: int, rationale: string}>  keyed by contact id
     */
    public function batchScoreContacts(\Illuminate\Support\Collection $contacts): array
    {
        $batches = $contacts->chunk(10);
        $results = [];

        foreach ($batches as $batch) {
            $entries = $batch->map(fn ($c) => sprintf(
                "ID:%d | %s %s | lifecycle:%s | emelia:%s | deals_ouverts:%d | activite_30j:%d",
                $c->id,
                $c->first_name,
                $c->last_name,
                $c->lifecycle_stage ?? 'none',
                $c->emelia_campaign_id ? 'oui' : 'non',
                $c->deals->where('status', 'open')->count(),
                Activity::where('subject_type', Contact::class)
                    ->where('subject_id', $c->id)
                    ->where('created_at', '>', now()->subDays(30))
                    ->count()
            ))->implode("\n");

            $prompt = "Score chaque contact de 0 à 100 sur son engagement commercial.\n"
                    . "Critères : lifecycle (0-30pts), interactions email (0-30pts), activité 30j (0-20pts), deals ouverts (0-20pts).\n"
                    . "Un score >70 est excellent (top 20%).\n\n"
                    . "Réponds UNIQUEMENT en JSON : [{\"id\": 1, \"score\": 65, \"rationale\": \"...\"}, ...]\n\n"
                    . $entries;

            $raw = $this->llm->complete(
                $this->systemPrompt('score-contact'),
                $prompt,
                ['max_tokens' => 1500]
            );

            $parsed = $this->parseJsonStrict($raw);

            if (is_array($parsed)) {
                foreach ($parsed as $item) {
                    if (isset($item['id'], $item['score'])) {
                        $results[(int) $item['id']] = [
                            'score'     => (int) $item['score'],
                            'rationale' => $item['rationale'] ?? '',
                        ];
                    }
                }
            }
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //  MÉTHODES PRIVÉES — COEUR
    // ──────────────────────────────────────────────

    /**
     * Résout le cache + génération LLM.
     * Le TTL est adaptatif selon le type d'entité et son état.
     */
    private function resolve(string $endpoint, string $type, int $id, bool $fresh, callable $builder, ?int $ttl = null): array
    {
        $built    = $builder();
        $cacheKey = "ai:{$endpoint}:{$type}:{$id}:{$built['hash']}";

        if (!$fresh && Cache::has($cacheKey)) {
            return ['data' => Cache::get($cacheKey), 'cached' => true];
        }

        $content = $this->generate($built['context'], $built['prompt']);
        Cache::put($cacheKey, $content, now()->addSeconds($ttl ?? 86400));

        return ['data' => $content, 'cached' => false];
    }

    /**
     * Génère la réponse LLM via OpenRouter avec le bon system prompt.
     * Pour les endpoints JSON, active le mode response_format.
     */
    private function generate(string $context, string $promptType): mixed
    {
        $system = $this->systemPrompt($promptType);
        $isJson = in_array($promptType, ['next-action', 'score', 'score-contact']);

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

        // Pour les endpoints JSON on demande un format structuré
        if ($isJson) {
            $options['response_format'] = ['type' => 'json_object'];
        }

        $raw = $this->llm->complete($system, $user, $options);

        if ($isJson) {
            $parsed = $this->parseJsonStrict($raw);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return $raw;
    }

    // ──────────────────────────────────────────────
    //  PARSING JSON ROBUSTE
    // ──────────────────────────────────────────────

    /**
     * Parse du JSON depuis une réponse LLM, en gérant :
     * - Markdown ```json ... ```
     * - Texte autour du JSON
     * - Objets imbriqués
     * - Tableaux JSON
     */
    private function parseJsonStrict(string $raw): ?array
    {
        // 1. Supprimer les blocs markdown ```json ... ``` ou ``` ... ```
        $cleaned = preg_replace('/```(?:json)?\s*\n?(.*?)\n?```/s', '$1', $raw);
        $cleaned = trim($cleaned);

        // 2. Tentative directe
        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 3. Fallback : extraire le premier { ... } avec gestion de profondeur
        $depth = 0;
        $start = null;
        $len   = strlen($cleaned);

        for ($i = 0; $i < $len; $i++) {
            $ch = $cleaned[$i];
            if ($ch === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $candidate = substr($cleaned, $start, $i - $start + 1);
                    $decoded = json_decode($candidate, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                    $start = null;
                }
            }
        }

        // 4. Dernier recours : tenter d'extraire un tableau JSON
        if (preg_match('/\[.*\]/s', $cleaned, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────
    //  TTL ADAPTATIF
    // ──────────────────────────────────────────────

    private function ttlFor(string $endpoint, $entity = null): int
    {
        return match ($endpoint) {
            'summarize-deal' => $entity?->close_date && $entity->close_date->isBefore(now()->addDays(7))
                ? 3600   // 1h si close dans les 7j
                : 21600, // 6h sinon
            'next-action-deal' => 3600,     // 1h — les actions suggérées changent vite
            'score-deal' => $entity?->status !== 'open'
                ? 86400   // 24h pour won/lost (figé)
                : ($entity?->close_date && $entity->close_date->isBefore(now()->addDays(7))
                    ? 3600    // 1h si close bientôt
                    : 21600), // 6h sinon
            'summarize-contact' => 43200, // 12h
            'score-contact'     => 86400, // 24h — recalculé la nuit en batch
            'summarize-company' => 86400, // 24h — données quasi-statiques
            default => 86400,
        };
    }

    // ──────────────────────────────────────────────
    //  SYSTEM PROMPTS SPÉCIALISÉS
    // ──────────────────────────────────────────────

    private function systemPrompt(string $task): string
    {
        return match ($task) {
            'summarize' => "Tu es un assistant CRM commercial qui prépare des briefs de dossiers pour des commerciaux. "
                         . "Sois synthétique (4-6 lignes max), va à l'essentiel. Structure : contexte, statut, prochaine action implicite.",

            'next-action' => "Tu es un coach commercial senior. Tu suggères LA prochaine action concrète "
                           . "qui ferait avancer ce deal. Sois spécifique : \"Appelle X pour relancer sur Y\" "
                           . "pas \"Relance le contact\". Réponds UNIQUEMENT en JSON valide.",

            'score' => "Tu es un analyste commercial qui score les deals CRM de 0 à 100. "
                     . "Critères : montant, probabilité de closing, activité récente, durée dans le pipeline. "
                     . "Sois exigeant : un score >70 est rare (top 20%). "
                     . "Détecte les vrais signaux : un deal avec activités récentes et réponses email "
                     . "vaut plus qu'un deal avec juste un montant élevé. Réponds UNIQUEMENT en JSON valide.",

            'score-contact' => "Tu évalues l'engagement commercial d'un contact de 0 à 100. "
                            . "Pondération : lifecycle (0-30pts), interactions email (0-30pts), "
                            . "activité CRM récente (0-20pts), deals associés (0-20pts). "
                            . "Un contact sans activité depuis 30 jours ne peut pas dépasser 30. "
                            . "Réponds UNIQUEMENT en JSON valide.",

            'draft' => "Tu es un rédacteur commercial B2B expert. Tu rédiges des emails percutants "
                     . "et personnalisés en français. Max 200 mots. Ton but : obtenir une réponse "
                     . "ou une action du prospect. Personnalise selon le contexte. "
                     . "Réponds UNIQUEMENT en JSON valide {\"subject\": \"...\", \"body\": \"...\"}.",

            'sentiment' => "Tu es un expert en analyse de communication. Tu détectes le vrai sentiment "
                         . "derrière un email. Ne conclus POSITIF que si : demande de démo, question "
                         . "sur le prix ou l'offre, mention d'un besoin explicite. Ne conclus NÉGATIF "
                         . "que si : objection ferme, refus poli, absence d'intérêt marqué. "
                         . "Par défaut : neutre. Réponds UNIQUEMENT en JSON valide.",

            'suggestions' => "Tu es un directeur commercial qui prépare le brief du matin pour son équipe. "
                          . "Tu vois le pipeline, les deals qui stagnent, les tâches en retard, les réponses "
                          . "Emelia. Priorise ce qui est URGENT vs IMPORTANT. Sois actionnable. "
                          . "Réponds UNIQUEMENT en JSON valide.",

            'analyze' => "Tu es un analyste CRM qui détecte les tendances, anomalies et opportunités "
                      . "dans les données. Regarde les variations mois/mois, les goulots d'étranglement "
                      . "dans le pipeline, les écarts de performance entre les commerciaux. "
                      . "Réponds UNIQUEMENT en JSON valide.",

            default => "Tu es un assistant CRM commercial B2B expert, concis et précis.",
        };
    }

    // ──────────────────────────────────────────────
    //  CONTEXTES ENRICHIS
    // ──────────────────────────────────────────────

    private function dealContext(Deal $deal, array $activities, array $auditLogs): string
    {
        $daysOld = $deal->updated_at ? now()->diffInDays($deal->updated_at) : '?';
        $trend   = $this->buildActivityTrends(Deal::class, $deal->id);

        $lines = [
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
            "\nTendance : {$trend}",
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
        $trend = $this->buildActivityTrends(Contact::class, $contact->id);

        $lines = [
            "=== CONTACT : {$contact->first_name} {$contact->last_name} ===",
            'Email : '.($contact->email ?? 'Non renseigné'),
            'Téléphone : '.($contact->phone ?? 'Non renseigné'),
            'Poste : '.($contact->job_title ?? 'Non renseigné'),
            'Entreprise : '.($contact->companies->first()?->name ?? 'Non renseignée'),
            'Responsable : '.($contact->owner?->name ?? 'Non assigné'),
            'Lifecycle : '.($contact->lifecycle_stage ?? 'N/A'),
            "\nTendance : {$trend}",
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
                ->selectRaw("type, COUNT(*) as cnt, MAX(occurred_at) as last_at")
                ->groupBy('type')
                ->get();

            if ($emeliaStats->isNotEmpty()) {
                $lines[] = "\nEngagement email (Emelia) :";
                foreach ($emeliaStats as $ea) {
                    $lastAt = $ea->last_at ? \Carbon\Carbon::parse($ea->last_at)->format('d/m/Y') : 'N/A';
                    $lines[] = "- {$ea->type} : {$ea->cnt}× (dernier : {$lastAt})";
                }
            }
            $campaignLabel = $contact->emelia_campaign_name ?? $contact->emelia_campaign_id;
            $lines[] = "Campagne Emelia : {$campaignLabel}";
        }

        // RAG léger : chercher les 3 activités les plus pertinentes pour ce contact
        $ragNotes = $this->searchContextNotes(Contact::class, $contact->id);
        if ($ragNotes) {
            $lines[] = "\nNotes clés trouvées :\n{$ragNotes}";
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

    // ──────────────────────────────────────────────
    //  TENDANCES D'ACTIVITÉ
    // ──────────────────────────────────────────────

    /**
     * Calcule une tendance d'activité lisible pour le LLM, basée sur des stats SQL agrégées.
     * Compare les 30 derniers jours avec les 30 précédents pour détecter les variations.
     */
    private function buildActivityTrends(string $subjectType, int $subjectId): string
    {
        $stats = DB::selectOne("
            SELECT
                COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '30 days') AS period_30d,
                COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '7 days')  AS period_7d,
                COUNT(*) FILTER (
                    WHERE created_at BETWEEN NOW() - INTERVAL '60 days' AND NOW() - INTERVAL '30 days'
                ) AS prev_30d,
                COUNT(*) FILTER (
                    WHERE type IN ('email_replied','email_opened')
                      AND created_at > NOW() - INTERVAL '7 days'
                ) AS email_engagement_7d
            FROM activities
            WHERE subject_type = ? AND subject_id = ?
        ", [$subjectType, $subjectId]);

        if (!$stats || (!$stats->period_30d && !$stats->period_7d)) {
            return '❄️ Aucune activité récente';
        }

        $parts = [];

        // Comparaison 30j vs 30j précédents
        if ($stats->prev_30d > 0) {
            $variation = (($stats->period_30d - $stats->prev_30d) / max($stats->prev_30d, 1)) * 100;
            $parts[] = match (true) {
                $variation > 50   => '📈 Forte hausse d\'activité (+'.round($variation).'%)',
                $variation > 10   => '📈 Activité en hausse (+'.round($variation).'%)',
                $variation < -50  => '📉 Forte baisse d\'activité (−'.round(abs($variation)).'%)',
                $variation < -10  => '📉 Activité en baisse (−'.round(abs($variation)).'%)',
                default            => '→ Activité stable',
            };
        } elseif ($stats->period_30d > 0) {
            $parts[] = '📊 '.$stats->period_30d.' activité(s) sur les 30 derniers jours';
        }

        // Engagement email cette semaine
        if ($stats->email_engagement_7d > 0) {
            $parts[] = '💬 '.$stats->email_engagement_7d.' interaction(s) email cette semaine';
        }

        return implode(' — ', $parts);
    }

    // ──────────────────────────────────────────────
    //  RAG LÉGER — Recherche full-text dans les notes
    // ──────────────────────────────────────────────

    /**
     * Cherche les notes et activités textuelles les plus pertinentes
     * pour enrichir le contexte passé au LLM.
     */
    private function searchContextNotes(string $subjectType, int $subjectId): string
    {
        try {
            $notes = Activity::where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->whereIn('type', ['note', 'call', 'email'])
                ->whereNotNull('title')
                ->latest()
                ->limit(3)
                ->get(['type', 'title', 'created_at']);

            if ($notes->isEmpty()) {
                return '';
            }

            return $notes->map(fn ($a) => sprintf(
                "- [%s] %s (%s)",
                $a->type,
                $a->title,
                $a->created_at?->format('d/m/Y') ?? '?'
            ))->implode("\n");
        } catch (\Exception) {
            return '';
        }
    }
}