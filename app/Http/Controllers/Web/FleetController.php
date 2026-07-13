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
                    // Règle SOUL : les réponses aux prospects (prospect_reply) se valident
                    // EXCLUSIVEMENT par Jimmy sur Telegram (« OUI <id> ») — on ne les affiche
                    // pas ici pour que le dashboard ne puisse pas court-circuiter ce flux.
                    if ($taskDecoded && ($taskDecoded['type'] ?? '') !== 'prospect_reply') {
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
            // « en ligne » si le dernier battement est récent. Les workers battent toutes
            // les ~5 s dans leur boucle (seuil 120 s) ; le CERVEAU (richard) n'a pas de
            // boucle à nous : son heartbeat est posé par fleet_watchdog à chaque tick de
            // 5 min → seuil élargi à 360 s pour cette seule carte (sinon clignotement).
            $threshold = $agent['dept'] === 'richard' ? 360 : 120;
            $online = false;
            if ($lastSeen) {
                try {
                    $online = \Carbon\Carbon::parse($lastSeen)->gt(now()->subSeconds($threshold));
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

        // 6. Budget LLM temps réel — publié par la passerelle or_gateway (TTL 300 s).
        // Clé absente = passerelle silencieuse > 5 min → null (remonte en warn santé).
        $gateway = null;
        try {
            $rawGw = $fleetRedis->get('fleet:or_gateway:stats');
            if ($rawGw) {
                $gw = json_decode($rawGw, true) ?: [];
                $used = (int) ($gw['used'] ?? 0);
                $budget = max(1, (int) ($gw['budget'] ?? 900));
                $gateway = [
                    'used'       => $used,
                    'budget'     => $budget,
                    'hard'       => (int) ($gw['hard'] ?? 1000),
                    'remaining'  => (int) ($gw['remaining'] ?? max(0, $budget - $used)),
                    'rpm'        => (int) ($gw['rpm'] ?? 0),
                    'rpm_limit'  => (int) ($gw['rpm_limit'] ?? 18),
                    'pct'        => (int) round($used / $budget * 100),
                    'updated_at' => $gw['updated_at'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            \Log::warning('[FleetController] Erreur stats passerelle : ' . $e->getMessage());
        }

        // 7. Vrai backlog par département : XINFO GROUPS → lag (jamais livrées) + pending
        // (livrées non ackées). ⚠️ PAS XLEN : les streams ne sont pas trimmés, XLEN est un
        // cumulatif depuis toujours (le chiffre trompeur « 62 tâches en file »).
        $onlineByDept = [];
        foreach (self::AGENTS as $k => $cfg) {
            $onlineByDept[$cfg['dept']] = $liveAgents[$k]['online'] ?? false;
        }
        $failed7dByDept = [];
        foreach ($allTasks as $t) {
            if (($t['status'] ?? '') === 'failed') {
                $d = $t['dept'] ?? '?';
                $failed7dByDept[$d] = ($failed7dByDept[$d] ?? 0) + 1;
            }
        }
        $queues = [];
        foreach (self::AGENTS as $cfg) {
            $dept = $cfg['dept'];
            if ($dept === 'richard') {
                continue; // le cerveau n'a pas de stream de tâches
            }
            $backlog = null;
            $pending = null;
            try {
                $groups = $fleetRedis->executeRaw(['XINFO', 'GROUPS', "tasks:{$dept}"]);
                if (is_array($groups)) {
                    foreach ($groups as $g) {
                        // predis renvoie chaque groupe en tableau plat [k1, v1, k2, v2, …]
                        $flat = [];
                        $n = is_array($g) ? count($g) : 0;
                        for ($i = 0; $i + 1 < $n; $i += 2) {
                            $flat[(string) $g[$i]] = $g[$i + 1];
                        }
                        $backlog = ($backlog ?? 0) + (int) ($flat['lag'] ?? 0);
                        $pending = ($pending ?? 0) + (int) ($flat['pending'] ?? 0);
                    }
                }
            } catch (\Exception $e) {
                // stream absent / Redis < 7 : backlog inconnu (« ? » à l'affichage), jamais de crash du polling
            }
            $queues[] = [
                'dept'      => $dept,
                'backlog'   => $backlog,
                'pending'   => $pending,
                'failed_7d' => $failed7dByDept[$dept] ?? 0,
                'online'    => $onlineByDept[$dept] ?? false,
            ];
        }
        usort($queues, fn ($a, $b) => (($b['backlog'] ?? -1) + ($b['pending'] ?? 0)) <=> (($a['backlog'] ?? -1) + ($a['pending'] ?? 0)));

        // 8. KPI fiabilité 7 jours — fenêtre naturelle : TTL 7 j des tâches terminées côté flotte.
        $kpiDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $kpiDays[$d] = ['date' => $d, 'done' => 0, 'failed' => 0];
        }
        $typeCounts = [];
        $done7 = 0;
        $failed7 = 0;
        foreach ($allTasks as $t) {
            $d = substr((string) ($t['created_at'] ?? ''), 0, 10);
            $st = $t['status'] ?? '';
            if (isset($kpiDays[$d])) {
                if ($st === 'done') {
                    $kpiDays[$d]['done']++;
                } elseif ($st === 'failed') {
                    $kpiDays[$d]['failed']++;
                }
            }
            if ($st === 'done') {
                $done7++;
            } elseif ($st === 'failed') {
                $failed7++;
            }
            $ty = $t['type'] ?? '?';
            $typeCounts[$ty] = ($typeCounts[$ty] ?? 0) + 1;
        }
        arsort($typeCounts);
        $topTypes = [];
        foreach (array_slice($typeCounts, 0, 5, true) as $ty => $n) {
            $topTypes[] = ['type' => $ty, 'n' => $n];
        }
        $kpi = [
            'days'         => array_values($kpiDays),
            'top_types'    => $topTypes,
            'success_rate' => ($done7 + $failed7) > 0 ? round($done7 / ($done7 + $failed7) * 100, 1) : null,
            'done_7d'      => $done7,
            'failed_7d'    => $failed7,
        ];

        // 9. Dernière synthèse CEO (publiée par richard_ceo_routine.py, TTL 7 j).
        $ceoReport = null;
        try {
            $rawCeo = $fleetRedis->get('fleet:ceo:last_report');
            if ($rawCeo) {
                $ceoReport = json_decode($rawCeo, true) ?: null;
            }
        } catch (\Exception $e) {
            // best effort — le bloc s'affiche « aucun rapport » si absent
        }

        // 9bis. Équipe Growth (Mia/content, Sam/sales, Nora/finance) — onglet dédié :
        // chaque action entreprise (tâches COMPLÈTES, result inclus → détail au clic sans
        // fetch) + agrégats 7 j par agent. Fenêtre naturelle : TTL 7 j des tâches terminées.
        $growthDepts = ['content', 'sales', 'finance'];
        $growthTasks = array_values(array_filter($allTasks, fn ($t) => in_array($t['dept'] ?? '', $growthDepts, true)));
        $growthPerAgent = [];
        foreach ($growthDepts as $gd) {
            $mine = array_values(array_filter($growthTasks, fn ($t) => ($t['dept'] ?? '') === $gd));
            $lastDone = null;
            foreach ($mine as $t) { // $allTasks est déjà trié created_at desc
                if (($t['status'] ?? '') === 'done') {
                    $lastDone = ['id' => $t['id'] ?? '?', 'type' => $t['type'] ?? '?', 'created_at' => $t['created_at'] ?? null];
                    break;
                }
            }
            $growthPerAgent[$gd] = [
                'done_7d'     => count(array_filter($mine, fn ($t) => ($t['status'] ?? '') === 'done')),
                'failed_7d'   => count(array_filter($mine, fn ($t) => ($t['status'] ?? '') === 'failed')),
                'in_progress' => count(array_filter($mine, fn ($t) => ($t['status'] ?? '') === 'in_progress')) > 0,
                'awaiting'    => count(array_filter($mine, fn ($t) => ($t['status'] ?? '') === 'awaiting_approval')),
                'last_done'   => $lastDone,
            ];
        }
        // Funnel CRM = dernier livrable kpi_report de Nora (result complet : kpis + deltas vs veille).
        $noraKpi = null;
        foreach ($growthTasks as $t) { // déjà trié created_at desc
            if (($t['dept'] ?? '') === 'finance' && ($t['type'] ?? '') === 'kpi_report'
                && ($t['status'] ?? '') === 'done' && !empty($t['result']['kpis'])) {
                $noraKpi = [
                    'task_id' => $t['id'] ?? '?',
                    'date'    => $t['result']['date'] ?? null,
                    'kpis'    => $t['result']['kpis'],
                    'deltas'  => $t['result']['deltas'] ?? [],
                ];
                break;
            }
        }

        // Aperçus des contenus Instagram de Mia : les résultats content exposent une image_url
        // publique servie par le media server de la flotte (api2.nana-intelligence.fr/media/…).
        $miaPosts = [];
        foreach ($growthTasks as $t) {
            if (($t['dept'] ?? '') === 'content' && ($t['status'] ?? '') === 'done'
                && !empty($t['result']['image_url'])) {
                $miaPosts[] = [
                    'id'         => $t['id'] ?? '?',
                    'type'       => $t['type'] ?? '?',
                    'image_url'  => $t['result']['image_url'],
                    'post_id'    => $t['result']['post_id'] ?? null,
                    'created_at' => $t['created_at'] ?? null,
                ];
                if (count($miaPosts) >= 8) {
                    break;
                }
            }
        }

        $growth = [
            'tasks'     => array_slice($growthTasks, 0, 40),
            'per_agent' => $growthPerAgent,
            'nora_kpi'  => $noraKpi,
            'mia_posts' => $miaPosts,
        ];

        // 10. Santé consolidée (bandeau haut de page) — calculée sur ce qui précède.
        $offline = [];
        foreach (self::AGENTS as $k => $cfg) {
            if ($cfg['dept'] !== 'richard' && !($liveAgents[$k]['online'] ?? false)) {
                $offline[] = $cfg['dept'];
            }
        }
        $issues = [];
        if ($gateway === null) {
            $issues[] = 'Passerelle LLM silencieuse (aucune stat depuis > 5 min)';
        }
        if ($offline) {
            $issues[] = 'Workers hors ligne : ' . implode(', ', $offline);
        }
        if (!($liveAgents['richard']['online'] ?? false)) {
            $issues[] = 'Cerveau (hermes-gateway) sans heartbeat récent';
        }
        if (($josephStatus['status'] ?? 'healthy') !== 'healthy') {
            $issues[] = 'Joseph signale : ' . ($josephStatus['status'] ?? '?');
        }
        if ($josephStale) {
            $issues[] = 'Diagnostic Joseph périmé (> 24 h)';
        }
        if ($julietteStale) {
            $issues[] = 'Statut Juliette périmé (> 30 min)';
        }
        if ($gateway && $gateway['pct'] >= 90) {
            $issues[] = "Budget LLM à {$gateway['pct']} % du quota du jour";
        }
        if ($gateway === null || count($offline) >= 2 || (($josephStatus['status'] ?? 'healthy') !== 'healthy')) {
            $healthLevel = 'crit';
        } elseif ($issues) {
            $healthLevel = 'warn';
        } else {
            $healthLevel = 'ok';
        }

        // Payload « live » compact (seed + polling), forme stable
        $live = [
            'health'          => ['level' => $healthLevel, 'issues' => $issues],
            'gateway'         => $gateway,
            'queues'          => $queues,
            'kpi'             => $kpi,
            'ceo_report'      => $ceoReport,
            'growth'          => $growth,
            // Approbations dans le payload live : le bloc « en attente de validation » se met
            // à jour au polling (avant : rendu serveur uniquement, F5 obligatoire).
            'approvals'       => array_map(fn ($a) => [
                'id'         => $a['id'] ?? '?',
                'dept'       => $a['dept'] ?? '?',
                'type'       => $a['type'] ?? '?',
                'label'      => $a['approval']['action_spec']['label'] ?? null,
                'created_at' => $a['created_at'] ?? null,
            ], $pendingApprovals),
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
                // Tracking d'ouverture (calculé depuis les activités CRM, pas Redis).
                'tracking'          => $this->collectJulietteTracking(),
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

    /**
     * Statistiques d'ouverture des cold emails de Juliette, calculées depuis les activités CRM
     * (Postgres) — indépendant de Redis. Alimente les KPI, le graphe et la table « par objet »
     * de l'onglet monitoring. Les ouvertures robots/proxies sont exclues du taux « humain ».
     */
    private function collectJulietteTracking(): array
    {
        $base = fn () => \App\Models\Activity::where('source', 'juliette');

        $sent        = (clone $base())->where('type', \App\Models\Activity::TYPE_EMAIL_SENT)->count();
        $opened      = (clone $base())->where('type', \App\Models\Activity::TYPE_EMAIL_OPENED)->count();
        $humanOpened = (clone $base())->where('type', \App\Models\Activity::TYPE_EMAIL_OPENED)
            ->whereRaw("(metadata->>'is_bot')::boolean IS NOT TRUE")->count();

        $openRate      = $sent > 0 ? round($opened / $sent * 100, 1) : 0.0;
        $humanOpenRate = $sent > 0 ? round($humanOpened / $sent * 100, 1) : 0.0;

        // ── Par objet : envoyés vs ouverts (humains) — boucle de feedback pour la rédaction ──
        $sentBySubject = (clone $base())->where('type', \App\Models\Activity::TYPE_EMAIL_SENT)
            ->selectRaw("COALESCE(NULLIF(metadata->>'subject',''),'(sans objet)') as subject, "
                . "COALESCE(metadata->>'step','?') as step, count(*) as n")
            ->groupByRaw('1, 2')->get();

        $openedBySubject = (clone $base())->where('type', \App\Models\Activity::TYPE_EMAIL_OPENED)
            ->whereRaw("(metadata->>'is_bot')::boolean IS NOT TRUE")
            ->selectRaw("COALESCE(NULLIF(metadata->>'subject',''),'(sans objet)') as subject, count(*) as n")
            ->groupByRaw('1')->get()->keyBy('subject');

        $bySubject = $sentBySubject->map(function ($r) use ($openedBySubject) {
            $n = (int) $r->n;
            $o = (int) ($openedBySubject[$r->subject]->n ?? 0);

            return [
                'subject' => $r->subject,
                'step'    => $r->step,
                'sent'    => $n,
                'opened'  => $o,
                'rate'    => $n > 0 ? round($o / $n * 100, 1) : 0.0,
            ];
        })->sortByDesc('sent')->values()->take(15)->all();

        // ── Timeline 14 jours : envois vs ouvertures humaines par jour ──
        $since      = now()->subDays(13)->startOfDay();
        $sentByDay  = (clone $base())->where('type', \App\Models\Activity::TYPE_EMAIL_SENT)
            ->where('occurred_at', '>=', $since)
            ->selectRaw('occurred_at::date as d, count(*) as n')->groupByRaw('1')->pluck('n', 'd');
        $openByDay  = (clone $base())->where('type', \App\Models\Activity::TYPE_EMAIL_OPENED)
            ->whereRaw("(metadata->>'is_bot')::boolean IS NOT TRUE")
            ->where('occurred_at', '>=', $since)
            ->selectRaw('occurred_at::date as d, count(*) as n')->groupByRaw('1')->pluck('n', 'd');

        $timeline = [];
        for ($i = 0; $i < 14; $i++) {
            $day = now()->subDays(13 - $i)->toDateString();
            $timeline[] = [
                'date'   => $day,
                'sent'   => (int) ($sentByDay[$day] ?? 0),
                'opened' => (int) ($openByDay[$day] ?? 0),
            ];
        }

        return [
            'sent'            => $sent,
            'opened'          => $opened,
            'human_opened'    => $humanOpened,
            'bot_opened'      => max(0, $opened - $humanOpened),
            'open_rate'       => $openRate,
            'human_open_rate' => $humanOpenRate,
            'by_subject'      => $bySubject,
            'timeline'        => $timeline,
        ];
    }

    /**
     * Drill-down agent : les 20 dernières tâches d'un département + mini-stats.
     * Fetch à la demande depuis le panneau des cartes agents (HORS polling 12 s).
     */
    public function agentTasks(Request $request, string $dept)
    {
        if (!in_array($dept, array_column(self::AGENTS, 'dept'), true)) {
            return response()->json(['error' => 'département inconnu'], 404);
        }

        $fleetRedis = Redis::connection('fleet');
        $tasks = [];
        try {
            foreach ($fleetRedis->keys('task:*') ?: [] as $key) {
                $cleanKey = str_replace(config('database.redis.options.prefix', ''), '', $key);
                $t = json_decode($fleetRedis->get($cleanKey) ?: 'null', true);
                if ($t && ($t['dept'] ?? null) === $dept) {
                    $tasks[] = $t;
                }
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Redis : ' . $e->getMessage()], 500);
        }
        usort($tasks, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $done = count(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'done'));
        $failed = count(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'failed'));

        return response()->json([
            'dept'  => $dept,
            'stats' => [
                'total_7d'     => count($tasks),
                'done'         => $done,
                'failed'       => $failed,
                'success_rate' => ($done + $failed) > 0 ? round($done / ($done + $failed) * 100, 1) : null,
            ],
            'tasks' => array_slice($tasks, 0, 20),
        ]);
    }

    /**
     * Code lettre du département — miroir exact de bus.DEPT_CODE côté flotte
     * (Richard/scripts/bus.py) pour des IDs courts partagés (#G3, #W7…).
     */
    private function deptCode(string $dept): string
    {
        // Doit couvrir TOUT bus.DEPT_CODE (pas seulement les depts déclenchables depuis
        // l'UI) : approveTask peut porter sur n'importe quel département — echo/charles
        // inclus (constaté : execute d'un parent E* créée en T* faute de mapping).
        return match ($dept) {
            'finance', 'sales', 'content' => 'G',
            'acquisition' => 'A',
            'web-lead', 'seo', 'devops', 'watch', 'cro', 'linking', 'qa' => 'W',
            'echo' => 'E',
            'charles' => 'C',
            'maintenance' => 'M',
            default => 'T',
        };
    }

    /**
     * ID de tâche court séquentiel — miroir de bus.new_task_id (INCR seq:<code>,
     * même clé Redis que la flotte : aucune collision possible).
     */
    private function newTaskId($fleetRedis, string $dept): string
    {
        $n = $fleetRedis->incr('seq:' . $this->deptCode($dept));
        return $this->deptCode($dept) . $n;
    }

    /**
     * Persiste une tâche et l'empile dans le stream de son département —
     * miroir de bus.save_task + XADD {id} (seul champ lu par les workers).
     */
    private function pushTask($fleetRedis, array $task): void
    {
        $fleetRedis->set("task:{$task['id']}", json_encode($task, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $fleetRedis->executeRaw(['XADD', "tasks:{$task['dept']}", '*', 'id', $task['id']]);
    }

    public function triggerAction(Request $request)
    {
        // NB : « richard » retiré du in: — le dept `richard` n'a AUCUN worker ; une tâche
        // y serait queued pour toujours (la garde anti-département-fantôme de bus.dispatch
        // ne s'applique pas ici puisque le CRM fabrique la tâche lui-même).
        $data = $request->validate([
            'agent' => 'required|string|in:sam,nora,mia,juliette,siteweb,lea,alex,vera,caro,lin,max,joseph',
            'action_type' => 'required|string',
        ]);

        $agentKey = $data['agent'];
        $actionType = $data['action_type'];
        $agentConfig = self::AGENTS[$agentKey];
        $dept = $agentConfig['dept'];

        try {
            $fleetRedis = Redis::connection('fleet');

            // ID court séquentiel partagé avec la flotte (helper, miroir de bus.new_task_id)
            $taskId = $this->newTaskId($fleetRedis, $dept);

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

            // Persistance + empilement stream (helper, miroir de bus.save_task + XADD)
            $this->pushTask($fleetRedis, $task);

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

    /**
     * Approuve une tâche en attente — MIROIR EXACT de bus.approve (Richard/scripts/bus.py).
     *
     * ⚠️ Contrat de la flotte : le worker n'exécute une action staged QUE via une tâche
     * enfant `type=execute` portant `payload.approved_task` + `payload.action_spec`.
     * Ré-empiler la tâche PARENT dans le stream (ancien comportement) faisait re-PRÉPARER
     * l'action en boucle (nouveau plan → nouveau awaiting_approval), sans jamais l'exécuter.
     */
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

            // Garde (règle SOUL) : une réponse à un prospect se valide EXCLUSIVEMENT par
            // Jimmy sur Telegram (« OUI <id> ») — jamais depuis le dashboard.
            if (($task['type'] ?? '') === 'prospect_reply') {
                return redirect()->route('fleet.index')->with('flash_toast', [
                    'message' => "Tâche {$taskId} : les réponses aux prospects se valident uniquement sur Telegram (OUI {$taskId}).",
                    'type' => 'danger',
                ]);
            }

            // 2. Marquer approuvée + retirer de la file (LREM count 0 = toutes occurrences, comme bus.approve)
            $task['status'] = 'approved';
            $task['approval']['status'] = 'approved';
            $fleetRedis->set("task:{$taskId}", json_encode($task, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $fleetRedis->lrem('approvals:pending', 0, $taskId);

            // 3. Tâche d'exécution enfant portant l'action staged (contrat bus.approve)
            $execPayload = [
                'approved_task' => $taskId,
                'action_spec'   => ($task['approval']['action_spec'] ?? null) ?: [],
            ];
            if (!empty($task['payload']['session_key'])) {
                // Fil de conversation stable prepare -> execute (cf. bus.approve)
                $execPayload['session_key'] = $task['payload']['session_key'];
            }

            $execTask = [
                'id' => $this->newTaskId($fleetRedis, $task['dept']),
                'squad' => $task['squad'] ?? 'growth',
                'dept' => $task['dept'],
                'type' => 'execute',
                'payload' => $execPayload,
                'status' => 'queued',
                'requested_by' => 'jimmy_crm',
                'parent_task' => $taskId,
                'created_at' => now()->toIso8601String(),
                'result' => null,
                'approval' => [
                    'required' => false,
                    'status' => 'none',
                    'action_spec' => null,
                ],
            ];
            $this->pushTask($fleetRedis, $execTask);
            // NB : pas de re-XADD du parent ; le worker met à jour la tâche parente
            // à la complétion de l'execute (mécanique existante côté flotte).

            return redirect()->route('fleet.index')->with('flash_toast', [
                'message' => "Tâche {$taskId} approuvée — exécution dispatchée ({$execTask['id']}).",
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

            // Garde (règle SOUL) : le sort d'une réponse prospect se décide sur Telegram
            // (« OUI/NON <id> »), pas depuis le dashboard.
            if (($task['type'] ?? '') === 'prospect_reply') {
                return redirect()->route('fleet.index')->with('flash_toast', [
                    'message' => "Tâche {$taskId} : les réponses aux prospects se traitent uniquement sur Telegram (NON {$taskId}).",
                    'type' => 'danger',
                ]);
            }

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
