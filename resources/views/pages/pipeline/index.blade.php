<x-app-shell active="pipeline" breadcrumb="Pipeline">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Pipeline {{ $pipeline?->name }}</h1>
        <p class="text-sm text-secondary mt-0.5">
            Glisse les deals d'une étape à l'autre ·
            <span class="num-mono">{{ number_format($total, 0, ',', "\xc2\xa0") }} €</span> total
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ '/deals' }}" class="btn sm ghost">
            <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>Table
        </a>
    </div>
</div>

{{-- Palette de couleurs d'étape (dérivée des tokens du design system) --}}
@php
    $stageColors = ['var(--info)', 'var(--accent)', 'var(--ok)', 'var(--warn)', '#8b5cf6', 'var(--err)', '#06b6d4'];
@endphp

{{-- Kanban board --}}
<div class="px-7 pb-12 flex gap-3 overflow-x-auto" id="kanban-board">
    @foreach($stagesWithDeals as $sw)
    @php
        $stage = $sw['stage'];
        $deals = $sw['deals'];
        $stageTotal = $deals->sum('amount');
        $stageColor = $stageColors[$loop->index % count($stageColors)];
    @endphp
    <div class="k-col flex-shrink-0" data-stage="{{ $stage->id }}" id="stage-{{ $stage->id }}">
        {{-- Column header --}}
        <div class="flex items-center justify-between px-1 pb-1">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $stageColor }};"></span>
                <span class="text-[13px] font-semibold text-primary">{{ $stage->name }}</span>
                <span class="chip num-mono">{{ $deals->count() }}</span>
            </div>
            <span class="num-mono text-[11px] text-tertiary">{{ number_format($stageTotal, 0, ',', "\xc2\xa0") }} €</span>
        </div>

        {{-- Deal cards --}}
        <div class="flex flex-col gap-2 sortable-list" data-stage="{{ $stage->id }}">
            @forelse($deals as $deal)
            @php
                $company = $deal->companies->first();
                $owner   = $deal->owner;
                $initials = $owner ? \App\Helpers\Avatar::initials($owner->name ?? $owner->email) : '?';
                $color    = $owner ? \App\Helpers\Avatar::color($owner->name ?? $owner->email) : 'c1';
                $closesSoon = $deal->close_date && $deal->close_date->lte(now()->addDays(7));
                $isHot = ($hotThreshold && $deal->amount >= $hotThreshold) || $closesSoon;
                $daysToClose = $deal->close_date ? (int) now()->startOfDay()->diffInDays($deal->close_date->copy()->startOfDay(), false) : null;
            @endphp
            <div class="k-card {{ $isHot ? 'hot' : '' }}" data-deal="{{ $deal->id }}" @unless($isHot) style="border-left:3px solid {{ $stageColor }};" @endunless>
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="text-[13px] font-medium text-primary leading-snug">{{ $deal->name }}</div>
                    @if($isHot)
                    <span class="flex-shrink-0" title="Deal prioritaire">🔥</span>
                    @endif
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="num-mono text-[12px] text-secondary">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span>
                    @if($stage->probability)
                    <span class="mono-label text-[10px] text-tertiary">· {{ $stage->probability }}%</span>
                    @endif
                    @if(!is_null($daysToClose))
                        @if($daysToClose < 0)
                        <x-chip color="red">En retard</x-chip>
                        @elseif($daysToClose <= 7)
                        <x-chip color="yellow">J-{{ $daysToClose }}</x-chip>
                        @endif
                    @endif
                </div>
                <div class="flex items-center gap-2 mt-3 pt-2 border-t border-default">
                    <span class="av {{ $color }} sm" title="{{ $owner?->name ?? $owner?->email ?? 'Non assigné' }}">{{ $initials }}</span>
                    @if($company)
                    <span class="mono-label text-[10px]">{{ Str::limit($company->name, 18) }}</span>
                    @endif
                    <span class="ml-auto mono-label text-[10px]">{{ $deal->created_at->diffForHumans(null, true) }}</span>
                </div>
            </div>
            @empty
            <div class="text-center py-6 text-tertiary text-xs font-mono">Vide</div>
            @endforelse
        </div>


    </div>
    @endforeach
</div>

</x-app-shell>
