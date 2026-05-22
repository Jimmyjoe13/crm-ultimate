@props([
    'activities'   => collect(),
    'subjectType'  => '',
    'subjectId'    => null,
    'showComposer' => false,
    'filterSource' => null,
])

<div class="flex flex-col gap-0">
    @if($showComposer)
    <form method="POST" action="/activities" class="card p-4 mb-4">
        @csrf
        <input type="hidden" name="subject_type" value="{{ $subjectType }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
        <div class="mono-label mb-3">Ajouter une activité</div>
        <div class="grid grid-cols-2 gap-3">
            <div class="field">
                <label>Type</label>
                <select name="type" class="select-arrow" required>
                    <option value="note">📝 Note</option>
                    <option value="call">📞 Appel</option>
                    <option value="email">📧 Email</option>
                    <option value="task">✓ Tâche</option>
                </select>
            </div>
            <div class="field">
                <label>Titre *</label>
                <input type="text" name="title" required placeholder="Objet…">
            </div>
            <div class="field col-span-2">
                <label>Détails</label>
                <textarea name="body" rows="2" placeholder="Notes…"></textarea>
            </div>
        </div>
        <div class="flex justify-end mt-2">
            <button type="submit" class="btn primary sm">Ajouter</button>
        </div>
    </form>
    @endif

    @forelse($activities as $activity)
    @php
        if ($filterSource !== null && $activity->source !== $filterSource) continue;
        $displayTime = $activity->occurred_at ?? $activity->created_at;
        $dot = match($activity->type) {
            'email'                               => 'info',
            'call'                                => 'accent',
            'email_sent', 'email_opened',
            'email_clicked', 'email_replied'      => 'info',
            'email_bounced', 'email_unsubscribed' => 'err',
            default                               => '',
        };
        $emoji = match($activity->type) {
            'email'               => '📧',
            'call'                => '📞',
            'note'                => '📝',
            'task'                => '✓',
            'email_sent'          => '📤',
            'email_opened'        => '👁',
            'email_clicked'       => '🔗',
            'email_replied'       => '↩️',
            'email_bounced'       => '⚠️',
            'email_unsubscribed'  => '🚫',
            default               => '➕',
        };
        $isTask = $activity->type === 'task';
    @endphp
    <div class="tl-item"
         data-source="{{ $activity->source }}"
         @if($isTask)
         x-data="{ done: {{ $activity->status === 'completed' ? 'true' : 'false' }} }"
         :class="{ 'opacity-60': done }"
         @endif>
        <span class="tl-time">
            {{ $displayTime->format('d/m') }}<br>
            <span style="font-size:9.5px;">{{ $displayTime->format('H:i') }}</span>
        </span>
        <div class="tl-axis">
            @if($isTask)
            <span class="ckb"
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
            <div class="ti">
                {{ $emoji }} {{ $activity->title }}
                @if($activity->source === 'emelia' && ($activity->metadata['synthetic'] ?? false))
                <span style="font-size:9px;padding:1px 4px;border-radius:3px;background:var(--surface-alt);color:var(--text-tertiary);vertical-align:middle;margin-left:4px;">sync</span>
                @elseif($activity->source === 'emelia')
                <span style="font-size:9px;padding:1px 4px;border-radius:3px;background:color-mix(in srgb,var(--ok) 15%,transparent);color:var(--ok);vertical-align:middle;margin-left:4px;">live</span>
                @endif
            </div>
            @if($activity->body)
            <div class="ts">{{ Str::limit($activity->body, 100) }}</div>
            @endif
        </div>
    </div>
    @empty
    <div class="py-8 text-center text-tertiary text-sm">Aucune activité pour l'instant.</div>
    @endforelse
</div>
