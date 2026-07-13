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

    <!-- ─── BANDEAU SANTÉ CONSOLIDÉE (visible seulement si ≠ ok) ─── -->
    <div class="px-7 mb-4" x-show="live.health && live.health.level !== 'ok'" x-cloak>
        <div class="card p-3 border-l-4 flex items-start gap-3"
             :style="live.health.level === 'crit'
                 ? 'border-left-color: var(--err); background: color-mix(in srgb, var(--err) 6%, var(--surface));'
                 : 'border-left-color: var(--warn); background: color-mix(in srgb, var(--warn) 5%, var(--surface));'">
            <span class="text-lg" x-text="live.health.level === 'crit' ? '🔴' : '🟠'"></span>
            <div class="flex-1">
                <div class="text-xs font-bold uppercase tracking-wider font-mono mb-1"
                     :style="live.health.level === 'crit' ? 'color: var(--err);' : 'color: var(--warn);'"
                     x-text="live.health.level === 'crit' ? 'Incident flotte' : 'Flotte dégradée'"></div>
                <ul class="m-0 pl-4 text-xs text-secondary" style="list-style: disc;">
                    <template x-for="issue in live.health.issues" :key="issue">
                        <li x-text="issue"></li>
                    </template>
                </ul>
            </div>
        </div>
    </div>

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

    <!-- ─── RANGÉE MONITORING : BUDGET LLM · FILES D'ATTENTE · KPI 7 JOURS ─── -->
    <div class="px-7 mb-5 grid grid-cols-1 lg:grid-cols-3 gap-3">

        <!-- Budget LLM du jour (fleet:or_gateway:stats, TTL 300s) -->
        <div class="card p-4 flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <span class="mono-label" style="font-size:10px;">⚡ Budget LLM du jour (passerelle)</span>
                <span class="text-[10px] font-mono text-tertiary" x-show="live.gateway" x-text="live.gateway ? ('RPM ' + live.gateway.rpm + '/' + live.gateway.rpm_limit) : ''"></span>
            </div>
            <template x-if="live.gateway">
                <div>
                    <div class="flex items-end justify-between mb-1">
                        <span class="text-xl font-bold" :style="'color:' + gwColor(live.gateway.pct)"
                              x-text="live.gateway.used + ' / ' + live.gateway.budget"></span>
                        <span class="text-xs font-mono text-secondary" x-text="live.gateway.pct + ' % — reste ' + live.gateway.remaining"></span>
                    </div>
                    <div class="w-full rounded h-2" style="background: var(--surface2);">
                        <div class="h-2 rounded transition-all" :style="'width:' + Math.min(100, live.gateway.pct) + '%; background:' + gwColor(live.gateway.pct)"></div>
                    </div>
                    <div class="text-[10px] font-mono text-tertiary mt-1">Paliers d'alerte : 70 / 90 / 100 % · plafond dur <span x-text="live.gateway.hard"></span></div>
                </div>
            </template>
            <template x-if="!live.gateway">
                <div class="text-xs" style="color: var(--warn);">⚠️ Passerelle silencieuse — aucune statistique depuis &gt; 5 min.</div>
            </template>
        </div>

        <!-- Files d'attente par département (XINFO GROUPS : vrai backlog, pas XLEN) -->
        <div class="card p-4 flex flex-col gap-2">
            <span class="mono-label" style="font-size:10px;">📥 Files d'attente (backlog réel par département)</span>
            <template x-if="busyQueues().length === 0">
                <div class="text-xs text-secondary py-2">✓ Aucune tâche en attente — tous les workers sont à jour.</div>
            </template>
            <div class="flex flex-col gap-1 overflow-y-auto" style="max-height: 130px;">
                <template x-for="q in busyQueues()" :key="q.dept">
                    <button type="button" @click="openAgent(q.dept)"
                            class="flex items-center justify-between text-xs rounded px-2 py-1 text-left w-full"
                            :style="(q.backlog > 5 || (!q.online && q.backlog > 0)) ? 'background: color-mix(in srgb, var(--err) 8%, var(--surface2));' : 'background: var(--surface2);'">
                        <span class="font-mono flex items-center gap-1.5">
                            <span class="led" :class="q.online ? 'green' : ''" :style="q.online ? '' : 'background: var(--err);'" style="width:5px;height:5px;"></span>
                            <span x-text="q.dept"></span>
                        </span>
                        <span class="font-mono text-tertiary">
                            <span x-text="'file ' + (q.backlog === null ? '?' : q.backlog)"></span>
                            <span x-show="q.pending > 0" :style="'color: var(--warn);'" x-text="' · en cours ' + q.pending"></span>
                            <span x-show="q.failed_7d > 0" style="color: var(--err);" x-text="' · ✕' + q.failed_7d"></span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        <!-- KPI fiabilité 7 jours (fenêtre naturelle : TTL 7j des tâches terminées) -->
        <div class="card p-4 flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <span class="mono-label" style="font-size:10px;">📊 Fiabilité 7 jours</span>
                <span class="text-xs font-mono" x-show="live.kpi && live.kpi.success_rate !== null"
                      :style="'color:' + (live.kpi.success_rate >= 90 ? 'var(--ok)' : (live.kpi.success_rate >= 70 ? 'var(--warn)' : 'var(--err)'))"
                      x-text="live.kpi.success_rate + ' % de réussite'"></span>
            </div>
            <!-- 7 barres empilées done (vert) / failed (rouge), même pattern que la timeline Juliette -->
            <div class="flex items-end gap-1.5" style="height: 72px;" x-show="live.kpi">
                <template x-for="d in (live.kpi ? live.kpi.days : [])" :key="d.date">
                    <div class="flex-1 flex flex-col items-center gap-0.5 h-full justify-end" :title="d.date + ' : ' + d.done + ' ok, ' + d.failed + ' échecs'">
                        <div class="w-full rounded-t bg-red-600 transition-all" :style="'height:' + kpiBarH(d.failed) + '%'"></div>
                        <div class="w-full bg-emerald-600 transition-all" :style="'height:' + kpiBarH(d.done) + '%'"></div>
                        <span class="text-[8px] font-mono text-tertiary" x-text="d.date.slice(8)"></span>
                    </div>
                </template>
            </div>
            <div class="text-[10px] font-mono text-tertiary" x-show="live.kpi"
                 x-text="live.kpi ? (live.kpi.done_7d + ' terminées · ' + live.kpi.failed_7d + ' échecs · top : ' + (live.kpi.top_types || []).slice(0,3).map(t => t.type).join(', ')) : ''"></div>
        </div>
    </div>

    <!-- Onglets de navigation -->
    <div class="px-7 mb-6 flex gap-6 border-b border-default items-center">
        <button @click="activeTab = 'fleet'" :class="activeTab === 'fleet' ? 'border-b-2 border-accent text-primary font-bold' : 'text-secondary'" class="pb-3 text-sm font-semibold transition-all relative">
            👥 Flotte Opérationnelle
        </button>
        <button @click="activeTab = 'growth'" :class="activeTab === 'growth' ? 'border-b-2 border-accent text-primary font-bold' : 'text-secondary'" class="pb-3 text-sm font-semibold transition-all flex items-center gap-2">
            📈 Équipe Growth (Mia · Sam · Nora)
            <span class="led pulse" x-show="growthAwaiting() > 0" style="background-color: var(--warn); box-shadow: 0 0 8px var(--warn); width: 6px; height: 6px;" x-cloak></span>
        </button>
        <button @click="activeTab = 'joseph'" :class="activeTab === 'joseph' ? 'border-b-2 border-accent text-primary font-bold' : 'text-secondary'" class="pb-3 text-sm font-semibold transition-all flex items-center gap-2">
            🛡️ Infrastructure & Maintenance (Joseph)
            <span class="led pulse" x-show="live.joseph.status !== 'healthy' || live.joseph.stale" style="background-color: var(--warn); box-shadow: 0 0 8px var(--warn); width: 6px; height: 6px;" x-cloak></span>
        </button>
        <button @click="activeTab = 'juliette'" :class="activeTab === 'juliette' ? 'border-b-2 border-accent text-primary font-bold' : 'text-secondary'" class="pb-3 text-sm font-semibold transition-all flex items-center gap-2">
            📧 Acquisition & Cold Email (Juliette)
            <span class="led pulse" x-show="live.juliette.status !== 'healthy' || live.juliette.stale" style="background-color: var(--warn); box-shadow: 0 0 8px var(--warn); width: 6px; height: 6px;" x-cloak></span>
        </button>
        <!-- Notifications navigateur (nouvelles validations) : l'API exige un geste utilisateur -->
        <button type="button" @click="toggleNotif()" class="ml-auto pb-3 text-sm"
                :title="notifEnabled ? 'Notifications activées (nouvelles validations) — cliquer pour couper' : 'Activer les notifications navigateur pour les nouvelles validations'"
                :style="notifEnabled ? '' : 'opacity: .4; filter: grayscale(1);'">🔔</button>
        <!-- Indicateur de rafraîchissement live -->
        <span class="flex items-center gap-1.5 text-[10px] font-mono text-tertiary pb-3" title="Rafraîchissement automatique toutes les 12s">
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
                                    <h3 class="text-sm font-semibold m-0" style="margin:0; cursor:pointer;"
                                        title="Voir l'historique des tâches de {{ $agent['name'] }}"
                                        @click="openAgent('{{ $agent['dept'] }}', '{{ $agent['name'] }}')">{{ $agent['name'] }} <span class="text-tertiary text-[10px]">📜</span></h3>
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
                                {{-- Pas de déclencheur : le dept « richard » n'a aucun worker sur le bus
                                     (une tâche y resterait queued pour toujours). La routine CEO est
                                     planifiée côté VPS par systemd (richard-ceo.timer, 08h30 Paris). --}}
                                <span class="btn sm w-full justify-center text-xs cursor-default select-none"
                                      style="background: var(--surface2); opacity: .7;"
                                      title="Déclenchée automatiquement par systemd sur le VPS — pas d'action manuelle depuis le CRM.">
                                    ⏰ Routine CEO planifiée (systemd · 08h30 Paris)
                                </span>
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
                                <input type="hidden" name="action_type" value="poll_inbox">
                                <button type="submit" class="btn primary sm w-full justify-center text-xs">
                                    📥 Relever la boîte mail
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
                                    <h3 class="text-xs font-semibold m-0" style="margin:0; cursor:pointer;"
                                        title="Voir l'historique des tâches de {{ $agent['name'] }}"
                                        @click="openAgent('{{ $agent['dept'] }}', '{{ $agent['name'] }}')">{{ $agent['name'] }} <span class="text-tertiary text-[10px]">📜</span></h3>
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

    <!-- ─── BLOC 2 : VALIDATIONS DE TÂCHES — TEMPS RÉEL (live.approvals, polling 12s) ───
         Avant : rendu serveur uniquement → une nouvelle demande n'apparaissait qu'après F5. -->
    <div class="col-span-12" x-show="live.approvals && live.approvals.length > 0" x-cloak>
        <div class="card p-4 border-l-4" style="border-left-color: var(--warn); background: color-mix(in srgb, var(--warn) 4%, var(--surface));">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-base">⚠️</span>
                <h2 class="text-sm font-bold uppercase tracking-wider font-mono m-0" style="margin:0; color: var(--warn);"
                    x-text="'Tâches en attente de votre validation (' + live.approvals.length + ')'"></h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template x-for="app in live.approvals" :key="app.id">
                    <div class="card p-4" style="background: var(--surface2); border-color: var(--border);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="chip warn font-mono text-[9px] uppercase">Awaiting Jimmy Approval</span>
                            <span class="font-mono text-tertiary text-xs" x-text="app.id"></span>
                        </div>
                        <div class="text-xs text-primary font-medium">
                            Agent <strong x-text="app.dept"></strong> — tâche <strong x-text="app.type"></strong>
                            <span class="text-tertiary font-mono" x-show="app.created_at" x-text="' · ' + fmtDate(app.created_at)"></span>
                        </div>
                        <div class="mt-2 p-2 bg-surface1 rounded font-mono text-[10px] text-secondary" x-show="app.label">
                            <strong>Action :</strong> <span x-text="app.label"></span>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <form method="POST" :action="'{{ url('/fleet/approve') }}/' + app.id">
                                @csrf
                                <button type="submit" class="btn primary sm">Approuver et exécuter ✓</button>
                            </form>
                            <form method="POST" :action="'{{ url('/fleet/reject') }}/' + app.id"
                                  @submit="if (!confirm('Rejeter la tâche ' + app.id + ' ? Elle ne sera pas exécutée.')) $event.preventDefault()">
                                @csrf
                                <button type="submit" class="btn sm" style="background: var(--surface1); border-color: var(--border); color: var(--err);">Rejeter ✕</button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ─── SYNTHÈSE CEO DU MATIN (fleet:ceo:last_report, publiée par la routine 08h30) ─── -->
    <div class="col-span-12" x-show="live.ceo_report && live.ceo_report.text" x-cloak
         x-data="{ open: false }" x-init="open = live.ceo_report && ((Date.now() - new Date(live.ceo_report.generated_at).getTime()) < 24*3600*1000)">
        <div class="card p-4">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left" style="background:none;border:none;padding:0;cursor:pointer;">
                <span class="text-sm font-bold uppercase tracking-wider font-mono" style="color: var(--err);">
                    🔴 Synthèse CEO
                    <span class="text-tertiary font-normal normal-case" x-text="live.ceo_report ? ('— ' + fmtDate(live.ceo_report.generated_at)) : ''"></span>
                </span>
                <span class="text-xs text-tertiary" x-text="open ? '▲ replier' : '▼ déplier'"></span>
            </button>
            <pre x-show="open" x-cloak class="mt-3 rounded p-3 text-[11px] overflow-x-auto"
                 style="background: var(--surface2); white-space: pre-wrap; font-family: inherit; line-height: 1.5;"
                 x-text="live.ceo_report ? live.ceo_report.text : ''"></pre>
        </div>
    </div>

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

    <!-- ─── Conteneur ÉQUIPE GROWTH (Mia · Sam · Nora) — chaque action de l'équipe ─── -->
    <div x-show="activeTab === 'growth'" class="px-7 pb-12 grid grid-cols-12 gap-6" x-cloak>

        <!-- Cartes des 3 agents Growth -->
        @foreach(['mia' => ['dept' => 'content', 'emoji' => '🎨', 'action' => 'generate_content', 'action_label' => 'Générer du contenu'],
                  'sam' => ['dept' => 'sales', 'emoji' => '💬', 'action' => 'inbox_sync', 'action_label' => 'Scanner les DMs'],
                  'nora' => ['dept' => 'finance', 'emoji' => '📊', 'action' => 'kpi_report', 'action_label' => 'Rapport KPI']] as $gKey => $gCfg)
        <div class="col-span-12 md:col-span-4">
            <div class="card p-4 flex flex-col gap-3 h-full">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="av sm text-white font-bold" style="background: color-mix(in srgb, var(--accent) 70%, #000);">{{ $gCfg['emoji'] }}</span>
                        <div>
                            <h3 class="text-sm font-bold m-0" style="margin:0; cursor:pointer;"
                                @click="openAgent('{{ $gCfg['dept'] }}', '{{ $agents[$gKey]['name'] }}')">{{ $agents[$gKey]['name'] }} <span class="text-tertiary text-[10px]">📜</span></h3>
                            <span class="text-[10px] text-tertiary">{{ $agents[$gKey]['role'] }}</span>
                        </div>
                    </div>
                    <span class="led" :class="live.agents.{{ $gKey }}.online ? 'green' : ''"
                          :style="live.agents.{{ $gKey }}.online ? '' : 'background: var(--err);'"
                          :title="live.agents.{{ $gKey }}.online ? 'Worker en ligne' : 'Worker hors ligne'"></span>
                </div>

                <!-- Compteurs 7 jours -->
                <div class="grid grid-cols-3 gap-2" x-data="{ pa() { return (live.growth && live.growth.per_agent && live.growth.per_agent['{{ $gCfg['dept'] }}']) || {}; } }">
                    <div class="rounded p-2 text-center" style="background: var(--surface2);">
                        <div class="text-[9px] font-mono uppercase text-tertiary">Actions 7j</div>
                        <div class="text-base font-bold" x-text="(pa().done_7d || 0) + (pa().failed_7d || 0)"></div>
                    </div>
                    <div class="rounded p-2 text-center" style="background: var(--surface2);">
                        <div class="text-[9px] font-mono uppercase text-tertiary">Réussies</div>
                        <div class="text-base font-bold" style="color: var(--ok);" x-text="pa().done_7d || 0"></div>
                    </div>
                    <div class="rounded p-2 text-center" style="background: var(--surface2);">
                        <div class="text-[9px] font-mono uppercase text-tertiary">Échecs</div>
                        <div class="text-base font-bold" :style="(pa().failed_7d || 0) > 0 ? 'color: var(--err);' : 'color: var(--text3);'" x-text="pa().failed_7d || 0"></div>
                    </div>
                    <div class="col-span-3 text-[10px] font-mono text-tertiary text-center" x-show="pa().in_progress" x-cloak style="color: var(--accent);">⚙ tâche en cours d'exécution…</div>
                    <div class="col-span-3 text-[10px] font-mono text-center" x-show="(pa().awaiting || 0) > 0" x-cloak style="color: var(--warn);" x-text="'⚠ ' + pa().awaiting + ' en attente de validation'"></div>
                    <div class="col-span-3 text-[10px] font-mono text-tertiary text-center" x-show="pa().last_done"
                         x-text="pa().last_done ? ('Dernier livrable : ' + pa().last_done.type + ' (' + fmtDate(pa().last_done.created_at) + ')') : ''"></div>
                </div>

                <!-- Action rapide -->
                <form method="POST" action="{{ route('fleet.trigger') }}" class="mt-auto">
                    @csrf
                    <input type="hidden" name="agent" value="{{ $gKey }}">
                    <input type="hidden" name="action_type" value="{{ $gCfg['action'] }}">
                    <button type="submit" class="btn primary sm w-full justify-center text-xs">⚡ {{ $gCfg['action_label'] }}</button>
                </form>
            </div>
        </div>
        @endforeach

        <!-- Flux d'actions de l'équipe Growth (chaque tâche, détail au clic) -->
        <div class="col-span-12">
            <div class="card p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="mono-label" style="font-size:10px;">🗂 Flux d'actions de l'équipe (7 jours — cliquer pour le détail complet)</span>
                    <div class="flex gap-1">
                        <template x-for="f in [['all','Tous'],['content','Mia'],['sales','Sam'],['finance','Nora']]" :key="f[0]">
                            <button type="button" @click="growthFilter = f[0]"
                                    class="btn sm text-[10px]" style="padding: 2px 10px;"
                                    :style="growthFilter === f[0] ? 'background: var(--accent); color: #fff;' : 'background: var(--surface2);'"
                                    x-text="f[1]"></button>
                        </template>
                    </div>
                </div>
                <div class="text-xs text-secondary py-2" x-show="growthTasks().length === 0">Aucune action sur les 7 derniers jours.</div>
                <div class="flex flex-col gap-1.5" style="max-height: 480px; overflow-y: auto;">
                    <template x-for="t in growthTasks()" :key="t.id">
                        <button type="button" @click="selectedTask = t"
                                class="card p-2.5 flex items-center justify-between text-left w-full" style="cursor:pointer;">
                            <span class="flex items-center gap-2 text-xs min-w-0">
                                <span class="chip font-mono text-[9px] shrink-0" :class="statusChip(t.status)" x-text="t.status"></span>
                                <span class="font-mono text-tertiary shrink-0" x-text="t.id"></span>
                                <span class="chip text-[9px] shrink-0" x-text="({content:'Mia',sales:'Sam',finance:'Nora'})[t.dept] || t.dept"></span>
                                <span class="text-secondary truncate" x-text="t.type + (t.result && t.result.summary ? ' — ' + t.result.summary : '')"></span>
                            </span>
                            <span class="text-[10px] font-mono text-tertiary shrink-0 ml-2" x-text="fmtDate(t.created_at)"></span>
                        </button>
                    </template>
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

    <!-- Conteneur Juliette (Acquisition & Cold Email) -->
    <div x-show="activeTab === 'juliette'" class="px-7 pb-12 grid grid-cols-12 gap-6" x-cloak>
        <!-- Colonne Gauche : Carte Juliette + actions + envois du jour -->
        <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
            <!-- Carte Juliette -->
            <div class="agent-card p-5 flex flex-col justify-between" style="background: var(--surface);">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="av sm text-white font-bold" style="background: #0284c7; width: 36px; height: 36px;">JU</span>
                            <div>
                                <h3 class="text-sm font-bold m-0">{{ $agents['juliette']['name'] }}</h3>
                                <span class="text-[9px] text-tertiary font-mono uppercase tracking-wider">acquisition squad</span>
                            </div>
                        </div>
                        <span class="led" :class="(live.agents['juliette'] && live.agents['juliette'].online) ? 'green' : 'off'"
                              :title="(live.agents['juliette'] && live.agents['juliette'].online) ? 'Worker en ligne' : 'Worker hors-ligne'"></span>
                    </div>
                    <div class="mb-4">
                        <div class="text-[11px] font-bold uppercase font-mono mb-1" style="color: #0284c7;">{{ $agents['juliette']['role'] }}</div>
                        <p class="text-xs text-secondary leading-relaxed">{{ $agents['juliette']['description'] }}</p>
                        <div class="mt-2 flex items-center gap-1.5 text-[10px] font-mono">
                            <span x-show="live.juliette.stale" class="chip warn text-[9px] uppercase font-bold py-0.5 px-1.5 rounded" x-cloak>⚠️ Statut périmé</span>
                            <span class="text-tertiary" x-text="live.juliette.last ? ('Statut : ' + fmtDate(live.juliette.last)) : 'Aucun statut exporté'"></span>
                        </div>
                    </div>
                </div>
                <!-- Actions rapides -->
                <div class="mt-4 pt-3 border-t border-default flex flex-col gap-2">
                    <form method="POST" action="{{ route('fleet.trigger') }}" onsubmit="return confirm('Déclencher une relève de la boîte mail (réponses / bounces) ?');">
                        @csrf
                        <input type="hidden" name="agent" value="juliette">
                        <input type="hidden" name="action_type" value="poll_inbox">
                        <button type="submit" class="btn sm w-full justify-center text-xs" style="background: var(--surface2); border-color: var(--border);">📥 Scanner la boîte mail</button>
                    </form>
                    <form method="POST" action="{{ route('fleet.trigger') }}">
                        @csrf
                        <input type="hidden" name="agent" value="juliette">
                        <input type="hidden" name="action_type" value="refresh_status">
                        <button type="submit" class="btn sm w-full justify-center text-xs" style="background: var(--surface2); border-color: var(--border);">🔄 Rafraîchir le statut</button>
                    </form>
                </div>
            </div>

            <!-- Envois du jour vs cap -->
            <div class="card p-5 flex flex-col gap-4" style="background: var(--surface); border-color: var(--border);">
                <div class="mono-label" style="font-size: 10px; color: #0284c7;">Envois du jour</div>
                <div>
                    <div class="flex justify-between text-xs font-mono mb-1">
                        <span class="text-secondary">Cold emails envoyés</span>
                        <span class="font-bold text-primary"><span x-text="live.juliette.sends.today ?? 0"></span> / <span x-text="live.juliette.sends.cap ?? 45"></span></span>
                    </div>
                    <div class="w-full bg-surface2 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500 bg-emerald-600"
                             :style="'width:' + Math.min(100, Math.round(((live.juliette.sends.today ?? 0) / (live.juliette.sends.cap || 45)) * 100)) + '%'"></div>
                    </div>
                </div>
                <!-- Compteurs -->
                <div class="grid grid-cols-2 gap-2">
                    <div class="p-2.5 bg-surface2 rounded text-xs font-mono flex flex-col gap-0.5">
                        <span class="text-tertiary text-[10px] uppercase">Batch en attente</span>
                        <span class="font-bold text-primary" x-text="live.juliette.pending_batch_size ?? 0"></span>
                    </div>
                    <div class="p-2.5 bg-surface2 rounded text-xs font-mono flex flex-col gap-0.5">
                        <span class="text-tertiary text-[10px] uppercase">Suppression</span>
                        <span class="font-bold text-primary" x-text="live.juliette.suppression_count ?? 0"></span>
                    </div>
                    <div class="p-2.5 bg-surface2 rounded text-xs font-mono flex flex-col gap-0.5 col-span-2">
                        <span class="text-tertiary text-[10px] uppercase">Dernière campagne planifiée</span>
                        <span class="font-bold text-primary" x-text="live.juliette.last_plan_date || '—'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne Droite : Funnel + campagnes + feed emails -->
        <div class="col-span-12 lg:col-span-8 flex flex-col gap-6">
            <!-- Funnel de séquence -->
            <div class="card p-5" style="background: var(--surface); border-color: var(--border);">
                <div class="mono-label mb-3" style="font-size: 10px; color: #0284c7;">Funnel de séquence</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold" style="color: var(--accent);" x-text="(live.juliette.funnel && live.juliette.funnel.active) || 0"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Actifs</div>
                    </div>
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold" style="color: var(--ok);" x-text="(live.juliette.funnel && live.juliette.funnel.replied) || 0"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Réponses</div>
                    </div>
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold" style="color: var(--err);" x-text="(live.juliette.funnel && live.juliette.funnel.bounced) || 0"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Bounces</div>
                    </div>
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold text-primary" x-text="(live.juliette.funnel && live.juliette.funnel.completed) || 0"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Complétés</div>
                    </div>
                </div>
                <!-- Actifs par étape -->
                <template x-if="live.juliette.funnel && live.juliette.funnel.active_by_step">
                    <div class="flex flex-col gap-2">
                        <template x-for="step in ['1','2','3']" :key="step">
                            <div>
                                <div class="flex justify-between text-[11px] font-mono mb-1">
                                    <span class="text-secondary">Étape <span x-text="step"></span></span>
                                    <span class="font-bold text-primary" x-text="(live.juliette.funnel.active_by_step[step]) || 0"></span>
                                </div>
                                <div class="w-full bg-surface2 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full bg-sky-600 transition-all duration-500"
                                         :style="'width:' + Math.min(100, ((live.juliette.funnel.active_by_step[step]||0) / Math.max(1,(live.juliette.funnel.active||1))) * 100) + '%'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Tracking d'ouverture (calculé depuis les activités CRM) -->
            <div class="card p-5" style="background: var(--surface); border-color: var(--border);">
                <div class="mono-label mb-3" style="font-size: 10px; color: #0284c7;">Ouvertures des cold emails</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold" style="color: var(--accent);" x-text="(tk().open_rate ?? 0) + '%'"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Taux d'ouverture</div>
                    </div>
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold" style="color: var(--ok);" x-text="(tk().human_open_rate ?? 0) + '%'"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Taux humain</div>
                    </div>
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold text-primary" x-text="tk().human_opened ?? 0"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Ouv. humaines</div>
                    </div>
                    <div class="p-3 bg-surface2 rounded text-center">
                        <div class="text-lg font-bold text-tertiary" x-text="tk().bot_opened ?? 0"></div>
                        <div class="text-[9px] font-mono uppercase text-tertiary">Robots filtrés</div>
                    </div>
                </div>
                <div class="text-[10px] font-mono text-tertiary mb-4">
                    <span x-text="tk().opened ?? 0"></span> ouverture(s) sur <span x-text="tk().sent ?? 0"></span> envoi(s) — ouvertures &lt; 10s ou proxy connu exclues du taux humain.
                </div>
                <!-- Timeline 14 jours (barres CSS : envois vs ouvertures) -->
                <div class="mono-label mb-2" style="font-size: 10px; color: var(--text-tertiary, #94a3b8);">14 derniers jours</div>
                <div class="flex items-end gap-1 h-28 relative">
                    <template x-for="(d, i) in (tk().timeline || [])" :key="i">
                        <div class="flex-1 flex flex-col items-center justify-end gap-0.5 h-full"
                             :title="d.date + ' — ' + d.sent + ' envois / ' + d.opened + ' ouvertures'">
                            <div class="w-full flex items-end justify-center gap-0.5" style="height: 90%;">
                                <div class="w-1/2 rounded-t bg-slate-500/40 transition-all duration-500" :style="'height:' + barH(d.sent) + '%'"></div>
                                <div class="w-1/2 rounded-t bg-sky-500 transition-all duration-500" :style="'height:' + barH(d.opened) + '%'"></div>
                            </div>
                            <span class="text-[7px] font-mono text-tertiary" x-text="(d.date || '').slice(8,10)"></span>
                        </div>
                    </template>
                    <div x-show="(tk().timeline || []).every(d => (d.sent||0) === 0 && (d.opened||0) === 0)"
                         class="absolute inset-0 flex items-center justify-center text-tertiary italic text-xs">Aucune donnée sur la période.</div>
                </div>
                <div class="flex items-center gap-3 mt-2 text-[9px] font-mono text-tertiary">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-slate-500/40"></span> Envois</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-sky-500"></span> Ouvertures</span>
                </div>
            </div>

            <!-- Table par campagne -->
            <div class="card p-0 overflow-hidden" style="border-color: var(--border);">
                <div class="p-3" style="background: var(--surface2); border-bottom: 1px solid var(--border);">
                    <div class="mono-label" style="font-size: 10px; color: #0284c7;">Par campagne</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-default w-full border-collapse text-left text-xs">
                        <thead>
                            <tr style="background: var(--surface2); border-bottom: 1px solid var(--border);">
                                <th class="p-2.5 font-semibold text-secondary">Campagne</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Inscrits</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Actifs</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Rép.</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Bnc.</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Envoyés</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-default">
                            <template x-for="c in (live.juliette.campaigns || [])" :key="c.campaign">
                                <tr class="hover:bg-surface2 transition-colors">
                                    <td class="p-2.5 font-mono text-primary text-[11px]" x-text="c.campaign"></td>
                                    <td class="p-2.5 text-center font-mono" x-text="c.enrolled"></td>
                                    <td class="p-2.5 text-center font-mono" x-text="c.active"></td>
                                    <td class="p-2.5 text-center font-mono" style="color: var(--ok);" x-text="c.replied"></td>
                                    <td class="p-2.5 text-center font-mono" style="color: var(--err);" x-text="c.bounced"></td>
                                    <td class="p-2.5 text-center font-mono text-secondary" x-text="c.sent_total"></td>
                                </tr>
                            </template>
                            <tr x-show="(live.juliette.campaigns || []).length === 0">
                                <td colspan="6" class="p-6 text-center text-tertiary">📭 Aucune campagne active.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Taux d'ouverture par objet (boucle de feedback rédaction) -->
            <div class="card p-0 overflow-hidden" style="border-color: var(--border);">
                <div class="p-3" style="background: var(--surface2); border-bottom: 1px solid var(--border);">
                    <div class="mono-label" style="font-size: 10px; color: #0284c7;">Taux d'ouverture par objet</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-default w-full border-collapse text-left text-xs">
                        <thead>
                            <tr style="background: var(--surface2); border-bottom: 1px solid var(--border);">
                                <th class="p-2.5 font-semibold text-secondary">Objet</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Étape</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Envoyés</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Ouverts</th>
                                <th class="p-2.5 font-semibold text-secondary text-center">Taux</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-default">
                            <template x-for="(s, i) in (tk().by_subject || [])" :key="i">
                                <tr class="hover:bg-surface2 transition-colors">
                                    <td class="p-2.5 font-mono text-primary text-[11px] max-w-[220px] truncate" x-text="s.subject" :title="s.subject"></td>
                                    <td class="p-2.5 text-center font-mono text-tertiary" x-text="s.step"></td>
                                    <td class="p-2.5 text-center font-mono text-secondary" x-text="s.sent"></td>
                                    <td class="p-2.5 text-center font-mono" style="color: var(--ok);" x-text="s.opened"></td>
                                    <td class="p-2.5 text-center font-mono font-bold" x-text="(s.rate ?? 0) + '%'"></td>
                                </tr>
                            </template>
                            <tr x-show="(tk().by_subject || []).length === 0">
                                <td colspan="5" class="p-6 text-center text-tertiary">Aucune donnée d'ouverture par objet pour l'instant.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feed des derniers emails (rendu serveur) -->
            <div class="card p-5" style="background: var(--surface); border-color: var(--border);">
                <div class="mono-label mb-3" style="font-size: 10px; color: #0284c7;">Derniers emails ({{ count($julietteFeed) }})</div>
                <div class="flex flex-col divide-y divide-default">
                    @forelse($julietteFeed as $a)
                        @php
                            $emoji = match($a->type) {
                                'email_sent' => '📤',
                                'email_replied' => '💬',
                                'email_bounced' => '⚠️',
                                default => '📧',
                            };
                            $camp = $a->metadata['campaign'] ?? null;
                            $subj = $a->metadata['subject'] ?? null;
                        @endphp
                        <div class="py-2.5 flex items-start gap-3 text-xs">
                            <span class="text-base leading-none">{{ $emoji }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-primary truncate">{{ $a->title }}</span>
                                    <span class="font-mono text-tertiary text-[10px] whitespace-nowrap">{{ optional($a->occurred_at ?? $a->created_at)->format('d/m H:i') }}</span>
                                </div>
                                @if($subj)
                                    <div class="text-[11px] text-secondary truncate">Objet : {{ $subj }}</div>
                                @endif
                                <div class="flex items-center gap-2 mt-0.5">
                                    @if($camp)<span class="chip font-mono text-[8px] uppercase bg-surface1">{{ $camp }}</span>@endif
                                    @if($a->subject_id)
                                        <a href="{{ route('contacts.show', $a->subject_id) }}" class="text-[10px] font-mono hover:underline" style="color: var(--accent);">Voir le contact →</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-tertiary italic py-6 text-xs">📭 Aucun email cold-email journalisé pour l'instant.</div>
                    @endforelse
                </div>
            </div>

            <!-- Approbations acquisition en attente -->
            {{-- Approbations acquisition en TEMPS RÉEL (live.approvals filtré, polling 12s) --}}
            <div x-show="(live.approvals || []).some(a => a.dept === 'acquisition')" x-cloak
                 class="card p-4 border-l-4" style="border-left-color: var(--warn); background: color-mix(in srgb, var(--warn) 4%, var(--surface));">
                <div class="mono-label mb-2" style="font-size: 10px; color: var(--warn);"
                     x-text="'Réponses / envois en attente de validation (' + (live.approvals || []).filter(a => a.dept === 'acquisition').length + ')'"></div>
                <div class="flex flex-col gap-2">
                    <template x-for="app in (live.approvals || []).filter(a => a.dept === 'acquisition')" :key="app.id">
                        <div class="flex items-center justify-between p-2.5 bg-surface2 rounded text-xs">
                            <span class="font-mono text-secondary" x-text="app.id + ' — ' + (app.label || app.type)"></span>
                            <div class="flex gap-1.5">
                                <form method="POST" :action="'{{ url('/fleet/approve') }}/' + app.id">
                                    @csrf
                                    <button type="submit" class="btn primary sm text-[10px]" style="padding:2px 8px;">Approuver ✓</button>
                                </form>
                                <form method="POST" :action="'{{ url('/fleet/reject') }}/' + app.id"
                                      @submit="if (!confirm('Rejeter ' + app.id + ' ?')) $event.preventDefault()">
                                    @csrf
                                    <button type="submit" class="btn sm text-[10px]" style="padding:2px 8px; background: var(--surface1); color: var(--err);">Rejeter ✕</button>
                                </form>
                            </div>
                        </div>
                    </template>
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

    <!-- ─── PANNEAU DRILL-DOWN AGENT (fetch à la demande, hors polling) ─── -->
    <div x-show="agentPanel" x-cloak @click.self="agentPanel = null"
         class="fixed inset-0 z-50 flex justify-end" style="background: rgba(0,0,0,.45);">
        <div class="h-full w-full max-w-md overflow-y-auto p-5 flex flex-col gap-3" style="background: var(--surface);">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold m-0" x-text="'📜 Historique — ' + (agentPanel ? agentPanel.name : '')"></h3>
                <button type="button" class="btn sm" @click="agentPanel = null">✕</button>
            </div>
            <template x-if="agentData && agentData.stats">
                <div class="grid grid-cols-3 gap-2">
                    <div class="card p-2 text-center">
                        <div class="text-[9px] font-mono uppercase text-tertiary">7 jours</div>
                        <div class="text-lg font-bold" x-text="agentData.stats.total_7d"></div>
                    </div>
                    <div class="card p-2 text-center">
                        <div class="text-[9px] font-mono uppercase text-tertiary">Réussite</div>
                        <div class="text-lg font-bold"
                             :style="'color:' + (agentData.stats.success_rate === null ? 'var(--text3)' : (agentData.stats.success_rate >= 90 ? 'var(--ok)' : (agentData.stats.success_rate >= 70 ? 'var(--warn)' : 'var(--err)')))"
                             x-text="agentData.stats.success_rate === null ? '—' : agentData.stats.success_rate + '%'"></div>
                    </div>
                    <div class="card p-2 text-center">
                        <div class="text-[9px] font-mono uppercase text-tertiary">Échecs</div>
                        <div class="text-lg font-bold" :style="agentData.stats.failed > 0 ? 'color: var(--err);' : 'color: var(--text3);'" x-text="agentData.stats.failed"></div>
                    </div>
                </div>
            </template>
            <div class="text-xs text-secondary" x-show="agentPanel && !agentData">Chargement…</div>
            <div class="text-xs" style="color: var(--err);" x-show="agentData && agentData.error" x-text="agentData ? agentData.error : ''"></div>
            <div class="flex flex-col gap-1.5">
                <template x-for="t in (agentData ? (agentData.tasks || []) : [])" :key="t.id">
                    <button type="button" @click="selectedTask = t; agentPanel = null"
                            class="card p-2.5 flex items-center justify-between text-left w-full" style="cursor:pointer;">
                        <span class="flex items-center gap-2 text-xs">
                            <span class="chip font-mono text-[9px]" :class="statusChip(t.status)" x-text="t.status"></span>
                            <span class="font-mono text-tertiary" x-text="t.id"></span>
                            <span class="text-secondary" x-text="t.type"></span>
                        </span>
                        <span class="text-[10px] font-mono text-tertiary" x-text="fmtDate(t.created_at)"></span>
                    </button>
                </template>
            </div>
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
        agentPanel: null,   // {dept, name} quand le panneau historique est ouvert
        agentData: null,    // réponse de /fleet/agent/<dept>/tasks
        growthFilter: 'all', // filtre du flux d'actions Growth (all|content|sales|finance)
        notifEnabled: localStorage.getItem('fleet_notif') === '1',
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
                const prevIds = new Set((this.live.approvals || []).map(a => a.id));
                this.live = await res.json();
                // Notification navigateur pour chaque NOUVELLE demande de validation
                if (this.notifEnabled && 'Notification' in window && Notification.permission === 'granted') {
                    (this.live.approvals || []).filter(a => !prevIds.has(a.id)).forEach(a => {
                        try {
                            new Notification('Flotte — validation en attente', {
                                body: a.id + ' · ' + a.dept + ' · ' + (a.label || a.type),
                                tag: 'fleet-approval-' + a.id, // anti-doublon si plusieurs onglets
                            });
                        } catch (e) { /* silencieux */ }
                    });
                }
            } catch (e) { /* réseau indisponible : on conserve le dernier état connu */ }
        },
        async toggleNotif() {
            if (this.notifEnabled) {
                this.notifEnabled = false;
                localStorage.setItem('fleet_notif', '0');
                return;
            }
            if (!('Notification' in window)) return;
            const perm = await Notification.requestPermission(); // exige le geste utilisateur (ce clic)
            if (perm === 'granted') {
                this.notifEnabled = true;
                localStorage.setItem('fleet_notif', '1');
                new Notification('Flotte — notifications activées', { body: 'Vous serez prévenu des nouvelles validations en attente.' });
            }
        },
        async openAgent(dept, name) {
            this.agentPanel = { dept, name: name || dept };
            this.agentData = null;
            try {
                const res = await fetch('{{ url('/fleet/agent') }}/' + dept + '/tasks', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                this.agentData = res.ok ? await res.json() : { error: 'Erreur ' + res.status };
            } catch (e) {
                this.agentData = { error: 'Réseau indisponible' };
            }
        },
        // Flux d'actions de l'équipe Growth, filtrable par agent (dept)
        growthTasks() {
            const all = (this.live.growth && this.live.growth.tasks) || [];
            return this.growthFilter === 'all' ? all : all.filter(t => t.dept === this.growthFilter);
        },
        // Total des tâches Growth en attente de validation (LED de l'onglet)
        growthAwaiting() {
            const pa = (this.live.growth && this.live.growth.per_agent) || {};
            return Object.values(pa).reduce((s, a) => s + (a.awaiting || 0), 0);
        },
        // Files d'attente : seulement les départements qui ont quelque chose à montrer
        busyQueues() {
            return (this.live.queues || []).filter(q =>
                (q.backlog || 0) > 0 || (q.pending || 0) > 0 || (q.failed_7d || 0) > 0 || !q.online || q.backlog === null);
        },
        // Couleur de la jauge budget — mêmes paliers que les alertes Telegram de la passerelle
        gwColor(pct) {
            return pct >= 90 ? 'var(--err)' : (pct >= 70 ? 'var(--warn)' : 'var(--ok)');
        },
        // Hauteur (%) d'un segment des barres KPI 7j, relative au jour le plus chargé
        kpiBarH(v) {
            const days = (this.live.kpi && this.live.kpi.days) || [];
            let max = 1;
            days.forEach(d => { max = Math.max(max, (d.done || 0) + (d.failed || 0)); });
            return Math.round(((v || 0) / max) * 88); // 88% : garde la place du libellé jour
        },
        filteredTasks() {
            if (!this.live || !this.live.tasks) return [];
            if (this.taskFilter === 'all') return this.live.tasks;
            return this.live.tasks.filter(t => (t.status || 'queued') === this.taskFilter);
        },
        statusChip(s) {
            return ({ queued: '', in_progress: 'accent', done: 'ok', failed: 'err', awaiting_approval: 'warn' })[s] || '';
        },
        // Tracking d'ouverture Juliette (agrégats CRM injectés dans live.juliette.tracking)
        tk() {
            return (this.live && this.live.juliette && this.live.juliette.tracking) || {};
        },
        // Hauteur de barre (%) relative au max envois/ouvertures de la timeline
        barH(v) {
            const t = this.tk().timeline || [];
            let max = 1;
            t.forEach(d => { max = Math.max(max, d.sent || 0, d.opened || 0); });
            return Math.round(((v || 0) / max) * 100);
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
