<x-app-shell active="contacts" breadcrumb="Contacts / {{ trim($contact->first_name . ' ' . $contact->last_name) }}">

@php
    $fullName = trim($contact->first_name . ' ' . $contact->last_name);
    $color    = \App\Helpers\Avatar::color($fullName ?: $contact->email);
    $initials = \App\Helpers\Avatar::initials($fullName ?: $contact->email);
    $company  = $contact->companies->first();
@endphp

<div class="px-7 pt-6 pb-3 flex items-center gap-4">
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
        <a href="{{ '/contacts/' . $contact->id . '/edit' }}" class="btn ghost">Modifier</a>
        <button type="button"
                class="btn ghost"
                style="color:var(--accent);"
                @click="$dispatch('open-emelia-modal')">
            📧 Emelia
        </button>
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

        @if($contact->emelia_contact_id)
        <div class="card p-4 flex items-center gap-2 text-sm">
            <span>📧</span>
            <span class="text-secondary">Dans une campagne Emelia</span>
        </div>
        @endif
    </div>
</div>

{{-- Modal Emelia --}}
<div x-data="{
    open: false,
    campaigns: [],
    loading: false,
    error: '',
    selectedId: '',
    submitting: false,
    init() {
        this.$el.addEventListener('open-emelia-modal', () => {
            this.open = true;
            if (!this.campaigns.length) this.fetchCampaigns();
        });
    },
    fetchCampaigns() {
        this.loading = true;
        this.error = '';
        fetch('/emelia/campaigns', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                this.campaigns = Array.isArray(data) ? data : (data.data ?? []);
                this.loading = false;
            })
            .catch(() => {
                this.error = 'Impossible de charger les campagnes.';
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
            body: JSON.stringify({ campaign_id: this.selectedId }),
        }).then(r => {
            if (r.redirected) { window.location.href = r.url; return; }
            return r.json();
        }).then(() => {
            window.location.reload();
        }).catch(() => {
            this.error = 'Une erreur est survenue.';
            this.submitting = false;
        });
    }
}"
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

        <div x-show="loading" class="py-6 text-center text-secondary text-sm">Chargement…</div>
        <div x-show="error && !loading" class="py-3 text-center text-sm" style="color:var(--err)" x-text="error"></div>

        <div x-show="!loading && !error">
            <div x-show="!campaigns.length" class="py-4 text-center text-secondary text-sm">Aucune campagne active trouvée.</div>
            <div x-show="campaigns.length" class="flex flex-col gap-2 max-h-64 overflow-y-auto mb-4">
                <template x-for="c in campaigns" :key="c.id ?? c._id">
                    <label class="flex items-center gap-3 p-2.5 rounded cursor-pointer hover:bg-surface-alt"
                           :class="{ 'ring-1 ring-accent': selectedId === (c.id ?? c._id) }">
                        <input type="radio" name="emelia_campaign" :value="c.id ?? c._id" x-model="selectedId" class="accent-accent">
                        <span class="text-sm" x-text="c.name ?? c.title ?? c.id ?? c._id"></span>
                    </label>
                </template>
            </div>
            <div class="flex justify-end gap-2">
                <button @click="open = false" class="btn ghost" :disabled="submitting">Annuler</button>
                <button @click="submit()" class="btn primary" :disabled="!selectedId || submitting">
                    <span x-show="!submitting">Ajouter →</span>
                    <span x-show="submitting">Envoi…</span>
                </button>
            </div>
        </div>
    </div>
</div>

</x-app-shell>
