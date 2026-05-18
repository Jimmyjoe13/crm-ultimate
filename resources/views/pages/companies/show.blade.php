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
        <p class="text-sm text-secondary">{{ $company->industry ?? '' }}</p>
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

<div class="px-7 pb-12 grid grid-cols-3 gap-5" x-data="{ tab: 'info' }">
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
        @if($company->contacts->count())
        <div class="card overflow-hidden">
            <div class="card-h">
                <span class="title">Contacts</span>
                <span class="meta">{{ $company->contacts->count() }}</span>
            </div>
            <table class="t">
                <thead><tr><th>Contact</th><th>Email</th><th>Poste</th></tr></thead>
                <tbody>
                    @foreach($company->contacts as $contact)
                    @php
                        $fullName = trim($contact->first_name . ' ' . $contact->last_name);
                        $cc = \App\Helpers\Avatar::color($fullName ?: $contact->email);
                        $ci = \App\Helpers\Avatar::initials($fullName ?: $contact->email);
                    @endphp
                    <tr onclick="window.location='{{ '/contacts/' . $contact->id }}'" style="cursor:pointer;">
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="av {{ $cc }} sm">{{ $ci }}</span>
                                <span class="font-medium">{{ $fullName }}</span>
                            </div>
                        </td>
                        <td><span class="text-secondary text-[12px] font-mono">{{ $contact->email ?? '—' }}</span></td>
                        <td><span class="text-secondary">{{ $contact->job_title ?? '—' }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($company->deals->count())
        <div class="card overflow-hidden">
            <div class="card-h">
                <span class="title">Deals</span>
                <span class="meta">{{ $company->deals->count() }}</span>
            </div>
            <table class="t">
                <thead><tr><th>Deal</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody>
                    @foreach($company->deals as $deal)
                    <tr>
                        <td class="font-medium">{{ $deal->name }}</td>
                        <td><span class="num-mono">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span></td>
                        <td>
                            <span class="chip {{ match($deal->status) { 'won' => 'ok', 'lost' => 'err', default => '' } }}">
                                {{ $deal->status }}
                            </span>
                        </td>
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
                subject-type="company"
                :subject-id="$company->id"
                :show-composer="true"
            />
        </div>
    </div>

    <div class="flex flex-col gap-3">
        <div class="card p-4 h-fit">
            <div class="mono-label mb-3">Propriétés</div>
            <div class="flex flex-col gap-3">
                @if($company->website)
                <div>
                    <div class="text-[11px] text-tertiary font-mono mb-0.5">Site web</div>
                    <a href="{{ $company->website }}" target="_blank" class="text-[13px] text-accent hover:underline">{{ $company->website }}</a>
                </div>
                @endif
                @if($company->phone)
                <div>
                    <div class="text-[11px] text-tertiary font-mono mb-0.5">Téléphone</div>
                    <div class="text-[13px] font-mono">{{ $company->phone }}</div>
                </div>
                @endif
                <div>
                    <div class="text-[11px] text-tertiary font-mono mb-0.5">Créé le</div>
                    <div class="text-[13px] num-mono">{{ $company->created_at->format('d/m/Y') }}</div>
                </div>
                <x-custom-fields-show :entity="$company" entity-type="company" />
            </div>
        </div>

        <x-ai-insight-card endpoint="/web/ai/company/{{ $company->id }}/summarize" title="Résumé entreprise" />
    </div>
</div>

</x-app-shell>
