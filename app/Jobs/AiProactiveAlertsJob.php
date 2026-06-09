<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\AiProactiveAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Job d'alertes IA proactives.
 * Analyse le pipeline et génère des alertes stockées en Redis.
 * Exécuté toutes les 2h par le scheduler.
 */
class AiProactiveAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 2;

    public function handle(): void
    {
        $alerts = [];

        // ── 1. Deals qui stagnent ──
        // Deal open + sans activité depuis 14j + close_date dans le futur
        // (pas de ai_score sur deals — on se base sur l'ancienneté de la dernière activité)
        $stagnantDeals = Deal::where('status', 'open')
            ->where('updated_at', '<', now()->subDays(14))
            ->where('close_date', '>', now())
            ->with('owner')
            ->orderBy('updated_at')
            ->limit(5)
            ->get();

        foreach ($stagnantDeals as $deal) {
            $alerts[] = [
                'type'     => 'deal_stagnant',
                'severity' => 'warning',
                'title'    => "Deal stagnant : {$deal->name}",
                'message'  => sprintf(
                    'Aucune activité depuis %dj — montant %s € — close prévue le %s',
                    $deal->updated_at ? now()->diffInDays($deal->updated_at) : '?',
                    number_format($deal->amount, 0, ',', ' '),
                    $deal->close_date?->format('d/m/Y') ?? '?'
                ),
                'deal_id'  => $deal->id,
                'owner_id' => $deal->owner_id,
                'icon'     => '🔻',
            ];
        }

        // ── 2. Deals qui closent bientôt sans activité récente ──
        $staleClosing = Deal::where('status', 'open')
            ->whereBetween('close_date', [now(), now()->addDays(3)])
            ->where('updated_at', '<', now()->subDays(3))
            ->with('owner')
            ->limit(5)
            ->get();

        foreach ($staleClosing as $deal) {
            $alerts[] = [
                'type'     => 'closing_soon_stale',
                'severity' => 'critical',
                'title'    => "Deal à clôturer bientôt — aucune activité",
                'message'  => sprintf(
                    '%s — clos dans %s — sans activité depuis %dj — %s €',
                    $deal->name,
                    $deal->close_date->diffForHumans(),
                    now()->diffInDays($deal->updated_at),
                    number_format($deal->amount, 0, ',', ' ')
                ),
                'deal_id'  => $deal->id,
                'owner_id' => $deal->owner_id,
                'icon'     => '⏰',
            ];
        }

        // ── 3. Réponses Emelia négatives récentes (< 6h) ──
        $negativeReplies = Activity::where('type', Activity::TYPE_EMAIL_REPLIED)
            ->where('created_at', '>', now()->subHours(6))
            ->where(function ($query) {
                $query->whereRaw("metadata->'sentiment'->>'sentiment' IN ('negatif', 'négatif', 'negative')")
                      ->orWhereRaw("(metadata->'sentiment'->>'score')::float < -0.3");
            })
            ->with('subject')
            ->limit(5)
            ->get();

        foreach ($negativeReplies as $activity) {
            $contact = $activity->subject;
            $name = $contact
                ? trim(($contact->first_name ?? '').' '.($contact->last_name ?? $contact->name ?? ''))
                : 'Contact inconnu';

            $alerts[] = [
                'type'       => 'negative_reply',
                'severity'   => 'critical',
                'title'      => "Réponse négative : {$name}",
                'message'    => $activity->title ?? 'Réponse émotionnellement négative détectée',
                'contact_id' => $contact?->id,
                'icon'       => '😟',
            ];
        }

        // ── 4. Tâches overdue non résolues (> 24h) ──
        $overdueTasks = Activity::where('type', Activity::TYPE_TASK)
            ->where('status', 'open')
            ->where('due_at', '<', now()->subDay())
            ->with('owner')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        foreach ($overdueTasks as $task) {
            $alerts[] = [
                'type'     => 'task_overdue',
                'severity' => 'warning',
                'title'    => "Tâche en retard : {$task->title}",
                'message'  => sprintf(
                    'Due le %s — en retard de %dj',
                    $task->due_at?->format('d/m/Y') ?? '?',
                    $task->due_at ? now()->diffInDays($task->due_at) : '?'
                ),
                'owner_id' => $task->owner_id,
                'icon'     => '📋',
            ];
        }

        // ── 5. Pipeline stagnant — aucun deal gagné cette semaine ──
        $wonThisWeek = Deal::where('status', 'won')
            ->where('updated_at', '>', now()->startOfWeek())
            ->count();

        $openDeals = Deal::where('status', 'open')->count();

        if ($wonThisWeek === 0 && $openDeals > 3) {
            $alerts[] = [
                'type'     => 'pipeline_stagnant',
                'severity' => 'info',
                'title'    => 'Pipeline stagnant cette semaine',
                'message'  => "Aucun deal gagné depuis lundi — {$openDeals} deals ouverts en cours",
                'icon'     => '🏊',
            ];
        }

        // Stocker en cache Redis pour affichage UI
        Cache::put('ai:proactive_alerts', $alerts, now()->addHours(2));

        // Notifier les utilisateurs concernés (alertes critiques uniquement)
        $criticalAlerts = array_filter($alerts, fn ($a) => $a['severity'] === 'critical');
        if (!empty($criticalAlerts)) {
            $admins = User::where('role', 'admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new AiProactiveAlertNotification($criticalAlerts));
            }
        }

        Log::info('AI Proactive Alerts generated', [
            'total'    => count($alerts),
            'critical' => count($criticalAlerts),
        ]);
    }
}
