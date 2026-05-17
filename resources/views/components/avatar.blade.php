@props([
    'name'     => '',
    'size'     => '',    // '', 'sm', 'lg'
    'square'   => false, // border-radius: 6px via .sq
    'initials' => null,  // override manuel
    'color'    => null,  // override manuel (c1..c5)
])

@php
    $i   = $initials ?? \App\Helpers\Avatar::initials($name ?: '?');
    $c   = $color    ?? \App\Helpers\Avatar::color($name ?: '');
    $cls = trim('av ' . ($size ? "$size " : '') . ($square ? 'sq ' : '') . $c);
@endphp
<span {{ $attributes->merge(['class' => $cls]) }}>{{ $i }}</span>
