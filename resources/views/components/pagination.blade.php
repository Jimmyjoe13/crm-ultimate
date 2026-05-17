@props([
    'paginator' => null,
    // Mode manuel (quand $paginator est null)
    'page'     => 1,
    'lastPage' => 1,
    'total'    => 0,
    'perPage'  => 25,
    'baseUrl'  => '',
])

@php
    if ($paginator) {
        $show     = $paginator->hasPages();
        $isFirst  = $paginator->onFirstPage();
        $isLast   = ! $paginator->hasMorePages();
        $prevUrl  = $paginator->previousPageUrl();
        $nextUrl  = $paginator->nextPageUrl();
        $from     = $paginator->firstItem();
        $to       = $paginator->lastItem();
        $tot      = $paginator->total();
    } else {
        $show     = $lastPage > 1;
        $isFirst  = $page <= 1;
        $isLast   = $page >= $lastPage;
        $prevUrl  = $baseUrl . '?page=' . ($page - 1);
        $nextUrl  = $baseUrl . '?page=' . ($page + 1);
        $from     = ($page - 1) * $perPage + 1;
        $to       = min($page * $perPage, $total);
        $tot      = $total;
    }
@endphp

@if($show)
<div {{ $attributes->merge(['class' => 'px-4 py-3 border-t border-default flex items-center justify-between text-[12px] text-secondary']) }}>
    <span class="num-mono">{{ $from }}–{{ $to }} sur {{ $tot }}</span>
    <div class="flex gap-2">
        @if($isFirst)
            <span class="btn sm" style="opacity:.4; cursor:not-allowed;">← Précédent</span>
        @else
            <a href="{{ $prevUrl }}" class="btn sm">← Précédent</a>
        @endif
        @if($isLast)
            <span class="btn sm" style="opacity:.4; cursor:not-allowed;">Suivant →</span>
        @else
            <a href="{{ $nextUrl }}" class="btn sm">Suivant →</a>
        @endif
    </div>
</div>
@endif
