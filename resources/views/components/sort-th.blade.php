@props(['column', 'label', 'sort', 'dir'])

@php
    $isActive = $sort === $column;
    $nextDir  = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
    $url      = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir]);
@endphp

<th>
    <a href="{{ $url }}" class="flex items-center gap-1 select-none"
       style="text-decoration:none; white-space:nowrap;
              color: {{ $isActive ? 'var(--text)' : 'inherit' }};
              font-weight: {{ $isActive ? '600' : 'inherit' }};">
        {{ $label }}
        @if($isActive)
        <svg style="width:9px;height:9px;flex-shrink:0;" viewBox="0 0 10 10" fill="currentColor">
            @if($dir === 'asc')
            <path d="M5 1L10 9H0L5 1Z"/>
            @else
            <path d="M5 9L0 1H10L5 9Z"/>
            @endif
        </svg>
        @else
        <svg style="width:9px;height:9px;flex-shrink:0;opacity:.25;" viewBox="0 0 10 10" fill="currentColor">
            <path d="M5 1L10 9H0L5 1Z"/>
        </svg>
        @endif
    </a>
</th>
