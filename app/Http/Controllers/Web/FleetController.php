<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class FleetController extends Controller
{
    // Liste des agents de la flotte avec leur config
    private const AGENTS = [
        'richard' => [
            'name' => 'Richard',
            'role' => 'CEO / Cerveau de la flotte',
            'description' => 'Orchestre la flotte, dispatch les tâches et envoie les synthèses Telegram.',
            'squad' => 'growth',
            'dept' => 'richard',
            'avatar_color' => 'bg-indigo-600',
        ],
        'sam' => [
            'name' => 'Sam',
            'role' => 'Closer Commercial Meta',
            'description' => 'Scanne les DMs Instagram/Facebook, qualifie les leads et interagit.',
            'squad' => 'growth',
            'dept' => 'sales',
            'avatar_color' => 'bg-emerald-600',
        ],
        'nora' => [
            'name' => 'Nora',
            'role' => 'Analyste Financière',
            'description' => 'Calcule les KPIs du funnel CRM et prépare les rapports de conversion.',
            'squad' => 'growth',
            'dept' => 'finance',
            'avatar_color' => 'bg-amber-600',
        ],
        'mia' => [
            'name' => 'Mia',
            'role' => 'Créatrice de Contenu',
            'description' => 'Génère des images engageantes avec Pillow et gère le planning Instagram.',
            'squad' => 'growth',
            'dept' => 'content',
            'avatar_color' => 'bg-pink-600',
        ],
        'juliette' => [
            'name' => 'Juliette',
            'role' => 'Séquenceur Cold Email',
            'description' => 'Pilote les campagnes de prospection froide sur les segments CRM.',
            'squad' => 'acquisition',
            'dept' => 'acquisition',
            'avatar_color' => 'bg-sky-600',
        ],
        'siteweb' => [
            'name' => 'SiteWeb',
            'role' => 'Chef de projet Web',
            'description' => 'Supervise la squad web et lance des audits globaux du site nana-intelligence.fr.',
            'squad' => 'web',
            'dept' => 'web-lead',
            'avatar_color' => 'bg-slate-700',
        ],
        'lea' => [
            'name' => 'Léa',
            'role' => 'Optimisation SEO',
            'description' => 'Audit sémantique, mots-clés, maillage et performances de trafic SEO.',
            'squad' => 'web',
            'dept' => 'seo',
            'avatar_color' => 'bg-teal-600',
        ],
        'alex' => [
            'name' => 'Alex',
            'role' => 'Ingénieur DevOps',
            'description' => 'Validation de builds, branches git, PRs GitHub et monitoring du VPS.',
            'squad' => 'web',
            'dept' => 'devops',
            'avatar_color' => 'bg-cyan-700',
        ],
        'vera' => [
            'name' => 'Véra',
            'role' => 'Veille Concurrentielle',
            'description' => 'Analyse les concurrents et détecte des opportunités d\'acquisition de trafic.',
            'squad' => 'web',
            'dept' => 'watch',
            'avatar_color' => 'bg-purple-600',
        ],
        'caro' => [
            'name' => 'Caro',
            'role' => 'Audit de Conversion (CRO)',
            'description' => 'Optimise le taux de conversion, la performance et les CTA du site.',
            'squad' => 'web',
            'dept' => 'cro',
            'avatar_color' => 'bg-rose-600',
        ],
        'lin' => [
            'name' => 'Lin',
            'role' => 'Stratégie Netlinking',
            'description' => 'Audit d\'autorité de domaine, backlinks, partenariats de liens et popularité.',
            'squad' => 'web',
            'dept' => 'linking',
            'avatar_color' => 'bg-yellow-600',
        ],
        'max' => [
            'name' => 'Max',
            'role' => 'Contrôle Qualité (QA)',
            'description' => 'Tests de non-régression sémantique, liens brisés, WCAG et sécurité Next.js.',
            'squad' => 'web',
            'dept' => 'qa',
            'avatar_color' => 'bg-emerald-700',
        ],
        'joseph' => [
            'name' => 'Joseph',
            'role' => 'Auto-remédiation & Infrastructure',
            'description' => 'Surveille la santé du VPS, l\'espace disque, les verrous et résout les anomalies.',
            'squad' => 'maintenance',
            'dept' => 'maintenance',
            'avatar_color' => 'bg-red-700',
        ],
    ];

    public function index(Request $request)
    {
        $data = $this->collectFleetData();

        // Le journal d'audit (Postgres) n'est utilisé que pour le rendu initial (pas de polling).
        $data['auditLogs'] = AuditLog::with('user')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // Feed des derniers emails cold-email de Juliette (rendu initial, hors polling léger).
        $data['julietteFeed'] = \App\Models\Activity::where('source', 'juliette')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('pages.fleet.index', $data);
    }

    /**
     * Endpoint JSON léger pour le rafraîchissement temps réel (polling Alpine).
     * Renvoie exactement le même « payload live » que celui utilisé pour amorcer
     * la vue, afin que le JS patche l'état sans divergence de forme.
     */
    public function data(Request $request)
    {
        $data = $this->collectFleetData();
        return response()->json($data['live']);
    }

    /**
     * Collecte l'état de la flotte depuis Redis et construit :
     *  - les variables de rendu Blade (agents complets, tâches, approbations, Joseph)
     *  - un « payload live » compact réutilisé par index() (seed) et data() (polling).
     */
    private function collectFleetData(): array
    {
        $fleetRedis = Redis::connection('fleet');

        // 1. Récupérer toutes les tâches task:*
        $taskKeys = [];
        try {
            $taskKeys = $fleetRedis->keys('task:*') ?: [];
        } catch (\Exception $e) {
            \Log::warning('[FleetController] Erreur de lecture des clés Redis : ' . $e->getMessage());
        }

        $allTasks = [];
        foreach ($taskKeys as $key) {
            // Sous certains drivers phpredis, les clés ont parfois le préfixe de la base
            $cleanKey = str_replace(config('database.redis.options.prefix', ''), '', $key);
            $raw = $fleetRedis->get($cleanKey);
            if ($raw) {
                $taskDecoded = json_decode($raw, true);
                if ($taskDecoded) {
                    $allTasks[] = $taskDecoded;
                }
            }
        }

        // Trier par date de création décroissante
        usort($allTasks, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        // Compteurs de statut calculés sur l'ENSEMBLE des tâches (avant plafonnement d'affichage)
        $stats = [
            'total'         => count($allTasks),
            'queued'        => 0,
            'in_progress'   => 0,
            'done'          => 0,
            'failed'        => 0,
            'other'         => 0,
        ];
        foreach ($allTasks as $t) {
            $st = $t['status'] ?? 'queued';
            if (isset($stats[$st])) {
                $stats[$st]++;
            } else {
                $stats['other']++;
            }
        }

        // On n'affiche/transmet que les 30 dernières tâches
        $tasks = array_slice($allTasks, 0, 30);

        // 2. Récupérer les tâches en attente d'approbation (approvals:pending)
        $pendingApprovals = [];
        try {
            $pendingKeys = $fleetRedis->lrange('approvals:pending', 0, -1) ?: [];
            foreach ($pendingKeys as $tid) {
                $rawTask = $fleetRedis->get("task:{$tid}");
                if ($rawTask) {
                    $taskDecoded = json_decode($rawTask, true);
                    if ($taskDecoded) {
                        $pendingApprovals[] = $taskDecoded;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('[FleetController] Erreur approvals Redis : ' . $e->getMessage());
        }
        $stats['pending_approvals'] = count($pendingApprovals);

        // 3. Déduire le statut de chaque agent + lire son heartbeat (last_seen) s'il existe
        $agents = self::AGENTS;
        $liveAgents = [];
        foreach ($agents as $key => &$agent) {
            $agent['status'] = 'idle'; // défaut : en veille

            foreach ($tasks as $t) {
                if (($t['dept'] ?? null) === $agent['dept'] && ($t['status'] ?? null) === 'in_progress') {
                    $agent['status'] = 'active';
                    break;
                }
            }

            // Heartbeat optionnel (écrit par les workers VPS — null tant que non déployé, sans casser l'UI)
            $lastSeen = null;
            try {
                $lastSeen = $fleetRedis->get("fleet:agent:{$agent['dept']}:last_seen") ?: null;
            } catch (\Exception $e) {
                // silencieux : le heartbeat est une amélioration optionnelle
            }
            // « en ligne » si le dernier battement date de moins de 120s
            $online = false;
            if ($lastSeen) {
                try {
                    $online = \Carbon\Carbon::parse($lastSeen)->gt(now()->subSeconds(120));
                } catch (\Exception $e) {
                    $online = false;
                }
            }
            $agent['last_seen'] = $lastSeen;
            $agent['online'] = $online;

            $liveAgents[$key] = [
                'status'        => $agent['status'],
                'last_seen'     => $lastSeen,
                'online'        => $online,
                'has_heartbeat' => $lastSeen !== null,
            ];
        }
        unset($agent);

        // 4. État de Joseph + fraîcheur du dernier diagnostic
        $josephStatus = [];
        try {
            $rawStatus = $fleetRedis->get('fleet:joseph:status');
            if ($rawStatus) {
                $josephStatus = json_decode($rawStatus, true) ?: [];
            }
        } catch (\Exception $e) {
            \Log::warning('[FleetController] Erreur lecture statut Joseph Redis : ' . $e->getMessage());
        }

        $josephLast = $josephStatus['timestamp'] ?? null;
        $josephStale = false;
        if ($josephLast) {
            try {
                $josephStale = \Carbon\Carbon::parse($josephLast)->lt(now()->subHours(24));
            } catch (\Exception $e) {
                $josephStale = false;
            }
        } else {
            // Aucun diagnostic connu => considéré comme périmé
            $josephStale = true;
        }

        // 5. État de Juliette (acquisition / cold email) — écrit par juliette_status.py
        $julietteStatus = [];
        try {
            $rawJuliette = $fleetRedis->get('fleet:juliette:status');
            if ($rawJuliette) {
                $julietteStatus = json_decode($rawJuliette, true) ?: [];
            }
        } catch (\Exception $e) {
            \Log::warning('[FleetController] Erreur lecture statut Juliette Redis : ' . $e->getMessage());
        }

        $julietteLast = $julietteStatus['timestamp'] ?? null;
        $julietteStale = true; // par défaut périmé si aucun export connu
        if ($julietteLast) {
            try {
                // L'exporteur tourne toutes les 10 min : > 30 min => périmé
                $julietteStale = \Carbon\Carbon::parse($julietteLast)->lt(now()->subMinutes(30));
            } catch (\Exception $e) {
                $julietteStale = false;
            }
        }

        // Payload « live » compact (seed + polling), forme stable
        $live = [
            'stats'           => $stats,
            'agents'          => $liveAgents,
            'tasks'           => $tasks,
            'approvals_count' => count($pendingApprovals),
            'joseph'          => [
                'status'   => $josephStatus['status'] ?? 'healthy',
                'system'   => $josephStatus['system'] ?? null,
                'services' => $josephStatus['services'] ?? null,
                'logs'     => $josephStatus['logs'] ?? [],
                'last'     => $josephLast,
                'stale'    => $josephStale,
            ],
            'juliette'        => [
                'status'            => $julietteStatus['status'] ?? 'healthy',
                'sends'             => $julietteStatus['sends'] ?? ['today' => 0, 'cap' => 45],
                'funnel'            => $julietteStatus['funnel'] ?? null,
                'campaigns'         => $julietteStatus['campaigns'] ?? [],
                'suppression_count' => $julietteStatus['suppression_count'] ?? 0,
                'inbox'             => $julietteStatus['inbox'] ?? null,
                'last_plan_date'    => $julietteStatus['last_plan_date'] ?? null,
                'pending_batch_size' => $julietteStatus['pending_batch_size'] ?? 0,
                'logs'              => $julietteStatus['logs'] ?? [],
                'last'              => $julietteLast,
                'stale'             => $julietteStale,
            ],
            'generated_at'    => now()->toIso8601String(),
        ];

        return [
            'agents'           => $agents,
            'tasks'            => $tasks,
            'pendingApprovals' => $pendingApprovals,
            'josephStatus'     => $josephStatus,
            'stats'            => $stats,
            'josephStale'      => $josephStale,
            'josephLast'       => $josephLast,
            'live'             => $live,
        ];
    }

    public function triggerAction(Request $request)
    {
        $data = $request->validate([
            'agent' => 'required|string|in:richard,sam,nora,mia,juliette,siteweb,lea,alex,vera,caro,lin,max,joseph',
            'action_type' => 'required|string',
        ]);

        $agentKey = $data['agent'];
        $actionType = $data['action_type'];
        $agentConfig = self::AGENTS[$agentKey];
        $dept = $agentConfig['dept'];

        try {
            $fleetRedis = Redis::connection('fleet');

            // 1. Code du département pour l'ID court
            $code = match($dept) {
                'finance', 'sales', 'content' => 'G',
                'acquisition' => 'A',
                'web-lead', 'seo', 'devops', 'watch', 'cro', 'linking', 'qa' => 'W',
                'maintenance' => 'M',
                default => 'T',
            };

            // 2. Générer l'ID de tâche séquentiel Redis
            $n = $fleetRedis->incr("seq:{$code}");
            $taskId = "{$code}{$n}";

            // Payload standardisé par action
            $payload = [];
            if ($actionType === 'kpi_report') {
                $payload = ['force' => true, 'period' => 'daily'];
            } elseif ($actionType === 'inbox_sync') {
                $payload = ['sync_all' => true];
            } elseif ($actionType === 'generate_content') {
                $payload = ['theme' => 'tech_insights', 'publish' => false];
            } elseif ($actionType === 'ceo_routine') {
                $payload = ['check_all' => true];
            } elseif (in_array($actionType, ['web_quality_audit', 'seo_audit', 'competitor_scan', 'conversion_audit', 'backlink_check', 'non_regression_test', 'deploy_build'])) {
                $payload = ['target' => 'nana-intelligence.fr', 'depth' => 2, 'initiated_by' => 'crm_ultimate'];
            } elseif ($actionType === 'infra_diagnostic') {
                $payload = ['target' => 'vps', 'initiated_by' => 'crm_ultimate'];
            } elseif (in_array($actionType, ['poll_inbox', 'refresh_status'])) {
                // Actions rapides Juliette (déterministes, sans LLM) traitées par le worker acquisition
                $payload = ['initiated_by' => 'crm_ultimate'];
            }

            $task = [
                'id' => $taskId,
                'squad' => $agentConfig['squad'],
                'dept' => $dept,
                'type' => $actionType,
                'payload' => $payload,
                'status' => 'queued',
                'requested_by' => 'jimmy_crm',
                'parent_task' => null,
                'created_at' => now()->toIso8601String(),
                'result' => null,
                'approval' => [
                    'required' => false,
                    'status' => 'none',
                    'action_spec' => null,
                ]
            ];

            // 3. Sauvegarder l'état
            $fleetRedis->set("task:{$taskId}", json_encode($task, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // 4. Pousser dans le Stream tasks:<dept> via XADD de façon brute pour éviter les bugs de conversion d'arguments
            $fleetRedis->executeRaw(['XADD', "tasks:{$dept}", '*', 'id', $taskId]);

            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Tâche {$taskId} envoyée avec succès au bus Redis pour {$agentConfig['name']} !",
                'type' => 'success',
            ]);

        } catch (\Exception $e) {
            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Erreur de communication Redis : " . $e->getMessage(),
                'type' => 'danger',
            ]);
        }
    }

    public function approveTask(Request $request, $taskId)
    {
        try {
            $fleetRedis = Redis::connection('fleet');
            
            // 1. Lire la tâche
            $raw = $fleetRedis->get("task:{$taskId}");
            if (!$raw) {
                return redirect()->route('fleet.index')->with('flash_toast', [
                    'message' => "Tâche {$taskId} introuvable.",
                    'type' => 'danger',
                ]);
            }

            $task = json_decode($raw, true);
            
            // 2. Mettre à jour le statut
            $task['status'] = 'approved';
            $task['approval']['status'] = 'approved';
            
            $fleetRedis->set("task:{$taskId}", json_encode($task, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // 3. Retirer de la liste approvals:pending
            $fleetRedis->lrem('approvals:pending', 1, $taskId);

            // 4. Republier le résultat ou relancer l'agent (XADD dans tasks:<dept>) de façon brute pour éviter les bugs de conversion d'arguments
            $fleetRedis->executeRaw(['XADD', "tasks:{$task['dept']}", '*', 'id', $taskId]);

            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Tâche {$taskId} approuvée et relancée !",
                'type' => 'success',
            ]);

        } catch (\Exception $e) {
            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Erreur Redis : " . $e->getMessage(),
                'type' => 'danger',
            ]);
        }
    }

    /**
     * Rejette une tâche en attente de validation : la retire de approvals:pending
     * et marque son statut « rejected » (miroir de bus.reject côté flotte).
     */
    public function rejectTask(Request $request, $taskId)
    {
        try {
            $fleetRedis = Redis::connection('fleet');

            $raw = $fleetRedis->get("task:{$taskId}");
            if (!$raw) {
                return redirect()->route('fleet.index')->with('flash_toast', [
                    'message' => "Tâche {$taskId} introuvable.",
                    'type' => 'danger',
                ]);
            }

            $task = json_decode($raw, true);
            $task['status'] = 'rejected';
            if (isset($task['approval']) && is_array($task['approval'])) {
                $task['approval']['status'] = 'rejected';
            }

            $fleetRedis->set("task:{$taskId}", json_encode($task, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $fleetRedis->lrem('approvals:pending', 0, $taskId);
            // Cohérence avec le GC de la flotte (phase 4) : une tâche terminée expire à 7j.
            $fleetRedis->expire("task:{$taskId}", 7 * 24 * 3600);

            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Tâche {$taskId} rejetée.",
                'type' => 'success',
            ]);

        } catch (\Exception $e) {
            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Erreur Redis : " . $e->getMessage(),
                'type' => 'danger',
            ]);
        }
    }

    /**
     * Relance une tâche en échec : repasse le statut à « queued », efface le résultat
     * précédent et ré-empile la tâche dans le stream de son département.
     */
    public function retryTask(Request $request, $taskId)
    {
        try {
            $fleetRedis = Redis::connection('fleet');

            $raw = $fleetRedis->get("task:{$taskId}");
            if (!$raw) {
                return redirect()->route('fleet.index')->with('flash_toast', [
                    'message' => "Tâche {$taskId} introuvable.",
                    'type' => 'danger',
                ]);
            }

            $task = json_decode($raw, true);
            $currentStatus = $task['status'] ?? 'queued';
            if ($currentStatus !== 'failed') {
                return redirect()->route('fleet.index')->with('flash_toast', [
                    'message' => "Seules les tâches en échec sont relançables (tâche {$taskId} : {$currentStatus}).",
                    'type' => 'danger',
                ]);
            }

            $dept = $task['dept'] ?? null;
            if (!$dept) {
                return redirect()->route('fleet.index')->with('flash_toast', [
                    'message' => "Tâche {$taskId} sans département : relance impossible.",
                    'type' => 'danger',
                ]);
            }

            // Réinitialiser pour ré-exécution
            $task['status'] = 'queued';
            $task['result'] = null;
            $fleetRedis->set("task:{$taskId}", json_encode($task, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // Ré-empiler dans le stream du dépt (XADD brut pour éviter les bugs de conversion d'arguments)
            $fleetRedis->executeRaw(['XADD', "tasks:{$dept}", '*', 'id', $taskId]);

            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Tâche {$taskId} relancée dans le bus ({$dept}).",
                'type' => 'success',
            ]);

        } catch (\Exception $e) {
            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Erreur Redis : " . $e->getMessage(),
                'type' => 'danger',
            ]);
        }
    }

    /**
     * Purge les tâches terminées du bus Redis (done et/ou failed), en préservant
     * systématiquement celles en attente d'approbation. Recoupe le GC automatique
     * prévu côté flotte (phase 4) mais reste déclenchable manuellement depuis l'UI.
     */
    public function purgeTasks(Request $request)
    {
        $data = $request->validate([
            'scope' => 'nullable|string|in:terminated,failed,done',
        ]);
        $scope = $data['scope'] ?? 'terminated';
        $targetStatuses = match ($scope) {
            'failed' => ['failed'],
            'done'   => ['done'],
            default  => ['done', 'failed'],
        };

        try {
            $fleetRedis = Redis::connection('fleet');
            $prefix = config('database.redis.options.prefix', '');

            $taskKeys = $fleetRedis->keys('task:*') ?: [];

            // Ne jamais purger une tâche en attente de validation
            $pending = $fleetRedis->lrange('approvals:pending', 0, -1) ?: [];
            $pendingSet = array_flip($pending);

            $purged = 0;
            foreach ($taskKeys as $key) {
                $cleanKey = str_replace($prefix, '', $key);
                $rawTask = $fleetRedis->get($cleanKey);
                if (!$rawTask) {
                    continue;
                }
                $t = json_decode($rawTask, true);
                if (!$t) {
                    continue;
                }
                $id = $t['id'] ?? null;
                if ($id !== null && isset($pendingSet[$id])) {
                    continue;
                }
                if (in_array($t['status'] ?? '', $targetStatuses, true)) {
                    $fleetRedis->del($cleanKey);
                    $purged++;
                }
            }

            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "{$purged} tâche(s) purgée(s) ({$scope}).",
                'type' => 'success',
            ]);

        } catch (\Exception $e) {
            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Erreur Redis : " . $e->getMessage(),
                'type' => 'danger',
            ]);
        }
    }
}
