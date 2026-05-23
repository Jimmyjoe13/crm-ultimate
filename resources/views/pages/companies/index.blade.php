<x-app-shell active="companies" breadcrumb="Entreprises">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Entreprises</h1>
        <p class="text-sm text-secondary mt-0.5">
            <span class="num-mono">{{ $companies->total() }}</span> entreprises
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <a href="/imports/company/create" class="btn sm ghost">
            <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importer CSV
        </a>
        <a href="/companies/export{{ $search ? '?search='.urlencode($search) : '' }}" class="btn sm ghost">
            <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exporter CSV
        </a>
        @endif
        <x-button href="/companies/create" size="sm">Nouvelle entreprise</x-button>
        <form method="GET" action="{{ '/companies' }}" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher…"
                   style="padding:6px 10px; border:1px solid var(--border); border-radius:7px; font-size:13px; background:var(--surface); color:var(--text);">
            <button type="submit" class="btn sm">Chercher</button>
        </form>
    </div>
</div>

<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        @php $pageIds = $companies->pluck('id')->toArray(); @endphp
        <table class="t" x-data>
            <thead>
                <tr>
                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <th style="width:36px;">
                        <span class="ckb"
                              :class="{ 'on': $store.bulk.allSelected('company', {{ json_encode($pageIds) }}) }"
                              @click.stop="$store.bulk.toggleAll('company', {{ json_encode($pageIds) }})"
                              style="cursor:pointer;"></span>
                    </th>
                    @endif
                    <x-sort-th column="name"       label="Entreprise" :sort="$sort" :dir="$dir" />
                    <x-sort-th column="industry"   label="Industrie"  :sort="$sort" :dir="$dir" />
                    <th>Contacts</th>
                    <x-sort-th column="city"       label="Ville"      :sort="$sort" :dir="$dir" />
                    <x-sort-th column="created_at" label="Ajouté le"  :sort="$sort" :dir="$dir" />
                </tr>
            </thead>
            <tbody>
                @forelse($companies as $company)
                @php
                    $color    = \App\Helpers\Avatar::color($company->name);
                    $initials = strtoupper(mb_substr($company->name, 0, 2));
                @endphp
                <tr onclick="window.location='{{ '/companies/' . $company->id }}'" style="cursor:pointer;"
                    :class="{ 'bg-surface2': $store.bulk.selections.company.has({{ $company->id }}) }">
                    @if(in_array(auth()->user()?->role, ['admin','manager']))
                    <td @click.stop>
                        <span class="ckb"
                              :class="{ 'on': $store.bulk.selections.company.has({{ $company->id }}) }"
                              @click.stop="$store.bulk.toggle('company', {{ $company->id }})"
                              style="cursor:pointer;"></span>
                    </td>
                    @endif
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
                <tr><td colspan="{{ in_array(auth()->user()?->role, ['admin','manager']) ? 6 : 5 }}" class="text-center py-12 text-tertiary text-sm">Aucune entreprise.</td></tr>
                @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$companies" />
    </div>
</div>

@if(in_array(auth()->user()?->role, ['admin','manager']))
<x-bulk-bar entity="company" delete-action="/companies/bulk-destroy" />
@endif

</x-app-shell>
