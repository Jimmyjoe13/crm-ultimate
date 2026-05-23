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
        <select name="{{ $name }}" class="select-arrow">
            <option value="0" @selected(!$value)>Non</option>
            <option value="1" @selected($value)>Oui</option>
        </select>
    @elseif($type === 'select')
        <select name="{{ $name }}" class="select-arrow" @if($required) required @endif>
            <option value="">Choisir…</option>
            @foreach((array) $options as $opt)
            <option value="{{ $opt }}" @selected($value === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
    @elseif($type === 'date')
        <input type="text" name="{{ $name }}" x-datepicker value="{{ $value }}" @if($required) required @endif placeholder="Sélectionnez une date...">
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
