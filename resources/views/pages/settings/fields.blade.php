<x-app-shell active="fields" breadcrumb="Paramètres / Champs personnalisés">

<div class="px-7 pt-6 pb-3">
    <h1>Champs personnalisés</h1>
    <p class="text-sm text-secondary mt-0.5">Définir des propriétés supplémentaires pour vos entités.</p>
</div>

<div class="px-7 pb-12 max-w-3xl">

    @if(session('success'))
    <div class="chip ok px-3 py-2 mb-4 rounded-lg" style="border-radius:8px;">{{ session('success') }}</div>
    @endif

    <div class="card overflow-hidden mb-4">
        <div class="card-h">
            <span class="title">Champs existants</span>
            <span class="meta">{{ $fields->count() }} champs</span>
        </div>
        <table class="t">
            <thead><tr><th>Entité</th><th>Label</th><th>Clé</th><th>Type</th><th style="width:120px;"></th></tr></thead>
            <tbody>
                @forelse($fields as $field)
                <tr x-data="{ editing: false }">
                    <td><span class="chip">{{ $field->entity_type }}</span></td>
                    <td class="font-medium" x-show="!editing">{{ $field->label }}</td>
                    <td x-show="!editing"><span class="num-mono text-[12px] text-tertiary">{{ $field->key }}</span></td>
                    <td x-show="!editing"><span class="chip">{{ $field->field_type }}</span></td>

                    {{-- Edit inline form (spans cols 2-4) --}}
                    <td colspan="3" x-show="editing" x-cloak>
                        <form method="POST" action="/settings/fields/{{ $field->id }}" class="flex items-center gap-2 py-1">
                            @csrf @method('PATCH')
                            <input type="text" name="label" value="{{ $field->label }}"
                                   class="field" style="padding:4px 8px; font-size:12px; width:140px;">
                            <select name="field_type" class="select-arrow" style="padding:4px 8px; font-size:12px;">
                                @foreach(['text','number','date','boolean','select'] as $ft)
                                <option value="{{ $ft }}" @selected($field->field_type === $ft)>{{ $ft }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn sm primary" style="font-size:11px; padding:3px 8px;">OK</button>
                            <button type="button" @click="editing = false" class="btn sm ghost" style="font-size:11px; padding:3px 8px;">✕</button>
                        </form>
                    </td>

                    <td class="text-right" style="white-space:nowrap;">
                        <button @click="editing = !editing" class="btn ghost" style="font-size:11px; padding:3px 8px;">Modifier</button>
                        <form method="POST" action="/settings/fields/{{ $field->id }}" class="inline"
                              onsubmit="return confirm('Supprimer le champ « {{ $field->label }} » ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn ghost" style="font-size:11px; padding:3px 8px; color:var(--err);">Suppr.</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-tertiary text-sm">Aucun champ personnalisé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card p-5">
        <div class="mono-label mb-4">Ajouter un champ</div>
        <form method="POST" action="/settings/fields" class="grid grid-cols-2 gap-4">
            @csrf
            <div class="field">
                <label>Entité</label>
                <select name="entity_type" class="select-arrow" required>
                    <option value="">Choisir…</option>
                    <option value="contact">Contact</option>
                    <option value="company">Entreprise</option>
                    <option value="deal">Deal</option>
                </select>
            </div>
            <div class="field">
                <label>Type</label>
                <select name="field_type" class="select-arrow" required>
                    <option value="text">Texte</option>
                    <option value="number">Nombre</option>
                    <option value="date">Date</option>
                    <option value="boolean">Booléen</option>
                    <option value="select">Liste</option>
                </select>
            </div>
            <div class="field">
                <label>Label</label>
                <input type="text" name="label" required placeholder="Ex: Segment client">
            </div>
            <div class="field">
                <label>Clé (slug)</label>
                <input type="text" name="key" required placeholder="Ex: segment_client" pattern="[a-z0-9_\-]+">
            </div>
            <div class="col-span-2 flex justify-end">
                <button type="submit" class="btn primary">Créer le champ</button>
            </div>
        </form>
    </div>
</div>

</x-app-shell>
