<x-app-shell active="segments" breadcrumb="Segments / {{ $segment ? 'Éditer' : 'Nouveau' }}">

@php
    $isEdit     = $segment !== null;
    $formUrl    = $isEdit ? '/segments/' . $segment->id : '/segments';
    $method     = $isEdit ? 'PUT' : 'POST';
    $initEntity = $isEdit ? $segment->entity_type : 'contact';
@endphp

<script>
window.__segFields = @json($fieldsByEntity);
window.__segRules  = {!! json_encode($isEdit ? ($segment->rules ?: ['op'=>'AND','rules'=>[]]) : ['op'=>'AND','rules'=>[]]) !!};
</script>

<div x-data="segmentBuilder(window.__segRules, '{{ $initEntity }}', window.__segFields)" x-init="init()" class="px-7 pt-6 pb-12">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ $isEdit ? '/segments/' . $segment->id : '/segments' }}" class="btn ghost icon">
            <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1>{{ $isEdit ? 'Éditer le segment' : 'Nouveau segment' }}</h1>
    </div>

    @if($errors->any())
    <div class="chip err px-3 py-2 rounded-lg mb-4">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5" style="grid-template-columns: 1fr 340px;">

        {{-- LEFT: form + builder --}}
        <div class="flex flex-col gap-4">

            {{-- Infos de base --}}
            <div class="card p-5">
                <div class="mono-label mb-4">Informations</div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="field col-span-2">
                        <label>Nom du segment *</label>
                        <input type="text" x-model="name" placeholder="Ex: Customers SaaS avec deal won > 10k€" required>
                    </div>
                    <div class="field col-span-2">
                        <label>Description</label>
                        <input type="text" x-model="description" placeholder="Optionnel">
                    </div>
                    <div class="field">
                        <label>Entité *</label>
                        <select x-model="entityType" @change="onEntityChange()" class="select-arrow">
                            <option value="contact">Contact</option>
                            <option value="company">Entreprise</option>
                            <option value="deal">Deal</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Rule builder --}}
            <div class="card p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="mono-label">Règles de filtrage</div>
                </div>

                <div x-html="renderGroup(rules, [])"></div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between">
                <a href="{{ $isEdit ? '/segments/' . $segment->id : '/segments' }}" class="btn">Annuler</a>
                <button @click="submitForm()" class="btn primary">
                    {{ $isEdit ? 'Enregistrer' : 'Créer le segment' }}
                </button>
            </div>
        </div>

        {{-- RIGHT: live preview --}}
        <div class="flex flex-col gap-3">
            <div class="card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="mono-label">Aperçu membres</div>
                    <button @click="refreshPreview()" class="btn sm ghost">
                        <svg class="ic" style="width:13px;height:13px;" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        Recalculer
                    </button>
                </div>

                <template x-if="previewLoading">
                    <div class="py-6 text-center text-tertiary text-sm">Calcul en cours…</div>
                </template>

                <template x-if="!previewLoading && previewError">
                    <div class="py-4 text-center text-[12px] font-mono" style="color: var(--err);" x-text="previewError"></div>
                </template>

                <template x-if="!previewLoading && !previewError">
                    <div>
                        <div class="text-3xl num font-bold mb-1" x-text="previewCount"></div>
                        <div class="mono-label mb-3" x-text="entityType + 's correspondant(s)'"></div>
                        <div class="flex flex-col gap-1">
                            <template x-for="m in previewSample" :key="m.id">
                                <div class="text-[12px] py-1 border-b border-default last:border-b-0 flex items-center gap-2">
                                    <span class="num-mono text-tertiary w-6" x-text="m.id"></span>
                                    <span x-text="memberLabel(m)"></span>
                                </div>
                            </template>
                        </div>
                        <div x-show="previewCount > previewSample.length"
                             class="text-[11.5px] text-tertiary font-mono mt-2 text-center"
                             x-text="'+ ' + (previewCount - previewSample.length) + ' autres'">
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Hidden form for submission --}}
    <form id="segmentForm" method="POST" action="{{ $formUrl }}" style="display:none;">
        @csrf
        @if($method === 'PUT') @method('PUT') @endif
        <input type="hidden" name="name" :value="name">
        <input type="hidden" name="description" :value="description">
        <input type="hidden" name="entity_type" :value="entityType">
        <input type="hidden" name="rules" :value="JSON.stringify(rules)">
    </form>
</div>

<script>
function segmentBuilder(initRules, initEntity, allFields) {
    return {
        name: '{{ $isEdit ? addslashes($segment->name) : '' }}',
        description: '{{ $isEdit && $segment->description ? addslashes($segment->description) : '' }}',
        entityType: initEntity,
        rules: initRules,
        allFields: allFields,
        fields: allFields[initEntity] || [],
        fieldsLoading: false,
        previewCount: 0,
        previewSample: [],
        previewLoading: false,
        previewError: null,
        previewTimer: null,

        async init() {
            this.schedulePreview();
        },

        onEntityChange() {
            this.fields = this.allFields[this.entityType] || [];
            this.schedulePreview();
        },

        schedulePreview() {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => this.refreshPreview(), 500);
        },

        async refreshPreview() {
            if (!this.rules.rules || this.rules.rules.length === 0) {
                this.previewCount = 0;
                this.previewSample = [];
                return;
            }
            this.previewLoading = true;
            this.previewError = null;
            try {
                const xsrf = decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                );
                const r = await fetch('/segments/preview', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ entity_type: this.entityType, rules: this.rules }),
                });
                if (r.ok) {
                    const data = await r.json();
                    this.previewCount = data.count ?? 0;
                    this.previewSample = data.sample ?? [];
                } else {
                    const e = await r.json();
                    this.previewError = e.message ?? 'Erreur de calcul';
                }
            } catch(e) {
                this.previewError = 'Erreur réseau';
            }
            this.previewLoading = false;
        },

        memberLabel(m) {
            if (this.entityType === 'contact') {
                return [m.first_name, m.last_name].filter(Boolean).join(' ') || m.email || '#' + m.id;
            }
            return m.name || '#' + m.id;
        },

        // Rule tree mutations
        addRule(path) {
            const node = this.getNode(path);
            if (!node.rules) return;
            const defaultField = this.fields[0]?.key ?? 'email';
            node.rules.push({ field: defaultField, operator: 'eq', value: '' });
            this.rules = { ...this.rules };
            this.schedulePreview();
        },

        addGroup(path) {
            const node = this.getNode(path);
            if (!node.rules) return;
            node.rules.push({ op: 'AND', rules: [] });
            this.rules = { ...this.rules };
        },

        removeNode(path) {
            if (path.length === 0) return;
            const parentPath = path.slice(0, -1);
            const idx = path[path.length - 1];
            const parent = this.getNode(parentPath);
            parent.rules.splice(idx, 1);
            this.rules = { ...this.rules };
            this.schedulePreview();
        },

        updateLeaf(path, key, value) {
            const node = this.getNode(path);
            node[key] = value;
            this.rules = { ...this.rules };
            this.schedulePreview();
        },

        toggleOp(path) {
            const node = this.getNode(path);
            node.op = node.op === 'AND' ? 'OR' : 'AND';
            this.rules = { ...this.rules };
            this.schedulePreview();
        },

        getNode(path) {
            let node = this.rules;
            for (const idx of path) node = node.rules[idx];
            return node;
        },

        // Render helpers
        renderGroup(node, path) {
            const pathStr = JSON.stringify(path);
            const isRoot = path.length === 0;
            const indent = path.length > 0 ? 'ml-4 pl-3 border-l-2' : '';
            const borderColor = 'border-[var(--border)]';

            let html = `<div class="${indent} ${borderColor}">`;

            // Group header: op toggle + add buttons
            html += `<div class="flex items-center gap-2 mb-2">`;
            html += `<button type="button" @click="toggleOp(${pathStr})"
                class="chip font-mono font-semibold text-[11px]" style="cursor:pointer; user-select:none;">
                ${node.op}
            </button>`;
            html += `<button type="button" @click="addRule(${pathStr})" class="btn sm ghost text-[11px]">+ Règle</button>`;
            html += `<button type="button" @click="addGroup(${pathStr})" class="btn sm ghost text-[11px]">+ Groupe</button>`;
            if (!isRoot) {
                html += `<button type="button" @click="removeNode(${pathStr})" class="btn sm ghost icon ml-auto" style="color:var(--err);">
                    <svg class="ic" style="width:13px;height:13px;" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>`;
            }
            html += `</div>`;

            // Children
            if (node.rules && node.rules.length) {
                node.rules.forEach((child, idx) => {
                    const childPath = [...path, idx];
                    if (child.op) {
                        html += this.renderGroup(child, childPath);
                    } else {
                        html += this.renderLeaf(child, childPath);
                    }
                });
            } else {
                html += `<div class="text-[12px] text-tertiary font-mono py-2">Aucune règle — cliquez "+ Règle" pour commencer.</div>`;
            }

            html += `</div>`;
            return html;
        },

        renderLeaf(rule, path) {
            const pathStr = JSON.stringify(path);
            const fieldKey = rule.field ?? '';
            const op = rule.operator ?? 'eq';
            const fieldMeta = this.fields.find(f => f.key === fieldKey);

            // Field select
            let fieldSel = `<select class="select-arrow text-[12px]" style="flex:1 1 0; min-width:120px;"
                @change="updateLeaf(${pathStr}, 'field', $event.target.value)">`;
            this.fields.forEach(f => {
                const sel = f.key === fieldKey ? 'selected' : '';
                fieldSel += `<option value="${f.key}" ${sel}>${f.label}</option>`;
            });
            fieldSel += '</select>';

            // Operator select
            const ops = this.opsForField(fieldMeta);
            let opSel = `<select class="select-arrow text-[12px]" style="width:130px;"
                @change="updateLeaf(${pathStr}, 'operator', $event.target.value)">`;
            ops.forEach(o => {
                const sel = o.value === op ? 'selected' : '';
                opSel += `<option value="${o.value}" ${sel}>${o.label}</option>`;
            });
            opSel += '</select>';

            // Value input
            const needsValue = !['is_null','is_not_null','exists','not_exists'].includes(op);
            let valInput = '';
            if (needsValue) {
                const v = rule.value ?? '';
                valInput = `<input type="text" class="text-[12px]" style="flex:1 1 0; min-width:100px;"
                    value="${String(v).replace(/"/g, '&quot;')}"
                    @input="updateLeaf(${pathStr}, 'value', $event.target.value)"
                    placeholder="Valeur">`;
            }

            return `<div class="flex items-center gap-2 mb-2">
                ${fieldSel}
                ${opSel}
                ${valInput}
                <button type="button" @click="removeNode(${pathStr})" class="btn ghost icon flex-shrink-0" style="color:var(--err);">
                    <svg class="ic" style="width:13px;height:13px;" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>`;
        },

        opsForField(fieldMeta) {
            const type = fieldMeta?.type ?? 'text';
            const base = [
                { value: 'eq', label: '=' },
                { value: 'neq', label: '≠' },
                { value: 'is_null', label: 'est vide' },
                { value: 'is_not_null', label: 'non vide' },
            ];
            if (['number','amount','date'].includes(type)) {
                return [...base,
                    { value: 'gt', label: '>' },
                    { value: 'gte', label: '>=' },
                    { value: 'lt', label: '<' },
                    { value: 'lte', label: '<=' },
                    { value: 'between', label: 'entre' },
                    { value: 'days_ago_lt', label: 'il y a < X jours' },
                    { value: 'days_ago_gt', label: 'il y a > X jours' },
                ];
            }
            if (type === 'rel') {
                return [
                    { value: 'exists', label: 'existe' },
                    { value: 'not_exists', label: 'n\'existe pas' },
                    { value: 'count_gte', label: 'count >=' },
                    { value: 'count_lt', label: 'count <' },
                    { value: 'gt', label: 'valeur >' },
                    { value: 'gte', label: 'valeur >=' },
                    { value: 'lt', label: 'valeur <' },
                    { value: 'eq', label: 'valeur =' },
                ];
            }
            return [...base,
                { value: 'contains', label: 'contient' },
                { value: 'not_contains', label: 'ne contient pas' },
                { value: 'starts_with', label: 'commence par' },
                { value: 'ends_with', label: 'finit par' },
                { value: 'in', label: 'dans la liste' },
                { value: 'not_in', label: 'hors liste' },
            ];
        },

        submitForm() {
            if (!this.name.trim()) { alert('Le nom est requis.'); return; }
            document.querySelector('#segmentForm input[name="name"]').value = this.name;
            document.querySelector('#segmentForm input[name="description"]').value = this.description;
            document.querySelector('#segmentForm input[name="entity_type"]').value = this.entityType;
            document.querySelector('#segmentForm input[name="rules"]').value = JSON.stringify(this.rules);
            document.getElementById('segmentForm').submit();
        },
    };
}
</script>

</x-app-shell>
