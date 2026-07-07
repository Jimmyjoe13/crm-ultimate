<x-app-shell active="dashboard" breadcrumb="Dashboard">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Bonjour {{ auth()->user()?->name ?? 'Alex' }} —</h1>
        <p class="text-sm text-secondary mt-0.5">
            Voici l'état de ton pipeline ce <span class="num-mono">{{ now()->locale('fr')->isoFormat('dddd D MMMM') }}</span>.
        </p>
    </div>
    <div class="flex items-center gap-2">
        <span class="chip ok"><span class="chip-dot"></span>Temps réel</span>
    </div>
</div>

{{-- ─── ALERTES IA PROACTIVES ─── --}}
<div x-data="aiAlerts()" x-init="fetchAlerts()">
    <template x-if="alerts.length > 0">
        <div class="px-7 pb-3">
            <div class="card border-0 overflow-hidden" style="background: var(--surface2); border-left: 3px solid var(--accent);">
                <div class="flex items-center justify-between px-4 py-2.5" style="background: var(--surface);">
                    <div class="flex items-center gap-2">
                        <svg class="ic" style="width:16px;height:16px;color:var(--accent);" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="text-sm font-medium text-primary">Alertes IA</span>
                        <span class="chip accent sm" x-text="alerts.length + ' alerte(s)'"></span>
                        <span x-show="criticalCount > 0" class="chip err sm" x-text="criticalCount + ' critique(s)'"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="expanded = !expanded" class="btn sm ghost">
                            <span x-text="expanded ? 'Réduire' : 'Tout voir'"></span>
                            <svg class="ic" style="width:12px;height:12px;transition:transform 0.2s;" :style="expanded ? 'transform:rotate(180deg)' : ''" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <button @click="dismiss()" class="btn sm ghost" title="Ignorer">
                            <svg class="ic" style="width:12px;height:12px;" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>
                {{-- Alertes critiques toujours visibles --}}
                <div class="divide-y" style="border-color: var(--border);">
                    <template x-for="alert in criticalAlerts" :key="alert.title">
                        <div class="flex items-start gap-3 px-4 py-2.5" style="border-color: var(--border);">
                            <span class="text-lg flex-shrink-0 mt-0.5" x-text="alert.icon"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-primary truncate" x-text="alert.title"></span>
                                    <span class="chip err sm flex-shrink-0" x-show="alert.severity === 'critical'">critique</span>
                                </div>
                                <p class="text-xs text-secondary mt-0.5 truncate" x-text="alert.message"></p>
                            </div>
                            <template x-if="alert.deal_id">
                                <a :href="'/deals/' + alert.deal_id" class="btn sm ghost flex-shrink-0">Voir</a>
                            </template>
                            <template x-if="alert.contact_id">
                                <a :href="'/contacts/' + alert.contact_id" class="btn sm ghost flex-shrink-0">Voir</a>
                            </template>
                        </div>
                    </template>
                </div>
                {{-- Alertes non-critiques : expandables --}}
                <div x-show="expanded" x-collapse class="divide-y" style="border-color: var(--border);">
                    <template x-for="alert in warningAlerts" :key="alert.title">
                        <div class="flex items-start gap-3 px-4 py-2.5" style="border-color: var(--border);">
                            <span class="text-lg flex-shrink-0 mt-0.5" x-text="alert.icon"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-primary truncate" x-text="alert.title"></span>
                                    <span class="chip warn sm flex-shrink-0" x-show="alert.severity === 'warning'">attention</span>
                                    <span class="chip info sm flex-shrink-0" x-show="alert.severity === 'info'">info</span>
                                </div>
                                <p class="text-xs text-secondary mt-0.5 truncate" x-text="alert.message"></p>
                            </div>
                            <template x-if="alert.deal_id">
                                <a :href="'/deals/' + alert.deal_id" class="btn sm ghost flex-shrink-0">Voir</a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- ─── KPI BAND ─── --}}
<div class="px-7 pt-2">
    {{-- Principaux indicateurs --}}
    <div class="grid grid-cols-3 gap-3 mb-3">
        {{-- Hero Pipeline Actuel --}}
        <div class="kpi-hero rounded-xl p-5 relative overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_12px_24px_-10px_rgba(239,106,42,0.4)] cursor-pointer group" title="Somme des montants de tous les deals encore ouverts">
            <div class="mono-label" style="color: rgba(255,255,255,0.75);">Pipeline actif</div>
            <div class="num text-4xl mt-2">{{ number_format($kpis['pipeline_total'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-2 mt-3 text-[12px]" style="color: rgba(255,255,255,0.9);">
                <span class="text-white opacity-80">Valeur totale des deals en cours</span>
            </div>
            <svg class="absolute bottom-3 right-4 opacity-70 transition-opacity duration-300 group-hover:opacity-100" width="120" height="40" viewBox="0 0 120 40" fill="none">
                <polyline points="0,32 15,28 30,30 45,22 60,24 75,16 90,18 105,8 120,12" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" fill="none"/>
                <polyline points="0,32 15,28 30,30 45,22 60,24 75,16 90,18 105,8 120,12 120,40 0,40" fill="rgba(255,255,255,0.12)"/>
            </svg>
        </div>

        {{-- Hero Chiffre d'Affaires (CA) --}}
        <div class="kpi-ok rounded-xl p-5 relative overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_12px_24px_-10px_rgba(47,138,95,0.4)] cursor-pointer group" title="Cumul des montants des deals gagnés">
            <div class="mono-label" style="color: rgba(255,255,255,0.75);">Chiffre d'Affaires (CA)</div>
            <div class="num text-4xl mt-2">{{ number_format($kpis['ca_total'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-2 mt-3 text-[12px]" style="color: rgba(255,255,255,0.9);">
                <span class="text-white opacity-80">Cumul des opportunités gagnées</span>
            </div>
            <svg class="absolute bottom-3 right-4 opacity-70 transition-opacity duration-300 group-hover:opacity-100" width="120" height="40" viewBox="0 0 120 40" fill="none">
                <polyline points="0,35 20,30 40,25 60,18 80,22 100,10 120,5" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" fill="none"/>
                <polyline points="0,35 20,30 40,25 60,18 80,22 100,10 120,5 120,40 0,40" fill="rgba(255,255,255,0.12)"/>
            </svg>
        </div>

        {{-- Hero CA Perdu --}}
        <div class="kpi-err rounded-xl p-5 relative overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_12px_24px_-10px_rgba(198,61,47,0.4)] cursor-pointer group" title="Cumul des montants des deals perdus">
            <div class="mono-label" style="color: rgba(255,255,255,0.75);">Chiffre d'Affaires Perdu</div>
            <div class="num text-4xl mt-2">{{ number_format($kpis['ca_lost'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-2 mt-3 text-[12px]" style="color: rgba(255,255,255,0.9);">
                <span class="text-white opacity-80">Cumul des opportunités perdues</span>
            </div>
            <svg class="absolute bottom-3 right-4 opacity-70 transition-opacity duration-300 group-hover:opacity-100" width="120" height="40" viewBox="0 0 120 40" fill="none">
                <polyline points="0,15 20,20 40,18 60,25 80,30 100,32 120,38" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" fill="none"/>
                <polyline points="0,15 20,20 40,18 60,25 80,30 100,32 120,38 120,40 0,40" fill="rgba(255,255,255,0.12)"/>
            </svg>
        </div>
    </div>

    {{-- Performance et mensuel --}}
    <div class="grid grid-cols-3 gap-3">
        {{-- Conversion --}}
        <div class="card p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg cursor-pointer">
            <div class="mono-label">Conversion · 30j</div>
            <div class="num text-3xl mt-2">{{ $kpis['conversion'] }}<span class="text-xl text-tertiary">%</span></div>
            <div class="flex items-center gap-2 mt-3 text-[12px]">
                <span class="text-tertiary">obj. 55%</span>
            </div>
            <div class="pbar accent mt-3"><div style="width: {{ min(($kpis['conversion'] / 55) * 100, 100) }}%;"></div></div>
        </div>

        {{-- Gagnés --}}
        <div class="card p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg cursor-pointer">
            <div class="mono-label">Gagnés · ce mois</div>
            <div class="num text-3xl mt-2" style="color: var(--ok);">{{ $kpis['won_count'] }}</div>
            <div class="num-mono text-[12px] mt-3" style="color: var(--text2);">{{ number_format($kpis['won_amount'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-1.5 mt-1 text-[11.5px] text-tertiary font-mono truncate">
                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background: var(--ok);"></span>
                {{ $kpis['won_names']->implode(' · ') ?: '—' }}
            </div>
        </div>

        {{-- Perdus --}}
        <div class="card p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg cursor-pointer">
            <div class="mono-label">Perdus · ce mois</div>
            <div class="num text-3xl mt-2" style="color: var(--err);">{{ $kpis['lost_count'] }}</div>
            <div class="num-mono text-[12px] mt-3" style="color: var(--text2);">{{ number_format($kpis['lost_amount'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-1.5 mt-1 text-[11.5px] text-tertiary font-mono truncate">
                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background: var(--err);"></span>
                {{ $kpis['lost_names']->implode(' · ') ?: '—' }}
            </div>
        </div>
    </div>
</div>

{{-- ─── MAIN GRID ─── --}}
<div class="px-7 pt-5 pb-12 grid grid-cols-3 gap-3">

    {{-- Activités récentes (2/3 width) --}}
    <div class="card col-span-2">
        <div class="card-h">
            <div class="flex items-center gap-2">
                <span class="title">Activités récentes</span>
            </div>
            <span class="meta">aujourd'hui</span>
        </div>
        <div class="px-4 py-2">
            @forelse($activities as $activity)
            @php
                $dot = match($activity->type) {
                    'email' => 'info',
                    'call'  => 'accent',
                    'note'  => '',
                    default => '',
                };
                $emoji = match($activity->type) {
                    'email'  => '📧',
                    'call'   => '📞',
                    'note'   => '📝',
                    'task'   => '✓',
                    default  => '➕',
                };
            @endphp
            <div class="tl-item">
                <span class="tl-time">{{ $activity->created_at->format('H:i') }}</span>
                <div class="tl-axis"><div class="tl-dot {{ $dot }}"></div></div>
                <div class="tl-content">
                    <div class="ti">{{ $emoji }} {{ $activity->title }}</div>
                    <div class="ts">{{ $activity->body ? Str::limit($activity->body, 60) : $activity->type }}</div>
                </div>
            </div>
            @empty
            <div class="py-6 text-center text-tertiary text-sm">Aucune activité récente.</div>
            @endforelse
        </div>
    </div>

    {{-- Sidebar: Pipeline + actions --}}
    <div class="flex flex-col gap-3">
        <div class="card">
            <div class="card-h">
                <span class="title">Pipeline par étape</span>
                <span class="meta">EUR</span>
            </div>
            <div class="p-4 flex flex-col gap-3">
                @foreach($stagesData as $sd)
                <div>
                    <div class="flex justify-between text-[12px] mb-1.5">
                        <span><span class="num-mono">{{ $sd['count'] }}</span> {{ $sd['name'] }}</span>
                        <span class="num-mono text-secondary">{{ number_format($sd['total'], 0, ',', "\xc2\xa0") }} €</span>
                    </div>
                    <div class="pbar @if($loop->index === 2) accent @endif">
                        <div style="width: {{ $maxTotal > 0 ? round(($sd['total'] / $maxTotal) * 100) : 0 }}%;"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-h">
                <span class="title">Accès rapide</span>
            </div>
            <div class="p-4 flex flex-col gap-2">
                <a href="{{ '/deals' }}" class="btn w-full justify-start">
                    <svg class="ic" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h12"/></svg>
                    Tous les deals
                </a>
                <a href="{{ '/contacts' }}" class="btn w-full justify-start">
                    <svg class="ic" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Contacts
                </a>
                <a href="{{ route('pipeline.index') }}" class="btn w-full justify-start">
                    <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="3" width="5" height="18"/><rect x="10" y="3" width="5" height="12"/><rect x="17" y="3" width="4" height="8"/></svg>
                    Kanban pipeline
                </a>
            </div>
        </div>

        <x-ai-insight-card endpoint="/web/ai/dashboard/suggestions" title="Suggestions du jour" />
    </div>
</div>

</x-app-shell>
