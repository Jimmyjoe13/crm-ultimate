<x-app-shell active="email-templates" breadcrumb="Modèles d'email">

@php $canShare = in_array(auth()->user()?->role, ['admin','manager']); @endphp

<div x-data="templatesPage()" class="px-7 pt-6 pb-12">

    <div class="flex items-end justify-between pb-3">
        <div>
            <h1>Modèles d'email</h1>
            <p class="text-sm text-secondary mt-0.5">
                Réutilise des emails type avec des variables dynamiques.
                <span class="num-mono">{{ $templates->count() }}</span> modèle(s).
            </p>
        </div>
        <x-button size="sm" @click="openCreate()">Nouveau modèle</x-button>
    </div>

    {{-- Aide variables --}}
    <div class="card p-4 mb-3" style="background: var(--surface2);">
        <div class="mono-label mb-2">Variables disponibles (clique pour copier)</div>
        <div class="flex flex-wrap gap-1.5">
            @foreach(collect($variables)->flatten()->unique()->sort() as $var)
                <button type="button" onclick="copyToClipboard('{{ '{{'.$var.'}}' }}'); window.toast('Variable copiée', 'success')"
                        class="chip" style="cursor:pointer;font-family:var(--font-mono,monospace);font-size:11px;">{{ '{{'.$var.'}}' }}</button>
            @endforeach
        </div>
    </div>

    {{-- Liste --}}
    <div class="card overflow-hidden">
        <table class="t">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Catégorie</th>
                    <th>Objet</th>
                    <th>Portée</th>
                    <th>Propriétaire</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                <tr>
                    <td><span class="font-medium text-[13px]">{{ $template->name }}</span></td>
                    <td>@if($template->category)<x-chip color="gray">{{ $template->category }}</x-chip>@else<span class="text-tertiary">—</span>@endif</td>
                    <td><span class="text-secondary text-[12px]">{{ Str::limit($template->subject ?: '—', 40) }}</span></td>
                    <td>
                        @if($template->is_shared)
                            <x-chip color="green" :dot="true">Partagé</x-chip>
                        @else
                            <x-chip color="gray">Privé</x-chip>
                        @endif
                    </td>
                    <td><span class="text-secondary text-[12px] font-mono">{{ $template->owner?->name ?? '—' }}</span></td>
                    <td>
                        <div class="flex items-center gap-1 justify-end">
                            <button type="button" class="btn ghost sm"
                                    @click='openEdit(@json([
                                        "id" => $template->id,
                                        "name" => $template->name,
                                        "category" => $template->category,
                                        "subject" => $template->subject,
                                        "body" => $template->body,
                                        "is_shared" => (bool) $template->is_shared,
                                    ]))'>Éditer</button>
                            <form method="POST" action="/email-templates/{{ $template->id }}" onsubmit="return confirm('Supprimer ce modèle ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn ghost sm" style="color:var(--err);">Suppr.</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6">
                    <x-empty-state title="Aucun modèle d'email"
                                   subtitle="Crée ton premier modèle pour gagner du temps sur tes relances."
                                   icon='<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'>
                        <button type="button" class="btn sm mt-4" @click="openCreate()">Nouveau modèle</button>
                    </x-empty-state>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal create/edit --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(0,0,0,.45);"
         @keydown.escape.window="open = false">
        <div class="card p-6 w-full max-w-lg" @click.stop style="max-height:90vh;display:flex;flex-direction:column;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold" x-text="mode === 'edit' ? 'Modifier le modèle' : 'Nouveau modèle'"></h2>
                <button @click="open = false" class="btn ghost icon">
                    <svg class="ic" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form :action="mode === 'edit' ? ('/email-templates/' + current.id) : '/email-templates'" method="POST"
                  class="flex flex-col gap-3 overflow-y-auto" style="min-height:0;">
                @csrf
                <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : 'POST'">

                <div>
                    <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider mb-1 block">Nom *</label>
                    <input type="text" name="name" x-model="current.name" required maxlength="255"
                           class="w-full" style="font-size:13px;padding:8px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);">
                </div>

                <div>
                    <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider mb-1 block">Catégorie</label>
                    <input type="text" name="category" x-model="current.category" maxlength="100" placeholder="ex : Relance, Prospection…"
                           class="w-full" style="font-size:13px;padding:8px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);">
                </div>

                <div>
                    <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider mb-1 block">Objet</label>
                    <input type="text" name="subject" x-model="current.subject" maxlength="255" placeholder="Bonjour {{ '{{first_name}}' }} —"
                           class="w-full" style="font-size:13px;padding:8px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);">
                </div>

                <div>
                    <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider mb-1 block">Corps</label>
                    <textarea name="body" x-model="current.body" rows="8" maxlength="20000"
                              style="width:100%;font-size:12px;line-height:1.6;resize:vertical;padding:8px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);font-family:var(--font-mono,monospace);"></textarea>
                </div>

                @if($canShare)
                <label class="flex items-center gap-2 text-xs text-secondary cursor-pointer select-none">
                    <input type="hidden" name="is_shared" value="0">
                    <input type="checkbox" name="is_shared" value="1" x-model="current.is_shared"
                           class="rounded border-default text-accent focus:ring-accent" style="width:14px;height:14px;cursor:pointer;">
                    <span>Partager avec toute l'équipe</span>
                </label>
                @endif

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-default">
                    <button type="button" @click="open = false" class="btn ghost sm">Annuler</button>
                    <button type="submit" class="btn sm" x-text="mode === 'edit' ? 'Enregistrer' : 'Créer'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function templatesPage() {
    return {
        open: false,
        mode: 'create',
        current: { id: null, name: '', category: '', subject: '', body: '', is_shared: false },
        openCreate() {
            this.mode = 'create';
            this.current = { id: null, name: '', category: '', subject: '', body: '', is_shared: false };
            this.open = true;
        },
        openEdit(tpl) {
            this.mode = 'edit';
            this.current = {
                id: tpl.id,
                name: tpl.name ?? '',
                category: tpl.category ?? '',
                subject: tpl.subject ?? '',
                body: tpl.body ?? '',
                is_shared: !!tpl.is_shared,
            };
            this.open = true;
        },
    };
}
</script>

</x-app-shell>
