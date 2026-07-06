@props([
    'activities'   => collect(),
    'subjectType'  => '',
    'subjectId'    => null,
    'showComposer' => false,
    'filterSource' => null,
])

<div x-data="{
    limit: 15,
    filterType: 'all',
    filterSource: '{{ $filterSource ?? 'all' }}',
    searchQuery: '',
    sortOrder: 'desc',
    totalVisible: 0,
    items: [],
    init() {
        this.updateItems();
        this.$watch('filterType', () => this.applyFilters());
        this.$watch('filterSource', () => this.applyFilters());
        this.$watch('searchQuery', () => this.applyFilters());
        this.$watch('sortOrder', () => this.applyFilters());
        this.$watch('limit', () => this.applyFilters());
    },
    updateItems() {
        this.items = Array.from(this.$refs.container.querySelectorAll('.tl-item')).map(el => {
            return {
                el: el,
                type: el.dataset.type,
                source: el.dataset.source || 'manual',
                text: (el.dataset.text || '').toLowerCase(),
                timestamp: parseInt(el.dataset.timestamp || '0')
            };
        });
        this.applyFilters();
    },
    applyFilters() {
        let sorted = [...this.items];
        sorted.sort((a, b) => {
            return this.sortOrder === 'desc' ? b.timestamp - a.timestamp : a.timestamp - b.timestamp;
        });

        let visibleIdx = 0;
        sorted.forEach(item => {
            let matchesType = this.filterType === 'all' || 
                (this.filterType === 'email' && ['email', 'email_sent', 'email_opened', 'email_clicked', 'email_replied', 'email_bounced', 'email_unsubscribed'].includes(item.type)) ||
                item.type === this.filterType;
            let matchesSource = this.filterSource === 'all' || item.source === this.filterSource;
            let matchesSearch = !this.searchQuery || item.text.includes(this.searchQuery.toLowerCase());

            let isVisible = matchesType && matchesSource && matchesSearch;
            
            if (isVisible) {
                item.el.style.order = visibleIdx;
                if (visibleIdx < this.limit) {
                    item.el.style.display = '';
                } else {
                    item.el.style.display = 'none';
                }
                visibleIdx++;
            } else {
                item.el.style.display = 'none';
            }
        });
        this.totalVisible = visibleIdx;
    }
}" x-ref="container" class="flex flex-col gap-0">
    @if($showComposer)
    <form method="POST" action="/activities" class="card p-4 mb-4" style="order: -1;">
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

    <!-- Barre de filtres et de tri -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 p-3 rounded-lg border border-default" style="background: var(--surface2); order: 0;">
        <div class="flex flex-wrap items-center gap-2">
            <!-- Filtre Type -->
            <div class="flex items-center gap-1.5">
                <span class="text-[11px] text-tertiary font-mono">Type:</span>
                <select x-model="filterType" class="select-arrow py-1 px-2 text-xs" style="height: auto; width: auto; min-width: 100px;">
                    <option value="all">Tous</option>
                    <option value="note">📝 Notes</option>
                    <option value="call">📞 Appels</option>
                    <option value="email">📧 Emails</option>
                    <option value="task">✓ Tâches</option>
                </select>
            </div>
            
            <!-- Filtre Source (uniquement si non imposé en PHP) -->
            @if($filterSource === null)
            <div class="flex items-center gap-1.5">
                <span class="text-[11px] text-tertiary font-mono">Source:</span>
                <select x-model="filterSource" class="select-arrow py-1 px-2 text-xs" style="height: auto; width: auto; min-width: 100px;">
                    <option value="all">Toutes</option>
                    <option value="manual">Manuel</option>
                    <option value="emelia">Emelia (Sync)</option>
                </select>
            </div>
            @endif
        </div>

        <div class="flex items-center gap-2 flex-1 min-w-[150px] lg:flex-none">
            <!-- Recherche -->
            <input type="text" x-model="searchQuery" placeholder="Rechercher..." class="text-xs py-1 px-2.5 rounded border border-default flex-1" style="height: auto; min-width: 120px; background: var(--surface1);">
            
            <!-- Tri -->
            <button type="button" @click="sortOrder = (sortOrder === 'desc' ? 'asc' : 'desc')" class="btn ghost sm p-1" title="Inverser l'ordre">
                <span x-show="sortOrder === 'desc'" class="text-xs">⬇️ Récent</span>
                <span x-show="sortOrder === 'asc'" class="text-xs">⬆️ Ancien</span>
            </button>
        </div>
    </div>

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
        $normalizedSubjectType = match(strtolower($subjectType)) {
            'contact', \App\Models\Contact::class => \App\Models\Contact::class,
            'company', \App\Models\Company::class => \App\Models\Company::class,
            'deal', \App\Models\Deal::class => \App\Models\Deal::class,
            default => $subjectType,
        };
        $isCurrentSubject = ($activity->subject_type === $normalizedSubjectType && $activity->subject_id == $subjectId);
        $subjectUrl = $isCurrentSubject ? null : match($activity->subject_type) {
            \App\Models\Contact::class => route('contacts.show', $activity->subject_id),
            \App\Models\Company::class => route('companies.show', $activity->subject_id),
            \App\Models\Deal::class    => route('deals.show', $activity->subject_id),
            default => null,
        };
    @endphp
    <div class="tl-item"
         data-type="{{ $activity->type }}"
         data-source="{{ $activity->source ?? 'manual' }}"
         data-text="{{ $activity->title }} {{ $activity->body }}"
         data-timestamp="{{ $displayTime->timestamp }}"
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
            <div class="ti flex items-center justify-between group/ti">
                <div>
                    @if($subjectUrl)<a href="{{ $subjectUrl }}" class="hover:underline">{{ $emoji }} {{ $activity->title }}</a>@else{{ $emoji }} {{ $activity->title }}@endif
                    @if($activity->source === 'emelia' && ($activity->metadata['synthetic'] ?? false))
                    <span style="font-size:9px;padding:1px 4px;border-radius:3px;background:var(--surface-alt);color:var(--text-tertiary);vertical-align:middle;margin-left:4px;">sync</span>
                    @elseif($activity->source === 'emelia')
                    <span style="font-size:9px;padding:1px 4px;border-radius:3px;background:color-mix(in srgb,var(--ok) 15%,transparent);color:var(--ok);vertical-align:middle;margin-left:4px;">live</span>
                    @endif
                    @if($activity->type === 'email_replied' && !empty($activity->metadata['sentiment']))
                    @php
                        $s = $activity->metadata['sentiment'];
                        $sentimentEmoji = match($s['sentiment'] ?? '') {
                            'positif'           => '😊',
                            'négatif', 'negatif' => '😟',
                            default             => '😐',
                        };
                        $sentimentColor = match($s['sentiment'] ?? '') {
                            'positif'           => 'var(--ok)',
                            'négatif', 'negatif' => 'var(--err)',
                            default             => 'var(--text-tertiary)',
                        };
                    @endphp
                    <span title="{{ $s['summary'] ?? '' }}"
                          style="font-size:12px;vertical-align:middle;margin-left:5px;cursor:default;opacity:.85;"
                    >{{ $sentimentEmoji }}</span>
                    @endif
                </div>
                @if($activity->owner_id === auth()->id() || in_array(auth()->user()?->role, ['admin', 'manager']))
                <form method="POST" action="/activities/{{ $activity->id }}"
                      onsubmit="return confirm('Supprimer cette activité ?')"
                      class="opacity-0 group-hover/ti:opacity-100 transition-opacity flex items-center">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-0.5 hover:bg-surface2 rounded text-tertiary hover:text-primary transition-colors" title="Supprimer l'activité">
                        <svg class="ic" style="width:12px;height:12px;stroke:var(--err);" viewBox="0 0 24 24">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </form>
                @endif
            </div>
            @php
                $activityContent = $activity->body ?: ($activity->metadata['reply_text'] ?? '');
                $isLong = mb_strlen($activityContent) > 220;
                $isSynth = $activity->metadata['synthetic'] ?? false;
            @endphp
            @if($activityContent)
            <div x-data="{ expanded: false }" class="mt-1.5">
                <div class="ts leading-relaxed"
                     style="white-space: pre-wrap; word-break: break-word;"
                     :class="expanded ? '' : 'line-clamp-4'">{{ $activityContent }}</div>
                @if($isLong)
                <button type="button"
                        @click="expanded = !expanded"
                        class="text-[10px] mt-1 hover:underline font-semibold"
                        style="color: var(--accent);">
                    <span x-show="!expanded">↓ Voir la suite</span>
                    <span x-show="expanded">↑ Réduire</span>
                </button>
                @endif
            </div>
            @elseif($activity->type === 'email_replied' && $isSynth)
            <div class="ts text-tertiary italic mt-1" style="font-size:10px;">Corps de la réponse non disponible (importé via sync)</div>
            @endif
        </div>
    </div>
    @empty
    <div class="py-8 text-center text-tertiary text-sm">Aucune activité pour l'instant.</div>
    @endforelse

    <div x-show="totalVisible === 0" class="py-8 text-center text-tertiary text-sm" style="order: 999998;">
        Aucune activité ne correspond à vos critères de recherche ou filtres.
    </div>

    <button type="button" x-show="limit < totalVisible" @click="limit += 15" class="btn ghost w-full justify-center mt-3 text-xs" style="order: 999999;">
        Afficher plus d'activités...
    </button>
</div>
