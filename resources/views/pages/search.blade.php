<x-app-shell active="" breadcrumb="Recherche">

<div class="px-7 pt-6 pb-3">
    <h1>Recherche</h1>
</div>

<div class="px-7 pb-12 max-w-2xl">
    <form method="GET" action="{{ route('search') }}" class="flex gap-2 mb-6" x-data x-init="$el.querySelector('input').focus()">
        <input type="text" name="q" value="{{ $q }}" placeholder="Rechercher deal, contact, entreprise…"
               class="flex-1" style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; background:var(--surface); color:var(--text);"
               autofocus>
        <button type="submit" class="btn primary">Chercher</button>
    </form>

    @if(strlen($q) >= 2)

    @if($results['deals']->count())
    <div class="mb-5">
        <div class="mono-label mb-2">Deals</div>
        <div class="card overflow-hidden">
            @foreach($results['deals'] as $deal)
            <a href="{{ route('deals.index') }}" class="flex items-center gap-3 px-4 py-3 border-b border-default hover:bg-surface2 last:border-b-0">
                <svg class="ic text-tertiary" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h12"/></svg>
                <div>
                    <div class="text-[13px] font-medium">{{ $deal->name }}</div>
                    <div class="text-[11.5px] text-tertiary font-mono">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    @if($results['contacts']->count())
    <div class="mb-5">
        <div class="mono-label mb-2">Contacts</div>
        <div class="card overflow-hidden">
            @foreach($results['contacts'] as $contact)
            @php $fullName = trim($contact->first_name . ' ' . $contact->last_name); @endphp
            <a href="{{ route('contacts.show', $contact) }}" class="flex items-center gap-3 px-4 py-3 border-b border-default hover:bg-surface2 last:border-b-0">
                <span class="av {{ \App\Helpers\Avatar::color($fullName ?: $contact->email) }} sm">{{ \App\Helpers\Avatar::initials($fullName ?: $contact->email) }}</span>
                <div>
                    <div class="text-[13px] font-medium">{{ $fullName ?: $contact->email }}</div>
                    <div class="text-[11.5px] text-tertiary font-mono">{{ $contact->email }}</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    @if($results['companies']->count())
    <div class="mb-5">
        <div class="mono-label mb-2">Entreprises</div>
        <div class="card overflow-hidden">
            @foreach($results['companies'] as $company)
            <a href="{{ route('companies.show', $company) }}" class="flex items-center gap-3 px-4 py-3 border-b border-default hover:bg-surface2 last:border-b-0">
                <span class="av {{ \App\Helpers\Avatar::color($company->name) }} sq sm">{{ strtoupper(mb_substr($company->name, 0, 2)) }}</span>
                <div class="text-[13px] font-medium">{{ $company->name }}</div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    @if(!array_sum(array_map('count', $results->toArray())))
    <div class="text-center py-12 text-tertiary">Aucun résultat pour "{{ $q }}".</div>
    @endif

    @endif
</div>

</x-app-shell>
