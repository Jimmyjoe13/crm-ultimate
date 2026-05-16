<x-app-shell active="segments" breadcrumb="Segments">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Segments</h1>
        <p class="text-sm text-secondary mt-0.5">
            <span class="num-mono">{{ $segments->count() }}</span> segments dynamiques
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <a href="/segments/create" class="btn primary">
            <svg class="ic" style="stroke-width:2;" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Nouveau segment
        </a>
        @endif
    </div>
</div>

@if(session('success'))
<div class="mx-7 mb-3 chip ok px-3 py-2 rounded-lg">{{ session('success') }}</div>
@endif

<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        @if($segments->isEmpty())
        <div class="py-16 text-center text-tertiary text-sm">
            Aucun segment. Créez votre premier segment dynamique pour filtrer vos contacts, entreprises ou deals.
        </div>
        @else
        <table class="t">
            <thead>
                <tr>
                    <th>Segment</th>
                    <th>Entité</th>
                    <th>Membres</th>
                    <th>Calculé</th>
                    <th>Créé par</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($segments as $segment)
                @php
                    $entityIcon = match($segment->entity_type) {
                        'contact' => '👤',
                        'company' => '🏢',
                        'deal'    => '📋',
                        default   => '◉',
                    };
                    $creator    = $segment->creator;
                    $creatorI   = $creator ? \App\Helpers\Avatar::initials($creator->name ?? $creator->email) : '?';
                    $creatorC   = $creator ? \App\Helpers\Avatar::color($creator->name ?? $creator->email) : 'c1';
                @endphp
                <tr onclick="window.location='/segments/{{ $segment->id }}'" style="cursor:pointer;">
                    <td>
                        <div class="font-medium">{{ $segment->name }}</div>
                        @if($segment->description)
                        <div class="text-[11.5px] text-tertiary font-mono mt-0.5">{{ Str::limit($segment->description, 60) }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="chip">
                            <span class="chip-dot" style="background: var(--info);"></span>
                            {{ $segment->entity_type }}
                        </span>
                    </td>
                    <td>
                        @if($segment->last_count !== null)
                        <span class="num-mono font-semibold">{{ number_format($segment->last_count) }}</span>
                        @else
                        <span class="text-tertiary text-xs">—</span>
                        @endif
                    </td>
                    <td>
                        @if($segment->last_computed_at)
                        <span class="text-[12px] text-secondary num-mono">{{ $segment->last_computed_at->diffForHumans() }}</span>
                        @else
                        <span class="text-tertiary text-xs">Jamais</span>
                        @endif
                    </td>
                    <td>
                        @if($creator)
                        <div class="flex items-center gap-1.5">
                            <span class="av sm {{ $creatorC }}">{{ $creatorI }}</span>
                            <span class="text-[12px] text-secondary">{{ $creator->name ?? $creator->email }}</span>
                        </div>
                        @else
                        <span class="text-tertiary">—</span>
                        @endif
                    </td>
                    <td onclick="event.stopPropagation()">
                        <div class="flex items-center gap-1">
                            @if(in_array(auth()->user()?->role, ['admin','manager']))
                            <a href="/segments/{{ $segment->id }}/edit" class="btn ghost icon" title="Éditer">
                                <svg class="ic" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <form method="POST" action="/segments/{{ $segment->id }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn ghost icon" title="Supprimer"
                                        onclick="return confirm('Supprimer ce segment ?')">
                                    <svg class="ic" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

</x-app-shell>
