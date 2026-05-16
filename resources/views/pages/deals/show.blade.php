<x-app-shell active="deals" breadcrumb="Deals / {{ $deal->name }}">

@php
    $company = $deal->companies->first();
    $contact = $deal->contacts->first();
    $owner   = $deal->owner;
    $ownerInitials = $owner ? \App\Helpers\Avatar::initials($owner->name ?? $owner->email) : '?';
    $ownerColor    = $owner ? \App\Helpers\Avatar::color($owner->name ?? $owner->email) : 'c1';
    $dealInitials  = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $deal->name), 0, 1) ?: 'D');
    $dealColor     = \App\Helpers\Avatar::color($deal->name);
    $isOverdue     = $deal->close_date && $deal->close_date->isPast();

    // Build stage progress
    $currentPos = $deal->stage?->position ?? 0;
@endphp

{{-- Background: dimmed deals list --}}
<div class="px-7 pt-6 pb-3 opacity-30 pointer-events-none select-none">
    <h1>Deals</h1>
</div>
<div class="px-7 pb-12 opacity-30 pointer-events-none select-none">
    <div class="card overflow-hidden" style="min-height: 500px;">
        <table class="t">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th>Deal</th><th>Company</th><th>Amount</th><th>Stage</th><th>Close date</th><th>Owner</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bgDeals as $bgDeal)
                @php
                    $bgCompany = $bgDeal->companies->first();
                    $bgOwner   = $bgDeal->owner;
                    $bgOwnI    = $bgOwner ? \App\Helpers\Avatar::initials($bgOwner->name ?? $bgOwner->email) : '?';
                    $bgOwnC    = $bgOwner ? \App\Helpers\Avatar::color($bgOwner->name ?? $bgOwner->email) : 'c1';
                @endphp
                <tr class="{{ $bgDeal->id === $deal->id ? 'bg-surface2' : '' }}">
                    <td><span class="ckb"></span></td>
                    <td><div class="font-medium">{{ $bgDeal->name }}</div></td>
                    <td><span class="text-secondary">{{ $bgCompany?->name ?? '—' }}</span></td>
                    <td><span class="num-mono font-semibold">{{ number_format($bgDeal->amount, 0, ',', "\xc2\xa0") }} €</span></td>
                    <td>@if($bgDeal->stage)<span class="chip">{{ $bgDeal->stage->name }}</span>@else<span class="text-tertiary">—</span>@endif</td>
                    <td>@if($bgDeal->close_date)<span class="num-mono">{{ $bgDeal->close_date->format('d/m/Y') }}</span>@else<span class="text-tertiary">—</span>@endif</td>
                    <td><span class="av {{ $bgOwnC }} sm">{{ $bgOwnI }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Backdrop --}}
<div class="fixed inset-0 drawer-backdrop z-30" onclick="window.location='/deals'"></div>

{{-- Drawer --}}
<aside class="fixed top-0 right-0 bottom-0 z-40 bg-surface shadow-pop flex flex-col"
       style="width: 720px; border-left: 1px solid var(--border);">

    {{-- Header --}}
    <div class="px-6 py-4 border-b border-default flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="av lg {{ $dealColor }}" style="border-radius: 6px; flex-shrink: 0;">{{ $dealInitials }}</div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base font-semibold truncate">{{ $deal->name }}</h2>
                    @if($deal->stage)
                    <span class="chip flex-shrink-0"><span class="chip-dot" style="background: var(--info);"></span>{{ $deal->stage->name }}</span>
                    @endif
                </div>
                <div class="text-[11.5px] text-tertiary font-mono mt-0.5">
                    DEAL-{{ str_pad($deal->id, 4, '0', STR_PAD_LEFT) }} ·
                    créé {{ $deal->created_at->diffForHumans() }} ·
                    {{ $activities->count() }} activité{{ $activities->count() !== 1 ? 's' : '' }}
                    @if($deal->close_date) · close {{ $deal->close_date->format('d/m') }}@endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
            <a href="{{ url('/deals') }}" class="btn icon ghost" title="Fermer">
                <svg class="ic" viewBox="0 0 24 24" style="stroke-width:2;"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </a>
        </div>
    </div>

    {{-- Stage progress --}}
    <div class="px-6 py-4 border-b border-default flex-shrink-0">
        <div class="flex items-center gap-0">
            @foreach($stages as $stage)
            @php
                $isActive  = $deal->pipeline_stage_id === $stage->id;
                $isPast    = $stage->position < $currentPos;
            @endphp
            <div class="flex-1 flex flex-col items-center">
                <div class="w-full h-1.5
                    @if($isActive) rounded-none outline outline-2 outline-offset-1
                    @elseif($loop->first) rounded-l-full
                    @elseif($loop->last) rounded-r-full
                    @endif"
                    style="background: {{ $isActive ? 'var(--accent)' : ($isPast ? 'var(--text)' : 'var(--surface2)') }};
                           @if($isActive) outline-color: var(--accent-soft); @endif">
                </div>
                <span class="mono-label mt-1.5 {{ $isActive ? 'font-semibold' : '' }}"
                      style="{{ $isActive ? 'color: var(--accent);' : ($isPast ? 'color: var(--text);' : '') }}">
                    {{ $stage->name }}{{ $isActive ? ' ●' : '' }}
                </span>
            </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between mt-4">
            <div class="flex gap-2">
                <form method="POST" action="{{ url('/deals/' . $deal->id . '/won') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn primary sm">Marquer gagné ✓</button>
                </form>
                <form method="POST" action="{{ url('/deals/' . $deal->id . '/lost') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn sm">Marquer perdu</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Two-col body --}}
    <div class="grid flex-1 overflow-hidden" style="grid-template-columns: 1fr 280px;">

        {{-- Left: activity --}}
        <div class="overflow-auto flex flex-col">
            {{-- Tabs --}}
            <div class="flex border-b border-default px-6 gap-0 flex-shrink-0">
                <button class="px-3 py-3 text-[13px] font-medium border-b-2" style="border-color: var(--accent); color: var(--text);">
                    Activité <span class="chip ml-1" style="padding: 0 6px; font-size: 10px;">{{ $activities->count() }}</span>
                </button>
                <button class="px-3 py-3 text-[13px] text-secondary">Notes</button>
                <button class="px-3 py-3 text-[13px] text-secondary">Emails</button>
                <button class="px-3 py-3 text-[13px] text-secondary">Tâches</button>
            </div>

            {{-- Composer --}}
            <div class="px-6 py-4 border-b border-default flex-shrink-0">
                <div class="card p-3">
                    <div class="text-[13px] text-tertiary">Ajouter une note, un email, un appel…</div>
                    <div class="flex gap-2 mt-2">
                        <button class="btn sm">📝 Note</button>
                        <button class="btn sm">📧 Email</button>
                        <button class="btn sm">📞 Appel</button>
                        <button class="btn sm">✓ Tâche</button>
                    </div>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="px-6 py-3 overflow-auto flex-1">
                @forelse($activities as $activity)
                @php
                    $dot = match($activity->type) {
                        'email' => 'info',
                        'call'  => 'accent',
                        default => '',
                    };
                    $emoji = match($activity->type) {
                        'email'  => '📧',
                        'call'   => '📞',
                        'note'   => '📝',
                        'task'   => '✓',
                        default  => '➕',
                    };
                @endphp
                <div class="tl-item">
                    <span class="tl-time">{{ $activity->created_at->format('d/m H:i') }}</span>
                    <div class="tl-axis"><div class="tl-dot {{ $dot }}"></div></div>
                    <div class="tl-content">
                        <div class="ti">{{ $emoji }} {{ $activity->title }}</div>
                        @if($activity->body)
                        <div class="ts">{{ Str::limit($activity->body, 80) }}</div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="py-8 text-center text-tertiary text-sm">Aucune activité pour ce deal.</div>
                @endforelse
            </div>
        </div>

        {{-- Right: properties --}}
        <aside class="border-l border-default overflow-auto" style="background: var(--surface2);">
            <div class="p-5">
                <div class="mono-label mb-3">Propriétés</div>
                <div class="flex flex-col gap-2.5 text-[13px]">
                    <div class="flex justify-between">
                        <span class="text-tertiary">Montant</span>
                        <span class="num font-semibold">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span>
                    </div>
                    @if($deal->stage)
                    <div class="flex justify-between">
                        <span class="text-tertiary">Étape</span>
                        <span class="chip"><span class="chip-dot" style="background: var(--info);"></span>{{ $deal->stage->name }}</span>
                    </div>
                    @endif
                    @if($deal->close_date)
                    <div class="flex justify-between">
                        <span class="text-tertiary">Close date</span>
                        <span class="num {{ $isOverdue ? 'text-err' : '' }}">{{ $deal->close_date->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    @if($owner)
                    <div class="flex justify-between items-center">
                        <span class="text-tertiary">Owner</span>
                        <span class="flex items-center gap-1.5">
                            <span class="av sm {{ $ownerColor }}">{{ $ownerInitials }}</span>
                            {{ $owner->name ?? $owner->email }}
                        </span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-tertiary">Statut</span>
                        <span class="chip {{ match($deal->status) { 'won' => 'ok', 'lost' => 'err', default => '' } }}">
                            {{ $deal->status }}
                        </span>
                    </div>
                </div>

                @if($contact)
                @php
                    $cFullName  = trim($contact->first_name . ' ' . $contact->last_name);
                    $cColor     = \App\Helpers\Avatar::color($cFullName ?: $contact->email);
                    $cInitials  = \App\Helpers\Avatar::initials($cFullName ?: $contact->email);
                @endphp
                <div class="mono-label mt-6 mb-3">Contact principal</div>
                <a href="{{ '/contacts/' . $contact->id }}" class="flex items-center gap-2 hover:opacity-80">
                    <span class="av {{ $cColor }} sm">{{ $cInitials }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-[13px] truncate">{{ $cFullName ?: $contact->email }}</div>
                        @if($contact->job_title)
                        <div class="font-mono text-[11.5px] text-tertiary truncate">{{ $contact->job_title }}</div>
                        @endif
                    </div>
                </a>
                @endif

                @if($company)
                @php
                    $coColor    = \App\Helpers\Avatar::color($company->name);
                    $coInitials = strtoupper(mb_substr($company->name, 0, 2));
                @endphp
                <div class="mono-label mt-6 mb-3">Entreprise</div>
                <a href="{{ '/companies/' . $company->id }}" class="flex items-center gap-2 hover:opacity-80">
                    <span class="av lg {{ $coColor }} sq" style="border-radius: 6px;">{{ $coInitials }}</span>
                    <div class="flex-1">
                        <div class="font-medium text-[13px]">{{ $company->name }}</div>
                        @if($company->industry)
                        <div class="font-mono text-[11.5px] text-tertiary">{{ $company->industry }}</div>
                        @endif
                    </div>
                </a>
                @endif

                @if($deal->custom_values && count($deal->custom_values))
                <div class="mono-label mt-6 mb-3">Champs perso</div>
                <div class="flex flex-col gap-2.5 text-[13px]">
                    @foreach($deal->custom_values as $key => $val)
                    <div class="flex justify-between">
                        <span class="text-tertiary">{{ $key }}</span>
                        <span class="font-mono text-[11.5px]">{{ is_array($val) ? implode(', ', $val) : $val }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </aside>
    </div>
</aside>

</x-app-shell>
