<x-app-shell active="deals" breadcrumb="Deals / {{ $deal->name }}">

@php
    $company = $deal->companies->first();
    $contact = $deal->contacts->first();
    $owner   = $deal->owner;
    $ownerInitials = $owner ? \App\Helpers\Avatar::initials($owner->name ?? $owner->email) : '?';
    $ownerColor    = $owner ? \App\Helpers\Avatar::color($owner->name ?? $owner->email) : 'c1';
    $dealInitials  = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $deal->name), 0, 1) ?: 'D');
    $dealColor     = \App\Helpers\Avatar::color($deal->name);
    $isOverdue     = $deal->close_date && $deal->close_date->isPast();
    $currentPos    = $deal->stage?->position ?? 0;
@endphp

{{-- Background: dimmed deals list --}}
<div class="px-7 pt-6 pb-3 opacity-30 pointer-events-none select-none">
    <h1>Deals</h1>
</div>
<div class="px-7 pb-12 opacity-30 pointer-events-none select-none">
    <div class="card overflow-hidden" style="min-height: 500px;">
        <table class="t">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th>Deal</th><th>Company</th><th>Amount</th><th>Stage</th><th>Close date</th><th>Owner</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bgDeals as $bgDeal)
                @php
                    $bgCompany = $bgDeal->companies->first();
                    $bgOwner   = $bgDeal->owner;
                    $bgOwnI    = $bgOwner ? \App\Helpers\Avatar::initials($bgOwner->name ?? $bgOwner->email) : '?';
                    $bgOwnC    = $bgOwner ? \App\Helpers\Avatar::color($bgOwner->name ?? $bgOwner->email) : 'c1';
                @endphp
                <tr class="{{ $bgDeal->id === $deal->id ? 'bg-surface2' : '' }}">
                    <td><span class="ckb"></span></td>
                    <td><div class="font-medium">{{ $bgDeal->name }}</div></td>
                    <td><span class="text-secondary">{{ $bgCompany?->name ?? '—' }}</span></td>
                    <td><span class="num-mono font-semibold">{{ number_format($bgDeal->amount, 0, ',', "\xc2\xa0") }} €</span></td>
                    <td>@if($bgDeal->stage)<span class="chip">{{ $bgDeal->stage->name }}</span>@else<span class="text-tertiary">—</span>@endif</td>
                    <td>@if($bgDeal->close_date)<span class="num-mono">{{ $bgDeal->close_date->format('d/m/Y') }}</span>@else<span class="text-tertiary">—</span>@endif</td>
                    <td><span class="av {{ $bgOwnC }} sm">{{ $bgOwnI }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Drawer deal --}}
<x-drawer close-url="/deals" width="720px">

    {{-- Header : avatar + nom + stage chip + ID --}}
    <x-slot:header>
        <div class="av lg {{ $dealColor }}" style="border-radius: 6px; flex-shrink: 0;">{{ $dealInitials }}</div>
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-base font-semibold truncate" style="margin:0;">{{ $deal->name }}</h2>
                @if($deal->stage)
                <span class="chip flex-shrink-0">
                    <span class="chip-dot" style="background: var(--info);"></span>{{ $deal->stage->name }}
                </span>
                @endif
            </div>
            <div class="text-[11.5px] text-tertiary font-mono mt-0.5">
                DEAL-{{ str_pad($deal->id, 4, '0', STR_PAD_LEFT) }} ·
                créé {{ $deal->created_at->diffForHumans() }} ·
                {{ $activities->count() }} activité{{ $activities->count() !== 1 ? 's' : '' }}
                @if($deal->close_date) · close {{ $deal->close_date->format('d/m') }}@endif
            </div>
        </div>
        <div class="flex items-center gap-2 ml-auto flex-shrink-0">
            <a href="{{ '/deals/' . $deal->id . '/edit' }}" class="btn ghost" style="font-size:12px; padding:4px 10px;">Modifier</a>
            @if(in_array(auth()->user()?->role, ['admin','manager']))
            <form method="POST" action="{{ '/deals/' . $deal->id }}"
                  onsubmit="return confirm('Supprimer ce deal ?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn ghost" style="font-size:12px; padding:4px 10px; color:var(--err)">Supprimer</button>
            </form>
            @endif
        </div>
    </x-slot:header>

    {{-- Body : stage progress + grille 2 colonnes --}}
    <x-slot:body>

        {{-- Stage progress --}}
        <div class="px-6 py-4 border-b border-default flex-shrink-0">
            <div class="flex items-center gap-0">
                @foreach($stages as $stage)
                @php
                    $isActive = $deal->pipeline_stage_id === $stage->id;
                    $isPast   = $stage->position < $currentPos;
                @endphp
                <form method="POST" action="{{ '/deals/' . $deal->id }}" class="flex-1 flex flex-col items-center">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $deal->name }}">
                    <input type="hidden" name="amount" value="{{ $deal->amount }}">
                    <input type="hidden" name="close_date" value="{{ $deal->close_date?->format('Y-m-d') }}">
                    <input type="hidden" name="pipeline_stage_id" value="{{ $stage->id }}">
                    @if($deal->custom_values)
                        @foreach($deal->custom_values as $k => $v)
                            @if(is_array($v))
                                @foreach($v as $subV)
                                    <input type="hidden" name="custom_values[{{ $k }}][]" value="{{ $subV }}">
                                @endforeach
                            @else
                                <input type="hidden" name="custom_values[{{ $k }}]" value="{{ is_bool($v) ? ($v ? '1' : '0') : $v }}">
                            @endif
                        @endforeach
                    @endif

                    <button type="submit" class="w-full flex flex-col items-center group cursor-pointer border-0 bg-transparent p-0 text-center hover:opacity-90 transition-opacity" style="font-family: inherit; font-size: inherit; color: inherit;" title="Passer à l'étape {{ $stage->name }}">
                        <div class="w-full h-1.5 transition-all group-hover:scale-y-125
                            @if($isActive) rounded-none outline outline-2 outline-offset-1
                            @elseif($loop->first) rounded-l-full
                            @elseif($loop->last) rounded-r-full
                            @endif"
                            style="background: {{ $isActive ? 'var(--accent)' : ($isPast ? 'var(--text)' : 'var(--surface2)') }};
                                   @if($isActive) outline-color: var(--accent-soft); @endif">
                        </div>
                        <span class="mono-label mt-1.5 group-hover:text-accent transition-colors {{ $isActive ? 'font-semibold' : '' }}"
                              style="{{ $isActive ? 'color: var(--accent);' : ($isPast ? 'color: var(--text);' : '') }} text-align: center;">
                            {{ $stage->name }}{{ $isActive ? ' ●' : '' }}
                        </span>
                    </button>
                </form>
                @endforeach
            </div>
            <div class="flex items-center justify-between mt-4">
                <div class="flex gap-2">
                    <form method="POST" action="{{ url('/deals/' . $deal->id . '/won') }}" class="inline">
                        @csrf
                        <button type="submit" class="btn primary sm">Marquer gagné ✓</button>
                    </form>
                    <form method="POST" action="{{ url('/deals/' . $deal->id . '/lost') }}" class="inline">
                        @csrf
                        <button type="submit" class="btn sm">Marquer perdu</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Grille 2 colonnes : activité + propriétés --}}
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] flex-1 overflow-y-auto lg:overflow-hidden">

            {{-- Gauche : activité --}}
            <div class="overflow-y-visible lg:overflow-auto flex flex-col px-6 py-4">
                <x-activity-timeline
                    :activities="$activities"
                    subject-type="deal"
                    :subject-id="$deal->id"
                    :show-composer="true"
                />
            </div>

            {{-- Droite : propriétés --}}
            <aside class="border-t lg:border-t-0 lg:border-l border-default overflow-y-visible lg:overflow-auto" style="background: var(--surface2);" x-data>
                <div class="p-5">
                    <div class="mono-label mb-3 flex items-center justify-between">
                        <span>Propriétés</span>
                        <button type="button" @click="$dispatch('open-edit-properties-modal')" class="text-accent hover:underline text-[11px] font-mono font-semibold" title="Modifier les propriétés">
                            ✏️ Modifier
                        </button>
                    </div>
                    <div class="flex flex-col gap-2.5 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-tertiary">Montant</span>
                            <span class="num font-semibold">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span>
                        </div>
                        @if($deal->stage)
                        <div class="flex justify-between">
                            <span class="text-tertiary">Étape</span>
                            <span class="chip"><span class="chip-dot" style="background: var(--info);"></span>{{ $deal->stage->name }}</span>
                        </div>
                        @endif
                        @if($deal->close_date)
                        <div class="flex justify-between">
                            <span class="text-tertiary">Close date</span>
                            <span class="num {{ $isOverdue ? 'text-err' : '' }}">{{ $deal->close_date->format('d/m/Y') }}</span>
                        </div>
                        @endif
                        @if($owner)
                        <div class="flex justify-between items-center">
                            <span class="text-tertiary">Owner</span>
                            <span class="flex items-center gap-1.5">
                                <span class="av sm {{ $ownerColor }}">{{ $ownerInitials }}</span>
                                {{ $owner->name ?? $owner->email }}
                            </span>
                        </div>
                        @endif
                        <div class="flex justify-between items-center" x-data="{ open: false }">
                            <span class="text-tertiary">Statut</span>
                            <div class="relative">
                                <button type="button" @click="open = !open" class="chip cursor-pointer hover:opacity-80 transition-opacity {{ match($deal->status) { 'won' => 'ok', 'lost' => 'err', default => '' } }}" style="padding-right: 8px;">
                                    {{ $deal->status }} <span class="text-[9px] opacity-70">▼</span>
                                </button>
                                <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-1 z-30 bg-surface border border-default rounded shadow-lg p-1 min-w-[110px] flex flex-col gap-0.5" style="background: var(--surface); border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                    @foreach(['open' => 'open', 'won' => 'won', 'lost' => 'lost'] as $val => $lbl)
                                    <form method="POST" action="{{ '/deals/' . $deal->id }}" class="m-0 p-0 flex">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $deal->name }}">
                                        <input type="hidden" name="amount" value="{{ $deal->amount }}">
                                        <input type="hidden" name="close_date" value="{{ $deal->close_date?->format('Y-m-d') }}">
                                        <input type="hidden" name="pipeline_stage_id" value="{{ $deal->pipeline_stage_id }}">
                                        <input type="hidden" name="status" value="{{ $val }}">
                                        @if($deal->custom_values)
                                            @foreach($deal->custom_values as $k => $v)
                                                @if(is_array($v))
                                                    @foreach($v as $subV)
                                                        <input type="hidden" name="custom_values[{{ $k }}][]" value="{{ $subV }}">
                                                    @endforeach
                                                @else
                                                    <input type="hidden" name="custom_values[{{ $k }}]" value="{{ is_bool($v) ? ($v ? '1' : '0') : $v }}">
                                                @endif
                                            @endforeach
                                        @endif
                                        <button type="submit" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-surface-alt rounded border-0 bg-transparent cursor-pointer font-sans flex items-center justify-between" style="color: var(--text);">
                                            <span>{{ $lbl }}</span>
                                            @if($deal->status === $val)
                                            <span class="text-accent text-[10px]">✓</span>
                                            @endif
                                        </button>
                                    </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Contacts --}}
                    <div class="flex items-center justify-between mt-6 mb-3">
                        <div class="mono-label" style="margin:0;">Contacts associés</div>
                        <button type="button" class="btn link text-accent font-medium text-[11px]" @click="$dispatch('open-attach-contact-modal')">
                            + Associer
                        </button>
                    </div>
                    @if($deal->contacts->count())
                    <div class="flex flex-col gap-3">
                        @foreach($deal->contacts as $c)
                        @php
                            $cFullName = trim($c->first_name . ' ' . $c->last_name);
                            $cColor    = \App\Helpers\Avatar::color($cFullName ?: $c->email);
                            $cInitials = \App\Helpers\Avatar::initials($cFullName ?: $c->email);
                        @endphp
                        <div class="flex items-center gap-2 group">
                            <a href="{{ '/contacts/' . $c->id }}" class="flex items-center gap-2 flex-1 min-w-0 hover:opacity-80">
                                <span class="av {{ $cColor }} sm">{{ $cInitials }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-[13px] truncate">{{ $cFullName ?: $c->email }}</div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="chip" style="font-size: 9px; padding: 1px 4px;">{{ $c->pivot->role }}</span>
                                        @if($c->job_title)
                                        <span class="text-tertiary text-[11px] truncate font-mono">{{ $c->job_title }}</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                            <form method="POST" action="{{ url('/deals/' . $deal->id . '/contacts/' . $c->id) }}" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn ghost icon sm text-err" title="Détacher le contact">
                                    <svg class="ic sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-[12px] text-tertiary italic">Aucun contact associé.</div>
                    @endif

                    {{-- Entreprises --}}
                    <div class="flex items-center justify-between mt-6 mb-3">
                        <div class="mono-label" style="margin:0;">Entreprises associées</div>
                        <button type="button" class="btn link text-accent font-medium text-[11px]" @click="$dispatch('open-attach-company-modal')">
                            + Associer
                        </button>
                    </div>
                    @if($deal->companies->count())
                    <div class="flex flex-col gap-3">
                        @foreach($deal->companies as $co)
                        @php
                            $coColor    = \App\Helpers\Avatar::color($co->name);
                            $coInitials = strtoupper(mb_substr($co->name, 0, 2));
                        @endphp
                        <div class="flex items-center gap-2 group">
                            <a href="{{ '/companies/' . $co->id }}" class="flex items-center gap-2 flex-1 min-w-0 hover:opacity-80">
                                <span class="av lg {{ $coColor }} sq" style="border-radius: 6px; width:28px; height:28px; font-size:11px;">{{ $coInitials }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-[13px] truncate">{{ $co->name }}</div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="chip" style="font-size: 9px; padding: 1px 4px;">{{ $co->pivot->role }}</span>
                                        @if($co->pivot->is_primary)
                                        <span class="chip ok" style="font-size: 9px; padding: 1px 4px;">Principale</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                            <form method="POST" action="{{ url('/deals/' . $deal->id . '/companies/' . $co->id) }}" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn ghost icon sm text-err" title="Détacher la société">
                                    <svg class="ic sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-[12px] text-tertiary italic">Aucune entreprise associée.</div>
                    @endif

                    <x-custom-fields-show :entity="$deal" entity-type="deal" layout="inline" />

                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <div class="mt-4 pt-3 border-t border-default flex justify-center">
                        <button type="button" @click="$dispatch('open-create-property-modal')" class="text-accent hover:underline text-[11px] font-mono font-semibold">
                            + Créer une propriété
                        </button>
                    </div>
                    @endif

                    <div class="mono-label mt-6 mb-3">Insights IA</div>
                    <div class="flex flex-col gap-3">
                        <x-ai-insight-card endpoint="/web/ai/deal/{{ $deal->id }}/summarize" title="Résumé" />
                        <x-ai-insight-card endpoint="/web/ai/deal/{{ $deal->id }}/next-action" title="Prochaine action" />
                        <x-ai-insight-card endpoint="/web/ai/deal/{{ $deal->id }}/score" title="Score deal" />
                        <button type="button"
                                @click="$dispatch('open-email-draft-modal')"
                                class="btn ghost w-full justify-center"
                                style="font-size:12px;">
                            <svg class="ic" style="width:12px;height:12px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            Rédiger un email
                        </button>
                    </div>
                </div>
            </aside>
        </div>

    </x-slot:body>

</x-drawer>

{{-- Modale d'association de Contact --}}
<div x-data="{
    open: false,
    search: '',
    selectedId: '',
    role: 'primary',
    contacts: @js($allContacts->map(fn($c) => [
        'id' => $c->id,
        'name' => trim($c->first_name . ' ' . $c->last_name) ?: $c->email,
        'email' => $c->email,
    ])),
    get filteredContacts() {
        if (!this.search) return this.contacts;
        const q = this.search.toLowerCase();
        return this.contacts.filter(c => c.name.toLowerCase().includes(q) || (c.email && c.email.toLowerCase().includes(q)));
    }
}"
     x-show="open"
     x-cloak
     @open-attach-contact-modal.window="open = true; search = ''; selectedId = ''; role = 'primary';"
     class="fixed inset-0 z-50 flex items-center justify-center animate-fade-in"
     style="background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-sm" @click.stop style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Associer un contact</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="{{ url('/deals/' . $deal->id . '/contacts') }}" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            
            <div class="field">
                <label>Rechercher un contact</label>
                <input type="text" x-model="search" placeholder="Nom ou email...">
            </div>

            <div class="field">
                <label>Sélectionner le contact <span class="text-err">*</span></label>
                <select name="contact_id" x-model="selectedId" class="select-arrow" required size="5" style="height:120px;">
                    <option value="" disabled>-- Choisir un contact --</option>
                    <template x-for="c in filteredContacts" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>

            <div class="field">
                <label>Rôle <span class="text-err">*</span></label>
                <select name="role" x-model="role" class="select-arrow" required>
                    <option value="primary">Principal</option>
                    <option value="technical">Technique</option>
                    <option value="billing">Facturation</option>
                    <option value="other">Autre</option>
                </select>
            </div>

            <div class="flex justify-end gap-2 mt-2">
                <button type="button" @click="open = false" class="btn ghost">Annuler</button>
                <button type="submit" class="btn primary" :disabled="!selectedId">Associer</button>
            </div>
        </form>
    </div>
</div>

{{-- Modale d'association d'Entreprise --}}
<div x-data="{
    open: false,
    search: '',
    selectedId: '',
    role: 'customer',
    isPrimary: false,
    companies: @js($allCompanies->map(fn($co) => [
        'id' => $co->id,
        'name' => $co->name,
    ])),
    get filteredCompanies() {
        if (!this.search) return this.companies;
        const q = this.search.toLowerCase();
        return this.companies.filter(c => c.name.toLowerCase().includes(q));
    }
}"
     x-show="open"
     x-cloak
     @open-attach-company-modal.window="open = true; search = ''; selectedId = ''; role = 'customer'; isPrimary = false;"
     class="fixed inset-0 z-50 flex items-center justify-center animate-fade-in"
     style="background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-sm" @click.stop style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Associer une entreprise</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="{{ url('/deals/' . $deal->id . '/companies') }}" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            
            <div class="field">
                <label>Rechercher une entreprise</label>
                <input type="text" x-model="search" placeholder="Nom...">
            </div>

            <div class="field">
                <label>Sélectionner l'entreprise <span class="text-err">*</span></label>
                <select name="company_id" x-model="selectedId" class="select-arrow" required size="5" style="height:120px;">
                    <option value="" disabled>-- Choisir une entreprise --</option>
                    <template x-for="c in filteredCompanies" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>

            <div class="field">
                <label>Rôle <span class="text-err">*</span></label>
                <select name="role" x-model="role" class="select-arrow" required>
                    <option value="customer">Client</option>
                    <option value="partner">Partenaire</option>
                    <option value="reseller">Revendeur</option>
                </select>
            </div>

            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="is_primary" id="is_primary_co" value="1" x-model="isPrimary" class="accent-accent">
                <label for="is_primary_co" class="text-sm select-none cursor-pointer">Définir comme entreprise principale</label>
            </div>

            <div class="flex justify-end gap-2 mt-2">
                <button type="button" @click="open = false" class="btn ghost">Annuler</button>
                <button type="submit" class="btn primary" :disabled="!selectedId">Associer</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal de modification des propriétés (Deal) --}}
<div x-data="{ open: false }"
     @open-edit-properties-modal.window="open = true;"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center animate-fade-in"
     style="background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-lg" @click.stop style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Modifier les propriétés de {{ $deal->name }}</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="{{ '/deals/' . $deal->id }}" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div class="field">
                    <label>Nom du deal <span class="text-err">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $deal->name) }}" required>
                </div>
                <div class="field">
                    <label>Montant (€) <span class="text-err">*</span></label>
                    <input type="number" name="amount" min="0" step="0.01" value="{{ old('amount', $deal->amount) }}" required>
                </div>
                <div class="field" x-data="{
                    setDate(days) {
                        const d = new Date();
                        d.setDate(d.getDate() + days);
                        this.$refs.closeInput._flatpickr.setDate(d, true);
                    },
                    setEndOfMonth() {
                        const d = new Date();
                        const nextMonth = new Date(d.getFullYear(), d.getMonth() + 1, 0);
                        this.$refs.closeInput._flatpickr.setDate(nextMonth, true);
                    },
                    setEndOfQuarter() {
                        const d = new Date();
                        const currentQuarter = Math.floor(d.getMonth() / 3);
                        const endOfQuarter = new Date(d.getFullYear(), (currentQuarter + 1) * 3, 0);
                        this.$refs.closeInput._flatpickr.setDate(endOfQuarter, true);
                    }
                }">
                    <label>Date de clôture</label>
                    <div class="relative flex items-center">
                        <input type="text" name="close_date" x-ref="closeInput" x-datepicker value="{{ old('close_date', $deal->close_date?->format('Y-m-d')) }}" placeholder="Sélectionnez une date..." class="w-full pr-10">
                        <span class="absolute right-3 pointer-events-none text-tertiary">
                            <svg class="ic sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        <button type="button" @click="setEndOfMonth()" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">Fin de mois</button>
                        <button type="button" @click="setEndOfQuarter()" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">Fin trim.</button>
                        <button type="button" @click="setDate(30)" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">+30j</button>
                        <button type="button" @click="setDate(90)" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">+90j</button>
                    </div>
                </div>
                <div class="field">
                    <label>Étape <span class="text-err">*</span></label>
                    <select name="pipeline_stage_id" class="select-arrow" required>
                        @foreach($stages as $stage)
                            <option value="{{ $stage->id }}" {{ old('pipeline_stage_id', $deal->pipeline_stage_id) === $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-custom-fields-form entity-type="deal" :values="old('custom_values', $deal->custom_values ?? [])" />
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-default">
                <button type="button" @click="open = false" class="btn ghost">Annuler</button>
                <button type="submit" class="btn primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal d'ajout de propriété (création de champ personnalisé) --}}
@if(in_array(auth()->user()?->role, ['admin','manager']))
<div x-data="{
    open: false,
    fieldType: 'text',
    label: '',
    key: '',
    options: [''],
    updateKey() {
        this.key = this.label.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // strip accents
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '_')
            .replace(/-+/g, '_');
    },
    addOption() {
        this.options.push('');
    },
    removeOption(index) {
        this.options.splice(index, 1);
    }
}"
     @open-create-property-modal.window="open = true; fieldType = 'text'; label = ''; key = ''; options = [''];"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center animate-fade-in"
     style="background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-md" @click.stop style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Créer une propriété (Deal)</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="/settings/fields" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            <input type="hidden" name="entity_type" value="deal">

            <div class="field">
                <label>Nom de la propriété (Label) <span class="text-err">*</span></label>
                <input type="text" name="label" x-model="label" @input="updateKey()" placeholder="ex: Source de lead" required>
            </div>

            <div class="field">
                <label>Clé technique (Key) <span class="text-err">*</span></label>
                <input type="text" name="key" x-model="key" placeholder="ex: source_lead" required>
            </div>

            <div class="field">
                <label>Type de champ <span class="text-err">*</span></label>
                <select name="field_type" x-model="fieldType" class="select-arrow" required>
                    <option value="text">Texte court</option>
                    <option value="number">Nombre</option>
                    <option value="date">Date</option>
                    <option value="boolean">Case à cocher (booléen)</option>
                    <option value="select">Liste de sélection</option>
                </select>
            </div>

            <template x-if="fieldType === 'select'">
                <div class="field mt-2">
                    <label class="flex justify-between items-center">
                        <span>Options</span>
                        <button type="button" @click="addOption()" class="text-accent hover:underline text-xs">+ Ajouter option</button>
                    </label>
                    <div class="flex flex-col gap-2 mt-1">
                        <template x-for="(opt, idx) in options" :key="idx">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="'options[' + idx + ']'" x-model="options[idx]" placeholder="Option label" class="flex-1" required>
                                <button type="button" @click="removeOption(idx)" class="text-err text-xs" :disabled="options.length <= 1">X</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-default">
                <button type="button" @click="open = false" class="btn ghost">Annuler</button>
                <button type="submit" class="btn primary">Créer</button>
            </div>
        </form>
    </div>
</div>
@endif

<x-email-draft-modal entity-type="deal" :entity-id="$deal->id" />

</x-app-shell>
