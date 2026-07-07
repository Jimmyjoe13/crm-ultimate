<x-app-shell active="settings" breadcrumb="Journal d'audit">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Journal d'Audit</h1>
        <p class="text-sm text-secondary mt-0.5">Supervisez l'activité et les modifications apportées par vos agents ou par le système</p>
    </div>
</div>

<div class="px-7 pb-12">
    <!-- Barre de filtres -->
    <form method="GET" action="{{ route('audit.index') }}" class="card p-4 mb-6 flex flex-wrap items-center justify-between gap-4" style="background: var(--surface2); border-color: var(--border);">
        <div class="flex flex-wrap items-center gap-4 flex-1">
            <!-- Filtre Agent -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider">Agent / Utilisateur</label>
                <select name="user_id" class="select-arrow py-1.5 px-3 text-xs" style="min-width: 160px; height: auto;">
                    <option value="">Tous les agents</option>
                    <option value="system" {{ request('user_id') === 'system' ? 'selected' : '' }}>⚙️ Système / Robots</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            👤 {{ $u->name }} ({{ $u->role }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filtre Action -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider">Type d'action</label>
                <select name="event" class="select-arrow py-1.5 px-3 text-xs" style="min-width: 140px; height: auto;">
                    <option value="">Toutes les actions</option>
                    <option value="created" {{ request('event') === 'created' ? 'selected' : '' }}>🆕 Création</option>
                    <option value="updated" {{ request('event') === 'updated' ? 'selected' : '' }}>✏️ Modification</option>
                    <option value="deleted" {{ request('event') === 'deleted' ? 'selected' : '' }}>❌ Suppression</option>
                    <option value="associated" {{ request('event') === 'associated' ? 'selected' : '' }}>🔗 Association</option>
                    <option value="dissociated" {{ request('event') === 'dissociated' ? 'selected' : '' }}>🚫 Dissociation</option>
                </select>
            </div>

            <!-- Filtre Entité -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-tertiary font-mono uppercase tracking-wider">Entité</label>
                <select name="entity_type" class="select-arrow py-1.5 px-3 text-xs" style="min-width: 140px; height: auto;">
                    <option value="">Toutes les entités</option>
                    <option value="contact" {{ request('entity_type') === 'contact' ? 'selected' : '' }}>👤 Contact</option>
                    <option value="company" {{ request('entity_type') === 'company' ? 'selected' : '' }}>🏢 Entreprise</option>
                    <option value="deal" {{ request('entity_type') === 'deal' ? 'selected' : '' }}>💼 Deal</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-2 mt-4 lg:mt-0">
            <button type="submit" class="btn primary sm">Filtrer</button>
            @if(request()->anyFilled(['user_id', 'event', 'entity_type']))
                <a href="{{ route('audit.index') }}" class="btn ghost sm">Réinitialiser</a>
            @endif
        </div>
    </form>

    <!-- Liste chronologique des logs d'audit -->
    <div class="card p-0 overflow-hidden" style="border-color: var(--border);">
        <div class="overflow-x-auto">
            <table class="table-default w-full border-collapse text-left text-xs">
                <thead>
                    <tr style="background: var(--surface2); border-bottom: 1px solid var(--border);">
                        <th class="p-4 font-semibold text-secondary" style="width: 120px;">Date</th>
                        <th class="p-4 font-semibold text-secondary" style="width: 140px;">Agent</th>
                        <th class="p-4 font-semibold text-secondary" style="width: 100px;">Action</th>
                        <th class="p-4 font-semibold text-secondary" style="width: 180px;">Entité cible</th>
                        <th class="p-4 font-semibold text-secondary">Détails des modifications</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-default">
                    @forelse($auditLogs as $log)
                        @php
                            $actionLabel = match($log->event) {
                                'created'     => 'Création',
                                'updated'     => 'Modification',
                                'deleted'     => 'Suppression',
                                'associated'  => 'Association',
                                'dissociated' => 'Dissociation',
                                default       => ucfirst($log->event),
                            };

                            $actionColorClass = match($log->event) {
                                'created'     => 'ok',
                                'updated'     => 'accent',
                                'deleted'     => 'err',
                                'associated'  => 'info',
                                'dissociated' => 'warn',
                                default       => '',
                            };

                            // Formatage de la cible
                            $entityName = match($log->auditable_type) {
                                \App\Models\Contact::class => '👤 Contact',
                                \App\Models\Company::class => '🏢 Entreprise',
                                \App\Models\Deal::class    => '💼 Deal',
                                default => basename(str_replace('\\', '/', $log->auditable_type)),
                            };

                            $targetUrl = null;
                            if ($log->event !== 'deleted') {
                                $targetUrl = match($log->auditable_type) {
                                    \App\Models\Contact::class => route('contacts.show', $log->auditable_id),
                                    \App\Models\Company::class => route('companies.show', $log->auditable_id),
                                    \App\Models\Deal::class    => route('deals.show', $log->auditable_id),
                                    default => null,
                                };
                            }

                            $ownerName = $log->user?->name ?? 'Système';
                            $ownerColor = \App\Helpers\Avatar::color($ownerName);
                            $ownerInitials = \App\Helpers\Avatar::initials($ownerName);
                        @endphp
                        <tr class="hover:bg-surface2 transition-colors">
                            <!-- Date -->
                            <td class="p-4 font-mono text-tertiary whitespace-nowrap">
                                {{ $log->created_at->format('d/m/Y') }}<br>
                                <span class="text-[10px] text-tertiary">{{ $log->created_at->format('H:i:s') }}</span>
                            </td>

                            <!-- Agent -->
                            <td class="p-4 font-medium text-primary">
                                <div class="flex items-center gap-2">
                                    <span class="av sm {{ $ownerColor }}">{{ $ownerInitials }}</span>
                                    <div class="min-w-0">
                                        <div class="truncate">{{ $ownerName }}</div>
                                        <div class="text-[10px] text-tertiary truncate font-mono">{{ $log->user?->role ?? 'robot' }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Action -->
                            <td class="p-4">
                                <span class="chip font-mono text-[10px] uppercase tracking-wider {{ $actionColorClass }}">
                                    {{ $actionLabel }}
                                </span>
                            </td>

                            <!-- Entité cible -->
                            <td class="p-4">
                                <div class="font-medium text-primary">
                                    {{ $entityName }}
                                </div>
                                @if($targetUrl)
                                    <a href="{{ $targetUrl }}" class="text-[10px] hover:underline" style="color: var(--accent);">
                                        ID #{{ $log->auditable_id }} ↗
                                    </a>
                                @else
                                    <span class="text-[10px] text-tertiary font-mono">ID #{{ $log->auditable_id }}</span>
                                @endif
                            </td>

                            <!-- Détails des modifications -->
                            <td class="p-4">
                                @if($log->event === 'updated')
                                    <div class="space-y-1">
                                        @foreach($log->new_values as $key => $newValue)
                                            @php
                                                $oldValue = $log->old_values[$key] ?? '—';
                                                if (is_array($newValue) || is_object($newValue)) $newValue = json_encode($newValue);
                                                if (is_array($oldValue) || is_object($oldValue)) $oldValue = json_encode($oldValue);
                                                
                                                // Masquer les grosses chaines pour garder la table lisible
                                                if (mb_strlen((string)$oldValue) > 60) $oldValue = mb_substr((string)$oldValue, 0, 57) . '...';
                                                if (mb_strlen((string)$newValue) > 60) $newValue = mb_substr((string)$newValue, 0, 57) . '...';
                                            @endphp
                                            <div class="flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                                                <span class="text-tertiary font-semibold">{{ $key }}:</span>
                                                <span class="px-1.5 py-0.5 rounded text-secondary bg-surface1 line-through" style="font-size:10px;">{{ $oldValue }}</span>
                                                <span class="text-tertiary font-bold">➔</span>
                                                <span class="px-1.5 py-0.5 rounded text-primary" style="font-size:10.5px; background: var(--ok-soft); border-left: 2px solid var(--ok);">{{ $newValue }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif($log->event === 'created')
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach($log->new_values as $key => $val)
                                            @php
                                                if (is_array($val) || is_object($val)) $val = json_encode($val);
                                                if (mb_strlen((string)$val) > 40) $val = mb_substr((string)$val, 0, 37) . '...';
                                            @endphp
                                            <span class="px-1.5 py-0.5 rounded font-mono text-[10px] text-secondary bg-surface1">
                                                <strong class="text-tertiary">{{ $key }}:</strong> {{ $val }}
                                            </span>
                                        @endforeach
                                    </div>
                                @elseif($log->event === 'associated')
                                    <div class="font-mono text-[11px] text-secondary">
                                        <span class="text-tertiary font-semibold">Liaison :</span>
                                        Relation <span class="text-primary font-bold">{{ $log->new_values['relation'] ?? '—' }}</span>
                                        liée à l'entité <span class="text-primary font-bold">{{ basename(str_replace('\\', '/', $log->new_values['child_type'] ?? '—')) }} #{{ $log->new_values['child_id'] ?? '—' }}</span>
                                    </div>
                                @elseif($log->event === 'dissociated')
                                    <div class="font-mono text-[11px] text-secondary">
                                        <span class="text-tertiary font-semibold">Détachement :</span>
                                        Relation <span class="text-primary font-bold">{{ $log->old_values['relation'] ?? '—' }}</span>
                                        retirée de l'entité <span class="text-primary font-bold">{{ basename(str_replace('\\', '/', $log->old_values['child_type'] ?? '—')) }} #{{ $log->old_values['child_id'] ?? '—' }}</span>
                                    </div>
                                @elseif($log->event === 'deleted')
                                    <span class="text-tertiary italic">L'entité a été supprimée définitivement.</span>
                                @else
                                    <span class="text-tertiary italic">Aucun détail supplémentaire.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-tertiary">
                                📭 Aucun enregistrement d'audit ne correspond à vos filtres.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($auditLogs->hasPages())
            <div class="p-4 border-t border-default">
                <x-pagination :paginator="$auditLogs" />
            </div>
        @endif
    </div>
</div>

</x-app-shell>
