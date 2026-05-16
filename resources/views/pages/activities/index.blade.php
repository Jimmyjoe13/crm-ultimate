<x-app-shell active="activities" breadcrumb="Activités">

<div class="px-7 pt-6 pb-3">
    <h1>Activités</h1>
    <p class="text-sm text-secondary mt-0.5"><span class="num-mono">{{ $activities->total() }}</span> activités</p>
</div>

<div class="px-7 pb-12">
    <div class="card p-4">
        @forelse($activities as $activity)
        @php
            $dot = match($activity->type) { 'email' => 'info', 'call' => 'accent', 'note' => '', default => '' };
            $emoji = match($activity->type) { 'email' => '📧', 'call' => '📞', 'note' => '📝', 'task' => '✓', default => '➕' };
        @endphp
        <div class="tl-item">
            <span class="tl-time">
                {{ $activity->created_at->format('H:i') }}<br>
                <span style="font-size:9.5px;">{{ $activity->created_at->format('d/m') }}</span>
            </span>
            <div class="tl-axis"><div class="tl-dot {{ $dot }}"></div></div>
            <div class="tl-content">
                <div class="ti">{{ $emoji }} {{ $activity->title }}</div>
                @if($activity->body)
                <div class="ts">{{ Str::limit($activity->body, 80) }}</div>
                @endif
                <div class="ts">{{ $activity->type }}</div>
            </div>
        </div>
        @empty
        <div class="py-12 text-center text-tertiary text-sm">Aucune activité.</div>
        @endforelse

        @if($activities->hasPages())
        <div class="mt-4 pt-4 border-t border-default flex items-center justify-between text-[12px] text-secondary">
            <span class="num-mono">{{ $activities->firstItem() }}–{{ $activities->lastItem() }} sur {{ $activities->total() }}</span>
            <div class="flex gap-2">
                @if(!$activities->onFirstPage())
                <a href="{{ $activities->previousPageUrl() }}" class="btn sm">← Précédent</a>
                @endif
                @if($activities->hasMorePages())
                <a href="{{ $activities->nextPageUrl() }}" class="btn sm">Suivant →</a>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

</x-app-shell>
