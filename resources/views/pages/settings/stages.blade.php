<x-app-shell active="stages" breadcrumb="Paramètres / Étapes">

<div class="px-7 pt-6 pb-3">
    <h1>Étapes du pipeline</h1>
    <p class="text-sm text-secondary mt-0.5">{{ $pipeline?->name ?? 'Pipeline Commerce' }}</p>
</div>

<div class="px-7 pb-12 max-w-2xl">

    @if(session('success'))
    <div class="chip ok px-3 py-2 mb-4 rounded-lg" style="border-radius:8px;">{{ session('success') }}</div>
    @endif

    <div class="card overflow-hidden mb-4">
        <div class="card-h">
            <span class="title">Étapes</span>
            <span class="meta">{{ $stages->count() }} étapes</span>
        </div>
        <table class="t">
            <thead><tr><th>#</th><th>Nom</th><th>Probabilité</th><th>Type</th></tr></thead>
            <tbody>
                @foreach($stages as $stage)
                <tr>
                    <td class="num-mono text-tertiary text-[11px]">{{ $stage->position }}</td>
                    <td class="font-medium">{{ $stage->name }}</td>
                    <td><span class="num-mono text-[12px]">{{ $stage->probability }}%</span></td>
                    <td>
                        @if($stage->is_won) <span class="chip ok">Won</span>
                        @elseif($stage->is_lost) <span class="chip err">Lost</span>
                        @else <span class="chip">Open</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Add stage form --}}
    <div class="card p-5">
        <div class="mono-label mb-4">Ajouter une étape</div>
        <form method="POST" action="/settings/stages" class="grid grid-cols-2 gap-4">
            @csrf
            <div class="field col-span-2">
                <label>Nom</label>
                <input type="text" name="name" required placeholder="Ex: Due Diligence">
            </div>
            <div class="field">
                <label>Probabilité (%)</label>
                <input type="number" name="probability" min="0" max="100" placeholder="50">
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

</x-app-shell>
