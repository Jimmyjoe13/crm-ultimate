@props([
    'color' => 'gray',
    'dot' => false,
    'removable' => false,
])

@php
    $colorClass = match($color) {
        'green'  => 'ok',
        'red'    => 'err',
        'yellow' => 'warn',
        'blue'   => 'info',
        'orange' => 'accent',
        default  => '',
    };
@endphp

<span class="chip {{ $colorClass }}" {{ $attributes }}>
    @if($dot)
        <span class="chip-dot"></span>
    @endif
    {{ $slot }}
    @if($removable)
        <button type="button"
                @click="$dispatch('chip-remove')"
                class="ml-1 opacity-60 hover:opacity-100"
                style="line-height:0; cursor:pointer; background:none; border:none; padding:0; color:inherit;">
            <svg style="width:10px;height:10px;" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    @endif
</span>
