@props([
    'entityType' => '',
    'values'     => [],
])

@php
    $customFields = \App\Support\CustomFieldRenderer::forEntity($entityType);
@endphp

@if($customFields->isNotEmpty())
<div class="col-span-2">
    <div class="mono-label mb-3 mt-2">Champs personnalisés</div>
    <div class="grid grid-cols-2 gap-4">
        @foreach($customFields as $field)
        <x-form-field
            :label="$field->label"
            :name="'custom_values[' . $field->key . ']'"
            :type="$field->field_type"
            :value="$values[$field->key] ?? null"
            :options="$field->options ?? []"
            :required="$field->is_required"
        />
        @endforeach
    </div>
</div>
@endif
