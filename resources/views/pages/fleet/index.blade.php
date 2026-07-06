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

<div class="px-7 pb-12 grid grid-cols-12 gap-6">

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
                                </div>
                            </div>
                            
                            <!-- Voyant LED d'activité -->
                            @if($agent['status'] === 'active')
                                <span class="led led.pulse" title="Actif - Tâche en cours de traitement"></span>
                            @else
                                <span class="led green" title="En veille - Prêt à agir"></span>
                            @endif
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
                            
                            <!-- Voyant LED d'activité -->
                            @if($agent['status'] === 'active')
                                <span class="led led.pulse" title="Actif - Audit en cours"></span>
                            @else
                                <span class="led green" title="En veille - Prêt à auditer"></span>
                            @endif
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
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- ─── BLOC 3 : LE BUS DE TÂCHES REDIS (30 DERNIÈRES) ─── -->
    <div class="col-span-12 lg:col-span-8 flex flex-col gap-3">
        <div class="mono-label">Bus de tâches Redis (Stream de la Flotte)</div>
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
                        @forelse($tasks as $t)
                            @php
                                $statusColor = match($t['status'] ?? 'queued') {
                                    'queued' => '',
                                    'in_progress' => 'accent',
                                    'done' => 'ok',
                                    'failed' => 'err',
                                    'awaiting_approval' => 'warn',
                                    default => '',
                                };
                            @endphp
                            <tr class="hover:bg-surface2 transition-colors">
                                <!-- ID -->
                                <td class="p-3 font-mono font-bold text-primary">{{ $t['id'] }}</td>
                                
                                <!-- Département -->
                                <td class="p-3">
                                    <span class="chip font-mono text-[9px] uppercase tracking-wider bg-surface1">
                                        {{ $t['dept'] }}
                                    </span>
                                </td>

                                <!-- Type -->
                                <td class="p-3 font-mono text-secondary text-[11px]">{{ $t['type'] }}</td>

                                <!-- Statut -->
                                <td class="p-3">
                                    <span class="chip font-mono text-[9px] uppercase tracking-wider {{ $statusColor }}">
                                        {{ $t['status'] ?? 'queued' }}
                                    </span>
                                </td>

                                <!-- Payload -->
                                <td class="p-3 font-mono text-[10px] text-tertiary truncate max-w-[240px]" title="{{ json_encode($t['payload'] ?? []) }}">
                                    {{ json_encode($t['payload'] ?? [], JSON_UNESCAPED_UNICODE) }}
                                </td>

                                <!-- Date -->
                                <td class="p-3 font-mono text-tertiary whitespace-nowrap text-[10.5px]">
                                    @try
                                        {{ \Carbon\Carbon::parse($t['created_at'])->format('d/m H:i') }}
                                    @catch(\Exception $e)
                                        {{ $t['created_at'] }}
                                    @endtry
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-tertiary">
                                    📭 Aucune tâche active enregistrée dans le bus Redis de la flotte.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
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

</x-app-shell>
