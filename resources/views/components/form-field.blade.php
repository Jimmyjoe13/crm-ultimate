@props([
    'label'    => '',
    'name'     => '',
    'type'     => 'text',
    'value'    => null,
    'options'  => [],
    'required' => false,
    'help'     => null,
])

<div class="field">
    @if($label)
    <label>{{ $label }}@if($required) *@endif</label>
    @endif

    @if($type === 'boolean')
        @php $boolTrue = ($value === true || $value === 1 || $value === '1' || $value === 'oui' || $value === 'yes'); @endphp
        <select name="{{ $name }}" class="select-arrow">
            <option value="0" @selected(!$boolTrue)>Non</option>
            <option value="1" @selected($boolTrue)>Oui</option>
        </select>
    @elseif($type === 'select')
        <select name="{{ $name }}" class="select-arrow" @if($required) required @endif>
            <option value="">Choisir…</option>
            @foreach((array) $options as $opt)
            <option value="{{ $opt }}" @selected($value === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
    @elseif($type === 'date')
        <div class="relative flex items-center">
            <input type="text" name="{{ $name }}" x-datepicker value="{{ $value }}" @if($required) required @endif placeholder="Sélectionnez une date..." class="w-full pr-10">
            <span class="absolute right-3 pointer-events-none text-tertiary">
                <svg class="ic sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </span>
        </div>
    @elseif($type === 'number')
        <input type="number" name="{{ $name }}" value="{{ $value }}" step="any" @if($required) required @endif>
    @elseif($type === 'textarea')
        <textarea name="{{ $name }}" rows="3" @if($required) required @endif>{{ $value }}</textarea>
    @else
        <input type="text" name="{{ $name }}" value="{{ $value }}" @if($required) required @endif>
    @endif

    @if($help)
    <div class="text-[11.5px] text-tertiary mt-0.5">{{ $help }}</div>
    @endif
</div>
