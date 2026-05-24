<x-app-shell active="segments" breadcrumb="Segments / {{ $segment->name }}">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div class="flex items-center gap-3">
        <a href="/segments" class="btn ghost icon">
            <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1>{{ $segment->name }}</h1>
            <p class="text-sm text-secondary mt-0.5">
                <span class="chip mr-1"><span class="chip-dot" style="background: var(--info);"></span>{{ $segment->entity_type }}</span>
                <span class="num-mono">{{ number_format($total) }}</span> membres ·
                @if($segment->last_computed_at)
                calculé {{ $segment->last_computed_at->diffForHumans() }}
                @endif
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('segments.export', $segment) }}" class="btn">
            <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exporter CSV
        </a>
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <a href="/segments/{{ $segment->id }}/edit" class="btn">
            <svg class="ic" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Éditer
        </a>
        @endif
    </div>
</div>

@if(session('success'))
<div class="mx-7 mb-3 chip ok px-3 py-2 rounded-lg">{{ session('success') }}</div>
@endif

@if($segment->description)
<div class="px-7 mb-3 text-sm text-secondary">{{ $segment->description }}</div>
@endif

{{-- Rules summary --}}
<div class="px-7 mb-4">
    <div class="card p-4">
        <div class="mono-label mb-2">Règles</div>
        <div class="text-[12px] font-mono text-secondary">
            {{ json_encode($segment->rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
        </div>
    </div>
</div>

{{-- Members table --}}
<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        @if($members->isEmpty())
        <div class="py-16 text-center text-tertiary text-sm">
            Aucun membre ne correspond à ces règles.
        </div>
        @else
        <table class="t">
            <thead>
                <tr>
                    <th>#</th>
                    <th>
                        @if($segment->entity_type === 'contact') Contact
                        @elseif($segment->entity_type === 'company') Entreprise
                        @else Deal
                        @endif
                    </th>
                    @if($segment->entity_type === 'contact')
                    <th>Email</th><th>Lifecycle</th>
                    @elseif($segment->entity_type === 'company')
                    <th>Industrie</th><th>Ville</th>
                    @else
                    <th>Montant</th><th>Étape</th>
                    @endif
                    <th>Créé le</th>
                </tr>
            </thead>
            <tbody>
                @foreach($members as $member)
                @php
                    if ($segment->entity_type === 'contact') {
                        $fullName = trim($member->first_name . ' ' . $member->last_name);
                        $color = \App\Helpers\Avatar::color($fullName ?: $member->email);
                        $initials = \App\Helpers\Avatar::initials($fullName ?: $member->email);
                        $link = '/contacts/' . $member->id;
                    } elseif ($segment->entity_type === 'company') {
                        $fullName = $member->name;
                        $color = \App\Helpers\Avatar::color($member->name);
                        $initials = strtoupper(mb_substr($member->name, 0, 2));
                        $link = '/companies/' . $member->id;
                    } else {
                        $fullName = $member->name;
                        $color = \App\Helpers\Avatar::color($member->name);
                        $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $member->name), 0, 1) ?: 'D');
                        $link = '/deals/' . $member->id;
                    }
                @endphp
                <tr onclick="window.location='{{ $link }}'" style="cursor:pointer;">
                    <td class="text-tertiary num-mono text-[11.5px]">{{ $member->id }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="av {{ $color }} sm {{ $segment->entity_type === 'company' ? 'sq' : '' }}">{{ $initials }}</span>
                            <span class="font-medium">{{ $fullName }}</span>
                        </div>
                    </td>
                    @if($segment->entity_type === 'contact')
                    <td><span class="text-[12px] font-mono text-secondary">{{ $member->email ?? '—' }}</span></td>
                    <td>@if($member->lifecycle_stage)<span class="chip">{{ $member->lifecycle_stage }}</span>@else<span class="text-tertiary">—</span>@endif</td>
                    @elseif($segment->entity_type === 'company')
                    <td><span class="text-secondary">{{ $member->industry ?? '—' }}</span></td>
                    <td><span class="text-secondary">{{ $member->city ?? '—' }}</span></td>
                    @else
                    <td><span class="num-mono font-semibold">{{ number_format($member->amount, 0, ',', "\xc2\xa0") }} €</span></td>
                    <td>@php $stg = $member->stage; @endphp @if($stg)<span class="chip">{{ $stg->name }}</span>@else<span class="text-tertiary">—</span>@endif</td>
                    @endif
                    <td><span class="num-mono text-[12px] text-secondary">{{ $member->created_at->format('d/m/Y') }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Pagination --}}
        <x-pagination
            :page="$page" :last-page="$lastPage" :total="$total" :per-page="$perPage"
            base-url="/segments/{{ $segment->id }}" />
        @endif
    </div>
</div>

</x-app-shell>
