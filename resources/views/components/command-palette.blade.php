{{-- Command palette — ouvre sur ⌘K / Ctrl+K, ferme sur Esc ou clic backdrop --}}
<div x-data="cmdPalette()" x-init="init()"
     @keydown.window.meta.k.prevent="open = true"
     @keydown.window.ctrl.k.prevent="open = true"
     @keydown.window.escape="open = false"
     @open-cmd-palette.window="open = true"
     style="position: fixed; inset: 0; z-index: 9999; pointer-events: none;">

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         style="position: absolute; inset: 0; background: rgba(0,0,0,.45); pointer-events: auto;"
         x-cloak></div>

    {{-- Dialog --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 translate-y-[-8px]"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-[-8px]"
         style="position: absolute; top: 15%; left: 50%; transform: translateX(-50%); width: 640px; max-width: calc(100vw - 2rem); pointer-events: auto; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.35);"
         x-cloak>

        {{-- Input --}}
        <div style="background: var(--surface); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; padding: 14px 16px;">
            <svg style="width:18px;height:18px;flex-shrink:0;color:var(--text3);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
            <input x-ref="input" x-model="query"
                   @input.debounce.200ms="search()"
                   @keydown.arrow-down.prevent="moveDown()"
                   @keydown.arrow-up.prevent="moveUp()"
                   @keydown.enter.prevent="navigate()"
                   placeholder="Rechercher deal, contact, entreprise…"
                   autocomplete="off" spellcheck="false"
                   style="flex:1; background:transparent; border:none; outline:none; font-size:16px; color:var(--text);">
            <button @click="open = false" style="color:var(--text3);cursor:pointer;background:none;border:none;padding:0;">
                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Results --}}
        <div style="background: var(--surface); max-height: 420px; overflow-y: auto; padding: 8px 0;">

            {{-- Loading --}}
            <template x-if="loading">
                <div style="padding: 24px; text-align: center; color: var(--text3); font-size: 13px;">Recherche…</div>
            </template>

            {{-- Empty query --}}
            <template x-if="!loading && query.length < 2 && !hasResults()">
                <div style="padding: 24px; text-align: center; color: var(--text3); font-size: 13px;">
                    Tapez au moins 2 caractères pour rechercher.
                </div>
            </template>

            {{-- No results --}}
            <template x-if="!loading && query.length >= 2 && !hasResults()">
                <div style="padding: 24px; text-align: center; color: var(--text3); font-size: 13px;">
                    Aucun résultat pour "<span x-text="query"></span>"
                </div>
            </template>

            {{-- Contacts --}}
            <template x-if="results.contacts && results.contacts.length > 0">
                <div>
                    <div style="padding: 6px 16px 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text3);">Contacts</div>
                    <template x-for="(item, i) in results.contacts" :key="'c'+item.id">
                        <a :href="item.url"
                           @click="open = false"
                           :data-idx="flatIndex(item, 'contacts', i)"
                           :style="activeIdx === flatIndex(item, 'contacts', i) ? 'background: var(--surface2);' : ''"
                           @mouseenter="activeIdx = flatIndex(item, 'contacts', i)"
                           style="display: flex; align-items: center; gap: 10px; padding: 8px 16px; text-decoration: none; color: inherit; cursor: pointer; border-radius: 0;">
                            <svg style="width:15px;height:15px;flex-shrink:0;color:var(--text3);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.label"></div>
                                <div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.sub"></div>
                            </div>
                        </a>
                    </template>
                </div>
            </template>

            {{-- Entreprises --}}
            <template x-if="results.companies && results.companies.length > 0">
                <div>
                    <div style="padding: 6px 16px 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text3);">Entreprises</div>
                    <template x-for="(item, i) in results.companies" :key="'co'+item.id">
                        <a :href="item.url"
                           @click="open = false"
                           :style="activeIdx === flatIndex(item, 'companies', i) ? 'background: var(--surface2);' : ''"
                           @mouseenter="activeIdx = flatIndex(item, 'companies', i)"
                           style="display: flex; align-items: center; gap: 10px; padding: 8px 16px; text-decoration: none; color: inherit; cursor: pointer;">
                            <svg style="width:15px;height:15px;flex-shrink:0;color:var(--text3);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 21h18M5 21V7l7-4 7 4v14"/>
                            </svg>
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.label"></div>
                                <div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.sub"></div>
                            </div>
                        </a>
                    </template>
                </div>
            </template>

            {{-- Deals --}}
            <template x-if="results.deals && results.deals.length > 0">
                <div>
                    <div style="padding: 6px 16px 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text3);">Deals</div>
                    <template x-for="(item, i) in results.deals" :key="'d'+item.id">
                        <a :href="item.url"
                           @click="open = false"
                           :style="activeIdx === flatIndex(item, 'deals', i) ? 'background: var(--surface2);' : ''"
                           @mouseenter="activeIdx = flatIndex(item, 'deals', i)"
                           style="display: flex; align-items: center; gap: 10px; padding: 8px 16px; text-decoration: none; color: inherit; cursor: pointer;">
                            <svg style="width:15px;height:15px;flex-shrink:0;color:var(--text3);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 7h18M3 12h18M3 17h12"/>
                            </svg>
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.label"></div>
                                <div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.sub"></div>
                            </div>
                        </a>
                    </template>
                </div>
            </template>

            {{-- Voir tous les résultats --}}
            <template x-if="!loading && query.length >= 2 && hasResults()">
                <div style="padding: 6px 16px 8px; border-top: 1px solid var(--border); margin-top: 4px;">
                    <a :href="'/search?q=' + encodeURIComponent(query)"
                       @click="open = false"
                       style="font-size:12px;color:var(--accent);text-decoration:none;display:flex;align-items:center;gap:6px;">
                        Voir tous les résultats pour "<span x-text="query"></span>"
                        <svg style="width:12px;height:12px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function cmdPalette() {
    return {
        open: false,
        query: '',
        results: { contacts: [], companies: [], deals: [] },
        loading: false,
        activeIdx: -1,

        init() {
            this.$watch('open', val => {
                if (val) {
                    this.query = '';
                    this.results = { contacts: [], companies: [], deals: [] };
                    this.activeIdx = -1;
                    this.$nextTick(() => this.$refs.input?.focus());
                }
            });
        },

        async search() {
            if (this.query.length < 2) {
                this.results = { contacts: [], companies: [], deals: [] };
                return;
            }
            this.loading = true;
            this.activeIdx = -1;
            try {
                const r = await fetch('/search/quick?q=' + encodeURIComponent(this.query), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                this.results = await r.json();
            } finally {
                this.loading = false;
            }
        },

        hasResults() {
            return (this.results.contacts?.length || 0) +
                   (this.results.companies?.length || 0) +
                   (this.results.deals?.length || 0) > 0;
        },

        flatIndex(item, group, i) {
            const cLen = this.results.contacts?.length || 0;
            const coLen = this.results.companies?.length || 0;
            if (group === 'contacts')  return i;
            if (group === 'companies') return cLen + i;
            return cLen + coLen + i;
        },

        allItems() {
            return [
                ...(this.results.contacts  || []),
                ...(this.results.companies || []),
                ...(this.results.deals     || []),
            ];
        },

        moveDown() {
            const total = this.allItems().length;
            this.activeIdx = total ? (this.activeIdx + 1) % total : -1;
        },

        moveUp() {
            const total = this.allItems().length;
            this.activeIdx = total ? (this.activeIdx - 1 + total) % total : -1;
        },

        navigate() {
            const items = this.allItems();
            if (this.activeIdx >= 0 && items[this.activeIdx]) {
                window.location.href = items[this.activeIdx].url;
                this.open = false;
            } else if (this.query.length >= 2) {
                window.location.href = '/search?q=' + encodeURIComponent(this.query);
                this.open = false;
            }
        },
    };
}
</script>
