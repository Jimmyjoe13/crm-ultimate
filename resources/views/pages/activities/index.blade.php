<x-app-shell active="activities" breadcrumb="Activités">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Activités</h1>
        <p class="text-sm text-secondary mt-0.5"><span class="num-mono">{{ $activities->total() }}</span> activités enregistrées</p>
    </div>
</div>

<div class="px-7 pb-12">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @forelse($activities as $activity)
        @php
            $displayTime = $activity->occurred_at ?? $activity->created_at;
            
            // Configuration de l'icône, couleur de fond et type
            $iconBg = match($activity->type) {
                'email', 'email_sent', 'email_opened', 'email_clicked', 'email_replied' => 'var(--info-soft)',
                'call' => 'var(--accent-soft)',
                'task' => 'var(--warn-soft)',
                'note' => 'var(--ok-soft)',
                default => 'var(--surface2)',
            };
            
            $iconColor = match($activity->type) {
                'email', 'email_sent', 'email_opened', 'email_clicked', 'email_replied' => 'var(--info)',
                'call' => 'var(--accent)',
                'task' => 'var(--warn)',
                'note' => 'var(--ok)',
                default => 'var(--text2)',
            };

            $emoji = match($activity->type) {
                'email' => '📧',
                'call' => '📞',
                'note' => '📝',
                'task' => '✓',
                'email_sent' => '📤',
                'email_opened' => '👁',
                'email_clicked' => '🔗',
                'email_replied' => '↩️',
                'email_bounced' => '⚠️',
                'email_unsubscribed' => '🚫',
                default => '➕',
            };

            $isTask = $activity->type === 'task';

            // Récupération des informations sur le sujet lié
            $subjectName = '—';
            $subjectUrl = null;
            $subjectBadgeClass = '';
            
            if ($activity->subject) {
                if ($activity->subject_type === \App\Models\Contact::class) {
                    $subjectName = trim($activity->subject->first_name . ' ' . $activity->subject->last_name) ?: $activity->subject->email;
                    $subjectUrl = "/contacts/" . $activity->subject->id;
                    $subjectBadgeClass = 'info';
                } elseif ($activity->subject_type === \App\Models\Company::class) {
                    $subjectName = $activity->subject->name;
                    $subjectUrl = "/companies/" . $activity->subject->id;
                    $subjectBadgeClass = 'ok';
                } elseif ($activity->subject_type === \App\Models\Deal::class) {
                    $subjectName = $activity->subject->name;
                    $subjectUrl = "/deals/" . $activity->subject->id;
                    $subjectBadgeClass = 'accent';
                }
            }

            // Gestion de l'avatar du commercial
            $ownerName = $activity->owner?->name ?? 'Système';
            $ownerColor = \App\Helpers\Avatar::color($ownerName);
            $ownerInitials = \App\Helpers\Avatar::initials($ownerName);
        @endphp

        <div class="card p-5 hover:shadow-md transition-all flex flex-col justify-between"
             style="min-height: 160px; border-color: var(--border);"
             @if($isTask)
             x-data="{ done: {{ $activity->status === 'completed' ? 'true' : 'false' }} }"
             :class="{ 'opacity-65 bg-surface2': done }"
             @endif>
            
            <div>
                <!-- En-tête de la carte d'activité -->
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <!-- Icône stylisée -->
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" 
                             style="background: {{ $iconBg }}; color: {{ $iconColor }}; font-size: 14px;">
                            {{ $emoji }}
                        </div>
                        
                        <!-- Sujet associé -->
                        @if($subjectUrl)
                        <div class="min-w-0">
                            <a href="{{ $subjectUrl }}" class="chip {{ $subjectBadgeClass }} hover:opacity-85 transition-opacity max-w-full">
                                <span class="truncate">{{ $subjectName }}</span>
                            </a>
                        </div>
                        @else
                        <span class="text-xs text-tertiary italic">Aucune association</span>
                        @endif
                    </div>

                    <!-- Date relative / Tâche interactive -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($isTask)
                        <span class="ckb"
                              :class="{ 'on': done }"
                              style="cursor:pointer;"
                              title="Marquer comme fait / à faire"
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
                        @endif

                        @if($activity->owner_id === auth()->id() || in_array(auth()->user()?->role, ['admin', 'manager']))
                        <form method="POST" action="/activities/{{ $activity->id }}"
                              onsubmit="return confirm('Supprimer cette activité ?')"
                              class="m-0 p-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn ghost sm p-1 text-tertiary hover:text-primary" title="Supprimer l'activité">
                                <svg class="ic" style="width:12.5px;height:12.5px;stroke:var(--err);" viewBox="0 0 24 24">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </form>
                        @endif
                    </div>
                </div>

                <!-- Contenu de l'activité -->
                <div class="mb-4">
                    <h3 class="text-[14.5px] font-bold text-primary leading-snug">
                        {{ $activity->title }}
                    </h3>
                    @if($activity->body)
                    <p class="text-xs text-secondary mt-1.5 leading-relaxed break-words" x-data="{ expanded: false }">
                        <span x-show="!expanded">{{ Str::limit($activity->body, 140) }}</span>
                        <span x-show="expanded" x-cloak style="white-space: pre-wrap;">{{ $activity->body }}</span>
                        
                        @if(mb_strlen($activity->body) > 140)
                        <button type="button" @click="expanded = !expanded" class="text-accent font-semibold hover:underline ml-1 focus:outline-none" style="font-size: 10.5px;">
                            <span x-show="!expanded">Lire la suite</span>
                            <span x-show="expanded">Réduire</span>
                        </button>
                        @endif
                    </p>
                    @endif
                </div>
            </div>

            <!-- Pied de page : Agent & Date -->
            <div class="flex items-center justify-between pt-3 border-t border-default mt-auto">
                <div class="flex items-center gap-1.5">
                    <span class="av sm {{ $ownerColor }}" title="Agent : {{ $ownerName }}">{{ $ownerInitials }}</span>
                    <span class="text-[11px] text-secondary font-medium">{{ $ownerName }}</span>
                </div>
                <div class="text-[11px] text-tertiary font-mono" title="{{ $displayTime->format('d/m/Y H:i') }}">
                    {{ $displayTime->diffForHumans() }}
                </div>
            </div>

        </div>
        @empty
        <div class="col-span-full py-16 text-center card bg-surface">
            <div class="text-tertiary text-2xl mb-2">📭</div>
            <p class="text-sm text-secondary">Aucune activité enregistrée pour le moment.</p>
        </div>
        @endforelse
    </div>

    @if($activities->hasPages())
    <div class="mt-6">
        <x-pagination :paginator="$activities" />
    </div>
    @endif
</div>

</x-app-shell>
