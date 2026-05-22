<x-app-shell active="deals" breadcrumb="Deals">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Deals</h1>
        <p class="text-sm text-secondary mt-0.5">
            <span class="num-mono">{{ $allCount }}</span> deals ouverts ·
            <span class="num-mono">{{ number_format($total, 0, ',', "\xc2\xa0") }} €</span> en pipeline
        </p>
    </div>
    <div class="flex items-center gap-2">
        {{-- View toggle --}}
        <div class="flex border rounded-lg overflow-hidden" style="border-color: var(--border);">
            <span class="btn sm" style="border-radius:0; border:none; background: var(--text); color: var(--bg);">
                <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>Table
            </span>
            <a href="{{ route('pipeline.index') }}" class="btn sm ghost" style="border-radius:0; border:none;">
                <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><rect x="3" y="3" width="5" height="18"/><rect x="10" y="3" width="5" height="12"/><rect x="17" y="3" width="4" height="8"/></svg>Kanban
            </a>
        </div>
    </div>
</div>

{{-- Filter bar --}}
<div class="px-7 pb-3 flex items-center gap-2 flex-wrap">
    <x-chip color="gray" :dot="true">All · {{ $allCount }}</x-chip>
    <span class="ml-auto text-xs text-tertiary font-mono">
        Tri : {{ $sort }} {{ $dir === 'asc' ? '↑' : '↓' }}
    </span>
</div>

{{-- Flash success --}}
@if(session('success'))
<div class="mx-7 mb-3 chip ok px-3 py-2 rounded-lg" style="border-radius:8px;">
    {{ session('success') }}
</div>
@endif

{{-- Table --}}
<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        @php $pageIds = $deals->pluck('id')->toArray(); @endphp
        <table class="t" x-data>
            <thead>
                <tr>
                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <th style="width:36px;">
                        <span class="ckb"
                              :class="{ 'on': $store.bulk.allSelected('deal', {{ json_encode($pageIds) }}) }"
                              @click.stop="$store.bulk.toggleAll('deal', {{ json_encode($pageIds) }})"
                              style="cursor:pointer;"></span>
                    </th>
                    @endif
                    <x-sort-th column="name"       label="Deal"       :sort="$sort" :dir="$dir" />
                    <th>Entreprise</th>
                    <x-sort-th column="amount"     label="Montant"    :sort="$sort" :dir="$dir" />
                    <th>Étape</th>
                    <x-sort-th column="close_date" label="Clôture"    :sort="$sort" :dir="$dir" />
                    <th>Owner</th>
                    <th style="width:32px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($deals as $deal)
                @php
                    $company = $deal->companies->first();
                    $owner   = $deal->owner;
                    $ownerInitials = $owner ? \App\Helpers\Avatar::initials($owner->name ?? $owner->email) : '?';
                    $ownerColor    = $owner ? \App\Helpers\Avatar::color($owner->name ?? $owner->email) : 'c1';
                    $isOverdue = $deal->close_date && $deal->close_date->isPast();
                @endphp
                <tr onclick="window.location='/deals/{{ $deal->id }}'" style="cursor:pointer;"
                    :class="{ 'bg-surface2': $store.bulk.selections.deal.has({{ $deal->id }}) }">
                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <td @click.stop>
                        <span class="ckb"
                              :class="{ 'on': $store.bulk.selections.deal.has({{ $deal->id }}) }"
                              @click.stop="$store.bulk.toggle('deal', {{ $deal->id }})"
                              style="cursor:pointer;"></span>
                    </td>
                    @endif
                    <td>
                        <div class="font-medium">{{ $deal->name }}</div>
                        <div class="text-[11.5px] text-tertiary font-mono mt-0.5">
                            Créé {{ $deal->created_at->diffForHumans() }}
                        </div>
                    </td>
                    <td><span class="text-secondary">{{ $company?->name ?? '—' }}</span></td>
                    <td><span class="num-mono font-semibold">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span></td>
                    <td>
                        @if($deal->stage)
                        <span class="chip"><span class="chip-dot" style="background: var(--info);"></span>{{ $deal->stage->name }}</span>
                        @else
                        <span class="text-tertiary text-xs">—</span>
                        @endif
                    </td>
                    <td>
                        @if($deal->close_date)
                        <span class="num-mono @if($isOverdue) text-err @endif">
                            {{ $deal->close_date->format('d/m/Y') }}
                        </span>
                        @else
                        <span class="text-tertiary">—</span>
                        @endif
                    </td>
                    <td><span class="av {{ $ownerColor }} sm">{{ $ownerInitials }}</span></td>
                    <td>
                        <svg class="ic text-tertiary" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ in_array(auth()->user()?->role, ['admin','manager']) ? 9 : 8 }}" class="text-center py-12 text-tertiary text-sm">
                        Aucun deal ouvert pour le moment.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        <x-pagination :paginator="$deals" />
    </div>
</div>

@if(in_array(auth()->user()?->role, ['admin','manager']))
<x-bulk-bar entity="deal" delete-action="/deals/bulk-destroy" />
@endif

</x-app-shell>
