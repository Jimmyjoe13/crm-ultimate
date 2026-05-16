<x-app-shell active="companies" breadcrumb="Entreprises">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Entreprises</h1>
        <p class="text-sm text-secondary mt-0.5">
            <span class="num-mono">{{ $companies->total() }}</span> entreprises
        </p>
    </div>
    <form method="GET" action="{{ route('companies.index') }}" class="flex items-center gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher…"
               style="padding:6px 10px; border:1px solid var(--border); border-radius:7px; font-size:13px; background:var(--surface); color:var(--text);">
        <button type="submit" class="btn sm">Chercher</button>
    </form>
</div>

<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        <table class="t">
            <thead>
                <tr>
                    <th>Entreprise</th>
                    <th>Industrie</th>
                    <th>Contacts</th>
                    <th>Ville</th>
                    <th>Ajouté le</th>
                </tr>
            </thead>
            <tbody>
                @forelse($companies as $company)
                @php
                    $color    = \App\Helpers\Avatar::color($company->name);
                    $initials = strtoupper(mb_substr($company->name, 0, 2));
                @endphp
                <tr onclick="window.location='{{ route('companies.show', $company) }}'" style="cursor:pointer;">
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="av {{ $color }} sq">{{ $initials }}</span>
                            <div class="font-medium text-[13px]">{{ $company->name }}</div>
                        </div>
                    </td>
                    <td><span class="text-secondary">{{ $company->industry ?? '—' }}</span></td>
                    <td><span class="num-mono text-[12px]">{{ $company->contacts->count() }}</span></td>
                    <td><span class="text-secondary">{{ $company->city ?? '—' }}</span></td>
                    <td><span class="num-mono text-[12px] text-tertiary">{{ $company->created_at->format('d/m/Y') }}</span></td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-12 text-tertiary text-sm">Aucune entreprise.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($companies->hasPages())
        <div class="px-4 py-3 border-t border-default flex items-center justify-between text-[12px] text-secondary">
            <span class="num-mono">{{ $companies->firstItem() }}–{{ $companies->lastItem() }} sur {{ $companies->total() }}</span>
            <div class="flex gap-2">
                @if(!$companies->onFirstPage())
                <a href="{{ $companies->previousPageUrl() }}" class="btn sm">← Précédent</a>
                @endif
                @if($companies->hasMorePages())
                <a href="{{ $companies->nextPageUrl() }}" class="btn sm">Suivant →</a>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

</x-app-shell>
