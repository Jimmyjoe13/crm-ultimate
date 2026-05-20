<x-app-shell active="{{ $entityType === 'company' ? 'companies' : 'contacts' }}" breadcrumb="{{ $entityType === 'company' ? 'Entreprises' : 'Contacts' }} / Importer CSV">

<div x-data="csvImporter('{{ $entityType }}')" class="px-7 pt-6 pb-12">

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
    <div x-show="step === 1" class="card p-6 max-w-xl">
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

    {{-- ───── STEP 2: Mapping HubSpot-style ───── --}}
    <div x-show="step === 2" class="flex gap-5 items-start">

        {{-- LEFT: Mapping cards --}}
        <div class="flex flex-col gap-3 flex-1 min-w-0">

            {{-- Info bar --}}
            <div class="flex items-center justify-between">
                <p class="text-[12.5px] text-secondary">
                    Associez chaque colonne CSV à un champ CRM.
                    <span class="font-mono"><span x-text="mappedCount()"></span> / <span x-text="columns.length"></span> colonnes mappées</span>
                </p>
                <span x-show="missingRequired().length > 0"
                      class="chip err text-[11px]"
                      x-text="'Requis manquants : ' + missingRequired().map(f => fieldLabel(f)).join(', ')"></span>
            </div>

            {{-- One card per CSV column --}}
            <template x-for="col in columns" :key="col.header">
                <div class="card p-4 transition-colors"
                     :class="{
                        'border border-[var(--ok)]'   : mappingState(col.header) === 'auto',
                        'border border-[var(--info)]' : mappingState(col.header) === 'manual',
                        'border border-[var(--err)]'  : mappingState(col.header) === 'missing-required',
                        'opacity-60'                  : dontImport[col.header]
                     }">
                    <div class="flex items-start gap-3">

                        {{-- Type icon --}}
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5"
                             style="background:var(--surface2)">
                            <span x-html="typeIcon(col.inferred_type)" class="text-tertiary" style="width:15px;height:15px;display:flex;"></span>
                        </div>

                        <div class="flex-1 min-w-0">
                            {{-- Header + badges --}}
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="font-medium text-[13px]" x-text="col.header"></span>
                                <template x-if="mappingState(col.header) === 'auto'">
                                    <span class="chip text-[10px] px-1.5" style="background:var(--ok-soft);color:var(--ok)">auto</span>
                                </template>
                                <template x-if="mappingState(col.header) === 'manual'">
                                    <span class="chip text-[10px] px-1.5" style="background:var(--info-soft);color:var(--info)">modifié</span>
                                </template>
                                <template x-if="mappingState(col.header) === 'missing-required'">
                                    <span class="chip err text-[10px] px-1.5">requis !</span>
                                </template>
                                <template x-if="dontImport[col.header]">
                                    <span class="chip text-[10px] px-1.5">ignoré</span>
                                </template>
                            </div>

                            {{-- Sample values --}}
                            <div class="flex flex-wrap gap-1 mb-3" x-show="col.samples.length > 0">
                                <template x-for="(s, si) in col.samples.slice(0,4)" :key="si">
                                    <span class="font-mono text-[11px] px-2 py-0.5 rounded" style="background:var(--surface2);color:var(--text2)" x-text="s"></span>
                                </template>
                                <template x-if="col.fill_rate < 80">
                                    <span class="text-[10.5px] text-tertiary font-mono" x-text="col.fill_rate + '% rempli'"></span>
                                </template>
                            </div>

                            {{-- Combobox --}}
                            <div x-show="!dontImport[col.header]" class="relative" x-data="{ open: false, search: '' }" @keydown.escape="open=false" @click.outside="open=false">
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer border"
                                     :style="open ? 'border-color:var(--accent);box-shadow:0 0 0 2px var(--accent-soft)' : 'border-color:var(--border)'"
                                     style="background:var(--surface);"
                                     @click="open=!open; if(open) $nextTick(() => $refs['search_' + col.header]?.focus())">
                                    <template x-if="mapping[col.header]">
                                        <div class="flex items-center gap-1.5 flex-1 min-w-0">
                                            <span x-html="typeIcon(fieldType(mapping[col.header]))" class="flex-shrink-0" style="width:13px;height:13px;color:var(--accent)"></span>
                                            <span class="text-[12.5px] font-medium truncate" x-text="fieldLabel(mapping[col.header])"></span>
                                            <template x-if="fieldGroup(mapping[col.header]) === 'custom'">
                                                <span class="chip text-[9.5px] px-1.5 flex-shrink-0" style="background:var(--accent-soft);color:var(--accent)">custom</span>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!mapping[col.header]">
                                        <span class="text-[12.5px] text-tertiary flex-1">— Ne pas importer —</span>
                                    </template>
                                    <svg class="flex-shrink-0 transition-transform" :class="open?'rotate-180':''" style="width:14px;height:14px;color:var(--text3)" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                                </div>

                                {{-- Dropdown --}}
                                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                     class="absolute z-50 mt-1 w-full rounded-xl border shadow-pop"
                                     style="background:var(--surface);border-color:var(--border);display:flex;flex-direction:column;">

                                    {{-- Search input --}}
                                    <div class="px-3 pt-3 pb-2 border-b" style="border-color:var(--borderS)">
                                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg" style="background:var(--surface2)">
                                            <svg style="width:13px;height:13px;color:var(--text3);flex-shrink:0" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                            <input :x-ref="'search_' + col.header"
                                                   x-model="search"
                                                   @click.stop
                                                   @keydown.arrow-down.prevent="focusNext(col.header, 1)"
                                                   @keydown.arrow-up.prevent="focusNext(col.header, -1)"
                                                   @keydown.enter.prevent="selectFocused(col.header)"
                                                   placeholder="Rechercher un champ…"
                                                   class="text-[12px] bg-transparent outline-none flex-1" style="color:var(--text)">
                                        </div>
                                    </div>

                                    {{-- Options list --}}
                                    <div class="overflow-y-auto" style="max-height:210px;" :id="'dd_' + col.header">
                                        {{-- None option --}}
                                        <div @click="mapping[col.header] = null; open = false; search = ''"
                                             class="flex items-center gap-2 px-3 py-2 cursor-pointer text-[12px] text-tertiary"
                                             :class="!mapping[col.header] ? 'font-semibold' : 'hover:bg-[var(--surface2)]'">
                                            <svg style="width:13px;height:13px;opacity:.4" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                            Ne pas importer
                                        </div>

                                        {{-- Grouped fields --}}
                                        <template x-for="grp in filteredGroups(search)" :key="grp.label">
                                            <div>
                                                <div class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wide" style="color:var(--text3);background:var(--surface2)" x-text="grp.label"></div>
                                                <template x-for="f in grp.fields" :key="f.key">
                                                    <div @click="mapping[col.header] = f.key; open = false; search = ''"
                                                         :id="'opt_' + col.header + '_' + f.key"
                                                         class="flex items-center gap-2 px-3 py-2 cursor-pointer text-[12.5px] min-w-0"
                                                         :class="mapping[col.header] === f.key ? 'font-semibold' : 'hover:bg-[var(--surface2)]'">
                                                        <span x-html="typeIcon(f.field_type)" class="flex-shrink-0" style="width:13px;height:13px;color:var(--text3)"></span>
                                                        <span class="truncate" style="flex:1;min-width:0" x-text="f.label"></span>
                                                        <template x-if="f.required">
                                                            <span class="chip text-[9.5px] px-1.5 flex-shrink-0" style="background:var(--err-soft);color:var(--err)">requis</span>
                                                        </template>
                                                        <template x-if="f.type === 'custom' && !f.required">
                                                            <span class="chip text-[9.5px] px-1.5 flex-shrink-0" style="background:var(--accent-soft);color:var(--accent)">✦</span>
                                                        </template>
                                                        <template x-if="mapping[col.header] === f.key">
                                                            <svg style="width:13px;height:13px;color:var(--accent);flex-shrink:0" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- No results --}}
                                        <template x-if="filteredGroups(search).every(g => g.fields.length === 0) && search">
                                            <div class="px-3 py-4 text-center text-[12px] text-tertiary">Aucun champ trouvé</div>
                                        </template>
                                    </div>

                                    {{-- Create custom field --}}
                                    <div class="border-t px-3 py-2.5" style="border-color:var(--borderS)">
                                        <button type="button" @click.stop="openQuickField(col.header)"
                                                class="flex items-center gap-2 text-[12px] w-full rounded-lg px-2 py-1.5 hover:bg-[var(--surface2)] transition-colors"
                                                style="color:var(--accent)">
                                            <svg style="width:13px;height:13px" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                                            Créer une propriété personnalisée
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Don't import message --}}
                            <div x-show="dontImport[col.header]" class="text-[12px] text-tertiary italic py-1">Cette colonne ne sera pas importée.</div>
                        </div>

                        {{-- Don't import toggle --}}
                        <button type="button"
                                @click="toggleDontImport(col.header)"
                                class="flex-shrink-0 mt-0.5 rounded-lg p-1.5 transition-colors"
                                :style="dontImport[col.header] ? 'color:var(--err);background:var(--err-soft)' : 'color:var(--text3)'"
                                :title="dontImport[col.header] ? 'Réactiver' : 'Ne pas importer'"
                                style="hover:background:var(--surface2)">
                            <svg style="width:14px;height:14px" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </template>

            {{-- Back button --}}
            <div class="pt-2">
                <button @click="step = 1" class="btn">← Retour</button>
            </div>
        </div>

        {{-- RIGHT: Sticky panel --}}
        <div class="w-72 flex-shrink-0" style="position:sticky;top:80px">

            {{-- Progress card --}}
            <div class="card p-4 mb-3">
                <div class="mono-label mb-3">Progression</div>
                <div class="flex items-end justify-between mb-1.5">
                    <span class="text-[12px] text-secondary">Colonnes mappées</span>
                    <span class="font-mono text-[13px] font-semibold"><span x-text="mappedCount()"></span> / <span x-text="columns.length"></span></span>
                </div>
                <div class="rounded-full h-1.5 mb-4" style="background:var(--surface2)">
                    <div class="h-1.5 rounded-full transition-all duration-300"
                         style="background:var(--accent)"
                         :style="'width:' + (columns.length ? Math.round(mappedCount() / columns.length * 100) : 0) + '%'"></div>
                </div>

                {{-- Required fields checklist --}}
                <div class="mono-label mb-2">Champs requis</div>
                <div class="flex flex-col gap-1.5">
                    <template x-for="rf in requiredFields" :key="rf">
                        <div class="flex items-center gap-2 text-[12.5px]">
                            <template x-if="isRequiredMapped(rf)">
                                <svg style="width:14px;height:14px;color:var(--ok);flex-shrink:0" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            </template>
                            <template x-if="!isRequiredMapped(rf)">
                                <svg style="width:14px;height:14px;color:var(--err);flex-shrink:0" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                            </template>
                            <span :class="isRequiredMapped(rf) ? 'text-secondary' : 'font-medium'" :style="isRequiredMapped(rf) ? '' : 'color:var(--err)'" x-text="fieldLabel(rf)"></span>
                        </div>
                    </template>
                    <template x-if="requiredFields.length === 0">
                        <div class="text-[11.5px] text-tertiary">Aucun champ obligatoire</div>
                    </template>
                </div>
            </div>

            {{-- Duplicate strategy card --}}
            <div class="card p-4 mb-3">
                <div class="mono-label mb-3">Doublons</div>
                <div class="flex flex-col gap-2.5">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="radio" x-model="duplicateStrategy" value="skip" class="mt-0.5 flex-shrink-0">
                        <div>
                            <div class="text-[12.5px] font-medium">Ignorer</div>
                            <div class="text-[11px] text-tertiary">Conserve la fiche existante</div>
                        </div>
                    </label>
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="radio" x-model="duplicateStrategy" value="update" class="mt-0.5 flex-shrink-0">
                        <div>
                            <div class="text-[12.5px] font-medium">Mettre à jour</div>
                            <div class="text-[11px] text-tertiary">Écrase les champs existants</div>
                        </div>
                    </label>
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="radio" x-model="duplicateStrategy" value="create" class="mt-0.5 flex-shrink-0">
                        <div>
                            <div class="text-[12.5px] font-medium">Créer quand même</div>
                            <div class="text-[11px] text-tertiary">Insère même si doublon</div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Launch button --}}
            <button @click="launchImport()"
                    :disabled="!canSubmit() || importing"
                    class="btn primary w-full"
                    :title="missingRequired().length ? 'Requis non mappés : ' + missingRequired().map(f => fieldLabel(f)).join(', ') : ''">
                <span x-show="!importing">Lancer l'import →</span>
                <span x-show="importing">Lancement…</span>
            </button>

            <p class="text-[11px] text-tertiary text-center mt-2 font-mono">
                Les colonnes ignorées ne seront pas importées
            </p>
        </div>
    </div>

    {{-- ───── STEP 3: Progress / Done ───── --}}
    <div x-show="step === 3" class="card p-8 text-center max-w-xl">
        <template x-if="jobStatus === 'pending' || jobStatus === 'processing'">
            <div>
                <div class="text-4xl mb-3">⏳</div>
                <div class="font-semibold mb-1">Import en cours…</div>
                <div class="text-sm text-secondary font-mono" x-text="jobProgress"></div>
                <div class="mt-4 rounded-full h-1.5" style="background:var(--surface2)">
                    <div class="h-1.5 rounded-full transition-all duration-500 pbar accent"
                         :style="'width:' + importPercent + '%'"></div>
                </div>
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

{{-- Modal "Créer une propriété" --}}
<div x-data x-show="$store.quickField.open"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     style="position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);display:none">
    <div class="card p-6 w-96" @click.stop x-transition:enter="transition ease-out duration-150" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100">
        <div class="flex items-center justify-between mb-4">
            <div class="font-semibold text-[14px]">Nouvelle propriété personnalisée</div>
            <button @click="$store.quickField.close()" class="btn ghost icon sm">
                <svg class="ic" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="field mb-3">
            <label>Nom de la propriété *</label>
            <input type="text" x-model="$store.quickField.label" placeholder="ex: LinkedIn URL" class="field" @keydown.enter="$store.quickField.submit()">
        </div>
        <div class="field mb-3">
            <label>Propriété de</label>
            <select x-model="$store.quickField.entityType" class="select-arrow">
                <template x-if="$store.quickField.availableEntities.includes('contact')">
                    <option value="contact">Contact</option>
                </template>
                <template x-if="$store.quickField.availableEntities.includes('company')">
                    <option value="company">Société</option>
                </template>
                <template x-if="$store.quickField.availableEntities.includes('deal')">
                    <option value="deal">Deal</option>
                </template>
            </select>
        </div>
        <div class="field mb-4">
            <label>Type de champ</label>
            <select x-model="$store.quickField.fieldType" class="select-arrow">
                <option value="text">Texte</option>
                <option value="number">Nombre</option>
                <option value="date">Date</option>
                <option value="boolean">Oui / Non</option>
                <option value="select">Liste de choix</option>
            </select>
        </div>
        <template x-if="$store.quickField.error">
            <div class="chip err px-3 py-2 rounded-lg mb-3 text-sm" x-text="$store.quickField.error"></div>
        </template>
        <div class="flex justify-end gap-2">
            <button @click="$store.quickField.close()" class="btn">Annuler</button>
            <button @click="$store.quickField.submit()" :disabled="$store.quickField.saving" class="btn primary">
                <span x-show="!$store.quickField.saving">Créer</span>
                <span x-show="$store.quickField.saving">Création…</span>
            </button>
        </div>
    </div>
</div>

<script>
// ── Alpine store for the quick-create field modal ──
document.addEventListener('alpine:init', () => {
    Alpine.store('quickField', {
        open: false,
        label: '',
        fieldType: 'text',
        entityType: 'contact',
        availableEntities: ['contact'],
        saving: false,
        error: null,
        _resolve: null,

        show(resolve, defaultLabel = '') {
            this.label = defaultLabel;
            this.fieldType = 'text';
            this.error = null;
            this.saving = false;
            this._resolve = resolve;
            // Propose contact + company when importing contacts, otherwise just the current entity
            const current = window._csvImporterEntityType ?? 'contact';
            this.availableEntities = current === 'contact' ? ['contact', 'company'] : [current];
            this.entityType = current;
            this.open = true;
        },
        close() {
            this.open = false;
            if (this._resolve) this._resolve(null);
        },
        async submit() {
            if (!this.label.trim()) { this.error = 'Le nom est requis.'; return; }
            this.saving = true;
            this.error = null;
            try {
                const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
                const r = await fetch('/imports/quick-field', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ entity_type: this.entityType, label: this.label, field_type: this.fieldType }),
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'Erreur'; return; }
                this.open = false;
                if (this._resolve) this._resolve({ ...data, _createdEntityType: this.entityType });
            } catch { this.error = 'Erreur réseau.'; }
            finally { this.saving = false; }
        },
    });
});

function csvImporter(initEntityType) {
    window._csvImporterEntityType = initEntityType;
    return {
        step: 1,
        entityType: initEntityType,
        file: null,
        dragging: false,
        uploading: false,
        uploadError: null,

        // Step 2 data
        previewToken: null,
        columns: [],          // [{header, samples, fill_rate, inferred_type}]
        availableFields: [],  // [{key, label, type, group, field_type, required}]
        requiredFields: [],
        mapping: {},          // {csvHeader: fieldKey|null}
        autoMapping: {},
        dontImport: {},       // {csvHeader: bool}
        duplicateStrategy: 'skip',
        importing: false,
        _focusedOption: null, // for keyboard nav

        // Step 3 data
        jobId: null,
        jobStatus: null,
        jobProgress: '',
        jobErrors: [],
        importPercent: 0,
        pollTimer: null,

        // ── Computed helpers ──

        get headers() { return this.columns.map(c => c.header); },

        mappingState(header) {
            if (this.dontImport[header]) return 'ignored';
            const val = this.mapping[header];
            if (!val) {
                if (this.requiredFields.includes(this.autoMapping[header])) return 'missing-required';
                return 'ignored';
            }
            if (this.requiredFields.includes(val)) {
                // it's mapped to a required field — good or missing?
            }
            if (this.autoMapping[header] === val) return 'auto';
            return 'manual';
        },

        missingRequired() {
            const mapped = Object.entries(this.mapping)
                .filter(([h, v]) => v && !this.dontImport[h])
                .map(([, v]) => v);
            return this.requiredFields.filter(f => !mapped.includes(f));
        },

        isRequiredMapped(rf) {
            return Object.entries(this.mapping).some(([h, v]) => v === rf && !this.dontImport[h]);
        },

        canSubmit() { return this.missingRequired().length === 0 && !this.importing; },

        mappedCount() {
            return Object.entries(this.mapping).filter(([h, v]) => v && !this.dontImport[h]).length;
        },

        fieldLabel(key) {
            const f = this.availableFields.find(f => f.key === key);
            return f ? f.label : key;
        },

        fieldType(key) {
            const f = this.availableFields.find(f => f.key === key);
            return f ? f.field_type : 'text';
        },

        fieldGroup(key) {
            const f = this.availableFields.find(f => f.key === key);
            return f ? f.group : 'standard';
        },

        filteredGroups(search) {
            const s = (search || '').toLowerCase();
            const groups = [
                { label: 'Champs standard', key: 'standard' },
                { label: 'Société liée', key: 'company' },
                { label: 'Propriétés personnalisées', key: 'custom' },
            ];
            return groups.map(g => ({
                label: g.label,
                fields: this.availableFields.filter(f =>
                    f.group === g.key &&
                    (!s || f.label.toLowerCase().includes(s) || f.key.toLowerCase().includes(s))
                ),
            }));
        },

        typeIcon(type) {
            const icons = {
                email:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
                phone:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l1.02-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
                date:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
                number:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>',
                url:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
                boolean: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="16" cy="12" r="3"/></svg>',
                select:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><path d="M3 6h18M3 12h18M3 18h9"/></svg>',
                text:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:100%;height:100%"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>',
            };
            return icons[type] || icons.text;
        },

        toggleDontImport(header) {
            this.dontImport[header] = !this.dontImport[header];
        },

        focusNext(header, dir) {
            // Keyboard nav placeholder — actual implementation via DOM traversal
        },
        selectFocused(header) {
            // Keyboard nav placeholder
        },

        async openQuickField(header) {
            const field = await new Promise(resolve => Alpine.store('quickField').show(resolve, header));
            if (!field) return;
            const createdFor = field._createdEntityType;
            // Only auto-map and add to dropdown if the field is for the current import entity
            if (createdFor === this.entityType) {
                this.availableFields.push(field);
                this.mapping[header] = field.key;
            }
            const entityLabel = createdFor === 'company' ? 'Société' : (createdFor === 'deal' ? 'Deal' : 'Contact');
            window.toast('Propriété "' + field.label + '" créée pour ' + entityLabel + '.', 'success');
        },

        // ── File handling ──

        handleDrop(e) { this.dragging = false; const f = e.dataTransfer.files[0]; if (f) this.file = f; },
        handleFile(e) { this.file = e.target.files[0] || null; },
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
                if (!r.ok) { const e = await r.json(); this.uploadError = e.message ?? 'Erreur lors de l\'analyse.'; return; }
                const data = await r.json();
                this.previewToken    = data.preview_token;
                this.columns         = data.columns ?? data.headers.map(h => ({ header: h, samples: [], fill_rate: 0, inferred_type: 'text' }));
                this.availableFields = data.available_fields;
                this.requiredFields  = data.required_fields ?? [];
                this.autoMapping     = { ...data.auto_mapping };
                this.mapping         = { ...data.auto_mapping };
                this.dontImport      = {};
                window._csvImporterEntityType = this.entityType;
                this.step = 2;
            } catch { this.uploadError = 'Erreur réseau.'; }
            finally { this.uploading = false; }
        },

        // ── Step 2 → 3 ──

        async launchImport() {
            const missing = this.missingRequired();
            if (missing.length > 0) {
                window.toast('Champs requis non mappés : ' + missing.map(f => this.fieldLabel(f)).join(', '), 'error');
                return;
            }
            this.importing = true;
            // Strip dontImport columns from mapping
            const cleanMapping = {};
            for (const [h, v] of Object.entries(this.mapping)) {
                if (!this.dontImport[h] && v) cleanMapping[h] = v;
            }
            try {
                const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
                const r = await fetch('/imports', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ entity_type: this.entityType, preview_token: this.previewToken, mapping: cleanMapping, duplicate_strategy: this.duplicateStrategy }),
                });
                const data = await r.json();
                if (!r.ok) {
                    if (data.missing) window.toast('Champs requis : ' + data.missing.map(f => this.fieldLabel(f)).join(', '), 'error');
                    else window.toast(data.message ?? 'Erreur lors du lancement.', 'error');
                    return;
                }
                this.jobId = data.id;
                this.jobStatus = 'pending';
                this.importPercent = 0;
                this.step = 3;
                this.pollStatus();
            } catch { window.toast('Erreur réseau.', 'error'); }
            finally { this.importing = false; }
        },

        // ── Polling ──

        async pollStatus() {
            if (!this.jobId) return;
            try {
                const r = await fetch('/imports/' + this.jobId + '/status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await r.json();
                const prevStatus = this.jobStatus;
                this.jobErrors = d.errors ?? [];
                if (d.total_rows > 0) {
                    this.importPercent = Math.round((d.processed_rows ?? 0) / d.total_rows * 100);
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
            } catch { /* ignore transient */ }
            if (this.jobStatus === 'pending' || this.jobStatus === 'processing') {
                this.pollTimer = setTimeout(() => this.pollStatus(), 1500);
            }
        },

        resetWizard() {
            clearTimeout(this.pollTimer);
            Object.assign(this, {
                step: 1, file: null, previewToken: null, columns: [], availableFields: [],
                requiredFields: [], autoMapping: {}, mapping: {}, dontImport: {},
                duplicateStrategy: 'skip', jobId: null, jobStatus: null,
                jobProgress: '', jobErrors: [], importPercent: 0,
            });
        },
    };
}
</script>

</x-app-shell>
