<x-app-shell active="trash" breadcrumb="Corbeille">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Corbeille</h1>
        <p class="text-sm text-secondary mt-0.5">
            Éléments supprimés — restaurez-les avant suppression définitive.
        </p>
    </div>
</div>

<div class="px-7 pb-12"
     x-data="{ tab: '{{ $contacts->count() > 0 ? 'contacts' : ($companies->count() > 0 ? 'companies' : 'deals') }}' }">

    {{-- Onglets --}}
    <div class="flex gap-1 mb-4 border-b" style="border-color: var(--border);">
        <button @click="tab = 'contacts'"
                :class="tab === 'contacts' ? 'border-b-2 text-primary font-medium' : 'text-secondary'"
                class="px-4 py-2 text-sm -mb-px border-b-2 border-transparent transition-colors">
            Contacts
            <span class="ml-1.5 chip" style="font-size:11px;">{{ $contacts->count() }}</span>
        </button>
        <button @click="tab = 'companies'"
                :class="tab === 'companies' ? 'border-b-2 text-primary font-medium' : 'text-secondary'"
                class="px-4 py-2 text-sm -mb-px border-b-2 border-transparent transition-colors">
            Entreprises
            <span class="ml-1.5 chip" style="font-size:11px;">{{ $companies->count() }}</span>
        </button>
        <button @click="tab = 'deals'"
                :class="tab === 'deals' ? 'border-b-2 text-primary font-medium' : 'text-secondary'"
                class="px-4 py-2 text-sm -mb-px border-b-2 border-transparent transition-colors">
            Deals
            <span class="ml-1.5 chip" style="font-size:11px;">{{ $deals->count() }}</span>
        </button>
    </div>

    {{-- ── Contacts ───────────────────────────────────────── --}}
    <div x-show="tab === 'contacts'" x-cloak>
        <div class="card overflow-hidden">
            <table class="t">
                <thead>
                    <tr>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Supprimé le</th>
                        <th style="width:120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contacts as $contact)
                    @php
                        $fullName = trim($contact->first_name . ' ' . $contact->last_name);
                        $color    = \App\Helpers\Avatar::color($fullName ?: $contact->email);
                        $initials = \App\Helpers\Avatar::initials($fullName ?: $contact->email);
                    @endphp
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="av {{ $color }}" style="opacity:.6;">{{ $initials }}</span>
                                <span class="font-medium text-[13px] text-secondary">{{ $fullName ?: $contact->email }}</span>
                            </div>
                        </td>
                        <td><span class="text-secondary text-[12px] font-mono">{{ $contact->email ?? '—' }}</span></td>
                        <td><span class="text-secondary text-[12px] font-mono">{{ $contact->phone ?? '—' }}</span></td>
                        <td><span class="text-tertiary text-[12px]">{{ $contact->deleted_at->format('d/m/Y H:i') }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('contacts.restore', $contact->id) }}">
                                @csrf
                                <x-button type="submit" variant="ghost" size="sm">Restaurer</x-button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-12 text-tertiary text-sm">Aucun contact en corbeille.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Entreprises ────────────────────────────────────── --}}
    <div x-show="tab === 'companies'" x-cloak>
        <div class="card overflow-hidden">
            <table class="t">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Domaine</th>
                        <th>Secteur</th>
                        <th>Supprimé le</th>
                        <th style="width:120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                    @php
                        $color    = \App\Helpers\Avatar::color($company->name);
                        $initials = \App\Helpers\Avatar::initials($company->name);
                    @endphp
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="av {{ $color }}" style="opacity:.6;">{{ $initials }}</span>
                                <span class="font-medium text-[13px] text-secondary">{{ $company->name }}</span>
                            </div>
                        </td>
                        <td><span class="text-secondary text-[12px] font-mono">{{ $company->domain ?? '—' }}</span></td>
                        <td><span class="text-secondary text-[12px]">{{ $company->industry ?? '—' }}</span></td>
                        <td><span class="text-tertiary text-[12px]">{{ $company->deleted_at->format('d/m/Y H:i') }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('companies.restore', $company->id) }}">
                                @csrf
                                <x-button type="submit" variant="ghost" size="sm">Restaurer</x-button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-12 text-tertiary text-sm">Aucune entreprise en corbeille.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Deals ──────────────────────────────────────────── --}}
    <div x-show="tab === 'deals'" x-cloak>
        <div class="card overflow-hidden">
            <table class="t">
                <thead>
                    <tr>
                        <th>Deal</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Supprimé le</th>
                        <th style="width:120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deals as $deal)
                    <tr>
                        <td><span class="font-medium text-[13px] text-secondary">{{ $deal->name }}</span></td>
                        <td>
                            @if($deal->amount)
                            <span class="num-mono text-[12px]">{{ number_format((float)$deal->amount, 0, ',', ' ') }} {{ $deal->currency }}</span>
                            @else
                            <span class="text-tertiary">—</span>
                            @endif
                        </td>
                        <td><span class="chip">{{ $deal->status }}</span></td>
                        <td><span class="text-tertiary text-[12px]">{{ $deal->deleted_at->format('d/m/Y H:i') }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('deals.restore', $deal->id) }}">
                                @csrf
                                <x-button type="submit" variant="ghost" size="sm">Restaurer</x-button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-12 text-tertiary text-sm">Aucun deal en corbeille.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

</x-app-shell>
