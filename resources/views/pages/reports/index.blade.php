<x-app-shell active="reports" breadcrumb="Rapports">

<div class="px-7 pt-6 pb-10 max-w-7xl mx-auto">

    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1>Rapports & Analytics</h1>
            <p class="text-sm text-secondary mt-0.5">Vue consolidée des performances commerciales du CRM</p>
        </div>
        <span class="chip accent font-semibold">
            <span class="chip-dot"></span>
            Données en temps réel
        </span>
    </div>

    {{-- CA Mensuel --}}
    <div class="card mb-6 p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md">
        <div class="flex items-center gap-2 mb-4">
            <svg class="ic text-accent" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <h2 class="text-base font-semibold">Chiffre d'Affaires mensuel (12 mois glissants)</h2>
        </div>
        <div style="position: relative; height: 320px; width: 100%;">
            <canvas id="chart-ca-mensuel"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Entonnoir de conversion --}}
        <div class="card p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-center justify-between mb-4 border-b border-default pb-3">
                <div class="flex items-center gap-2">
                    <svg class="ic text-accent" viewBox="0 0 24 24"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                    <h2 class="text-base font-semibold">Entonnoir de conversion</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-secondary">Taux global :</span>
                    <span class="chip ok font-bold">{{ $entonnoir['taux_conversion_global'] }}%</span>
                </div>
            </div>
            <div class="space-y-4">
                @foreach($entonnoir['stages'] as $stage)
                <div class="group">
                    <div class="flex justify-between items-center text-xs mb-1.5">
                        <span class="font-medium text-secondary group-hover:text-primary transition-colors">{{ $stage['name'] }}</span>
                        <span class="num-mono text-tertiary group-hover:text-primary transition-colors font-medium">{{ $stage['count'] }} deals</span>
                    </div>
                    @php
                        $maxCount = max(array_column($entonnoir['stages'], 'count') ?: [1]);
                        $pct = $maxCount > 0 ? round($stage['count'] / $maxCount * 100) : 0;
                    @endphp
                    <div class="pbar accent w-full">
                        <div style="width: {{ $pct }}%; transition: width 0.6s ease-out;"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Classement commerciaux --}}
        <div class="card p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-center gap-2 mb-4 border-b border-default pb-3">
                <svg class="ic text-accent" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <h2 class="text-base font-semibold">Classement commerciaux (mois en cours)</h2>
            </div>
            @if(count($classement) === 0)
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <svg class="ic text-tertiary mb-2 w-8 h-8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                    <p class="text-sm text-secondary">Aucun deal gagné ce mois-ci.</p>
                </div>
            @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-secondary border-b border-default text-xs uppercase tracking-wider font-semibold">
                        <th class="text-left pb-2 font-medium text-tertiary" style="width: 50%;">Commercial</th>
                        <th class="text-center pb-2 font-medium text-tertiary" style="width: 20%;">Deals</th>
                        <th class="text-right pb-2 font-medium text-tertiary" style="width: 30%;">CA</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-default">
                    @foreach($classement as $i => $row)
                    <tr class="hover:bg-surface2 transition-colors">
                        <td class="py-3 flex items-center gap-2">
                            @php
                                $rankClass = '';
                                if ($i === 0) $rankClass = 'bg-[#ffd700] text-black font-bold';
                                elseif ($i === 1) $rankClass = 'bg-[#c0c0c0] text-black font-bold';
                                elseif ($i === 2) $rankClass = 'bg-[#cd7f32] text-black font-bold';
                                else $rankClass = 'bg-surface2 text-secondary';
                            @endphp
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] {{ $rankClass }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="font-medium text-primary">{{ $row['commercial'] }}</span>
                        </td>
                        <td class="py-3 text-center num-mono text-secondary">{{ $row['nb_deals'] }}</td>
                        <td class="py-3 text-right num-mono font-semibold text-ok">{{ number_format($row['ca'], 0, ',', ' ') }} €</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

    </div>

    {{-- Activité hebdomadaire --}}
    <div class="card p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="ic text-accent" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <h2 class="text-base font-semibold">Activité hebdomadaire (8 semaines)</h2>
        </div>
        <div style="position: relative; height: 260px; width: 100%;">
            <canvas id="chart-activite-hebdo"></canvas>
        </div>
    </div>

    {{-- Carte IA Insights (admin/manager uniquement) --}}
    @if(in_array(auth()->user()?->role, ['admin','manager']))
    <div class="card p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md mb-6"
         x-data="{
            loading: false,
            loaded: false,
            error: false,
            insights: [],
            alerts: [],
            recommendations: [],
            generatedAt: '',
            cached: false,
            fetchInsights() {
                this.loading = true;
                this.error = false;
                fetch('/web/ai/report-insights', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({})
                })
                .then(res => {
                    if (!res.ok) throw new Error();
                    return res.json();
                })
                .then(res => {
                    const data = res.data || {};
                    this.insights = data.insights || [];
                    this.alerts = data.alerts || [];
                    this.recommendations = data.recommendations || [];
                    this.generatedAt = res.generated_at || '';
                    this.cached = res.cached || false;
                    this.loading = false;
                    this.loaded = true;
                })
                .catch(() => {
                    this.error = true;
                    this.loading = false;
                });
            }
         }">

        <div class="flex items-center justify-between mb-4 border-b border-default pb-3">
            <div class="flex items-center gap-2">
                {{-- Icône IA Sparkles --}}
                <svg class="ic text-accent" viewBox="0 0 24 24">
                    <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m11.314 11.314l.707-.707M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>
                </svg>
                <h2 class="text-base font-semibold">CRM Intelligence & Insights IA</h2>
            </div>
            <template x-if="loaded && !loading && !error">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] text-secondary num-mono" x-text="generatedAt ? 'Généré le ' + new Date(generatedAt).toLocaleString('fr-FR') : ''"></span>
                    <template x-if="cached">
                        <span class="chip font-medium text-[9px] accent">En cache</span>
                    </template>
                </div>
            </template>
        </div>

        {{-- Bouton initial Analyser --}}
        <template x-if="!loading && !loaded && !error">
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <svg class="ic text-accent w-10 h-10 mb-3" viewBox="0 0 24 24">
                    <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m11.314 11.314l.707-.707M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>
                </svg>
                <h3 class="text-base font-semibold mb-1 text-primary">Analyse IA des Performances</h3>
                <p class="text-xs text-secondary mb-4 max-w-md">Obtenez instantanément des analyses prédictives, des alertes de risques et des recommandations opérationnelles sur vos performances commerciales.</p>
                <button @click="fetchInsights()" class="btn primary">
                    <svg class="ic" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    Analyser avec l'IA
                </button>
            </div>
        </template>

        {{-- Spinner de chargement --}}
        <template x-if="loading">
            <div class="flex flex-col items-center justify-center py-12">
                <svg class="animate-spin h-6 w-6 text-accent mb-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm text-secondary">Analyse des performances par l'IA en cours...</span>
            </div>
        </template>

        {{-- Message d'erreur --}}
        <template x-if="error">
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <svg class="ic text-err w-8 h-8 mb-2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <p class="text-sm text-secondary">Impossible de charger les insights IA pour le moment.</p>
                <button @click="fetchInsights()" class="btn sm mt-3">Réessayer</button>
            </div>
        </template>

        {{-- Contenu une fois chargé --}}
        <template x-if="loaded && !loading && !error">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                {{-- Colonne 1: Alertes (Rouge/Orange) --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-err uppercase tracking-wider">
                        <svg class="ic text-err" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
                        Alertes & Risques
                    </div>
                    <template x-if="alerts.length === 0">
                        <p class="text-xs text-secondary italic">Aucune alerte détectée.</p>
                    </template>
                    <ul class="space-y-2 list-none p-0 m-0">
                        <template x-for="(alert, idx) in alerts" :key="idx">
                            <li class="p-3 rounded-lg text-xs" style="background: var(--err-soft); border-left: 3px solid var(--err); color: var(--text);">
                                <span class="font-medium" x-text="alert"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Colonne 2: Insights généraux (Gris/Bleu) --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-primary uppercase tracking-wider">
                        <svg class="ic text-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                        Analyses & Tendances
                    </div>
                    <template x-if="insights.length === 0">
                        <p class="text-xs text-secondary italic">Aucun insight disponible.</p>
                    </template>
                    <ul class="space-y-2 list-none p-0 m-0">
                        <template x-for="(insight, idx) in insights" :key="idx">
                            <li class="p-3 rounded-lg text-xs" style="background: var(--surface2); border-left: 3px solid var(--text3); color: var(--text);">
                                <span class="font-medium" x-text="insight"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Colonne 3: Recommandations (Vert) --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-ok uppercase tracking-wider">
                        <svg class="ic text-ok" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14M22 4L12 14.01l-3-3"/></svg>
                        Recommandations
                    </div>
                    <template x-if="recommendations.length === 0">
                        <p class="text-xs text-secondary italic">Aucune recommandation.</p>
                    </template>
                    <ul class="space-y-2 list-none p-0 m-0">
                        <template x-for="(rec, idx) in recommendations" :key="idx">
                            <li class="p-3 rounded-lg text-xs" style="background: var(--ok-soft); border-left: 3px solid var(--ok); color: var(--text);">
                                <span class="font-medium" x-text="rec"></span>
                            </li>
                        </template>
                    </ul>
                </div>

            </div>
        </template>

    </div>
    @endif

</div>

<script type="application/json" id="reports-data">
    @json(['ca_mensuel' => $ca_mensuel, 'activite_hebdo' => $activite_hebdo])
</script>

</x-app-shell>
