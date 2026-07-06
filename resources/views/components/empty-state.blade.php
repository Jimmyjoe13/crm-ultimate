@props([
    'icon' => null,      // SVG inline (chemin <path>…) ; un défaut est fourni
    'title' => 'Rien à afficher',
    'subtitle' => null,
    'ctaLabel' => null,
    'ctaHref' => null,
])

<div class="flex flex-col items-center justify-center text-center py-16 px-6">
    <div class="mb-3" style="color: var(--text3);">
        <svg class="ic" style="width:40px;height:40px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            @if($icon)
                {!! $icon !!}
            @else
                <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            @endif
        </svg>
    </div>
    <div class="text-sm font-medium text-primary">{{ $title }}</div>
    @if($subtitle)
        <div class="text-[12.5px] text-tertiary mt-1 max-w-sm">{{ $subtitle }}</div>
    @endif
    @if($ctaLabel && $ctaHref)
        <a href="{{ $ctaHref }}" class="btn sm mt-4">{{ $ctaLabel }}</a>
    @endif
    {{ $slot }}
</div>
