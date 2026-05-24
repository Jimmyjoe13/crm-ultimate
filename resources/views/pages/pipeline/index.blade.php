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

{{-- Kanban board --}}
<div class="px-7 pb-12 flex gap-3 overflow-x-auto" id="kanban-board">
    @foreach($stagesWithDeals as $sw)
    @php
        $stage = $sw['stage'];
        $deals = $sw['deals'];
        $stageTotal = $deals->sum('amount');
    @endphp
    <div class="k-col flex-shrink-0" data-stage="{{ $stage->id }}" id="stage-{{ $stage->id }}">
        {{-- Column header --}}
        <div class="flex items-center justify-between px-1 pb-1">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full" style="background: var(--info);"></span>
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
            @endphp
            <div class="k-card" data-deal="{{ $deal->id }}">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="text-[13px] font-medium text-primary leading-snug">{{ $deal->name }}</div>
                </div>
                <div class="num-mono text-[12px] text-secondary">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</div>
                <div class="flex items-center gap-2 mt-3 pt-2 border-t border-default">
                    <span class="av {{ $color }} sm">{{ $initials }}</span>
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

<script>
// SortableJS wiring for drag-drop between columns
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Sortable === 'undefined') return;

    document.querySelectorAll('.sortable-list').forEach(function(list) {
        Sortable.create(list, {
            group: 'kanban',
            animation: 150,
            ghostClass: 'opacity-50',
            onEnd: function(evt) {
                const dealId  = evt.item.dataset.deal;
                const stageId = evt.to.dataset.stage;
                if (!dealId || !stageId) return;

                fetch('/api/v1/deals/' + dealId + '/move', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ pipeline_stage_id: parseInt(stageId) }),
                });
            }
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

</x-app-shell>
