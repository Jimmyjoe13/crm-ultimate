@props([
    'action',
    'search' => null,
    'searchName' => 'search',
    'placeholder' => 'Rechercher…',
    'filters' => [],   // [ ['name'=>, 'label'=>, 'options'=>[val=>label], 'value'=>], ... ]
    'preserve' => [],  // ['sort'=>'...', 'dir'=>'...'] réinjectés en hidden
])

@php
    // Un filtre est « actif » si la recherche est remplie ou si l'un des selects optionnels
    // a une valeur. Les filtres « required » ont toujours une valeur → exclus du calcul.
    $hasActive = filled($search);
    foreach ($filters as $f) {
        if (! ($f['required'] ?? false) && filled($f['value'] ?? null)) {
            $hasActive = true;
        }
    }
@endphp

<div class="px-7 pb-3">
    <form method="GET" action="{{ $action }}" x-data class="flex items-center gap-2 flex-wrap">
        @foreach($preserve as $k => $v)
            @if(filled($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach

        <input type="text" name="{{ $searchName }}" value="{{ $search }}" placeholder="{{ $placeholder }}"
               class="field" style="padding:6px 10px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--surface);color:var(--text);min-width:220px;">

        @foreach($filters as $f)
        <select name="{{ $f['name'] }}" @change="$el.form.submit()" class="select-arrow" style="font-size:13px;height:auto;width:auto;padding:6px 28px 6px 10px;">
            @unless($f['required'] ?? false)
                <option value="">{{ $f['label'] }} : tous</option>
            @endunless
            @foreach($f['options'] as $val => $label)
                <option value="{{ $val }}" @selected((string) ($f['value'] ?? '') === (string) $val)>{{ $label }}</option>
            @endforeach
        </select>
        @endforeach

        {{ $slot }}

        <x-button type="submit" size="sm">Filtrer</x-button>
        @if($hasActive)
            <a href="{{ $action }}" class="btn sm ghost">Réinitialiser</a>
        @endif
    </form>
</div>
