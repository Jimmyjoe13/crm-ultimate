<x-app-shell active="contacts" breadcrumb="Contacts">

@php $total = $contacts->total(); @endphp

<div x-data class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Contacts</h1>
        <p class="text-sm text-secondary mt-0.5">
            <span class="num-mono">{{ $total }}</span> contacts
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        {{-- Bouton "Tout sélectionner / Désélectionner" --}}
        <button x-show="!$store.bulk.isSelectAllMode('contact')"
                @click="$store.bulk.enableSelectAll('contact')"
                class="btn ghost sm">
            Tout sélectionner ({{ $total }})
        </button>
        <button x-show="$store.bulk.isSelectAllMode('contact')"
                @click="$store.bulk.clear('contact')"
                class="btn ghost sm" style="color:var(--err)">
            Désélectionner tout
        </button>
        <x-button variant="ghost" size="sm" href="/imports/contact/create"
                  icon='<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'>
            Importer CSV
        </x-button>
        <a href="/contacts/export{{ $search ? '?search='.urlencode($search) : '' }}" class="btn sm ghost">
            <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exporter CSV
        </a>
        @endif
        <x-button href="/contacts/create" size="sm">Nouveau contact</x-button>
    </div>
</div>

@php
    $contactFilters = [
        ['name' => 'lifecycle_stage', 'label' => 'Lifecycle', 'value' => $lifecycle, 'options' => [
            'lead' => 'Lead', 'mql' => 'MQL', 'sql' => 'SQL', 'opportunity' => 'Opportunité',
            'customer' => 'Client', 'evangelist' => 'Évangéliste', 'other' => 'Autre',
        ]],
        ['name' => 'lead_status', 'label' => 'Statut', 'value' => $leadStatus, 'options' => [
            'new' => 'Nouveau', 'open' => 'Ouvert', 'in_progress' => 'En cours',
            'connected' => 'Connecté', 'unqualified' => 'Non qualifié', 'bad_fit' => 'Hors cible',
        ]],
        ['name' => 'has_deal', 'label' => 'Deals', 'value' => $hasDeal, 'options' => [
            'yes' => 'Avec deal', 'no' => 'Sans deal',
        ]],
    ];
    if ($owners->count() > 1) {
        $contactFilters[] = ['name' => 'owner_id', 'label' => 'Propriétaire', 'value' => $ownerId,
            'options' => $owners->pluck('name', 'id')->all()];
    }
@endphp

<x-filter-bar action="/contacts" :search="$search" placeholder="Rechercher un contact…"
              :filters="$contactFilters" :preserve="['sort' => $sort, 'dir' => $dir]">
    <label class="flex items-center gap-2 text-xs text-secondary cursor-pointer select-none">
        <input type="hidden" name="hide_blacklisted" value="0">
        <input type="checkbox" name="hide_blacklisted" value="1"
               @checked($hideBlacklisted)
               @change="$el.form.submit()"
               class="rounded border-default text-accent focus:ring-accent" style="width:14px;height:14px;cursor:pointer;">
        <span>Masquer blacklistés ({{ \App\Models\Contact::blacklisted()->count() }})</span>
    </label>
    <label class="flex items-center gap-2 text-xs text-secondary cursor-pointer select-none ml-2">
        <input type="hidden" name="is_hot" value="0">
        <input type="checkbox" name="is_hot" value="1"
               @checked($isHot)
               @change="$el.form.submit()"
               class="rounded border-default text-accent focus:ring-accent" style="width:14px;height:14px;cursor:pointer;">
        <span>🔥 Contacts Chauds (score ≥ 70)</span>
    </label>
</x-filter-bar>

<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        @php $pageIds = $contacts->pluck('id')->toArray(); @endphp
        <table class="t" x-data>
            <thead>
                <tr>
                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <th style="width:36px;">
                        <span class="ckb"
                              :class="{ 'on': $store.bulk.isSelectAllMode('contact') || $store.bulk.allSelected('contact', {{ json_encode($pageIds) }}) }"
                              @click.stop="
                                if ($store.bulk.isSelectAllMode('contact')) {
                                    $store.bulk.clear('contact');
                                } else {
                                    $store.bulk.toggleAll('contact', {{ json_encode($pageIds) }});
                                }
                              "
                              style="cursor:pointer;"></span>
                    </th>
                    @endif
                    <x-sort-th column="last_name" label="Contact"  :sort="$sort" :dir="$dir" />
                    <x-sort-th column="email"    label="Email"    :sort="$sort" :dir="$dir" />
                    <th>Téléphone</th>
                    <th>Entreprise</th>
                    <th>Lifecycle</th>
                    <x-sort-th column="ai_score" label="Score IA" :sort="$sort" :dir="$dir" />
                    <x-sort-th column="last_activity" label="Dernière Activité" :sort="$sort" :dir="$dir" />
                </tr>
            </thead>
            <tbody>
                {{-- Bannière "tous sélectionnés" --}}
                @if(in_array(auth()->user()?->role, ['admin','manager']))
                <tr x-show="$store.bulk.isSelectAllMode('contact')" style="display:none;">
                    <td colspan="8" class="text-center py-2.5 text-[12.5px] font-medium" style="background:var(--ok-soft);color:var(--ok)">
                        Les {{ $total }} contacts sont sélectionnés.
                        <button @click="$store.bulk.clear('contact')"
                                class="underline ml-1" style="color:var(--err)">
                            Annuler la sélection
                        </button>
                    </td>
                </tr>
                @endif

                @forelse($contacts as $contact)
                @php
                    $fullName = trim($contact->first_name . ' ' . $contact->last_name);
                    $color    = \App\Helpers\Avatar::color($fullName ?: $contact->email);
                    $initials = \App\Helpers\Avatar::initials($fullName ?: $contact->email);
                    $company  = $contact->companies->first();
                @endphp
                <tr onclick="window.location='{{ '/contacts/' . $contact->id }}'" style="cursor:pointer;"
                    :class="{ 'bg-surface2': $store.bulk.isSelectAllMode('contact') || $store.bulk.selections.contact.has({{ $contact->id }}) }">
                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <td @click.stop>
                        <span class="ckb"
                              :class="{ 'on': $store.bulk.isSelectAllMode('contact') || $store.bulk.selections.contact.has({{ $contact->id }}) }"
                              @click.stop="
                                if ($store.bulk.isSelectAllMode('contact')) {
                                    $store.bulk.clear('contact');
                                } else {
                                    $store.bulk.toggle('contact', {{ $contact->id }});
                                }
                              "
                              style="cursor:pointer;"></span>
                    </td>
                    @endif
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="av {{ $color }}">{{ $initials }}</span>
                            <div>
                                <div class="font-medium text-[13px] flex items-center gap-1.5">
                                    <span>{{ $fullName ?: $contact->email }}</span>
                                    @if($contact->blacklisted_at)
                                    <span class="chip err sm" style="font-size: 10px; padding: 1px 6px;">Blacklisté</span>
                                    @endif
                                </div>
                                @if($contact->job_title)
                                <div class="text-[11.5px] text-tertiary font-mono">{{ $contact->job_title }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td><span class="text-secondary text-[12px] font-mono">{{ $contact->email ?? '—' }}</span></td>
                    <td><span class="text-secondary text-[12px] font-mono">{{ $contact->phone ?? '—' }}</span></td>
                    <td><span class="text-secondary">{{ $company?->name ?? '—' }}</span></td>
                    <td>
                        @if($contact->lifecycle_stage)
                        <span class="chip">{{ $contact->lifecycle_stage }}</span>
                        @else
                        <span class="text-tertiary">—</span>
                        @endif
                    </td>
                    <td>
                        @if($contact->ai_score !== null)
                        @php $s = $contact->ai_score; $hue = (int)($s * 1.2); @endphp
                        <span title="Score IA : {{ $s }}/100 — mis à jour {{ $contact->ai_score_updated_at?->diffForHumans() }}"
                              style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;padding:2px 7px;border-radius:999px;font-size:11px;font-weight:600;color:#fff;background:hsl({{ $hue }},65%,42%);">
                            {{ $s }}
                        </span>
                        @else
                        <span class="text-tertiary">—</span>
                        @endif
                    </td>
                    <td>
                        @if($contact->activities_max_created_at)
                        <span class="text-secondary text-[12px] font-mono" title="{{ \Carbon\Carbon::parse($contact->activities_max_created_at)->format('d/m/Y H:i') }}">
                            {{ \Carbon\Carbon::parse($contact->activities_max_created_at)->diffForHumans() }}
                        </span>
                        @else
                        <span class="text-tertiary text-[12px]">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                @php $hasFilters = request()->hasAny(['search', 'lifecycle_stage', 'lead_status', 'owner_id', 'has_deal', 'is_hot']); @endphp
                <tr><td colspan="{{ in_array(auth()->user()?->role, ['admin','manager']) ? 8 : 7 }}">
                    @if($hasFilters)
                        <x-empty-state title="Aucun contact ne correspond" subtitle="Essaie d'élargir ta recherche ou de réinitialiser les filtres." ctaLabel="Réinitialiser" ctaHref="/contacts" />
                    @else
                        <x-empty-state title="Aucun contact" subtitle="Crée ton premier contact ou importe un fichier CSV."
                                       icon='<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>'
                                       ctaLabel="Nouveau contact" ctaHref="/contacts/create" />
                    @endif
                </td></tr>
                @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$contacts" />
    </div>
</div>

@if(in_array(auth()->user()?->role, ['admin','manager']))
<x-bulk-bar entity="contact" delete-action="/contacts/bulk-destroy" :total-count="$contacts->total()" />
@endif

</x-app-shell>
