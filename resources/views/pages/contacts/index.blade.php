<x-app-shell active="contacts" breadcrumb="Contacts">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Contacts</h1>
        <p class="text-sm text-secondary mt-0.5">
            <span class="num-mono">{{ $contacts->total() }}</span> contacts
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <a href="/imports/contact/create" class="btn sm ghost">
            <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importer CSV
        </a>
        @endif
        <form method="GET" action="{{ '/contacts' }}" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher…"
                   class="field" style="padding: 6px 10px; border: 1px solid var(--border); border-radius:7px; font-size:13px; background: var(--surface); color: var(--text);">
            <button type="submit" class="btn sm">Chercher</button>
        </form>
    </div>
</div>

<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        <table class="t">
            <thead>
                <tr>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Entreprise</th>
                    <th>Lifecycle</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $contact)
                @php
                    $fullName = trim($contact->first_name . ' ' . $contact->last_name);
                    $color    = \App\Helpers\Avatar::color($fullName ?: $contact->email);
                    $initials = \App\Helpers\Avatar::initials($fullName ?: $contact->email);
                    $company  = $contact->companies->first();
                @endphp
                <tr onclick="window.location='{{ '/contacts/' . $contact->id }}'" style="cursor:pointer;">
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="av {{ $color }}">{{ $initials }}</span>
                            <div>
                                <div class="font-medium text-[13px]">{{ $fullName ?: $contact->email }}</div>
                                @if($contact->job_title)
                                <div class="text-[11.5px] text-tertiary font-mono">{{ $contact->job_title }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td><span class="text-secondary text-[12px] font-mono">{{ $contact->email ?? '—' }}</span></td>
                    <td><span class="text-secondary text-[12px] font-mono">{{ $contact->phone ?? '—' }}</span></td>
                    <td><span class="text-secondary">{{ $company?->name ?? '—' }}</span></td>
                    <td>
                        @if($contact->lifecycle_stage)
                        <span class="chip">{{ $contact->lifecycle_stage }}</span>
                        @else
                        <span class="text-tertiary">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-12 text-tertiary text-sm">Aucun contact.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($contacts->hasPages())
        <div class="px-4 py-3 border-t border-default flex items-center justify-between text-[12px] text-secondary">
            <span class="num-mono">{{ $contacts->firstItem() }}–{{ $contacts->lastItem() }} sur {{ $contacts->total() }}</span>
            <div class="flex gap-2">
                @if(!$contacts->onFirstPage())
                <a href="{{ $contacts->previousPageUrl() }}" class="btn sm">← Précédent</a>
                @endif
                @if($contacts->hasMorePages())
                <a href="{{ $contacts->nextPageUrl() }}" class="btn sm">Suivant →</a>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

</x-app-shell>
