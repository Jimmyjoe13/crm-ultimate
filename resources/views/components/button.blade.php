@props([
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'loading' => false,
    'disabled' => false,
    'href' => null,
    'type' => 'button',
])

@php
    $classes = 'btn';
    $classes .= match($variant) {
        'primary' => ' primary',
        'ghost'   => ' ghost',
        'danger'  => ' danger',
        default   => '',
    };
    $classes .= match($size) {
        'sm' => ' sm',
        'lg' => ' lg',
        default => '',
    };
    if ($icon && !$slot->isNotEmpty()) $classes .= ' icon';
@endphp

@if($href)
<a href="{{ $href }}"
   class="{{ $classes }}"
   @if($disabled) aria-disabled="true" style="pointer-events:none;opacity:0.5;" @endif
   {{ $attributes->except(['href','variant','size','icon','loading','disabled','type']) }}>
    @if($loading)
        <svg class="ic animate-spin" style="width:14px;height:14px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5" fill="none" stroke-dasharray="50" stroke-dashoffset="20"/></svg>
    @elseif($icon)
        <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24">{!! $icon !!}</svg>
    @endif
    {{ $slot }}
</a>
@else
<button type="{{ $type }}"
        class="{{ $classes }}"
        @if($disabled || $loading) disabled aria-disabled="true" @endif
        @if($loading) aria-busy="true" @endif
        {{ $attributes->except(['variant','size','icon','loading','disabled','type','href']) }}>
    @if($loading)
        <svg class="ic animate-spin" style="width:14px;height:14px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5" fill="none" stroke-dasharray="50" stroke-dashoffset="20"/></svg>
    @elseif($icon)
        <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24">{!! $icon !!}</svg>
    @endif
    {{ $slot }}
</button>
@endif
