@props([
    'entity'     => null,
    'entityType' => '',
    'layout'     => 'stacked',
])

@php
    $customFields = \App\Support\CustomFieldRenderer::forEntity($entityType);
    $values = $entity?->custom_values ?? [];
@endphp

@if($customFields->isNotEmpty())
<div class="mono-label mt-6 mb-3">Champs personnalisés</div>
@if($layout === 'stacked')
<div class="flex flex-col gap-4">
    @foreach($customFields as $field)
    @php $raw = $values[$field->key] ?? null; @endphp
    @if(!is_null($raw) && $raw !== '')
    <div>
        <div class="text-[10px] text-tertiary font-mono uppercase tracking-wider">{{ $field->label }}</div>
        <div class="text-[13px] text-primary mt-0.5 whitespace-pre-wrap leading-relaxed">{{ \App\Support\CustomFieldRenderer::displayValue($field, $raw) }}</div>
    </div>
    @endif
    @endforeach
</div>
@else
<div class="flex flex-col gap-2.5 text-[13px]">
    @foreach($customFields as $field)
    @php $raw = $values[$field->key] ?? null; @endphp
    <div class="flex justify-between">
        <span class="text-tertiary">{{ $field->label }}</span>
        <span class="font-mono text-[11.5px]">{{ \App\Support\CustomFieldRenderer::displayValue($field, $raw) }}</span>
    </div>
    @endforeach
</div>
@endif
@endif
