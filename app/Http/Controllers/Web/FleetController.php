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
    ];

    public function index(Request $request)
    {
        $fleetRedis = Redis::connection('fleet');
        
        // 1. Récupérer toutes les clés task:*
        $taskKeys = [];
        try {
            $taskKeys = $fleetRedis->keys('task:*') ?: [];
        } catch (\Exception $e) {
            \Log::warning('[FleetController] Erreur de lecture des clés Redis : ' . $e->getMessage());
        }

        $tasks = [];
        foreach ($taskKeys as $key) {
            // Sous certains drivers phpredis, les clés ont parfois le préfixe de la base
            $cleanKey = str_replace(config('database.redis.options.prefix', ''), '', $key);
            $raw = $fleetRedis->get($cleanKey);
            if ($raw) {
                $taskDecoded = json_decode($raw, true);
                if ($taskDecoded) {
                    $tasks[] = $taskDecoded;
                }
            }
        }

        // Trier les tâches par date de création décroissante
        usort($tasks, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        // Limiter à 30 tâches
        $tasks = array_slice($tasks, 0, 30);

        // 2. Récupérer les tâches de la queue d'approbation (approvals:pending)
        $pendingApprovals = [];
        try {
            // approvals:pending est stocké soit comme une liste Redis (LRANGE)
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

        // 3. Déduire le statut de santé des agents en fonction des tâches actives
        $agents = self::AGENTS;
        foreach ($agents as $key => &$agent) {
            $agent['status'] = 'idle'; // par défaut : prêt / en veille
            
            // Si l'agent a une tâche in_progress, il est actif
            foreach ($tasks as $t) {
                if (($t['dept'] === $agent['dept']) && ($t['status'] === 'in_progress')) {
                    $agent['status'] = 'active';
                    break;
                }
            }
        }

        // 4. Récupérer l'activité récente de la flotte à partir des logs d'audit
        $auditLogs = AuditLog::with('user')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return view('pages.fleet.index', compact('agents', 'tasks', 'pendingApprovals', 'auditLogs'));
    }

    public function triggerAction(Request $request)
    {
        $data = $request->validate([
            'agent' => 'required|string|in:richard,sam,nora,mia,juliette,siteweb,lea,alex,vera,caro,lin,max',
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

            // 4. Pousser dans le Stream tasks:<dept> via XADD
            $fleetRedis->xadd("tasks:{$dept}", '*', ['id' => $taskId]);

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

            // 4. Republier le résultat ou relancer l'agent (XADD dans tasks:<dept>)
            $fleetRedis->xadd("tasks:{$task['dept']}", '*', ['id' => $taskId]);

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
}
