<x-app-shell active="fleet" breadcrumb="Monitoring de la Flotte">

<style>
    /* Styles spécifiques pour le Dashboard de la Flotte */
    .agent-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        transition: all 0.2s ease-in-out;
        position: relative;
        overflow: hidden;
    }
    .agent-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        border-color: var(--accent);
    }
    .led {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .led.green {
        background-color: var(--ok);
        box-shadow: 0 0 8px var(--ok);
    }
    .led.pulse {
        background-color: var(--accent);
        box-shadow: 0 0 10px var(--accent);
        animation: led-pulse 1.5s infinite alternate;
    }
    .led.off {
        background-color: var(--text3);
        box-shadow: none;
    }
    @keyframes led-pulse {
        0% { opacity: 0.4; }
        100% { opacity: 1; transform: scale(1.1); }
    }
</style>

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Monitoring de la Flotte</h1>
        <p class="text-sm text-secondary mt-0.5">Pilotez et supervisez vos agents virtuels autonomes connectés au CRM</p>
    </div>
</div>

<div x-data="fleetDashboard(@js($live))" class="col-span-12">

    <!-- ─── BANDEAU DE STATS LIVE ─── -->
    <div class="px-7 mb-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="card p-3 flex flex-col gap-0.5" style="border-color: var(--border);">
            <span class="text-[10px] font-mono uppercase tracking-wider text-tertiary">Tâches (total)</span>
            <span class="text-xl font-bold text-primary" x-text="live.stats.total"></span>
        </div>
        <div class="card p-3 flex flex-col gap-0.5" style="border-color: var(--border);">
            <span class="text-[10px] font-mono uppercase tracking-wider text-tertiary">En file</span>
            <span class="text-xl font-bold text-primary" x-text="live.stats.queued"></span>
        </div>
        <div class="card p-3 flex flex-col gap-0.5" style="border-left: 3px solid var(--accent);">
            <span class="text-[10px] font-mono uppercase tracking-wider text-tertiary">En cours</span>
            <span class="text-xl font-bold" style="color: var(--accent);" x-text="live.stats.in_progress"></span>
        </div>
        <div class="card p-3 flex flex-col gap-0.5" style="border-left: 3px solid var(--ok);">
            <span class="text-[10px] font-mono uppercase tracking-wider text-tertiary">Terminées</span>
            <span class="text-xl font-bold" style="color: var(--ok);" x-text="live.stats.done"></span>
        </div>
        <div class="card p-3 flex flex-col gap-0.5" :style="live.stats.failed > 0 ? 'border-left: 3px solid var(--err);' : 'border-color: var(--border);'">
            <span class="text-[10px] font-mono uppercase tracking-wider text-tertiary">Échecs</span>
            <span class="text-xl font-bold" :style="live.stats.failed > 0 ? 'color: var(--err);' : 'color: var(--text3);'" x-text="live.stats.failed"></span>
        </div>
        <div class="card p-3 flex flex-col gap-0.5" :style="live.stats.pending_approvals > 0 ? 'border-left: 3px solid var(--warn);' : 'border-color: var(--border);'">
            <span class="text-[10px] font-mono uppercase tracking-wider text-tertiary">À valider</span>
            <span class="text-xl font-bold" :style="live.stats.pending_approvals > 0 ? 'color: var(--warn);' : 'color: var(--text3);'" x-text="live.stats.pending_approvals"></span>
        </div>
    </div>

    <!-- Onglets de navigation -->
    <div class="px-7 mb-6 flex gap-6 border-b border-default items-center">
        <button @click="activeTab = 'fleet'" :class="activeTab === 'fleet' ? 'border-b-2 border-accent text-primary font-bold' : 'text-secondary'" class="pb-3 text-sm font-semibold transition-all relative">
            👥 Flotte Opérationnelle
        </button>
        <button @click="activeTab = 'joseph'" :class="activeTab === 'joseph' ? 'border-b-2 border-accent text-primary font-bold' : 'text-secondary'" class="pb-3 text-sm font-semibold transition-all flex items-center gap-2">
            🛡️ Infrastructure & Maintenance (Joseph)
            <span class="led pulse" x-show="live.joseph.status !== 'healthy' || live.joseph.stale" style="background-color: var(--warn); box-shadow: 0 0 8px var(--warn); width: 6px; height: 6px;" x-cloak></span>
        </button>
        <!-- Indicateur de rafraîchissement live -->
        <span class="ml-auto flex items-center gap-1.5 text-[10px] font-mono text-tertiary pb-3" title="Rafraîchissement automatique toutes les 12s">
            <span class="led green" style="width:6px;height:6px;"></span>
            <span x-text="'MAJ ' + fmtDate(live.generated_at)"></span>
        </span>
    </div>

    <!-- Conteneur Flotte Opérationnelle -->
    <div x-show="activeTab === 'fleet'" class="px-7 pb-12 grid grid-cols-12 gap-6">

    <!-- ─── BLOC 1 : CARTES DES AGENTS DE CROISSANCE (GROWTH & ACQUISITION) ─── -->
    <div class="col-span-12 flex flex-col gap-3">
        <div class="mono-label">Équipe Croissance & Acquisition (Growth / Acquisition)</div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach(collect($agents)->filter(fn($a) => $a['squad'] !== 'web') as $key => $agent)
                <div class="agent-card p-5 flex flex-col justify-between h-full">
                    <div>
                        <!-- En-tête : Avatar + LED statut -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="av sm text-white font-bold" style="background: color-mix(in srgb, var(--accent) 70%, #000);">
                                    {{ strtoupper(substr($agent['name'], 0, 2)) }}
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold m-0" style="margin:0;">{{ $agent['name'] }}</h3>
                                    <span class="text-[9px] text-tertiary font-mono uppercase tracking-wider">{{ $agent['squad'] }} squad</span>
                                    <template x-if="live.agents['{{ $key }}'] && live.agents['{{ $key }}'].has_heartbeat">
                                        <span class="block text-[8px] font-mono mt-0.5" :class="live.agents['{{ $key }}'].online ? 'text-emerald-500' : 'text-tertiary'"
                                              :title="'Dernier battement : ' + fmtDate(live.agents['{{ $key }}'].last_seen)"
                                              x-text="live.agents['{{ $key }}'].online ? '● worker en ligne' : '○ worker hors-ligne'"></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Voyant LED d'activité (live) -->
                            <span class="led"
                                  :class="(live.agents['{{ $key }}'] && live.agents['{{ $key }}'].status === 'active') ? 'pulse' : 'green'"
                                  :title="(live.agents['{{ $key }}'] && live.agents['{{ $key }}'].status === 'active') ? 'Actif — tâche en cours de traitement' : 'En veille — prêt à agir'"></span>
                        </div>

                        <!-- Rôle & Description -->
                        <div class="mb-4">
                            <div class="text-[11px] font-bold" style="color: var(--accent);">{{ $agent['role'] }}</div>
                            <p class="text-xs text-secondary mt-1 leading-relaxed" style="margin: 4px 0 0 0;">{{ $agent['description'] }}</p>
                        </div>
                    </div>

                    <!-- Boutons d'actions rapides -->
                    <div class="mt-4 pt-3 border-t border-default flex flex-col gap-1.5">
                        <form method="POST" action="{{ route('fleet.trigger') }}">
                            @csrf
                            <input type="hidden" name="agent" value="{{ $key }}">
                            
                            @if($key === 'richard')
                                <input type="hidden" name="action_type" value="ceo_routine">
                                <button type="submit" class="btn primary sm w-full justify-center text-xs">
                                    ⚡ Lancer la routine CEO
                                </button>
                            @elseif($key === 'nora')
                                <input type="hidden" name="action_type" value="kpi_report">
                                <button type="submit" class="btn primary sm w-full justify-center text-xs">
                                    ⚡ Générer Rapport KPI
                                </button>
                            @elseif($key === 'sam')
                                <input type="hidden" name="action_type" value="inbox_sync">
                                <button type="submit" class="btn sm w-full justify-center text-xs" style="background: var(--surface2); border-color: var(--border);">
                                    🔄 Scanner DMs
                                </button>
                            @elseif($key === 'mia')
                                <input type="hidden" name="action_type" value="generate_content">
                                <button type="submit" class="btn sm w-full justify-center text-xs" style="background: var(--surface2); border-color: var(--border);">
                                    🎨 Créer un post
                                </button>
                            @elseif($key === 'juliette')
                                <input type="hidden" name="action_type" value="inbox_sync">
                                <button type="submit" class="btn primary sm w-full justify-center text-xs">
                                    ⚡ Sync Campaign & Inbox
                                </button>
                            @endif
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ─── BLOC 1.5 : CARTES DES AGENTS DE L'EQUIPE WEB ─── -->
    <div class="col-span-12 flex flex-col gap-3">
        <div class="mono-label">Équipe Web (Site internet nana-intelligence.fr & SEO)</div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            @foreach(collect($agents)->filter(fn($a) => $a['squad'] === 'web') as $key => $agent)
                <div class="agent-card p-4 flex flex-col justify-between h-full" style="padding: 14px;">
                    <div>
                        <!-- En-tête : Avatar + LED statut -->
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-1.5">
                                <span class="av sm text-white font-bold" style="background: color-mix(in srgb, var(--accent) 70%, #000); width: 28px; height: 28px; font-size: 10px;">
                                    {{ strtoupper(substr($agent['name'], 0, 2)) }}
                                </span>
                                <div>
                                    <h3 class="text-xs font-semibold m-0" style="margin:0;">{{ $agent['name'] }}</h3>
                                    <span class="text-[9px] text-tertiary font-mono truncate max-w-[80px]" title="{{ $agent['role'] }}">{{ $agent['name'] === 'SiteWeb' ? 'Lead' : $agent['dept'] }}</span>
                                </div>
                            </div>
                            
                            <!-- Heartbeat worker + Voyant LED d'activité (live) -->
                            <div class="flex items-center gap-1.5">
                                <template x-if="live.agents['{{ $key }}'] && live.agents['{{ $key }}'].has_heartbeat">
                                    <span class="rounded-full inline-block" style="width:6px;height:6px;"
                                          :class="live.agents['{{ $key }}'].online ? 'bg-emerald-500' : 'bg-slate-500'"
                                          :title="(live.agents['{{ $key }}'].online ? 'Worker en ligne' : 'Worker hors-ligne') + ' — ' + fmtDate(live.agents['{{ $key }}'].last_seen)"></span>
                                </template>
                                <span class="led"
                                      :class="(live.agents['{{ $key }}'] && live.agents['{{ $key }}'].status === 'active') ? 'pulse' : 'green'"
                                      :title="(live.agents['{{ $key }}'] && live.agents['{{ $key }}'].status === 'active') ? 'Actif — audit en cours' : 'En veille — prêt à auditer'"></span>
                            </div>
                        </div>

                        <!-- Rôle & Description -->
                        <div class="mb-3">
                            <div class="text-[9.5px] font-bold" style="color: var(--accent);">{{ $agent['role'] }}</div>
                            <p class="text-[11px] text-secondary mt-1 leading-normal" style="margin: 2px 0 0 0;">{{ $agent['description'] }}</p>
                        </div>
                    </div>

                    <!-- Boutons d'actions rapides -->
                    <div class="mt-3 pt-2 border-t border-default flex flex-col gap-1">
                        <form method="POST" action="{{ route('fleet.trigger') }}">
                            @csrf
                            <input type="hidden" name="agent" value="{{ $key }}">
                            
                            @if($key === 'siteweb')
                                <input type="hidden" name="action_type" value="web_quality_audit">
                                <button type="submit" class="btn primary sm w-full justify-center text-[10.5px]" style="padding: 3px 6px;">
                                    ⚡ Audit global
                                </button>
                            @elseif($key === 'lea')
                                <input type="hidden" name="action_type" value="seo_audit">
                                <button type="submit" class="btn sm w-full justify-center text-[10.5px]" style="background: var(--surface2); border-color: var(--border); padding: 3px 6px;">
                                    🔍 Audit SEO
                                </button>
                            @elseif($key === 'alex')
                                <input type="hidden" name="action_type" value="deploy_build">
                                <button type="submit" class="btn sm w-full justify-center text-[10.5px]" style="background: var(--surface2); border-color: var(--border); padding: 3px 6px;">
                                    🚀 Build/Deploy
                                </button>
                            @elseif($key === 'vera')
                                <input type="hidden" name="action_type" value="competitor_scan">
                                <button type="submit" class="btn sm w-full justify-center text-[10.5px]" style="background: var(--surface2); border-color: var(--border); padding: 3px 6px;">
                                    🌐 Scan Conc.
                                </button>
                            @elseif($key === 'caro')
                                <input type="hidden" name="action_type" value="conversion_audit">
                                <button type="submit" class="btn sm w-full justify-center text-[10.5px]" style="background: var(--surface2); border-color: var(--border); padding: 3px 6px;">
                                    🎯 Audit CRO
                                </button>
                            @elseif($key === 'lin')
                                <input type="hidden" name="action_type" value="backlink_check">
                                <button type="submit" class="btn sm w-full justify-center text-[10.5px]" style="background: var(--surface2); border-color: var(--border); padding: 3px 6px;">
                                    🔗 Backlinks
                                </button>
                            @elseif($key === 'max')
                                <input type="hidden" name="action_type" value="non_regression_test">
                                <button type="submit" class="btn sm w-full justify-center text-[10.5px]" style="background: var(--surface2); border-color: var(--border); padding: 3px 6px;">
                                    🧪 Tests QA
                                </button>
                            @endif
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ─── BLOC 2 : VALIDAIONS DE TÂCHES (APPROVALS PENDING) ─── -->
    @if(count($pendingApprovals) > 0)
        <div class="col-span-12">
            <div class="card p-4 border-l-4" style="border-left-color: var(--warn); background: color-mix(in srgb, var(--warn) 4%, var(--surface));">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-base">⚠️</span>
                    <h2 class="text-sm font-bold uppercase tracking-wider font-mono m-0" style="margin:0; color: var(--warn);">Tâches en attente de votre validation ({{ count($pendingApprovals) }})</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($pendingApprovals as $app)
                        <div class="card p-4" style="background: var(--surface2); border-color: var(--border);">
                            <div class="flex items-center justify-between mb-2">
                                <span class="chip warn font-mono text-[9px] uppercase">Awaiting Jimmy Approval</span>
                                <span class="font-mono text-tertiary text-xs">{{ $app['id'] }}</span>
                            </div>
                            <div class="text-xs text-primary font-medium">
                                Agent <strong>{{ ucfirst($app['dept']) }}</strong> requis pour la tâche <strong>{{ $app['type'] }}</strong>
                            </div>
                            
                            @if(isset($app['payload']) && count($app['payload']) > 0)
                                <div class="mt-2 p-2 bg-surface1 rounded font-mono text-[10px] text-secondary">
                                    <strong>Payload :</strong> {{ json_encode($app['payload'], JSON_UNESCAPED_UNICODE) }}
                                </div>
                            @endif

                            <div class="mt-4 flex gap-2">
                                <form method="POST" action="{{ route('fleet.approve', $app['id']) }}">
                                    @csrf
                                    <button type="submit" class="btn primary sm">Approuver et envoyer ✓</button>
                                </form>
                                <form method="POST" action="{{ route('fleet.reject', $app['id']) }}" onsubmit="return confirm('Rejeter la tâche {{ $app['id'] }} ? Elle ne sera pas exécutée.');">
                                    @csrf
                                    <button type="submit" class="btn sm" style="background: var(--surface1); border-color: var(--border); color: var(--err);">Rejeter ✕</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- ─── BLOC 3 : LE BUS DE TÂCHES REDIS (30 DERNIÈRES) ─── -->
    <div class="col-span-12 lg:col-span-8 flex flex-col gap-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="mono-label">Bus de tâches Redis (Stream de la Flotte)</div>
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Filtres de statut -->
                <div class="flex items-center gap-1 flex-wrap">
                    <template x-for="f in ['all','queued','in_progress','done','failed']" :key="f">
                        <button @click="taskFilter = f"
                                class="chip font-mono text-[9px] uppercase tracking-wider transition-all"
                                :class="taskFilter === f ? 'accent' : 'bg-surface1 text-tertiary'"
                                style="cursor:pointer;"
                                x-text="f === 'all' ? 'Tout' : f.replace('_',' ')"></button>
                    </template>
                </div>
                <!-- Purge des tâches terminées -->
                <form method="POST" action="{{ route('fleet.purge') }}" onsubmit="return confirm('Purger toutes les tâches terminées (done + failed) du bus ?\n\nLes tâches en attente de validation sont conservées. Action irréversible.');">
                    @csrf
                    <input type="hidden" name="scope" value="terminated">
                    <button type="submit" class="chip font-mono text-[9px] uppercase tracking-wider" style="cursor:pointer; background: var(--surface1); color: var(--err);" title="Supprimer du bus les tâches done + failed">🗑 Purger terminées</button>
                </form>
            </div>
        </div>
        <div class="card p-0 overflow-hidden" style="border-color: var(--border);">
            <div class="overflow-x-auto">
                <table class="table-default w-full border-collapse text-left text-xs">
                    <thead>
                        <tr style="background: var(--surface2); border-bottom: 1px solid var(--border);">
                            <th class="p-3 font-semibold text-secondary" style="width: 70px;">Task ID</th>
                            <th class="p-3 font-semibold text-secondary" style="width: 100px;">Département</th>
                            <th class="p-3 font-semibold text-secondary" style="width: 120px;">Action / Type</th>
                            <th class="p-3 font-semibold text-secondary" style="width: 100px;">Statut</th>
                            <th class="p-3 font-semibold text-secondary">Payload / Paramètres</th>
                            <th class="p-3 font-semibold text-secondary" style="width: 100px;">Lancée le</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-default">
                        <template x-for="t in filteredTasks()" :key="t.id">
                            <tr class="hover:bg-surface2 transition-colors" style="cursor:pointer;" @click="selectedTask = t" title="Voir le détail">
                                <td class="p-3 font-mono font-bold text-primary" x-text="t.id"></td>
                                <td class="p-3">
                                    <span class="chip font-mono text-[9px] uppercase tracking-wider bg-surface1" x-text="t.dept"></span>
                                </td>
                                <td class="p-3 font-mono text-secondary text-[11px]" x-text="t.type"></td>
                                <td class="p-3">
                                    <span class="chip font-mono text-[9px] uppercase tracking-wider" :class="statusChip(t.status || 'queued')" x-text="t.status || 'queued'"></span>
                                </td>
                                <td class="p-3 font-mono text-[10px] text-tertiary truncate max-w-[240px]" :title="JSON.stringify(t.payload || {})" x-text="JSON.stringify(t.payload || {})"></td>
                                <td class="p-3 font-mono text-tertiary whitespace-nowrap text-[10.5px]" x-text="fmtDate(t.created_at)"></td>
                            </tr>
                        </template>
                        <tr x-show="filteredTasks().length === 0">
                            <td colspan="6" class="p-8 text-center text-tertiary">
                                📭 Aucune tâche <span x-show="taskFilter !== 'all'" x-text="'(' + taskFilter.replace('_',' ') + ')'"></span> dans le bus Redis de la flotte.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-[10px] text-tertiary font-mono px-1">💡 Clique sur une tâche pour voir son détail (payload + résultat/erreur).</div>
    </div>

    <!-- ─── BLOC 4 : ACTIONS D'AUDIT COMPLEMENTAIRES (HISTORIQUE) ─── -->
    <div class="col-span-12 lg:col-span-4 flex flex-col gap-3">
        <div class="mono-label">Derniers impacts en base CRM</div>
        <div class="card p-4 flex flex-col gap-4" style="border-color: var(--border);">
            <div class="relative pl-6 border-l border-default space-y-4">
                @forelse($auditLogs as $log)
                    @php
                        $actionLabel = match($log->event) {
                            'created'     => 'Créé',
                            'updated'     => 'Modifié',
                            'deleted'     => 'Supprimé',
                            'associated'  => 'Associé',
                            'dissociated' => 'Dissocié',
                            default       => ucfirst($log->event),
                        };
                        $actionColor = match($log->event) {
                            'created'     => 'var(--ok)',
                            'updated'     => 'var(--accent)',
                            'deleted'     => 'var(--err)',
                            'associated'  => 'var(--info)',
                            'dissociated' => 'var(--warn)',
                            default       => 'var(--text-tertiary)',
                        };
                        $userName = $log->user?->name ?? 'Système';
                    @endphp
                    <div class="relative text-xs">
                        <div class="absolute -left-[30px] top-0.5 w-2 h-2 rounded-full border-2 bg-surface" style="border-color: {{ $actionColor }};"></div>
                        
                        <div class="flex items-center justify-between text-[10px] text-secondary mb-1">
                            <div>
                                <span class="font-bold text-primary">{{ $userName }}</span> 
                                <span class="px-1 py-0.2 rounded text-[8px] uppercase tracking-wider font-mono" style="background: color-mix(in srgb, {{ $actionColor }} 12%, transparent); color: {{ $actionColor }}; font-weight: 600;">
                                    {{ $actionLabel }}
                                </span>
                            </div>
                            <span class="font-mono text-tertiary">{{ $log->created_at->format('H:i') }}</span>
                        </div>
                        <div class="text-[11px] text-secondary font-medium">
                            {{ basename(str_replace('\\', '/', $log->auditable_type)) }} #{{ $log->auditable_id }}
                        </div>
                    </div>
                @empty
                    <div class="text-center text-tertiary italic text-xs py-4">Aucune action d'audit récente.</div>
                @endforelse
            </div>
            
            <div class="pt-2 border-t border-default text-center">
                <a href="{{ route('audit.index') }}" class="text-[11px] font-mono font-semibold hover:underline" style="color: var(--accent);">
                    Visualiser tout le journal d'audit ➔
                </a>
            </div>
        </div>
    </div>

    </div>

    <!-- Conteneur Joseph (Maintenance & Infrastructure) -->
    <div x-show="activeTab === 'joseph'" class="px-7 pb-12 grid grid-cols-12 gap-6" x-cloak>
        <!-- Colonne Gauche : Carte Joseph & Métriques VPS -->
        <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
            <!-- Carte Joseph -->
            <div class="agent-card p-5 flex flex-col justify-between" style="background: var(--surface);">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="av sm text-white font-bold" style="background: #b91c1c; width: 36px; height: 36px;">
                                JO
                            </span>
                            <div>
                                <h3 class="text-sm font-bold m-0">{{ $agents['joseph']['name'] }}</h3>
                                <span class="text-[9px] text-tertiary font-mono uppercase tracking-wider">maintenance squad</span>
                            </div>
                        </div>
                        <span class="led" :class="(live.joseph.status === 'healthy' && !live.joseph.stale) ? 'green' : 'pulse'" title="Statut de Joseph"></span>
                    </div>
                    <div class="mb-4">
                        <div class="text-[11px] font-bold text-red-600 uppercase font-mono mb-1">{{ $agents['joseph']['role'] }}</div>
                        <p class="text-xs text-secondary leading-relaxed">{{ $agents['joseph']['description'] }}</p>
                        <!-- Badge de fraîcheur du dernier diagnostic -->
                        <div class="mt-2 flex items-center gap-1.5 text-[10px] font-mono">
                            <span x-show="live.joseph.stale" class="chip warn text-[9px] uppercase font-bold py-0.5 px-1.5 rounded" x-cloak>⚠️ Diagnostic périmé</span>
                            <span class="text-tertiary" x-text="live.joseph.last ? ('Dernier diagnostic : ' + fmtDate(live.joseph.last)) : 'Aucun diagnostic enregistré'"></span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-default">
                    <form method="POST" action="{{ route('fleet.trigger') }}">
                        @csrf
                        <input type="hidden" name="agent" value="joseph">
                        <input type="hidden" name="action_type" value="infra_diagnostic">
                        <button type="submit" class="btn primary sm w-full justify-center text-xs" style="background: #b91c1c; border-color: #991b1b;">
                            ⚡ Déclencher Diagnostic Manuel
                        </button>
                    </form>
                </div>
            </div>

            <!-- Métriques VPS -->
            <div class="card p-5 flex flex-col gap-4" style="background: var(--surface); border-color: var(--border);">
                <div class="mono-label" style="font-size: 10px; color: var(--accent);">Métriques Physiques du VPS</div>

                <!-- CPU (live) -->
                <div>
                    <div class="flex justify-between text-xs font-mono mb-1">
                        <span class="text-secondary">Charge CPU</span>
                        <span class="font-bold text-primary"><span x-text="(live.joseph.system?.cpu_usage_pct ?? 0)"></span>%</span>
                    </div>
                    <div class="w-full bg-surface2 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500" :class="barColor(live.joseph.system?.cpu_usage_pct)" :style="'width:'+(live.joseph.system?.cpu_usage_pct ?? 0)+'%'"></div>
                    </div>
                </div>

                <!-- RAM (live) -->
                <div>
                    <div class="flex justify-between text-xs font-mono mb-1">
                        <span class="text-secondary">Mémoire RAM</span>
                        <span class="font-bold text-primary"><span x-text="(live.joseph.system?.ram_usage?.pct ?? 0)"></span>% (<span x-text="(live.joseph.system?.ram_usage?.used_gb ?? 0)"></span>G / <span x-text="(live.joseph.system?.ram_usage?.total_gb ?? 0)"></span>G)</span>
                    </div>
                    <div class="w-full bg-surface2 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500" :class="barColor(live.joseph.system?.ram_usage?.pct)" :style="'width:'+(live.joseph.system?.ram_usage?.pct ?? 0)+'%'"></div>
                    </div>
                </div>

                <!-- Espace Disque (live) -->
                <div>
                    <div class="flex justify-between text-xs font-mono mb-1">
                        <span class="text-secondary">Espace Disque</span>
                        <span class="font-bold text-primary"><span x-text="(live.joseph.system?.disk_usage?.pct ?? 0)"></span>% (<span x-text="(live.joseph.system?.disk_usage?.used_gb ?? 0)"></span>G / <span x-text="(live.joseph.system?.disk_usage?.total_gb ?? 0)"></span>G)</span>
                    </div>
                    <div class="w-full bg-surface2 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500" :class="barColor(live.joseph.system?.disk_usage?.pct)" :style="'width:'+(live.joseph.system?.disk_usage?.pct ?? 0)+'%'"></div>
                    </div>
                </div>

                <!-- Verrous (live) -->
                <div class="mt-2 p-3 bg-surface2 rounded flex items-center justify-between text-xs font-mono">
                    <span class="text-secondary">Verrous de fichiers actifs</span>
                    <span class="font-bold" :class="(live.joseph.system?.file_locks ?? 0) > 0 ? 'text-amber-500' : 'text-emerald-600'" x-text="(live.joseph.system?.file_locks ?? 0)"></span>
                </div>
            </div>
        </div>

        <!-- Colonne Droite : Services Systemd & Logs -->
        <div class="col-span-12 lg:col-span-8 flex flex-col gap-6">
            <!-- Services Systemd -->
            <div class="card p-5" style="background: var(--surface); border-color: var(--border);">
                <div class="mono-label mb-3" style="font-size: 10px; color: var(--accent);">État des Services Systemd de la Flotte</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="svc in Object.entries(live.joseph.services || {})" :key="svc[0]">
                        <div class="flex items-center justify-between p-2.5 bg-surface2 rounded border border-default text-xs font-mono">
                            <span class="text-primary truncate max-w-[200px]" :title="svc[0]" x-text="svc[0]"></span>
                            <span class="chip text-[9px] uppercase font-bold py-0.5 px-1.5 rounded"
                                  :class="(svc[1] === 'active' || svc[1] === 'activating') ? 'ok' : 'err'"
                                  x-text="(svc[1] === 'active' || svc[1] === 'activating') ? 'Actif' : 'Inactif'"></span>
                        </div>
                    </template>
                    <div class="col-span-2 text-center text-tertiary italic py-6" x-show="Object.keys(live.joseph.services || {}).length === 0">
                        📭 Aucun service systemd répertorié. Lancez un diagnostic.
                    </div>
                </div>
            </div>

            <!-- Terminal Logs -->
            <div class="card p-5 flex flex-col gap-3" style="background: #090d16; border-color: #1e293b;">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                        <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                        <span class="text-xs font-mono text-slate-400 ml-2">joseph@richard-vps:~</span>
                    </div>
                    <span class="text-[10px] font-mono text-emerald-500">diagnostic console</span>
                </div>
                <div class="font-mono text-xs text-emerald-400 bg-black/40 p-4 rounded border border-slate-800/50 h-64 overflow-y-auto space-y-2 leading-relaxed">
                    <template x-for="(logLine, i) in (live.joseph.logs || [])" :key="i">
                        <div x-text="logLine"></div>
                    </template>
                    <div class="text-slate-500 italic" x-show="(live.joseph.logs || []).length === 0">Console vide. Aucun historique de diagnostic disponible.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── DRAWER : DÉTAIL D'UNE TÂCHE ─── -->
    <div x-show="selectedTask" x-cloak @keydown.escape.window="selectedTask = null" @click.self="selectedTask = null"
         class="fixed inset-0 z-50 flex justify-end" style="background: rgba(0,0,0,0.45);">
        <div class="h-full w-full max-w-md overflow-y-auto p-6 flex flex-col gap-4 shadow-2xl border-l border-default"
             style="background: var(--surface);"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-x-6" x-transition:enter-end="opacity-100 translate-x-0">
            <template x-if="selectedTask">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold m-0">Tâche <span class="font-mono" x-text="selectedTask.id"></span></h3>
                        <button @click="selectedTask = null" class="btn sm" style="background: var(--surface2); border-color: var(--border);">✕</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="chip font-mono text-[9px] uppercase bg-surface1" x-text="selectedTask.dept"></span>
                        <span class="chip font-mono text-[9px] uppercase" :class="statusChip(selectedTask.status || 'queued')" x-text="selectedTask.status || 'queued'"></span>
                        <span class="chip font-mono text-[9px] uppercase bg-surface1" x-text="selectedTask.type"></span>
                    </div>

                    <!-- Action : relancer une tâche en échec -->
                    <div x-show="selectedTask.status === 'failed'">
                        <form method="POST" :action="'{{ url('fleet/retry') }}/' + selectedTask.id"
                              onsubmit="return confirm('Relancer cette tâche en échec dans le bus ?');">
                            @csrf
                            <button type="submit" class="btn primary sm w-full justify-center text-xs">🔄 Relancer cette tâche</button>
                        </form>
                    </div>
                    <div class="text-[11px] font-mono text-tertiary flex flex-col gap-0.5">
                        <div>Demandé par : <span class="text-secondary" x-text="selectedTask.requested_by || '—'"></span></div>
                        <div>Créée le : <span class="text-secondary" x-text="fmtDate(selectedTask.created_at)"></span></div>
                        <div x-show="selectedTask.parent_task">Tâche parente : <span class="text-secondary" x-text="selectedTask.parent_task"></span></div>
                    </div>
                    <div>
                        <div class="mono-label mb-1" style="font-size:10px;">Payload</div>
                        <pre class="bg-surface2 rounded p-3 text-[10px] font-mono text-secondary overflow-x-auto whitespace-pre-wrap" style="background: var(--surface2);" x-text="JSON.stringify(selectedTask.payload || {}, null, 2)"></pre>
                    </div>
                    <div>
                        <div class="mono-label mb-1" style="font-size:10px;">Résultat / Erreur</div>
                        <pre class="rounded p-3 text-[10px] font-mono overflow-x-auto whitespace-pre-wrap"
                             :class="(selectedTask.status === 'failed') ? 'text-red-400' : 'text-secondary'"
                             style="background: var(--surface2);"
                             x-text="selectedTask.result ? (typeof selectedTask.result === 'string' ? selectedTask.result : JSON.stringify(selectedTask.result, null, 2)) : '— Aucun résultat enregistré —'"></pre>
                    </div>
                    <div x-show="selectedTask.approval && selectedTask.approval.required" class="text-[11px] font-mono text-tertiary">
                        Approbation : <span class="text-secondary" x-text="selectedTask.approval ? selectedTask.approval.status : ''"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>

<script>
function fleetDashboard(seed) {
    return {
        activeTab: 'fleet',
        live: seed,
        taskFilter: 'all',
        selectedTask: null,
        _timer: null,
        init() {
            // Rafraîchissement automatique toutes les 12s (polling léger de /fleet/data)
            this._timer = setInterval(() => this.refresh(), 12000);
        },
        async refresh() {
            try {
                const res = await fetch('{{ route('fleet.data') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (!res.ok) return;
                this.live = await res.json();
            } catch (e) { /* réseau indisponible : on conserve le dernier état connu */ }
        },
        filteredTasks() {
            if (!this.live || !this.live.tasks) return [];
            if (this.taskFilter === 'all') return this.live.tasks;
            return this.live.tasks.filter(t => (t.status || 'queued') === this.taskFilter);
        },
        statusChip(s) {
            return ({ queued: '', in_progress: 'accent', done: 'ok', failed: 'err', awaiting_approval: 'warn' })[s] || '';
        },
        barColor(p) {
            p = p || 0;
            return p > 85 ? 'bg-red-600' : (p > 70 ? 'bg-amber-500' : 'bg-emerald-600');
        },
        fmtDate(iso) {
            if (!iso) return '—';
            try {
                return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
            } catch (e) { return iso; }
        }
    };
}
</script>

</x-app-shell>
