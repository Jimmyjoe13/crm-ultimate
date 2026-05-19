<x-app-shell active="{{ $entityType === 'company' ? 'companies' : 'contacts' }}" breadcrumb="{{ $entityType === 'company' ? 'Entreprises' : 'Contacts' }} / Importer CSV">

<div x-data="csvImporter('{{ $entityType }}')" class="px-7 pt-6 pb-12 max-w-3xl">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ $entityType === 'company' ? '/companies' : '/contacts' }}" class="btn ghost icon">
            <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1>Importer {{ $entityType === 'company' ? 'des entreprises' : ($entityType === 'deal' ? 'des deals' : 'des contacts') }}</h1>
            <p class="text-sm text-secondary mt-0.5">Import CSV en 3 étapes</p>
        </div>
    </div>

    {{-- Stepper --}}
    <x-import-stepper />

    {{-- ───── STEP 1: Upload ───── --}}
    <div x-show="step === 1" class="card p-6">
        <div class="mono-label mb-4">Sélectionner le fichier CSV</div>

        <div class="field mb-4">
            <label>Type d'entité</label>
            <select x-model="entityType" class="select-arrow">
                <option value="contact">Contacts</option>
                <option value="company">Entreprises</option>
                <option value="deal">Deals</option>
            </select>
        </div>

        <div class="field mb-4">
            <label>Fichier CSV *</label>
            <div
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="handleDrop($event)"
                :class="dragging ? 'border-[var(--accent)]' : 'border-[var(--border)]'"
                class="border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors"
                @click="$refs.fileInput.click()"
            >
                <template x-if="!file">
                    <div>
                        <svg class="ic mx-auto mb-2" style="width:28px;height:28px;color:var(--text3);" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <div class="text-sm text-secondary">Glissez un fichier ici ou <span style="color:var(--accent)">parcourir</span></div>
                        <div class="text-[11.5px] text-tertiary font-mono mt-1">CSV ou TXT · encodage UTF-8</div>
                    </div>
                </template>
                <template x-if="file">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="ic" style="color:var(--ok);" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <span class="font-mono text-sm" x-text="file.name"></span>
                        <span class="text-tertiary text-[11px]" x-text="formatSize(file.size)"></span>
                    </div>
                </template>
            </div>
            <input type="file" accept=".csv,.txt" x-ref="fileInput" @change="handleFile($event)" style="display:none;">
        </div>

        <template x-if="uploadError">
            <div class="chip err px-3 py-2 rounded-lg mb-4 text-sm" x-text="uploadError"></div>
        </template>

        <div class="flex justify-end gap-2">
            <a href="{{ $entityType === 'company' ? '/companies' : '/contacts' }}" class="btn">Annuler</a>
            <button @click="uploadAndPreview()" :disabled="!file || uploading" class="btn primary">
                <span x-show="!uploading">Analyser →</span>
                <span x-show="uploading">Analyse en cours…</span>
            </button>
        </div>
    </div>

    {{-- ───── STEP 2: Mapping ───── --}}
    <div x-show="step === 2" class="flex flex-col gap-4">

        {{-- Aperçu --}}
        <div class="card p-5">
            <div class="mono-label mb-1">Aperçu du fichier</div>
            <div class="text-[11.5px] text-tertiary font-mono mb-3">
                <span x-text="headers.length"></span> colonnes détectées ·
                <span x-text="sampleRows.length"></span> lignes d'exemple
            </div>
            <div class="overflow-x-auto rounded border border-default">
                <table class="t text-[11.5px]">
                    <thead>
                        <tr>
                            <template x-for="h in headers" :key="h">
                                <th x-text="h" class="font-mono text-[11px]"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, ri) in sampleRows" :key="ri">
                            <tr>
                                <template x-for="(cell, ci) in row" :key="ci">
                                    <td x-text="cell || '—'" class="text-tertiary max-w-[120px] truncate"></td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mapping --}}
        <div class="card p-5">
            <div class="flex items-baseline justify-between mb-1">
                <div class="mono-label">Mapping des colonnes</div>
                <div class="text-[11px] font-mono text-tertiary">
                    <span x-text="mappedCount()"></span> / <span x-text="headers.length"></span> mappées
                </div>
            </div>
            <div class="text-[12px] text-secondary mb-4">
                Associez chaque colonne CSV à un champ CRM.
                <span class="font-medium" style="color:var(--err)" x-show="missingRequired().length > 0"
                      x-text="'Champs requis manquants : ' + missingRequired().map(f => fieldLabel(f)).join(', ')"></span>
            </div>

            <div class="flex flex-col divide-y divide-[var(--border)]">
                <template x-for="header in headers" :key="header">
                    <div class="flex items-center gap-3 py-2.5">
                        {{-- Colonne CSV --}}
                        <div class="w-32 flex-shrink-0">
                            <div class="font-mono text-[12px] truncate" x-text="header"></div>
                            <div class="text-[10.5px] text-tertiary">CSV</div>
                        </div>
                        {{-- Flèche --}}
                        <svg class="ic flex-shrink-0" style="width:14px;height:14px;color:var(--text3);" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        {{-- Select champ CRM --}}
                        <select class="select-arrow text-[12px] flex-1"
                                :value="mapping[header] ?? ''"
                                @change="mapping[header] = $event.target.value || null">
                            <option value="">— Ignorer —</option>
                            <template x-for="f in availableFields" :key="f.key">
                                <option :value="f.key"
                                        :selected="mapping[header] === f.key"
                                        x-text="f.label + (f.type === 'custom' ? ' ✦' : '') + (f.required ? ' *' : '')"></option>
                            </template>
                        </select>
                        {{-- Badge état --}}
                        <div class="w-24 flex-shrink-0 text-right">
                            <template x-if="mappingState(header) === 'auto'">
                                <span class="chip text-[10.5px] px-2" style="background:var(--ok-soft);color:var(--ok)">auto</span>
                            </template>
                            <template x-if="mappingState(header) === 'manual'">
                                <span class="chip text-[10.5px] px-2" style="background:var(--info-soft);color:var(--info)">manuel</span>
                            </template>
                            <template x-if="mappingState(header) === 'ignored'">
                                <span class="chip text-[10.5px] px-2">ignoré</span>
                            </template>
                            <template x-if="mappingState(header) === 'missing-required'">
                                <span class="chip err text-[10.5px] px-2">requis !</span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="text-[11px] text-tertiary font-mono mt-3">✦ champ personnalisé · * obligatoire</div>
        </div>

        {{-- Stratégie doublons --}}
        <div class="card p-5">
            <div class="mono-label mb-3">Si la ligne existe déjà</div>
            <div class="flex flex-col gap-2.5 text-[13px]">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio" x-model="duplicateStrategy" value="skip" class="mt-0.5">
                    <div>
                        <div class="font-medium">Ignorer <span class="chip text-[10.5px] ml-1">défaut</span></div>
                        <div class="text-[11.5px] text-tertiary">La ligne existante n'est pas modifiée</div>
                    </div>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio" x-model="duplicateStrategy" value="update" class="mt-0.5">
                    <div>
                        <div class="font-medium">Mettre à jour</div>
                        <div class="text-[11.5px] text-tertiary">Les champs de la ligne CSV écrasent les valeurs existantes</div>
                    </div>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio" x-model="duplicateStrategy" value="create" class="mt-0.5">
                    <div>
                        <div class="font-medium">Créer quand même</div>
                        <div class="text-[11.5px] text-tertiary">Insère une nouvelle ligne même si un doublon existe</div>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex justify-between gap-2">
            <button @click="step = 1" class="btn">← Retour</button>
            <button @click="launchImport()"
                    :disabled="!canSubmit() || importing"
                    :title="missingRequired().length ? 'Champs requis non mappés : ' + missingRequired().map(f => fieldLabel(f)).join(', ') : ''"
                    class="btn primary">
                <span x-show="!importing">Lancer l'import →</span>
                <span x-show="importing">Lancement…</span>
            </button>
        </div>
    </div>

    {{-- ───── STEP 3: Progress / Done ───── --}}
    <div x-show="step === 3" class="card p-8 text-center">
        <template x-if="jobStatus === 'pending' || jobStatus === 'processing'">
            <div>
                <div class="text-4xl mb-3">⏳</div>
                <div class="font-semibold mb-1">Import en cours…</div>
                <div class="text-sm text-secondary font-mono" x-text="jobProgress"></div>
            </div>
        </template>
        <template x-if="jobStatus === 'completed' || jobStatus === 'completed_with_errors'">
            <div>
                <div class="text-4xl mb-3" x-text="jobStatus === 'completed' ? '✅' : '⚠️'"></div>
                <div class="font-semibold mb-2" x-text="jobStatus === 'completed' ? 'Import terminé !' : 'Import terminé avec erreurs'"></div>
                <div class="text-sm text-secondary font-mono mb-1" x-text="jobProgress"></div>
                <template x-if="jobErrors.length > 0">
                    <div class="text-left mt-4 text-[11.5px] font-mono text-[var(--err)] bg-[var(--surface2)] rounded p-3 max-h-32 overflow-auto">
                        <template x-for="(e, ei) in jobErrors.slice(0,10)" :key="ei">
                            <div x-text="'Ligne ' + e.row + ' : ' + e.message"></div>
                        </template>
                        <template x-if="jobErrors.length > 10">
                            <div x-text="'… et ' + (jobErrors.length - 10) + ' autres erreurs'"></div>
                        </template>
                    </div>
                </template>
                <div class="flex justify-center gap-2 mt-5">
                    <a :href="entityType === 'company' ? '/companies' : (entityType === 'deal' ? '/deals' : '/contacts')" class="btn primary">
                        Voir les résultats
                    </a>
                    <button @click="resetWizard()" class="btn">Nouvel import</button>
                </div>
            </div>
        </template>
        <template x-if="jobStatus === 'failed'">
            <div>
                <div class="text-4xl mb-3">❌</div>
                <div class="font-semibold mb-2">Échec de l'import</div>
                <div class="text-[12px] text-secondary font-mono">Voir les erreurs ci-dessous</div>
                <template x-if="jobErrors.length > 0">
                    <div class="text-left mt-4 text-[11.5px] font-mono text-[var(--err)] bg-[var(--surface2)] rounded p-3 max-h-32 overflow-auto">
                        <template x-for="(e, ei) in jobErrors.slice(0,10)" :key="ei">
                            <div x-text="'Ligne ' + e.row + ' : ' + e.message"></div>
                        </template>
                    </div>
                </template>
                <button @click="step = 2" class="btn mt-4">← Retour au mapping</button>
            </div>
        </template>
    </div>

</div>

<script>
function csvImporter(initEntityType) {
    return {
        step: 1,
        entityType: initEntityType,
        file: null,
        dragging: false,
        uploading: false,
        uploadError: null,

        // Step 2 data
        previewToken: null,
        headers: [],
        sampleRows: [],
        availableFields: [],
        requiredFields: [],
        mapping: {},
        autoMapping: {},   // snapshot of server auto_mapping for state detection
        duplicateStrategy: 'skip',
        importing: false,

        // Step 3 data
        jobId: null,
        jobStatus: null,
        jobProgress: '',
        jobErrors: [],
        pollTimer: null,

        // ── State helpers ──

        mappingState(header) {
            const val = this.mapping[header];
            if (!val) {
                // Is this header required?
                const autoVal = this.autoMapping[header];
                if (autoVal && this.requiredFields.includes(autoVal)) return 'missing-required';
                // Check if the column was originally auto-mapped to a required field
                return 'ignored';
            }
            if (this.requiredFields.includes(val) && !val) return 'missing-required';
            if (this.autoMapping[header] === val) return 'auto';
            return 'manual';
        },

        missingRequired() {
            const mapped = Object.values(this.mapping).filter(Boolean);
            return this.requiredFields.filter(f => !mapped.includes(f));
        },

        canSubmit() {
            return this.missingRequired().length === 0 && !this.importing;
        },

        mappedCount() {
            return Object.values(this.mapping).filter(Boolean).length;
        },

        fieldLabel(key) {
            const f = this.availableFields.find(f => f.key === key);
            return f ? f.label : key;
        },

        // ── File handling ──

        handleDrop(e) {
            this.dragging = false;
            const f = e.dataTransfer.files[0];
            if (f) this.file = f;
        },

        handleFile(e) {
            this.file = e.target.files[0] || null;
        },

        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' o';
            if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' Ko';
            return (bytes / 1024 / 1024).toFixed(1) + ' Mo';
        },

        // ── Step 1 → 2 ──

        async uploadAndPreview() {
            if (!this.file) return;
            this.uploading = true;
            this.uploadError = null;

            const fd = new FormData();
            fd.append('entity_type', this.entityType);
            fd.append('file', this.file);

            try {
                const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
                const r = await fetch('/imports/preview', {
                    method: 'POST',
                    headers: { 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                if (!r.ok) {
                    const e = await r.json();
                    this.uploadError = e.message ?? 'Erreur lors de l\'analyse.';
                    return;
                }
                const data = await r.json();
                this.previewToken    = data.preview_token;
                this.headers         = data.headers;
                this.sampleRows      = data.sample_rows;
                this.availableFields = data.available_fields;
                this.requiredFields  = data.required_fields ?? [];
                this.autoMapping     = { ...data.auto_mapping };
                this.mapping         = { ...data.auto_mapping };
                this.step = 2;
            } catch (e) {
                this.uploadError = 'Erreur réseau.';
            } finally {
                this.uploading = false;
            }
        },

        // ── Step 2 → 3 ──

        async launchImport() {
            const missing = this.missingRequired();
            if (missing.length > 0) {
                window.toast('Champs requis non mappés : ' + missing.map(f => this.fieldLabel(f)).join(', '), 'error');
                return;
            }
            this.importing = true;
            try {
                const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
                const r = await fetch('/imports', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        entity_type:        this.entityType,
                        preview_token:      this.previewToken,
                        mapping:            this.mapping,
                        duplicate_strategy: this.duplicateStrategy,
                    }),
                });
                const data = await r.json();
                if (!r.ok) {
                    if (data.missing) {
                        window.toast('Champs requis : ' + data.missing.map(f => this.fieldLabel(f)).join(', '), 'error');
                    } else {
                        window.toast(data.message ?? 'Erreur lors du lancement.', 'error');
                    }
                    return;
                }
                this.jobId = data.id;
                this.jobStatus = 'pending';
                this.step = 3;
                this.pollStatus();
            } catch(e) {
                window.toast('Erreur réseau.', 'error');
            } finally {
                this.importing = false;
            }
        },

        // ── Polling ──

        async pollStatus() {
            if (!this.jobId) return;
            try {
                const r = await fetch('/imports/' + this.jobId + '/status', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                const prevStatus = this.jobStatus;
                this.jobErrors = d.errors ?? [];

                if (d.total_rows > 0) {
                    this.jobProgress = (d.processed_rows ?? 0) + ' / ' + d.total_rows + ' lignes'
                        + (d.duplicates_skipped > 0 ? ' · ' + d.duplicates_skipped + ' doublons ignorés' : '')
                        + (d.failed_rows > 0 ? ' · ' + d.failed_rows + ' erreur(s)' : '');
                } else {
                    this.jobProgress = 'En attente…';
                }

                this.jobStatus = d.status;

                if ((d.status === 'completed' || d.status === 'completed_with_errors') && prevStatus !== d.status) {
                    window.toast('Import terminé ! ' + this.jobProgress, d.status === 'completed' ? 'success' : 'warning');
                } else if (d.status === 'failed' && prevStatus !== 'failed') {
                    window.toast('Échec de l\'import.', 'error');
                }
            } catch(e) {
                // ignore transient errors
            }

            if (this.jobStatus === 'pending' || this.jobStatus === 'processing') {
                this.pollTimer = setTimeout(() => this.pollStatus(), 1500);
            }
        },

        resetWizard() {
            clearTimeout(this.pollTimer);
            this.step = 1;
            this.file = null;
            this.previewToken = null;
            this.headers = [];
            this.sampleRows = [];
            this.availableFields = [];
            this.requiredFields = [];
            this.autoMapping = {};
            this.mapping = {};
            this.duplicateStrategy = 'skip';
            this.jobId = null;
            this.jobStatus = null;
            this.jobProgress = '';
            this.jobErrors = [];
        },
    };
}
</script>

</x-app-shell>
