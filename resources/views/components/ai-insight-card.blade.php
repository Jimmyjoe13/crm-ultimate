@props([
    'endpoint'  => '',
    'title'     => 'Insight IA',
    'entityId'  => null,
])

<div class="card p-4"
     x-data="{
         loading: false,
         content: null,
         error: null,
         cached: false,
         init() {
             const stored = sessionStorage.getItem('ai:{{ $endpoint }}');
             if (stored) {
                 try { const p = JSON.parse(stored); this.content = p.data; this.cached = true; } catch {}
             }
         },
         async generate(fresh = false) {
             this.loading = true;
             this.error = null;
             const url = '{{ $endpoint }}' + (fresh ? '?fresh=1' : '');
             try {
                 const resp = await fetch(url, {
                     method: 'POST',
                     headers: {
                         'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                         'Accept': 'application/json',
                     },
                     credentials: 'same-origin',
                 });
                 const json = await resp.json();
                 if (!resp.ok) { this.error = json.message ?? 'Erreur serveur'; return; }
                 this.content = json.data;
                 this.cached = json.cached ?? false;
                 if (!fresh) sessionStorage.setItem('ai:{{ $endpoint }}', JSON.stringify({ data: json.data }));
             } catch (e) {
                 this.error = 'Impossible de contacter le serveur.';
             } finally {
                 this.loading = false;
             }
         }
     }">

    <div class="flex items-center justify-between mb-3">
        <div class="mono-label flex items-center gap-1.5">
            <svg class="ic" style="width:12px;height:12px;" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            {{ $title }}
        </div>
        <div class="flex items-center gap-1">
            <span x-show="cached && content" class="chip" style="font-size:10px; padding:1px 5px;">cache</span>
            @if(in_array(auth()->user()?->role, ['admin','manager']))
            <button x-show="content" @click="generate(true)" class="btn ghost" style="font-size:10px; padding:2px 6px;" title="Régénérer">↺</button>
            @endif
        </div>
    </div>

    {{-- État initial --}}
    <div x-show="!content && !loading && !error">
        <button @click="generate()" class="btn sm w-full" style="font-size:12px;">
            <svg class="ic" style="width:12px;height:12px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
            Générer
        </button>
    </div>

    {{-- Chargement --}}
    <div x-show="loading" class="flex items-center gap-2 text-[12px] text-tertiary py-2">
        <svg class="ic animate-spin" style="width:12px;height:12px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        Génération en cours…
    </div>

    {{-- Erreur --}}
    <div x-show="error" class="text-[12px] text-err" x-text="error"></div>

    {{-- Contenu texte (summarize) --}}
    <template x-if="content && typeof content === 'string'">
        <div class="text-[12.5px] leading-relaxed text-secondary" x-text="content"></div>
    </template>

    {{-- Contenu JSON next-action --}}
    <template x-if="content && content.action">
        <div class="flex flex-col gap-1.5">
            <div class="text-[12.5px] font-medium" x-text="content.action"></div>
            <div class="text-[11.5px] text-secondary" x-text="content.rationale"></div>
            <span class="chip" :class="content.priority === 'high' ? 'err' : ''" style="font-size:10px; padding:2px 6px; align-self:start;" x-text="content.priority"></span>
        </div>
    </template>

    {{-- Contenu JSON score --}}
    <template x-if="content && content.score !== undefined">
        <div class="flex flex-col gap-2.5">
            <div class="flex items-center gap-2">
                <span class="num text-2xl font-bold" x-text="content.score"></span>
                <span class="text-[11px] text-tertiary">/100</span>
                <span class="chip ml-auto" style="font-size:10px;"
                      :class="content.trend === 'warming' ? 'ok' : (content.trend === 'cooling' ? 'err' : '')"
                      x-text="content.trend === 'warming' ? 'Hausse ↗' : (content.trend === 'cooling' ? 'Baisse ↘' : 'Stable →')"></span>
            </div>
            <div class="pbar" :class="content.score >= 70 ? 'ok' : (content.score >= 40 ? 'accent' : 'err')" style="height:4px;">
                <div :style="'width:' + content.score + '%'"></div>
            </div>
            
            {{-- Synthèse --}}
            <template x-if="content.reasons && content.reasons.length">
                <ul class="flex flex-col gap-0.5 mt-0.5">
                    <template x-for="r in content.reasons">
                        <li class="text-[11.5px] text-secondary flex gap-1"><span>·</span><span x-text="r"></span></li>
                    </template>
                </ul>
            </template>

            {{-- Green Flags --}}
            <template x-if="content.green_flags && content.green_flags.length">
                <div class="mt-1.5 pt-2 border-t border-default">
                    <div class="mono-label text-[9.5px] text-ok font-semibold mb-1 flex items-center gap-1">
                        <span class="chip-dot" style="background: var(--ok); width:5px; height:5px;"></span>
                        Points forts
                    </div>
                    <ul class="flex flex-col gap-1">
                        <template x-for="gf in content.green_flags">
                            <li class="text-[11.5px] text-secondary flex items-start gap-1">
                                <span class="text-ok">✓</span>
                                <span x-text="gf"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            {{-- Red Flags --}}
            <template x-if="content.red_flags && content.red_flags.length">
                <div class="mt-1.5 pt-2 border-t border-default">
                    <div class="mono-label text-[9.5px] text-err font-semibold mb-1 flex items-center gap-1">
                        <span class="chip-dot" style="background: var(--err); width:5px; height:5px;"></span>
                        Risques
                    </div>
                    <ul class="flex flex-col gap-1">
                        <template x-for="rf in content.red_flags">
                            <li class="text-[11.5px] text-secondary flex items-start gap-1">
                                <span class="text-err">⚠</span>
                                <span x-text="rf"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            {{-- Recommendations --}}
            <template x-if="content.recommendations && content.recommendations.length">
                <div class="mt-1.5 pt-2 border-t border-default">
                    <div class="mono-label text-[9.5px] text-accent font-semibold mb-1 flex items-center gap-1">
                        <span class="chip-dot" style="background: var(--accent); width:5px; height:5px;"></span>
                        Actions conseillées
                    </div>
                    <ul class="flex flex-col gap-1">
                        <template x-for="rec in content.recommendations">
                            <li class="text-[11.5px] text-secondary flex items-start gap-1">
                                <span class="text-accent">✦</span>
                                <span x-text="rec"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </template>

    {{-- Suggestions (dashboard) --}}
    <template x-if="content && content.suggestions">
        <ul class="flex flex-col gap-1.5">
            <template x-for="s in content.suggestions">
                <li class="flex items-start gap-1.5 text-[12.5px] text-secondary">
                    <span class="text-accent mt-0.5">›</span>
                    <span x-text="s"></span>
                </li>
            </template>
        </ul>
    </template>
</div>
