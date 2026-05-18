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
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <span class="num text-2xl font-bold" x-text="content.score"></span>
                <span class="text-[11px] text-tertiary">/100</span>
                <span class="chip ml-auto" style="font-size:10px;" x-text="content.trend"></span>
            </div>
            <div class="pbar accent" style="height:4px;"><div :style="'width:' + content.score + '%'"></div></div>
            <template x-if="content.reasons && content.reasons.length">
                <ul class="flex flex-col gap-0.5 mt-1">
                    <template x-for="r in content.reasons">
                        <li class="text-[11.5px] text-secondary flex gap-1"><span>·</span><span x-text="r"></span></li>
                    </template>
                </ul>
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
