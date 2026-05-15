<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Deal;
use App\Services\LlmService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AiController extends Controller
{
    public function __construct(private readonly LlmService $llm) {}

    public function summarizeDeal(int $id): JsonResponse
    {
        return $this->respond('summarize-deal', 'deal', $id, function () use ($id) {
            $deal = Deal::query()
                ->with(['company', 'contact', 'pipeline', 'stage', 'owner'])
                ->findOrFail($id);

            $activities = Activity::query()
                ->where('subject_type', Deal::class)
                ->where('subject_id', $id)
                ->latest()
                ->limit(10)
                ->get();

            $auditLogs = AuditLog::query()
                ->where('auditable_type', Deal::class)
                ->where('auditable_id', $id)
                ->latest('created_at')
                ->limit(5)
                ->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), $auditLogs->toArray()),
                'hash' => md5((string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt' => 'summarize',
            ];
        });
    }

    public function summarizeContact(int $id): JsonResponse
    {
        return $this->respond('summarize-contact', 'contact', $id, function () use ($id) {
            $contact = Contact::query()
                ->with(['company', 'owner'])
                ->findOrFail($id);

            $activities = Activity::query()
                ->where('subject_type', Contact::class)
                ->where('subject_id', $id)
                ->latest()
                ->limit(10)
                ->get();

            return [
                'context' => $this->contactContext($contact, $activities->toArray()),
                'hash' => md5((string) $contact->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt' => 'summarize',
            ];
        });
    }

    public function nextActionDeal(int $id): JsonResponse
    {
        return $this->respond('next-action-deal', 'deal', $id, function () use ($id) {
            $deal = Deal::query()
                ->with(['company', 'contact', 'pipeline', 'stage', 'owner'])
                ->findOrFail($id);

            $activities = Activity::query()
                ->where('subject_type', Deal::class)
                ->where('subject_id', $id)
                ->latest()
                ->limit(10)
                ->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), []),
                'hash' => md5('next-action'.(string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt' => 'next-action',
            ];
        });
    }

    public function scoreDeal(int $id): JsonResponse
    {
        return $this->respond('score-deal', 'deal', $id, function () use ($id) {
            $deal = Deal::query()
                ->with(['stage'])
                ->findOrFail($id);

            $activities = Activity::query()
                ->where('subject_type', Deal::class)
                ->where('subject_id', $id)
                ->latest()
                ->limit(5)
                ->get();

            return [
                'context' => $this->dealContext($deal, $activities->toArray(), []),
                'hash' => md5('score'.(string) $deal->updated_at.$activities->count().($activities->first()?->created_at ?? '')),
                'prompt' => 'score',
            ];
        });
    }

    private function respond(string $endpoint, string $type, int $id, callable $builder): JsonResponse
    {
        try {
            $built = $builder();
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        }

        $cacheKey = "ai:{$endpoint}:{$type}:{$id}:{$built['hash']}";

        if (Cache::has($cacheKey)) {
            return response()->json([
                'data' => Cache::get($cacheKey),
                'cached' => true,
                'generated_at' => now()->toIso8601String(),
            ]);
        }

        try {
            $content = $this->generate($built['context'], $built['prompt']);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        Cache::put($cacheKey, $content, now()->addHours(24));

        return response()->json([
            'data' => $content,
            'cached' => false,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function generate(string $context, string $promptType): mixed
    {
        $system = 'Tu es un assistant CRM commercial B2B expert. Tu analyses les données CRM et génères des insights actionnables en français. Sois concis et précis.';

        $user = match ($promptType) {
            'summarize' => $context."\n\nGénère un brief synthétique de 4 à 6 lignes pour aider le commercial à reprendre ce dossier.",
            'next-action' => $context."\n\nSuggère la prochaine action commerciale concrète. Réponds UNIQUEMENT en JSON valide : {\"action\": \"...\", \"rationale\": \"...\", \"priority\": \"high\"}",
            'score' => $context."\n\nScore ce deal sur 100. Réponds UNIQUEMENT en JSON valide : {\"score\": 75, \"trend\": \"warming\", \"reasons\": [\"raison 1\"]}",
            default => $context,
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

        $lines = [
            "=== DEAL : {$deal->name} ===",
            'Montant : '.number_format((float) $deal->amount, 2, ',', ' ')." {$deal->currency}",
            "Statut : {$deal->status}",
            'Étape : '.($deal->stage?->name ?? 'N/A').' (probabilité : '.($deal->stage?->probability ?? 0).'%)',
            'Pipeline : '.($deal->pipeline?->name ?? 'N/A'),
            'Entreprise : '.($deal->company?->name ?? 'Non renseignée'),
            'Contact : '.($deal->contact ? "{$deal->contact->first_name} {$deal->contact->last_name}" : 'Non renseigné'),
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
            'Entreprise : '.($contact->company?->name ?? 'Non renseignée'),
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
}
