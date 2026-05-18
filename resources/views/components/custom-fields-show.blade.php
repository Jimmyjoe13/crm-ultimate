@props([
    'entity'     => null,
    'entityType' => '',
])

@php
    $customFields = \App\Support\CustomFieldRenderer::forEntity($entityType);
    $values = $entity?->custom_values ?? [];
@endphp

@if($customFields->isNotEmpty())
<div class="mono-label mt-6 mb-3">Champs personnalisés</div>
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
