@props(['route', 'active' => false, 'tooltip' => ''])

<a href="{{ route($route) }}" class="rail-ic {{ $active ? 'on' : '' }}">
    {{ $slot }}
    @if($tooltip)
    <span class="tt">{{ $tooltip }}</span>
    @endif
</a>
