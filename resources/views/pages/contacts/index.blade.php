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
        <form method="GET" action="{{ '/contacts' }}" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher…"
                   class="field" style="padding: 6px 10px; border: 1px solid var(--border); border-radius:7px; font-size:13px; background: var(--surface); color: var(--text);">
            <x-button type="submit" size="sm">Chercher</x-button>
        </form>
    </div>
</div>

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
                </tr>
            </thead>
            <tbody>
                {{-- Bannière "tous sélectionnés" --}}
                @if(in_array(auth()->user()?->role, ['admin','manager']))
                <tr x-show="$store.bulk.isSelectAllMode('contact')" style="display:none;">
                    <td colspan="6" class="text-center py-2.5 text-[12.5px] font-medium" style="background:var(--ok-soft);color:var(--ok)">
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
                                <div class="font-medium text-[13px]">{{ $fullName ?: $contact->email }}</div>
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
                </tr>
                @empty
                <tr><td colspan="{{ in_array(auth()->user()?->role, ['admin','manager']) ? 6 : 5 }}" class="text-center py-12 text-tertiary text-sm">Aucun contact.</td></tr>
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
