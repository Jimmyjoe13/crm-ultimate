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
    @if($contact->lifecycle_stage)
    <span class="chip ml-2">{{ $contact->lifecycle_stage }}</span>
    @endif
    <div class="flex items-center gap-2 ml-auto">
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

<div class="px-7 pb-12 grid grid-cols-3 gap-5" x-data="{ tab: 'info' }">
    {{-- Infos + Activité --}}
    <div class="col-span-2 flex flex-col gap-4">
        {{-- Onglets --}}
        <div class="flex border-b border-default gap-0 -mb-2">
            <button @click="tab = 'info'" class="px-4 py-2.5 text-[13px] font-medium border-b-2 transition-colors"
                    :style="tab === 'info' ? 'border-color: var(--accent); color: var(--text);' : 'border-color: transparent; color: var(--text-tertiary);'">
                Informations
            </button>
            <button @click="tab = 'activity'" class="px-4 py-2.5 text-[13px] font-medium border-b-2 transition-colors"
                    :style="tab === 'activity' ? 'border-color: var(--accent); color: var(--text);' : 'border-color: transparent; color: var(--text-tertiary);'">
                Activité <span class="chip ml-1" style="padding: 0 5px; font-size:10px;">{{ $activities->count() }}</span>
            </button>
        </div>

        <div x-show="tab === 'info'" class="flex flex-col gap-4">
            <div class="card p-5">
                <div class="card-h mb-4" style="margin: -20px -20px 16px; padding: 10px 14px;">
                    <span class="title">Informations</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    @if($contact->email)
                    <div class="field">
                        <label>Email</label>
                        <div class="text-[13px] font-mono">{{ $contact->email }}</div>
                    </div>
                    @endif
                    @if($contact->phone)
                    <div class="field">
                        <label>Téléphone</label>
                        <div class="text-[13px] font-mono">{{ $contact->phone }}</div>
                    </div>
                    @endif
                    @if($contact->job_title)
                    <div class="field">
                        <label>Poste</label>
                        <div class="text-[13px]">{{ $contact->job_title }}</div>
                    </div>
                    @endif
                    @if($company)
                    <div class="field">
                        <label>Entreprise</label>
                        <a href="{{ '/companies/' . $company->id }}" class="text-[13px] text-accent hover:underline">{{ $company->name }}</a>
                    </div>
                    @endif
                    @if($contact->lead_status)
                    <div class="field">
                        <label>Statut lead</label>
                        <span class="chip">{{ $contact->lead_status }}</span>
                    </div>
                    @endif
                    @if($contact->owner)
                    <div class="field">
                        <label>Propriétaire</label>
                        <div class="text-[13px]">{{ $contact->owner->name }}</div>
                    </div>
                    @endif
                </div>
            </div>

            @if($contact->deals->count())
            <div class="card overflow-hidden">
                <div class="card-h">
                    <span class="title">Deals associés</span>
                    <span class="meta">{{ $contact->deals->count() }}</span>
                </div>
                <table class="t">
                    <thead><tr><th>Deal</th><th>Montant</th><th>Étape</th></tr></thead>
                    <tbody>
                        @foreach($contact->deals as $deal)
                        <tr>
                            <td class="font-medium">{{ $deal->name }}</td>
                            <td><span class="num-mono">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span></td>
                            <td><span class="chip">{{ $deal->stage?->name ?? '—' }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <div x-show="tab === 'activity'" x-cloak>
            <x-activity-timeline
                :activities="$activities"
                subject-type="contact"
                :subject-id="$contact->id"
                :show-composer="true"
            />
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="flex flex-col gap-3">
        <div class="card p-4">
            <div class="mono-label mb-3">Propriétés</div>
            <div class="flex flex-col gap-3">
                <div>
                    <div class="text-[11px] text-tertiary font-mono mb-0.5">Créé le</div>
                    <div class="text-[13px] num-mono">{{ $contact->created_at->format('d/m/Y') }}</div>
                </div>
                @if($contact->lifecycle_stage)
                <div>
                    <div class="text-[11px] text-tertiary font-mono mb-0.5">Lifecycle stage</div>
                    <span class="chip">{{ $contact->lifecycle_stage }}</span>
                </div>
                @endif
                <x-custom-fields-show :entity="$contact" entity-type="contact" />
            </div>
        </div>

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
                 }
             }">
            <div class="mono-label mb-3">📧 Emelia</div>

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

            {{-- Dans Emelia --}}
            <template x-if="!loading && status && status.in_emelia">
                <div>
                    {{-- Campagne --}}
                    <div class="mb-3">
                        <div class="text-[10px] text-tertiary font-mono mb-0.5">CAMPAGNE</div>
                        <div class="text-[13px] font-medium leading-tight" x-text="status.campaign_name || '—'"></div>
                    </div>

                    {{-- Stats email --}}
                    <div class="grid grid-cols-3 gap-1 mb-3">
                        <div class="rounded p-2 text-center" style="background:var(--surface-alt);">
                            <div class="text-base font-mono font-semibold" x-text="status.stats.sent"></div>
                            <div class="text-[9px] text-tertiary mt-0.5">Envois</div>
                        </div>
                        <div class="rounded p-2 text-center" style="background:var(--surface-alt);">
                            <div class="text-base font-mono font-semibold" x-text="status.stats.opened"></div>
                            <div class="text-[9px] text-tertiary mt-0.5">Ouvertures</div>
                        </div>
                        <div class="rounded p-2 text-center" style="background:var(--surface-alt);">
                            <div class="text-base font-mono font-semibold" x-text="status.stats.clicked"></div>
                            <div class="text-[9px] text-tertiary mt-0.5">Clics</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-1 mb-3">
                        <div class="rounded p-2 text-center" style="background:var(--surface-alt);">
                            <div class="text-base font-mono font-semibold" x-text="status.stats.replied"></div>
                            <div class="text-[9px] text-tertiary mt-0.5">Réponses</div>
                        </div>
                        <div class="rounded p-2 text-center" style="background:var(--surface-alt);">
                            <div class="text-base font-mono font-semibold" x-text="status.stats.bounced"></div>
                            <div class="text-[9px] text-tertiary mt-0.5">Bounces</div>
                        </div>
                        <div class="rounded p-2 text-center" style="background:var(--surface-alt);">
                            <div class="text-base font-mono font-semibold" x-text="status.stats.unsubscribed"></div>
                            <div class="text-[9px] text-tertiary mt-0.5">Désabonnés</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-tertiary mb-3"
                         x-show="status.last_activity">
                        <span>Dernière activité</span>
                        <span x-text="status.last_activity"></span>
                    </div>

                    <div x-show="status.total_activities === 0" class="text-[10px] text-tertiary mb-3 leading-relaxed">
                        Aucune activité reçue. Configurez le webhook Emelia pour synchroniser automatiquement.
                    </div>

                    <div class="flex gap-1.5">
                        <button class="btn ghost text-xs flex-1"
                                @click="$dispatch('open-emelia-modal')">
                            Changer
                        </button>
                        <button class="btn ghost text-xs"
                                @click="tab = 'activity'"
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
<div x-data="{
    open: false,
    campaigns: [],
    loading: false,
    error: '',
    selectedId: '',
    selectedName: '',
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
        if (!this.selectedId) return;
        this.submitting = true;
        fetch('/contacts/{{ $contact->id }}/emelia', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ campaign_id: this.selectedId, campaign_name: this.selectedName }),
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
}"
     @open-emelia-modal.window="open = true; if (!campaigns.length) fetchCampaigns()"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center"
     style="background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false">
    <div class="card p-6 w-full max-w-md" @click.stop>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold">Ajouter à une campagne Emelia</h2>
            <button @click="open = false" class="btn ghost icon">
                <svg class="ic" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div x-show="loading" class="py-6 text-center text-secondary text-sm">Chargement des campagnes…</div>
        <div x-show="error && !loading" class="py-3 text-center text-sm" style="color:var(--err)" x-text="error"></div>

        <div x-show="!loading && !error">
            <p class="text-xs text-secondary mb-3">Sélectionnez la campagne Emelia dans laquelle ajouter ce contact.</p>
            <div class="flex flex-col gap-2 max-h-64 overflow-y-auto mb-4">
                <template x-for="c in campaigns" :key="c.id">
                    <label class="flex items-center gap-3 p-2.5 rounded cursor-pointer hover:bg-surface-alt"
                           :class="{ 'ring-1 ring-accent': selectedId === c.id }"
                           @click="selectedId = c.id; selectedName = c.name">
                        <input type="radio" name="emelia_campaign" :value="c.id" x-model="selectedId" class="accent-accent">
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
                <button @click="submit()" class="btn primary" :disabled="!selectedId || submitting">
                    <span x-show="!submitting">Ajouter à la campagne →</span>
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
     style="background: rgba(0,0,0,.45);"
     @keydown.escape.window="open = false">
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
                <input type="date" name="close_date">
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

</x-app-shell>
