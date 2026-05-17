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
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <form method="POST" action="{{ '/contacts/' . $contact->id }}"
              onsubmit="return confirm('Supprimer ce contact ? Cette action est irréversible.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn ghost" style="color:var(--err)">Supprimer</button>
        </form>
        @endif
    </div>
</div>

<div class="px-7 pb-12 grid grid-cols-3 gap-5">
    {{-- Infos --}}
    <div class="col-span-2 flex flex-col gap-4">
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
            </div>
        </div>
    </div>
</div>

</x-app-shell>
