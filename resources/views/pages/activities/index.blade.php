<x-app-shell active="activities" breadcrumb="Activités">

<div class="px-7 pt-6 pb-3">
    <h1>Activités</h1>
    <p class="text-sm text-secondary mt-0.5"><span class="num-mono">{{ $activities->total() }}</span> activités</p>
</div>

<div class="px-7 pb-12">
    <div class="card p-4">
        @forelse($activities as $activity)
        @php
            $dot   = match($activity->type) { 'email' => 'info', 'call' => 'accent', default => '' };
            $emoji = match($activity->type) { 'email' => '📧', 'call' => '📞', 'note' => '📝', 'task' => '✓', default => '➕' };
            $isTask = $activity->type === 'task';
        @endphp
        <div class="tl-item"
             @if($isTask)
             x-data="{ done: {{ $activity->status === 'completed' ? 'true' : 'false' }} }"
             :class="{ 'opacity-60': done }"
             @endif>
            <span class="tl-time">
                {{ $activity->created_at->format('H:i') }}<br>
                <span style="font-size:9.5px;">{{ $activity->created_at->format('d/m') }}</span>
            </span>
            <div class="tl-axis">
                @if($isTask)
                <span class="ckb"
                      x-data
                      :class="{ 'on': done }"
                      style="cursor:pointer; margin-top:3px;"
                      @click.prevent="
                          done = !done;
                          fetch('/activities/{{ $activity->id }}/toggle-done', {
                              method: 'POST',
                              headers: {
                                  'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                  'Accept': 'application/json',
                              },
                              credentials: 'same-origin',
                          }).then(r => r.json()).then(d => { done = d.status === 'completed'; })
                              .catch(() => { done = !done; });
                      "></span>
                @else
                <div class="tl-dot {{ $dot }}"></div>
                @endif
            </div>
            <div class="tl-content">
                <div class="ti">{{ $emoji }} {{ $activity->title }}</div>
                @if($activity->body)
                <div class="ts">{{ Str::limit($activity->body, 80) }}</div>
                @endif
                <div class="ts">{{ $activity->type }}
                    @if($isTask && $activity->status === 'completed')
                    · <span class="text-accent">terminé</span>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="py-12 text-center text-tertiary text-sm">Aucune activité.</div>
        @endforelse

        <x-pagination :paginator="$activities" class="mt-4 pt-4 border-t border-default flex items-center justify-between text-[12px] text-secondary" />
    </div>
</div>

</x-app-shell>
