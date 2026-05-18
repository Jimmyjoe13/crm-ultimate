<x-app-shell active="dashboard" breadcrumb="Dashboard">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Bonjour {{ auth()->user()?->name ?? 'Alex' }} —</h1>
        <p class="text-sm text-secondary mt-0.5">
            Voici l'état de ton pipeline ce <span class="num-mono">{{ now()->locale('fr')->isoFormat('dddd D MMMM') }}</span>.
        </p>
    </div>
    <div class="flex items-center gap-2">
        <button class="btn sm">
            <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M10 18h4"/></svg>
            Filtrer
        </button>
        <span class="chip ok"><span class="chip-dot"></span>Temps réel</span>
    </div>
</div>

{{-- ─── KPI BAND ─── --}}
<div class="px-7 pt-2">
    <div class="grid grid-cols-4 gap-3">

        {{-- Hero --}}
        <div class="kpi-hero rounded-xl p-5 relative overflow-hidden">
            <div class="mono-label" style="color: rgba(255,255,255,0.75);">Pipeline total · 30j</div>
            <div class="num text-4xl mt-2">{{ number_format($kpis['pipeline_total'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-2 mt-3 text-[12px]" style="color: rgba(255,255,255,0.9);">
                <span class="num-mono">▲ +12,4%</span>
                <span style="color: rgba(255,255,255,0.7);">vs mois dernier</span>
            </div>
            <svg class="absolute bottom-3 right-4" width="120" height="40" viewBox="0 0 120 40" fill="none">
                <polyline points="0,32 15,28 30,30 45,22 60,24 75,16 90,18 105,8 120,12" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" fill="none"/>
                <polyline points="0,32 15,28 30,30 45,22 60,24 75,16 90,18 105,8 120,12 120,40 0,40" fill="rgba(255,255,255,0.12)"/>
            </svg>
        </div>

        {{-- Conversion --}}
        <div class="card p-5">
            <div class="mono-label">Conversion · 30j</div>
            <div class="num text-3xl mt-2">{{ $kpis['conversion'] }}<span class="text-xl text-tertiary">%</span></div>
            <div class="flex items-center gap-2 mt-3 text-[12px]">
                <span class="num delta-up">▲ +6 pts</span>
                <span class="text-tertiary">obj. 55%</span>
            </div>
            <div class="pbar accent mt-3"><div style="width: {{ min(($kpis['conversion'] / 55) * 100, 100) }}%;"></div></div>
        </div>

        {{-- Gagnés --}}
        <div class="card p-5">
            <div class="mono-label">Gagnés · ce mois</div>
            <div class="num text-3xl mt-2" style="color: var(--ok);">{{ $kpis['won_count'] }}</div>
            <div class="num-mono text-[12px] mt-3" style="color: var(--text2);">{{ number_format($kpis['won_amount'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-1.5 mt-1 text-[11.5px] text-tertiary font-mono">
                <span class="inline-block w-2 h-2 rounded-full" style="background: var(--ok);"></span>
                {{ $kpis['won_names']->implode(' · ') ?: '—' }}
            </div>
        </div>

        {{-- Perdus --}}
        <div class="card p-5">
            <div class="mono-label">Perdus · ce mois</div>
            <div class="num text-3xl mt-2" style="color: var(--err);">{{ $kpis['lost_count'] }}</div>
            <div class="num-mono text-[12px] mt-3" style="color: var(--text2);">{{ number_format($kpis['lost_amount'], 0, ',', "\xc2\xa0") }} €</div>
            <div class="flex items-center gap-1.5 mt-1 text-[11.5px] text-tertiary font-mono">
                <span class="inline-block w-2 h-2 rounded-full" style="background: var(--err);"></span>
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
