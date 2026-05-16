<x-app-shell active="deals" breadcrumb="Deals">

<div class="px-7 pt-6 pb-3 flex items-end justify-between">
    <div>
        <h1>Deals</h1>
        <p class="text-sm text-secondary mt-0.5">
            <span class="num-mono">{{ $allCount }}</span> deals ouverts ·
            <span class="num-mono">{{ number_format($total, 0, ',', "\xc2\xa0") }} €</span> en pipeline
        </p>
    </div>
    <div class="flex items-center gap-2">
        {{-- View toggle --}}
        <div class="flex border rounded-lg overflow-hidden" style="border-color: var(--border);">
            <span class="btn sm" style="border-radius:0; border:none; background: var(--text); color: var(--bg);">
                <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>Table
            </span>
            <a href="{{ route('pipeline.index') }}" class="btn sm ghost" style="border-radius:0; border:none;">
                <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><rect x="3" y="3" width="5" height="18"/><rect x="10" y="3" width="5" height="12"/><rect x="17" y="3" width="4" height="8"/></svg>Kanban
            </a>
        </div>
    </div>
</div>

{{-- Filter bar --}}
<div class="px-7 pb-3 flex items-center gap-2 flex-wrap">
    <span class="chip solid num-mono">All · {{ $allCount }}</span>
    <span class="ml-auto text-xs text-tertiary font-mono">Sort: close date ↑</span>
</div>

{{-- Flash success --}}
@if(session('success'))
<div class="mx-7 mb-3 chip ok px-3 py-2 rounded-lg" style="border-radius:8px;">
    {{ session('success') }}
</div>
@endif

{{-- New deal modal trigger --}}
<div x-data="{ open: false }" class="px-7 mb-3">
    <button @click="open = true" class="btn primary">
        <svg class="ic" style="stroke-width:2;" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Nouveau deal
    </button>

    {{-- Modal overlay --}}
    <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-40 flex items-center justify-center">
        <div @click="open = false" class="absolute inset-0 drawer-backdrop"></div>
        <div class="relative card shadow-pop z-50" style="width: 580px; max-height: 90vh; overflow: auto;">
            <div class="flex items-center justify-between px-6 py-4 border-b border-default">
                <div>
                    <div class="text-base font-semibold text-primary">Nouveau deal</div>
                    <div class="mono-label mt-0.5">Remplir les informations de base</div>
                </div>
                <button @click="open = false" class="btn ghost icon">
                    <svg class="ic" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('deals.store') }}" class="px-6 py-5 grid grid-cols-2 gap-4">
                @csrf
                <div class="field col-span-2">
                    <label>Nom du deal *</label>
                    <input type="text" name="name" required placeholder="Ex: Acme SA — Licence annuelle">
                </div>
                <div class="field">
                    <label>Montant (€)</label>
                    <input type="number" name="amount" min="0" step="100" placeholder="0">
                </div>
                <div class="field">
                    <label>Date de clôture</label>
                    <input type="date" name="close_date">
                </div>
                <div class="field col-span-2">
                    <label>Étape *</label>
                    <select name="pipeline_stage_id" class="select-arrow" required>
                        <option value="">Choisir une étape…</option>
                        @foreach($stages as $stage)
                        <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 flex justify-between border-t border-default pt-4 mt-2">
                    <button type="button" @click="open = false" class="btn">Annuler</button>
                    <button type="submit" class="btn primary">Créer le deal</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="px-7 pb-12">
    <div class="card overflow-hidden">
        <table class="t">
            <thead>
                <tr>
                    <th style="width:32px;"><span class="ckb"></span></th>
                    <th>Deal</th>
                    <th>Company</th>
                    <th>Amount</th>
                    <th>Stage</th>
                    <th>Close date</th>
                    <th>Owner</th>
                    <th style="width:32px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($deals as $deal)
                @php
                    $company = $deal->companies->first();
                    $owner   = $deal->owner;
                    $ownerInitials = $owner ? \App\Helpers\Avatar::initials($owner->name ?? $owner->email) : '?';
                    $ownerColor    = $owner ? \App\Helpers\Avatar::color($owner->name ?? $owner->email) : 'c1';
                    $isOverdue = $deal->close_date && $deal->close_date->isPast();
                @endphp
                <tr>
                    <td><span class="ckb"></span></td>
                    <td>
                        <div class="font-medium">{{ $deal->name }}</div>
                        <div class="text-[11.5px] text-tertiary font-mono mt-0.5">
                            Créé {{ $deal->created_at->diffForHumans() }}
                        </div>
                    </td>
                    <td><span class="text-secondary">{{ $company?->name ?? '—' }}</span></td>
                    <td><span class="num-mono font-semibold">{{ number_format($deal->amount, 0, ',', "\xc2\xa0") }} €</span></td>
                    <td>
                        @if($deal->stage)
                        <span class="chip"><span class="chip-dot" style="background: var(--info);"></span>{{ $deal->stage->name }}</span>
                        @else
                        <span class="text-tertiary text-xs">—</span>
                        @endif
                    </td>
                    <td>
                        @if($deal->close_date)
                        <span class="num-mono @if($isOverdue) text-err @endif">
                            {{ $deal->close_date->format('d/m/Y') }}
                        </span>
                        @else
                        <span class="text-tertiary">—</span>
                        @endif
                    </td>
                    <td><span class="av {{ $ownerColor }} sm">{{ $ownerInitials }}</span></td>
                    <td>
                        <svg class="ic text-tertiary" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-12 text-tertiary text-sm">
                        Aucun deal ouvert pour le moment.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        @if($deals->hasPages())
        <div class="px-4 py-3 border-t border-default flex items-center justify-between text-[12px] text-secondary">
            <span class="num-mono">{{ $deals->firstItem() }}–{{ $deals->lastItem() }} sur {{ $deals->total() }}</span>
            <div class="flex gap-2">
                @if($deals->onFirstPage())
                <span class="btn sm" style="opacity:.4; cursor:not-allowed;">← Précédent</span>
                @else
                <a href="{{ $deals->previousPageUrl() }}" class="btn sm">← Précédent</a>
                @endif
                @if($deals->hasMorePages())
                <a href="{{ $deals->nextPageUrl() }}" class="btn sm">Suivant →</a>
                @else
                <span class="btn sm" style="opacity:.4; cursor:not-allowed;">Suivant →</span>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

</x-app-shell>
