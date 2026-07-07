@props([
    'entityType' => 'contact',
    'entityId'   => null,
])

<div x-data="{
    open: false,
    loading: false,
    subject: '',
    body: '',
    error: '',
    intent: '',
    entityType: '{{ $entityType }}',
    entityId: {{ (int) $entityId }},
    intents: [
        { value: '',                       label: 'Prise de contact' },
        { value: 'relance',                label: 'Relance' },
        { value: 'suivi',                  label: 'Suivi après échange' },
        { value: 'proposition commerciale',label: 'Proposition commerciale' },
        { value: 'remerciement',           label: 'Remerciement' },
    ],
    copiedSubject: false,
    copiedBody: false,
    copiedAll: false,
    templates: [],
    selectedTemplate: '',

    async fetchTemplates() {
        try {
            const resp = await fetch('/email-templates/options', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!resp.ok) return;
            this.templates = await resp.json();
        } catch (e) { /* silencieux : la fonctionnalité reste optionnelle */ }
    },

    async applyTemplate() {
        if (!this.selectedTemplate) return;
        this.error = '';
        try {
            const payload = {};
            if (this.entityType === 'contact') payload.contact_id = this.entityId;
            else payload.deal_id = this.entityId;

            const resp = await fetch('/email-templates/' + this.selectedTemplate + '/render', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const json = await resp.json();
            if (!resp.ok) { this.error = json.message ?? 'Erreur serveur'; return; }
            this.subject = json.subject ?? '';
            this.body    = json.body ?? '';
        } catch (e) {
            this.error = 'Impossible de charger le modèle.';
        }
    },

    async generate() {
        this.loading = true;
        this.error   = '';
        this.subject = '';
        this.body    = '';
        try {
            const payload = { intent: this.intent };
            if (this.entityType === 'contact') payload.contact_id = this.entityId;
            else payload.deal_id = this.entityId;

            const resp = await fetch('/web/ai/draft-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const json = await resp.json();
            if (!resp.ok) { this.error = json.message ?? 'Erreur serveur'; return; }
            this.subject = json.subject ?? '';
            this.body    = json.body ?? '';
        } catch (e) {
            this.error = 'Impossible de contacter le serveur.';
        } finally {
            this.loading = false;
        }
    },

    copy(text, flag) {
        navigator.clipboard.writeText(text).then(() => {
            this[flag] = true;
            setTimeout(() => { this[flag] = false; }, 2000);
        });
    },
}"
     @open-email-draft-modal.window="open = true; fetchTemplates()"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center"
     style="background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false">

    <div class="card p-6 w-full max-w-lg" @click.stop style="max-height: 90vh; display: flex; flex-direction: column; gap: 0;">

        {{-- En-tête --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <svg class="ic" style="width:15px;height:15px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                <h2 class="text-base font-semibold">Rédiger un email</h2>
                <span class="chip" style="font-size:9px; padding:1px 6px; background:var(--accent-soft,rgba(99,102,241,.1)); color:var(--accent);">IA</span>
            </div>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Partir d'un modèle (optionnel) --}}
        <div class="field mb-3" x-show="templates.length > 0">
            <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider mb-1 block">Partir d'un modèle</label>
            <select x-model="selectedTemplate" @change="applyTemplate()" class="select-arrow" style="font-size:13px;">
                <option value="">— Aucun (rédaction libre / IA) —</option>
                <template x-for="t in templates" :key="t.id">
                    <option :value="t.id" x-text="t.name + (t.category ? ' · ' + t.category : '')"></option>
                </template>
            </select>
        </div>

        {{-- Sélecteur d'objectif --}}
        <div class="field mb-4">
            <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider mb-1 block">Objectif (IA)</label>
            <select x-model="intent" class="select-arrow" style="font-size:13px;">
                <template x-for="opt in intents" :key="opt.value">
                    <option :value="opt.value" x-text="opt.label"></option>
                </template>
            </select>
        </div>

        {{-- État initial : bouton Générer --}}
        <template x-if="!subject && !body && !loading && !error">
            <button @click="generate()" class="btn primary w-full justify-center">
                <svg class="ic" style="width:12px;height:12px;" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Générer l'email
            </button>
        </template>

        {{-- Chargement --}}
        <template x-if="loading">
            <div class="flex items-center justify-center gap-2 py-10 text-secondary text-sm">
                <svg class="ic animate-spin" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                Rédaction en cours…
            </div>
        </template>

        {{-- Erreur --}}
        <div x-show="error && !loading" class="text-sm py-2 mb-1" style="color:var(--err)" x-text="error"></div>

        {{-- Résultat éditable --}}
        <template x-if="(subject || body) && !loading">
            <div class="flex flex-col gap-3 overflow-y-auto" style="flex:1; min-height:0;">

                {{-- Objet --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider">Objet</label>
                        <button type="button"
                                @click="copy(subject, 'copiedSubject')"
                                class="text-[10px] font-mono hover:underline transition-colors"
                                :style="copiedSubject ? 'color:var(--ok)' : 'color:var(--accent)'">
                            <span x-text="copiedSubject ? 'Copié !' : 'Copier'"></span>
                        </button>
                    </div>
                    <input type="text" x-model="subject"
                           class="w-full"
                           style="font-size:13px; padding:8px 10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text);">
                </div>

                {{-- Corps --}}
                <div style="flex:1; display:flex; flex-direction:column; min-height:0;">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider">Corps</label>
                        <button type="button"
                                @click="copy(body, 'copiedBody')"
                                class="text-[10px] font-mono hover:underline transition-colors"
                                :style="copiedBody ? 'color:var(--ok)' : 'color:var(--accent)'">
                            <span x-text="copiedBody ? 'Copié !' : 'Copier'"></span>
                        </button>
                    </div>
                    <textarea x-model="body"
                              rows="9"
                              style="flex:1; font-size:12px; line-height:1.65; resize:vertical; padding:8px 10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); font-family:var(--font-mono, monospace);"></textarea>
                </div>

                {{-- Barre d'actions --}}
                <div class="flex items-center justify-between pt-3 border-t border-default">
                    <button type="button" @click="generate()" class="btn ghost sm" :disabled="loading">
                        <svg class="ic" style="width:11px;height:11px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                        Régénérer
                    </button>
                    <button type="button"
                            @click="copy('Objet : ' + subject + '\n\n' + body, 'copiedAll')"
                            class="btn sm"
                            :class="copiedAll ? 'ok' : ''">
                        <svg class="ic" style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path x-show="!copiedAll" d="M8 17.75a3 3 0 0 1-3-3V5.5a3 3 0 0 1 3-3h5.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H8z"/>
                            <path x-show="!copiedAll" d="M16 8h2.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H13a3 3 0 0 1-3-3V17.75"/>
                            <path x-show="copiedAll" d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5"/>
                        </svg>
                        <span x-text="copiedAll ? 'Copié !' : 'Tout copier'"></span>
                    </button>
                </div>
            </div>
        </template>

    </div>
</div>
