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
                <div class="flex-1 flex flex-col items-center">
                    <div class="w-full h-1.5
                        @if($isActive) rounded-none outline outline-2 outline-offset-1
                        @elseif($loop->first) rounded-l-full
                        @elseif($loop->last) rounded-r-full
                        @endif"
                        style="background: {{ $isActive ? 'var(--accent)' : ($isPast ? 'var(--text)' : 'var(--surface2)') }};
                               @if($isActive) outline-color: var(--accent-soft); @endif">
                    </div>
                    <span class="mono-label mt-1.5 {{ $isActive ? 'font-semibold' : '' }}"
                          style="{{ $isActive ? 'color: var(--accent);' : ($isPast ? 'color: var(--text);' : '') }}">
                        {{ $stage->name }}{{ $isActive ? ' ●' : '' }}
                    </span>
                </div>
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
            <div class="overflow-y-visible lg:overflow-auto flex flex-col">
                {{-- Onglets --}}
                <div class="flex border-b border-default px-6 gap-0 flex-shrink-0">
                    <button class="px-3 py-3 text-[13px] font-medium border-b-2" style="border-color: var(--accent); color: var(--text);">
                        Activité <span class="chip ml-1" style="padding: 0 6px; font-size: 10px;">{{ $activities->count() }}</span>
                    </button>
                    <button class="px-3 py-3 text-[13px] text-secondary">Notes</button>
                    <button class="px-3 py-3 text-[13px] text-secondary">Emails</button>
                    <button class="px-3 py-3 text-[13px] text-secondary">Tâches</button>
                </div>

                {{-- Composer --}}
                <div class="px-6 py-4 border-b border-default flex-shrink-0">
                    <div class="card p-3">
                        <div class="text-[13px] text-tertiary">Ajouter une note, un email, un appel…</div>
                        <div class="flex gap-2 mt-2">
                            <button class="btn sm">📝 Note</button>
                            <button class="btn sm">📧 Email</button>
                            <button class="btn sm">📞 Appel</button>
                            <button class="btn sm">✓ Tâche</button>
                        </div>
                    </div>
                </div>

                {{-- Timeline --}}
                <div class="px-6 py-3 overflow-auto flex-1">
                    @forelse($activities as $activity)
                    @php
                        $dot = match($activity->type) {
                            'email' => 'info',
                            'call'  => 'accent',
                            default => '',
                        };
                        $emoji = match($activity->type) {
                            'email'  => '📧',
                            'call'   => '📞',
                            'note'   => '📝',
                            'task'   => '✓',
                            default  => '➕',
                        };
                    @endphp
                    <div class="tl-item">
                        <span class="tl-time">{{ $activity->created_at->format('d/m H:i') }}</span>
                        <div class="tl-axis"><div class="tl-dot {{ $dot }}"></div></div>
                        <div class="tl-content">
                            <div class="ti">{{ $emoji }} {{ $activity->title }}</div>
                            @if($activity->body)
                            <div class="ts">{{ Str::limit($activity->body, 80) }}</div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="py-8 text-center text-tertiary text-sm">Aucune activité pour ce deal.</div>
                    @endforelse
                </div>
            </div>

            {{-- Droite : propriétés --}}
            <aside class="border-t lg:border-t-0 lg:border-l border-default overflow-y-visible lg:overflow-auto" style="background: var(--surface2);">
                <div class="p-5">
                    <div class="mono-label mb-3">Propriétés</div>
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
                        <div class="flex justify-between">
                            <span class="text-tertiary">Statut</span>
                            <span class="chip {{ match($deal->status) { 'won' => 'ok', 'lost' => 'err', default => '' } }}">
                                {{ $deal->status }}
                            </span>
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

                    <div class="mono-label mt-6 mb-3">Insights IA</div>
                    <div class="flex flex-col gap-3">
                        <x-ai-insight-card endpoint="/web/ai/deal/{{ $deal->id }}/summarize" title="Résumé" />
                        <x-ai-insight-card endpoint="/web/ai/deal/{{ $deal->id }}/next-action" title="Prochaine action" />
                        <x-ai-insight-card endpoint="/web/ai/deal/{{ $deal->id }}/score" title="Score deal" />
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

</x-app-shell>
