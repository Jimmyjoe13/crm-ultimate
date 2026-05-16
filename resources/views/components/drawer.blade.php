@props([
    'id' => 'drawer',
    'title' => '',
    'width' => '720px',
])

<div x-data="{ open: true }"
     x-show="open"
     x-transition:enter="transition ease-out duration-220"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-180"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="open = false"
     id="{{ $id }}"
     class="fixed inset-0 z-40"
     style="display:none;">

    {{-- Backdrop --}}
    <div class="absolute inset-0 drawer-backdrop" @click="open = false"></div>

    {{-- Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-220 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-180 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="absolute top-0 right-0 h-full flex flex-col bg-surface overflow-hidden"
         style="width: {{ $width }}; box-shadow: var(--shadow-pop); border-left: 1px solid var(--border);">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b" style="border-color: var(--border);">
            <div class="flex items-center gap-3">
                @if(isset($header))
                    {{ $header }}
                @else
                    <h3 style="font-size:18px; margin:0;">{{ $title }}</h3>
                @endif
            </div>
            <button @click="open = false" class="btn icon ghost" aria-label="Fermer">
                <svg class="ic" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-5 py-4">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if(isset($footer))
        <div class="px-5 py-3 border-t flex items-center justify-end gap-2" style="border-color: var(--border);">
            {{ $footer }}
        </div>
        @endif
    </div>
</div>
