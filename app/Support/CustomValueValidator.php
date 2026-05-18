<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class CustomValueValidator
{
    /**
     * Returns Laravel validation rules for custom_values of a given entity type.
     * Merge these into the controller's validate() call.
     */
    public static function validationRules(string $entityType): array
    {
        $fields = CustomFieldRenderer::forEntity($entityType);
        $rules  = ['custom_values' => ['nullable', 'array']];

        foreach ($fields as $field) {
            $base = $field->is_required ? ['required'] : ['nullable'];

            $typeRules = match ($field->field_type) {
                'number'  => ['numeric'],
                'date'    => ['date'],
                'boolean' => ['in:0,1'],
                'select'  => $field->options ? [Rule::in($field->options)] : ['string'],
                default   => ['string', 'max:1000'],
            };

            $rules["custom_values.{$field->key}"] = array_merge($base, $typeRules);
        }

        return $rules;
    }

    /**
     * Casts raw form values to proper PHP types based on field definitions.
     * Unknown keys are silently dropped.
     */
    public static function cast(string $entityType, array $values): array
    {
        $fields = CustomFieldRenderer::forEntity($entityType)->keyBy('key');
        $result = [];

        foreach ($values as $key => $raw) {
            $field = $fields->get($key);
            if ($field === null) {
                continue;
            }

            if ($raw === null || $raw === '') {
                $result[$key] = null;
                continue;
            }

            $result[$key] = match ($field->field_type) {
                'number'  => (float) $raw,
                'date'    => date('Y-m-d', strtotime((string) $raw)),
                'boolean' => (bool) (int) $raw,
                default   => trim((string) $raw),
            };
        }

        return $result;
    }
}
