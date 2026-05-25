<x-app-shell active="contacts" breadcrumb="Contacts / {{ trim($contact->first_name . ' ' . $contact->last_name) }}">

@php
    $fullName = trim($contact->first_name . ' ' . $contact->last_name);
    $color    = \App\Helpers\Avatar::color($fullName ?: $contact->email);
    $initials = \App\Helpers\Avatar::initials($fullName ?: $contact->email);
    $company  = $contact->companies->first();

    $defaultDealName = '[Titre à remplir] - ' . ($fullName ?: $contact->email);
    if ($company) {
        $defaultDealName .= ' de ' . $company->name;
    }
@endphp

<div class="px-7 pt-6 pb-3 flex items-center gap-4" x-data>
    <a href="{{ '/contacts' }}" class="btn ghost icon">
        <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <div class="av lg {{ $color }}">{{ $initials }}</div>
    <div class="flex-1">
        <h1 class="text-2xl">{{ $fullName ?: $contact->email }}</h1>
        <p class="text-sm text-secondary">{{ $contact->job_title ?? '' }} @if($company) · {{ $company->name }} @endif</p>
    </div>
    @if($contact->blacklisted_at)
    <span class="chip err ml-2" @if($contact->blacklist_reason) title="Raison : {{ $contact->blacklist_reason }}" @endif>Blacklisté</span>
    @endif
    @if($contact->lifecycle_stage)
    <span class="chip ml-2">{{ $contact->lifecycle_stage }}</span>
    @endif
    <div class="flex items-center gap-2 ml-auto">
        <button type="button" @click="$dispatch('open-email-draft-modal')" class="btn ghost">
            <svg class="ic" style="width:13px;height:13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
            Rédiger un email
        </button>
        <button type="button" class="btn primary" @click="$dispatch('open-create-deal-modal')">
            <svg class="ic" style="stroke-width: 2;" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Créer un deal
        </button>
        <a href="{{ '/contacts/' . $contact->id . '/edit' }}" class="btn ghost">Modifier</a>
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <form method="POST" action="{{ '/contacts/' . $contact->id }}"
              onsubmit="return confirm('Supprimer ce contact ? Cette action est irréversible.')">
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
                    <svg class="ic" style="width:12px;height:12px;" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    À propos
                </div>
                <button type="button" @click="$dispatch('open-edit-properties-modal')" class="text-accent hover:underline text-[11px] font-mono font-semibold" title="Modifier les propriétés">
                    ✏️ Modifier
                </button>
            </div>
            
            <div class="flex flex-col gap-4">
                @if($contact->email)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Email
                    </div>
                    <div x-data="{ copied: false }" class="flex items-center gap-1.5 group/copy mt-0.5">
                        <a href="mailto:{{ $contact->email }}" class="text-[13px] font-mono text-accent hover:underline truncate">{{ $contact->email }}</a>
                        <button type="button" @click="window.copyToClipboard('{{ $contact->email }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="opacity-100 lg:opacity-0 lg:group-hover/copy:opacity-100 transition-opacity p-0.5 text-tertiary hover:text-primary flex-shrink-0"
                                title="Copier l'email">
                            <svg class="ic" style="width:11px; height:11px;" viewBox="0 0 24 24">
                                <path x-show="!copied" d="M8 17.75a3 3 0 0 1-3-3V5.5a3 3 0 0 1 3-3h5.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H8z" fill="none" stroke="currentColor"/>
                                <path x-show="!copied" d="M16 8h2.25a3 3 0 0 1 3 3v9.25a3 3 0 0 1-3 3H13a3 3 0 0 1-3-3V17.75" fill="none" stroke="currentColor"/>
                                <path x-show="copied" d="M20 6L9 17l-5-5" fill="none" stroke="var(--ok)" stroke-width="2.5"/>
                            </svg>
                        </button>
                    </div>
                </div>
                @endif

                @if($contact->phone)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Téléphone
                    </div>
                    <div x-data="{ copied: false }" class="flex items-center gap-1.5 group/copy mt-0.5">
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="text-[13px] font-mono text-primary hover:text-accent hover:underline truncate">{{ $contact->phone }}</a>
                        <button type="button" @click="window.copyToClipboard('{{ $contact->phone }}'); copied = true; setTimeout(() => copied = false, 2000)"
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

                @if($contact->job_title)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Poste
                    </div>
                    <div class="text-[13px] text-primary font-medium mt-0.5">{{ $contact->job_title }}</div>
                </div>
                @endif

                @if($company)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9v2M9 13v2M9 17v2M15 9v2M15 13v2M15 17v2"/></svg>
                        Entreprise
                    </div>
                    <div class="mt-0.5">
                        <a href="{{ '/companies/' . $company->id }}" class="text-[13px] text-accent hover:underline font-medium">{{ $company->name }}</a>
                    </div>
                </div>
                @endif

                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Étape Lifecycle
                    </div>
                    <div class="mt-0.5" x-data="{ open: false }">
                        <div class="relative inline-block">
                            <button type="button" @click="open = !open" class="chip cursor-pointer hover:opacity-80 transition-opacity" style="padding-right: 8px;">
                                {{ $contact->lifecycle_stage ?: '— Non défini' }} <span class="text-[9px] opacity-70">▼</span>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak class="absolute left-0 mt-1 z-30 bg-surface border border-default rounded shadow-lg p-1 min-w-[150px] flex flex-col gap-0.5" style="background: var(--surface); border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                @foreach(['lead' => 'Lead', 'mql' => 'MQL', 'sql' => 'SQL', 'opportunity' => 'Opportunité', 'customer' => 'Client', 'evangelist' => 'Évangéliste', 'other' => 'Autre'] as $val => $lbl)
                                <form method="POST" action="{{ '/contacts/' . $contact->id }}" class="m-0 p-0 flex">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="lifecycle_stage" value="{{ $val }}">
                                    <button type="submit" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-surface-alt rounded border-0 bg-transparent cursor-pointer font-sans flex items-center justify-between" style="color: var(--text);">
                                        <span>{{ $lbl }}</span>
                                        @if($contact->lifecycle_stage === $val)
                                        <span class="text-accent text-[10px]">✓</span>
                                        @endif
                                    </button>
                                </form>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>
                        Statut Lead
                    </div>
                    <div class="mt-0.5" x-data="{ open: false }">
                        <div class="relative inline-block">
                            <button type="button" @click="open = !open" class="chip cursor-pointer hover:opacity-80 transition-opacity" style="padding-right: 8px;">
                                {{ $contact->lead_status ? (\App\Models\Contact::LEAD_STATUSES[$contact->lead_status] ?? $contact->lead_status) : '— Non défini' }} <span class="text-[9px] opacity-70">▼</span>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak class="absolute left-0 mt-1 z-30 bg-surface border border-default rounded shadow-lg p-1 min-w-[150px] flex flex-col gap-0.5" style="background: var(--surface); border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                @foreach(\App\Models\Contact::LEAD_STATUSES as $val => $lbl)
                                <form method="POST" action="{{ '/contacts/' . $contact->id }}" class="m-0 p-0 flex">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="lead_status" value="{{ $val }}">
                                    <button type="submit" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-surface-alt rounded border-0 bg-transparent cursor-pointer font-sans flex items-center justify-between" style="color: var(--text);">
                                        <span>{{ $lbl }}</span>
                                        @if($contact->lead_status === $val)
                                        <span class="text-accent text-[10px]">✓</span>
                                        @endif
                                    </button>
                                </form>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                @if($contact->owner)
                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Propriétaire
                    </div>
                    <div class="text-[13px] text-primary font-medium mt-0.5">{{ $contact->owner->name }}</div>
                </div>
                @endif

                <div>
                    <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider flex items-center gap-1">
                        <svg class="ic" style="width:10px;height:10px;" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Créé le
                    </div>
                    <div class="text-[13px] text-primary mt-0.5 font-mono">{{ $contact->created_at->format('d/m/Y') }}</div>
                </div>
            </div>

            <x-custom-fields-show :entity="$contact" entity-type="contact" layout="stacked" />

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
    <div class="col-span-12 lg:col-span-6 flex flex-col gap-4" id="activityFeed"
         x-data="{
             activeTab: 'all',
             init() {
                 this.$el.addEventListener('switch-activity-tab', e => {
                     this.activeTab = e.detail;
                 });
             }
         }">
        @php
            $emeliaCnt = $activities->where('source', 'emelia')->count();
        @endphp
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-lg font-semibold" style="margin:0;">Fil d'activité</h2>
            <div class="flex items-center gap-2">
                <div class="flex gap-1">
                    <button @click="activeTab='all'"
                            :class="activeTab==='all' ? 'chip font-mono text-[11px]' : 'btn ghost sm'"
                            style="font-size:11px;padding:2px 8px;">
                        Tout ({{ $activities->count() }})
                    </button>
                    @if($emeliaCnt > 0)
                    <button @click="activeTab='emelia'"
                            :class="activeTab==='emelia' ? 'chip font-mono text-[11px]' : 'btn ghost sm'"
                            style="font-size:11px;padding:2px 8px;">
                        Emelia ({{ $emeliaCnt }})
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Timeline "Tout" --}}
        <div x-show="activeTab==='all'">
            <x-activity-timeline
                :activities="$activities"
                subject-type="contact"
                :subject-id="$contact->id"
                :show-composer="true"
            />
        </div>

        {{-- Timeline filtrée Emelia --}}
        @if($emeliaCnt > 0)
        <div x-show="activeTab==='emelia'">
            <x-activity-timeline
                :activities="$activities"
                subject-type="contact"
                :subject-id="$contact->id"
                :show-composer="false"
                filter-source="emelia"
            />
        </div>
        @endif
    </div>

    {{-- Colonne Droite : Deals, IA, Emelia --}}
    <div class="col-span-12 lg:col-span-3 flex flex-col gap-4">
        {{-- Deals associés --}}
        <div class="card overflow-hidden">
            <div class="card-h">
                <span class="title">Deals associés</span>
                <div class="flex items-center gap-2">
                    <button type="button" class="text-accent hover:underline text-[11px] font-mono font-semibold" @click="$dispatch('open-create-deal-modal')">
                        + Créer un deal
                    </button>
                    <span class="meta">{{ $contact->deals->count() }}</span>
                </div>
            </div>
            @if($contact->deals->count())
            <div class="p-3 flex flex-col gap-2.5">
                @foreach($contact->deals as $deal)
                @php
                    $dealColor = \App\Helpers\Avatar::color($deal->name);
                    $dealInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $deal->name), 0, 1) ?: 'D');
                @endphp
                <div class="flex items-center gap-2">
                    <div class="av {{ $dealColor }} sm" style="border-radius:4px;">{{ $dealInitials }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-[13px] truncate">{{ $deal->name }}</div>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="chip" style="font-size: 9px; padding: 1px 4px;">{{ $deal->stage?->name ?? '—' }}</span>
                            <span class="text-secondary text-[11px] font-mono">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-4 text-center">
                <p class="text-xs text-secondary mb-3">Aucun deal associé.</p>
                <button type="button" class="btn sm w-full justify-center" @click="$dispatch('open-create-deal-modal')">
                    + Créer un deal
                </button>
            </div>
            @endif
        </div>

        {{-- Brief IA --}}
        <x-ai-insight-card endpoint="/web/ai/contact/{{ $contact->id }}/summarize" title="Brief IA" />

        {{-- Panneau Emelia --}}
        <div class="card p-4"
             x-data="{
                 status: null,
                 loading: true,
                 init() {
                     fetch('/contacts/{{ $contact->id }}/emelia/status', {
                         credentials: 'same-origin',
                         headers: { 'Accept': 'application/json' }
                     })
                     .then(r => r.json())
                     .then(d => { this.status = d; this.loading = false; })
                     .catch(() => { this.loading = false; });
                 },
                 statusLabel(s) {
                     const m = { SENT: 'Envoyé', OPENED: 'Ouvert', CLICKED: 'Cliqué',
                                 REPLIED: 'Répondu', BOUNCED: 'Bounce', UNSUBSCRIBED: 'Désabonné' };
                     return m[s] || s;
                 },
                 statusColor(s) {
                     if (s === 'REPLIED') return 'var(--ok)';
                     if (s === 'OPENED' || s === 'CLICKED') return 'var(--accent)';
                     if (s === 'BOUNCED' || s === 'UNSUBSCRIBED') return 'var(--err)';
                     return 'var(--text-secondary)';
                 }
             }">
            <div class="mono-label mb-3">EMELIA</div>

            <div x-show="loading" class="text-xs text-secondary">Chargement…</div>

            {{-- Pas dans Emelia --}}
            <template x-if="!loading && status && !status.in_emelia">
                <div>
                    <p class="text-xs text-secondary mb-3 leading-relaxed">
                        Ce contact n'est pas encore dans une campagne Emelia.
                    </p>
                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <button class="btn ghost w-full text-xs"
                            @click="$dispatch('open-emelia-modal')">
                        Ajouter à une campagne →
                    </button>
                    @endif
                </div>
            </template>

            {{-- Dans Emelia — liste multi-campagnes --}}
            <template x-if="!loading && status && status.in_emelia">
                <div>
                    <div class="text-[10px] text-tertiary font-mono mb-2 flex items-center gap-1.5">
                        CAMPAGNES (<span x-text="(status.campaigns || []).length"></span>)
                        <template x-if="!status.webhook_active">
                            <span style="font-size:9px; padding:1px 5px; border-radius:4px; background:var(--surface-alt); color:var(--text-tertiary)">webhook OFF</span>
                        </template>
                    </div>

                    <div class="flex flex-col gap-2 mb-3">
                        <template x-for="c in (status.campaigns || [])" :key="c.emelia_id || c.name">
                            <div class="rounded p-2" style="background:var(--surface-alt);">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                          :style="'background:' + statusColor(c.pivot_status || c.emelia_status)"></span>
                                    <span class="text-[12px] font-medium leading-tight truncate" x-text="c.name"></span>
                                </div>
                                <div x-show="c.client_name" class="text-[10px] text-tertiary mb-1 pl-3" x-text="c.client_name"></div>
                                <div x-show="c.last_event_at" class="text-[10px] text-tertiary pl-3 mb-1">
                                    Dernier event : <span class="font-medium" x-text="c.last_event_at"></span>
                                </div>
                                <div class="grid grid-cols-3 gap-1 pl-3 mt-1">
                                    <div class="text-center">
                                        <div class="text-[11px] font-mono font-semibold" x-text="(c.stats && c.stats.sent) || 0"></div>
                                        <div class="text-[9px] text-tertiary">Envois</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-[11px] font-mono font-semibold"
                                             :style="((c.stats && c.stats.opened) || 0) > 0 ? 'color:var(--accent)' : ''"
                                             x-text="(c.stats && c.stats.opened) || 0"></div>
                                        <div class="text-[9px] text-tertiary">Ouverts</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-[11px] font-mono font-semibold"
                                             :style="((c.stats && c.stats.replied) || 0) > 0 ? 'color:var(--ok)' : ''"
                                             x-text="(c.stats && c.stats.replied) || 0"></div>
                                        <div class="text-[9px] text-tertiary">Réponses</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <template x-if="!status.webhook_active">
                        <p class="text-[10px] text-tertiary mb-3 leading-relaxed">
                            Webhook non disponible — utilisez Sync pour importer les événements manuellement.
                        </p>
                    </template>

                    <div class="flex gap-1.5" x-data="{ syncing: false, syncDone: false }">
                        <button class="btn ghost text-xs flex-1"
                                @click="$dispatch('open-emelia-modal')">
                            Gérer
                        </button>
                        <button class="btn ghost text-xs"
                                :disabled="syncing"
                                :class="{ 'opacity-50': syncing }"
                                @click="
                                    syncing = true; syncDone = false;
                                    fetch('{{ route('contacts.emelia.sync', $contact) }}', {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            'Accept': 'application/json',
                                        },
                                        credentials: 'same-origin',
                                    })
                                    .then(r => r.json())
                                    .then(d => { syncing = false; syncDone = true; if (d.created > 0) window.location.reload(); })
                                    .catch(() => { syncing = false; });
                                ">
                            <span x-show="!syncing && !syncDone">Sync</span>
                            <span x-show="syncing">…</span>
                            <span x-show="syncDone">OK</span>
                        </button>
                        <button class="btn ghost text-xs"
                                @click="$dispatch('switch-activity-tab', 'emelia'); document.getElementById('activityFeed').scrollIntoView({behavior:'smooth'})"
                                x-show="status.total_activities > 0">
                            Voir →
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

{{-- Modal Emelia (écoute les events window) --}}
@php $linkedEmeliaIds = $contact->emeliaCampaigns()->pluck('emelia_id')->toArray(); @endphp
<div x-data="emeliaModalComponent({{ $contact->id }}, @json($linkedEmeliaIds))"
     @open-emelia-modal.window="open = true; if (!campaigns.length) fetchCampaigns()"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center"
     style="display:none; background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-md" @click.stop>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Campagnes Emelia</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div x-show="loading" class="py-6 text-center text-secondary text-sm">Chargement des campagnes…</div>
        <div x-show="error && !loading" class="py-3 text-center text-sm" style="color:var(--err)" x-text="error"></div>

        <div x-show="!loading && !error">
            <p class="text-xs text-secondary mb-3">Cochez les campagnes auxquelles associer ce contact. Les campagnes déjà liées sont pré-cochées.</p>
            <div class="flex flex-col gap-2 max-h-64 overflow-y-auto mb-4">
                <template x-for="c in campaigns" :key="c.id">
                    <label class="flex items-center gap-3 p-2.5 rounded cursor-pointer hover:bg-surface-alt"
                           :class="{ 'ring-1 ring-accent': selectedIds.includes(c.id) }">
                        <input type="checkbox" :value="c.id" x-model="selectedIds" class="accent-accent">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm truncate" x-text="c.name"></div>
                            <div class="text-[10px] text-tertiary flex gap-2">
                                <span x-text="c.status"></span>
                                <span x-show="c.contacts_count > 0">· <span x-text="c.contacts_count"></span> contacts</span>
                            </div>
                        </div>
                    </label>
                </template>
            </div>
            <div class="flex justify-end gap-2">
                <button @click="open = false" class="btn ghost" :disabled="submitting">Annuler</button>
                <button @click="submit()" class="btn primary" :disabled="!selectedIds.length || submitting">
                    <span x-show="!submitting">Enregistrer →</span>
                    <span x-show="submitting">Envoi…</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal de création de Deal (depuis le Contact) --}}
<div x-data="{ open: false }"
     @open-create-deal-modal.window="open = true;"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center animate-fade-in"
     style="display:none; background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-md" @click.stop style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Nouveau deal</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="/deals" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            <input type="hidden" name="contact_id" value="{{ $contact->id }}">

            <div class="field">
                <label>Nom du deal *</label>
                <input type="text" name="name" value="{{ $defaultDealName }}" required autofocus>
            </div>

            <div class="field">
                <label>Montant (€) *</label>
                <input type="number" name="amount" min="0" step="0.01" value="0.00" required>
            </div>

            <div class="field">
                <label>Date de clôture</label>
                <input type="text" name="close_date" x-datepicker placeholder="Sélectionnez une date...">
            </div>

            <div class="field">
                <label>Étape *</label>
                <select name="pipeline_stage_id" class="select-arrow" required>
                    <option value="" disabled selected>-- Choisir une étape --</option>
                    @foreach($stages as $stage)
                        <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                    @endforeach
                </select>
            </div>

            <x-custom-fields-form entity-type="deal" :values="[]" />

            <div class="flex justify-end gap-2 mt-2">
                <button type="button" @click="open = false" class="btn ghost">Annuler</button>
                <button type="submit" class="btn primary">Créer le deal</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal de modification des propriétés (Contact) --}}
<div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }"
     @open-edit-properties-modal.window="open = true;"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center animate-fade-in"
     style="display:none; background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-lg" @click.stop style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Modifier les propriétés</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="{{ '/contacts/' . $contact->id }}" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            @method('PUT')

            @if($errors->any())
            <div class="chip err px-3 py-2 rounded-lg mb-2 text-xs flex flex-col gap-1">
                @foreach($errors->all() as $error)
                    <div>• {{ $error }}</div>
                @endforeach
            </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div class="field">
                    <label>Prénom <span class="text-err">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $contact->first_name) }}" required>
                </div>
                <div class="field">
                    <label>Nom</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $contact->last_name) }}">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email', $contact->email) }}">
                </div>
                <div class="field">
                    <label>Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $contact->phone) }}">
                </div>
                <div class="field">
                    <label>Poste</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $contact->job_title) }}">
                </div>
                <div class="field">
                    <label>Lifecycle stage</label>
                    <select name="lifecycle_stage" class="select-arrow">
                        <option value="">—</option>
                        @foreach(['lead','mql','sql','opportunity','customer','evangelist','other'] as $stage)
                        <option value="{{ $stage }}" {{ old('lifecycle_stage', $contact->lifecycle_stage) === $stage ? 'selected' : '' }}>{{ $stage }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Statut Lead</label>
                    <select name="lead_status" class="select-arrow">
                        <option value="">—</option>
                        @foreach(['new' => 'Nouveau', 'open' => 'Ouvert', 'in_progress' => 'En cours', 'connected' => 'Connecté', 'unqualified' => 'Non qualifié', 'bad_fit' => 'Hors cible'] as $val => $lbl)
                        <option value="{{ $val }}" {{ old('lead_status', $contact->lead_status) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <x-custom-fields-form entity-type="contact" :values="old('custom_values', $contact->custom_values ?? [])" />
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
     style="display:none; background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false"
     @click="open = false">
    <div class="card p-6 w-full max-w-md" @click.stop style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Créer une propriété (Contact)</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <form method="POST" action="/settings/fields" class="flex flex-col gap-4 overflow-y-auto pr-1">
            @csrf
            <input type="hidden" name="entity_type" value="contact">

            <div class="field">
                <label>Nom de la propriété (Label) <span class="text-err">*</span></label>
                <input type="text" name="label" x-model="label" @input="updateKey()" placeholder="ex: Statut de facturation" required>
            </div>

            <div class="field">
                <label>Clé technique (Key) <span class="text-err">*</span></label>
                <input type="text" name="key" x-model="key" placeholder="ex: statut_facturation" required>
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

<x-email-draft-modal entity-type="contact" :entity-id="$contact->id" />

<script>
window.emeliaModalComponent = function(contactId, initialSelectedIds) {
    return {
        open: false,
        campaigns: [],
        loading: false,
        error: '',
        selectedIds: initialSelectedIds,
        submitting: false,
        fetchCampaigns() {
            this.loading = true;
            this.error = '';
            fetch('/emelia/campaigns', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    this.campaigns = Array.isArray(data) ? data : [];
                    this.loading = false;
                    if (this.campaigns.length === 0) this.error = 'Aucune campagne trouvée dans Emelia.';
                })
                .catch(() => {
                    this.error = 'Impossible de charger les campagnes Emelia.';
                    this.loading = false;
                });
        },
        submit() {
            if (!this.selectedIds.length) return;
            this.submitting = true;
            fetch('/contacts/' + contactId + '/emelia', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ campaign_ids: this.selectedIds }),
            })
            .then(r => r.json())
            .then(d => {
                if (d.error) { this.error = d.error; this.submitting = false; return; }
                window.location.reload();
            })
            .catch(() => {
                this.error = 'Une erreur est survenue.';
                this.submitting = false;
            });
        }
    };
};
</script>

</x-app-shell>
