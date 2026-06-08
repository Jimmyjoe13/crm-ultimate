<x-app-shell active="companies" breadcrumb="Entreprises / {{ $company->name }}">

@php
    $color    = \App\Helpers\Avatar::color($company->name);
    $initials = strtoupper(mb_substr($company->name, 0, 2));
@endphp

<div class="px-7 pt-6 pb-3 flex items-center gap-4">
    <a href="{{ '/companies' }}" class="btn ghost icon">
        <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <div class="av lg {{ $color }} sq">{{ $initials }}</div>
    <div class="flex-1">
        <h1 class="text-2xl">{{ $company->name }}</h1>
        <p class="text-sm text-secondary">{{ $company->industry ?? '—' }}</p>
    </div>
    <div class="flex items-center gap-2 ml-auto">
        <a href="{{ '/companies/' . $company->id . '/edit' }}" class="btn ghost">Modifier</a>
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <form method="POST" action="{{ '/companies/' . $company->id }}"
              onsubmit="return confirm('Supprimer cette entreprise ? Cette action est irréversible.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn ghost" style="color:var(--err)">Supprimer</button>
        </form>
        @endif
    </div>
</div>

<div class="px-7 pb-12 grid grid-cols-12 gap-6">
    {{-- Colonne Gauche : Propriétés & Informations --}}
    <div class="col-span-12 lg:col-span-3 flex flex-col gap-4">
        <div class="card p-5" x-data>
            <div class="mono-label mb-4 pb-2 border-b border-default flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="ic" style="width:12px;height:12px;" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9v2M9 13v2M9 17v2M15 9v2M15 13v2M15 17v2"/></svg>
                    À propos
                </div>
                <button type="button" @click="$dispatch('open-edit-properties-modal')" class="text-accent hover:underline text-[11px] font-mono font-semibold" title="Modifier les propriétés">
                    ✏️ Modifier
                </button>
            </div>

            <div class="flex flex-col gap-4">
                @if($company->website)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        Site web
                    </div>
                    <div x-data="{ copied: false }" class="flex items-center gap-1.5 group/copy mt-0.5">
                        <a href="{{ $company->website }}" target="_blank" class="text-[13px] text-accent hover:underline truncate font-medium">{{ $company->website }}</a>
                        <button type="button" @click="window.copyToClipboard('{{ $company->website }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="opacity-100 lg:opacity-0 lg:group-hover/copy:opacity-100 transition-opacity p-0.5 text-tertiary hover:text-primary flex-shrink-0"
                                title="Copier le site web">
                            <svg class="ic" style="width:11px; height:11px;" viewBox="0 0 24 24">
                                <path x-show="!copied" d="M8 17.75a3 3 0 0 1-3-3V5.5a3 3 0 0 1 3-3h5.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H8z" fill="none" stroke="currentColor"/>
                                <path x-show="!copied" d="M16 8h2.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H13a3 3 0 0 1-3-3V17.75" fill="none" stroke="currentColor"/>
                                <path x-show="copied" d="M20 6L9 17l-5-5" fill="none" stroke="var(--ok)" stroke-width="2.5"/>
                            </svg>
                        </button>
                    </div>
                </div>
                @endif

                @if($company->domain)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                        Domaine
                    </div>
                    <div class="text-[13px] text-primary font-mono mt-0.5">{{ $company->domain }}</div>
                </div>
                @endif

                @if($company->phone)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Téléphone
                    </div>
                    <div x-data="{ copied: false }" class="flex items-center gap-1.5 group/copy mt-0.5">
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company->phone) }}" class="text-[13px] font-mono text-primary hover:text-accent hover:underline truncate">{{ $company->phone }}</a>
                        <button type="button" @click="window.copyToClipboard('{{ $company->phone }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="opacity-100 lg:opacity-0 lg:group-hover/copy:opacity-100 transition-opacity p-0.5 text-tertiary hover:text-primary flex-shrink-0"
                                title="Copier le téléphone">
                            <svg class="ic" style="width:11px; height:11px;" viewBox="0 0 24 24">
                                <path x-show="!copied" d="M8 17.75a3 3 0 0 1-3-3V5.5a3 3 0 0 1 3-3h5.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H8z" fill="none" stroke="currentColor"/>
                                <path x-show="!copied" d="M16 8h2.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H13a3 3 0 0 1-3-3V17.75" fill="none" stroke="currentColor"/>
                                <path x-show="copied" d="M20 6L9 17l-5-5" fill="none" stroke="var(--ok)" stroke-width="2.5"/>
                            </svg>
                        </button>
                    </div>
                </div>
                @endif

                @if($company->industry)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Secteur
                    </div>
                    <div class="text-[13px] text-primary font-medium mt-0.5">{{ $company->industry }}</div>
                </div>
                @endif

                @if($company->city || $company->country)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Localisation
                    </div>
                    <div class="text-[13px] text-primary font-medium mt-0.5">
                        {{ implode(', ', array_filter([$company->city, $company->country])) }}
                    </div>
                </div>
                @endif

                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Créé le
                    </div>
                    <div class="text-[13px] text-primary mt-0.5 font-mono">{{ $company->created_at->format('d/m/Y') }}</div>
                </div>
            </div>

            <x-custom-fields-show :entity="$company" entity-type="company" layout="stacked" />

            @if(in_array(auth()->user()?->role, ['admin','manager']))
            <div class="mt-4 pt-3 border-t border-default flex justify-center">
                <button type="button" @click="$dispatch('open-create-property-modal')" class="text-accent hover:underline text-[11px] font-mono font-semibold">
                    + Créer une propriété
                </button>
            </div>
            @endif
        </div>
    </div>

    {{-- Colonne Centrale : Activité Composer & Timeline --}}
    <div class="col-span-12 lg:col-span-6 flex flex-col gap-4">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-lg font-semibold" style="margin:0;">Fil d'activité</h2>
            <span class="chip font-mono text-[11px]">{{ $activities->count() }}</span>
        </div>

        <x-activity-timeline
            :activities="$activities"
            subject-type="company"
            :subject-id="$company->id"
            :show-composer="true"
        />
    </div>

    {{-- Colonne Droite : Contacts, Deals, IA --}}
    <div class="col-span-12 lg:col-span-3 flex flex-col gap-4">
        {{-- Contacts associés --}}
        <div class="card overflow-hidden">
            <div class="card-h">
                <span class="title">Contacts associés</span>
                <span class="meta">{{ $company->contacts->count() }}</span>
            </div>
            @if($company->contacts->count())
            <div class="p-3 flex flex-col gap-3">
                @foreach($company->contacts as $contact)
                @php
                    $fullName = trim($contact->first_name . ' ' . $contact->last_name) ?: $contact->email;
                    $cc = \App\Helpers\Avatar::color($fullName);
                    $ci = \App\Helpers\Avatar::initials($fullName);
                @endphp
                <div class="flex items-center gap-2">
                    <a href="{{ '/contacts/' . $contact->id }}" class="flex items-center gap-2 flex-1 min-w-0 hover:opacity-80">
                        <span class="av {{ $cc }} sm">{{ $ci }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-[13px] truncate">{{ $fullName }}</div>
                            @if($contact->job_title)
                            <div class="text-tertiary text-[11px] truncate mt-0.5 font-mono">{{ $contact->job_title }}</div>
                            @endif
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-4 text-center text-xs text-tertiary">
                Aucun contact associé.
            </div>
            @endif
        </div>

        {{-- Deals associés --}}
        <div class="card overflow-hidden">
            <div class="card-h">
                <span class="title">Deals associés</span>
                <span class="meta">{{ $company->deals->count() }}</span>
            </div>
            @if($company->deals->count())
            <div class="p-3 flex flex-col gap-2.5">
                @foreach($company->deals as $deal)
                @php
                    $dealColor = \App\Helpers\Avatar::color($deal->name);
                    $dealInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $deal->name), 0, 1) ?: 'D');
                @endphp
                <div class="flex items-center gap-2">
                    <div class="av {{ $dealColor }} sm" style="border-radius:4px;">{{ $dealInitials }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-[13px] truncate">{{ $deal->name }}</div>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="chip {{ match($deal->status) { 'won' => 'ok', 'lost' => 'err', default => '' } }}" style="font-size: 9px; padding: 1px 4px;">
                                {{ $deal->status }}
                            </span>
                            <span class="chip" style="font-size: 9px; padding: 1px 4px;">{{ $deal->stage?->name ?? '—' }}</span>
                            <span class="text-secondary text-[11px] font-mono">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-4 text-center text-xs text-tertiary">
                Aucun deal associé.
            </div>
            @endif
        </div>

        {{-- Résumé IA --}}
        <x-ai-insight-card endpoint="/web/ai/company/{{ $company->id }}/summarize" title="Résumé entreprise" />
    </div>
</div>

{{-- Modal de modification des propriétés (Entreprise) --}}
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
            <h2 class="text-base font-semibold">Modifier les propriétés de {{ $company->name }}</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="{{ '/companies/' . $company->id }}" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div class="field">
                    <label>Nom <span class="text-err">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $company->name) }}" required>
                </div>
                <div class="field">
                    <label>Site web</label>
                    <input type="url" name="website" value="{{ old('website', $company->website) }}">
                </div>
                <div class="field">
                    <label>Domaine</label>
                    <input type="text" name="domain" value="{{ old('domain', $company->domain) }}">
                </div>
                <div class="field">
                    <label>Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $company->phone) }}">
                </div>
                <div class="field">
                    <label>Secteur d'activité</label>
                    <input type="text" name="industry" value="{{ old('industry', $company->industry) }}">
                </div>
                <div class="field">
                    <label>Ville</label>
                    <input type="text" name="city" value="{{ old('city', $company->city) }}">
                </div>
                <div class="field">
                    <label>Pays</label>
                    <input type="text" name="country" value="{{ old('country', $company->country) }}">
                </div>
                <x-custom-fields-form entity-type="company" :values="old('custom_values', $company->custom_values ?? [])" />
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
            <h2 class="text-base font-semibold">Créer une propriété (Entreprise)</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="/settings/fields" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            <input type="hidden" name="entity_type" value="company">

            <div class="field">
                <label>Nom de la propriété (Label) <span class="text-err">*</span></label>
                <input type="text" name="label" x-model="label" @input="updateKey()" placeholder="ex: CA Annuel" required>
            </div>

            <div class="field">
                <label>Clé technique (Key) <span class="text-err">*</span></label>
                <input type="text" name="key" x-model="key" placeholder="ex: ca_annuel" required>
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

</x-app-shell>
