<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AiInsightService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AiController extends Controller
{
    public function __construct(private readonly AiInsightService $ai) {}

    public function dealInsight(Request $request, int $id, string $action): JsonResponse
    {
        $user = $request->user();
        $fresh = $request->boolean('fresh') && in_array($user?->role, ['admin', 'manager']);

        try {
            $result = match ($action) {
                'summarize' => $this->ai->summarizeDeal($id, $fresh, $user),
                'next-action' => $this->ai->nextActionDeal($id, $fresh, $user),
                'score' => $this->ai->scoreDeal($id, $fresh, $user),
                default => null,
            };
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        if ($result === null) {
            return response()->json(['message' => 'Action inconnue.'], 422);
        }

        return response()->json(array_merge($result, ['generated_at' => now()->toIso8601String()]));
    }

    public function contactInsight(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $fresh = $request->boolean('fresh') && in_array($user?->role, ['admin', 'manager']);

        try {
            $result = $this->ai->summarizeContact($id, $fresh, $user);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(array_merge($result, ['generated_at' => now()->toIso8601String()]));
    }

    public function companyInsight(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $fresh = $request->boolean('fresh') && in_array($user?->role, ['admin', 'manager']);

        try {
            $result = $this->ai->summarizeCompany($id, $fresh, $user);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(array_merge($result, ['generated_at' => now()->toIso8601String()]));
    }

    public function draftEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'intent' => ['nullable', 'string', 'max:200'],
        ]);

        if (empty($validated['contact_id']) && empty($validated['deal_id'])) {
            return response()->json(['message' => 'contact_id ou deal_id requis.'], 422);
        }

        try {
            $draft = $this->ai->draftEmail(
                $validated['contact_id'] ?? null,
                $validated['deal_id'] ?? null,
                $validated['intent'] ?? '',
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json($draft);
    }

    public function dashboardSuggestions(Request $request): JsonResponse
    {
        $fresh = $request->boolean('fresh') && in_array(auth()->user()?->role, ['admin', 'manager']);

        try {
            $result = $this->ai->dailySuggestions(auth()->user(), $fresh);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(array_merge($result, ['generated_at' => now()->toIso8601String()]));
    }

    public function reportInsights(Request $request): JsonResponse
    {
        $fresh = $request->boolean('fresh') && in_array(auth()->user()?->role, ['admin', 'manager']);

        $reportData = Cache::get('reports.data', []);

        try {
            $result = $this->ai->analyzeReports($reportData, $fresh);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(array_merge($result, ['generated_at' => now()->toIso8601String()]));
    }

    /**
     * Retourne les alertes IA proactives depuis le cache Redis.
     * Lecture seule — pas d'appel LLM.
     */
    public function proactiveAlerts(): JsonResponse
    {
        $alerts = Cache::get('ai:proactive_alerts', []);

        // Filtrer les alertes par utilisateur (les alertes owner-specific)
        $user = auth()->user();
        $filtered = array_filter($alerts, function ($alert) use ($user) {
            // Alertes globales (pipeline_stagnant etc.) → visibles par tous
            if (! isset($alert['owner_id'])) {
                return true;
            }

            // Alertes owner-specific → visible par le owner ou admin/manager
            return $alert['owner_id'] === $user->id || in_array($user->role, ['admin', 'manager']);
        });

        return response()->json([
            'alerts' => array_values($filtered),
            'total' => count($filtered),
            'critical' => count(array_filter($filtered, fn ($a) => ($a['severity'] ?? '') === 'critical')),
            'fetched_at' => now()->toIso8601String(),
        ]);
    }
}
