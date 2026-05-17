<x-app-shell active="{{ $entityType === 'company' ? 'companies' : 'contacts' }}" breadcrumb="{{ $entityType === 'company' ? 'Entreprises' : 'Contacts' }} / Importer CSV">

<div x-data="csvImporter('{{ $entityType }}')" class="px-7 pt-6 pb-12 max-w-3xl">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ $entityType === 'company' ? '/companies' : '/contacts' }}" class="btn ghost icon">
            <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1>Importer {{ $entityType === 'company' ? 'des entreprises' : 'des contacts' }}</h1>
            <p class="text-sm text-secondary mt-0.5">Import CSV en 3 étapes</p>
        </div>
    </div>

    {{-- Stepper --}}
    <div class="flex items-center gap-0 mb-6">
        <template x-for="(label, i) in ['Fichier', 'Mapping', 'Import']" :key="i">
            <div class="flex items-center">
                <div class="flex items-center gap-1.5">
                    <div :class="step > i+1 ? 'bg-[var(--ok)] text-white' : step === i+1 ? 'bg-[var(--accent)] text-white' : 'bg-[var(--surface2)] text-tertiary'"
                         class="w-6 h-6 rounded-full text-[11px] font-mono font-bold flex items-center justify-center flex-shrink-0"
                         x-text="step > i+1 ? '✓' : i+1"></div>
                    <span :class="step === i+1 ? 'text-primary' : 'text-tertiary'"
                          class="text-[12px] font-mono" x-text="label"></span>
                </div>
                <div x-show="i < 2" class="w-8 h-px bg-[var(--border)] mx-2"></div>
            </div>
        </template>
    </div>

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
                        <svg class="ic mx-auto mb-2" style="width:28px;height:28px;color:var(--text-tertiary);" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <div class="text-sm text-secondary">Glissez un fichier ici ou <span class="text-[var(--accent)]">parcourir</span></div>
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

        <div class="card p-5">
            <div class="mono-label mb-3">Mapping des colonnes</div>
            <div class="text-[12px] text-secondary mb-4">Associez chaque colonne CSV à un champ CRM. Les colonnes sans mapping seront ignorées.</div>

            <div class="flex flex-col gap-2">
                <template x-for="header in headers" :key="header">
                    <div class="flex items-center gap-3">
                        <div class="font-mono text-[12px] text-secondary flex-1 truncate" x-text="header"></div>
                        <svg class="ic flex-shrink-0" style="width:14px;height:14px;color:var(--text-tertiary);" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        <select class="select-arrow text-[12px] flex-1"
                                @change="mapping[header] = $event.target.value">
                            <option value="" :selected="!mapping[header]">— Ignorer —</option>
                            <template x-for="f in availableFields" :key="f.key">
                                <option :value="f.key"
                                        :selected="mapping[header] === f.key"
                                        x-text="f.label + (f.type === 'custom' ? ' *' : '')"></option>
                            </template>
                        </select>
                    </div>
                </template>
            </div>

            <div class="text-[11px] text-tertiary font-mono mt-3">* champ personnalisé</div>
        </div>

        <div class="flex justify-between gap-2">
            <button @click="step = 1" class="btn">← Retour</button>
            <button @click="launchImport()" :disabled="importing" class="btn primary">
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
        <template x-if="jobStatus === 'done'">
            <div>
                <div class="text-4xl mb-3">✅</div>
                <div class="font-semibold mb-2">Import terminé !</div>
                <div class="text-sm text-secondary font-mono mb-1" x-text="jobProgress"></div>
                <template x-if="jobErrors.length > 0">
                    <div class="text-left mt-4 text-[11.5px] font-mono text-[var(--err)] bg-[var(--surface2)] rounded p-3 max-h-32 overflow-auto">
                        <template x-for="e in jobErrors.slice(0,10)" :key="e">
                            <div x-text="e"></div>
                        </template>
                        <template x-if="jobErrors.length > 10">
                            <div x-text="'… et ' + (jobErrors.length - 10) + ' autres erreurs'"></div>
                        </template>
                    </div>
                </template>
                <div class="flex justify-center gap-2 mt-5">
                    <a :href="entityType === 'company' ? '/companies' : '/contacts'" class="btn primary">
                        Voir les {{ $entityType === 'company' ? 'entreprises' : 'contacts' }}
                    </a>
                    <button @click="resetWizard()" class="btn">Nouvel import</button>
                </div>
            </div>
        </template>
        <template x-if="jobStatus === 'failed'">
            <div>
                <div class="text-4xl mb-3">❌</div>
                <div class="font-semibold mb-2">Échec de l'import</div>
                <div class="text-[12px] text-secondary font-mono" x-text="jobErrors.join(', ')"></div>
                <button @click="step = 2" class="btn mt-4">Réessayer</button>
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
        mapping: {},
        importing: false,

        // Step 3 data
        jobId: null,
        jobStatus: null,
        jobProgress: '',
        jobErrors: [],
        pollTimer: null,

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
                this.previewToken = data.preview_token;
                this.headers      = data.headers;
                this.sampleRows   = data.sample_rows;
                this.availableFields = data.available_fields;
                this.mapping      = { ...data.auto_mapping };
                this.step = 2;
            } catch (e) {
                this.uploadError = 'Erreur réseau.';
            } finally {
                this.uploading = false;
            }
        },

        async launchImport() {
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
                        entity_type:   this.entityType,
                        preview_token: this.previewToken,
                        mapping:       this.mapping,
                    }),
                });
                const data = await r.json();
                if (!r.ok) {
                    alert(data.message ?? 'Erreur lors du lancement.');
                    return;
                }
                this.jobId = data.id;
                this.jobStatus = 'pending';
                this.step = 3;
                this.pollStatus();
            } catch(e) {
                alert('Erreur réseau.');
            } finally {
                this.importing = false;
            }
        },

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
                        + (d.failed_rows > 0 ? ' · ' + d.failed_rows + ' erreurs' : '');
                } else {
                    this.jobProgress = 'En attente…';
                }

                // Toasts sur transition de statut
                if (d.status === 'done' && prevStatus !== 'done') {
                    window.toast('Import terminé ! ' + this.jobProgress, 'success');
                } else if (d.status === 'failed' && prevStatus !== 'failed') {
                    window.toast('Échec de l\'import.', 'error');
                }

                this.jobStatus = d.status;
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
            this.mapping = {};
            this.jobId = null;
            this.jobStatus = null;
            this.jobProgress = '';
            this.jobErrors = [];
        },
    };
}
</script>

</x-app-shell>
