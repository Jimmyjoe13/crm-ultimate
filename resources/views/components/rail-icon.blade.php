@props(['route' => null, 'href' => null, 'active' => false, 'tooltip' => ''])

<a href="{{ $href ?? ($route ? route($route) : '#') }}" class="rail-ic {{ $active ? 'on' : '' }}">
    {{ $slot }}
    @if($tooltip)
    <span class="tt">{{ $tooltip }}</span>
    @endif
</a>
