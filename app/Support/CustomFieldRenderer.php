<?php

namespace App\Support;

use App\Models\CustomField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CustomFieldRenderer
{
    public static function forEntity(string $type): Collection
    {
        return Cache::remember('custom_fields.' . $type, 60, function () use ($type) {
            return CustomField::where('entity_type', $type)
                ->orderBy('position')
                ->orderBy('label')
                ->get();
        });
    }

    public static function displayValue(CustomField $field, mixed $rawValue): string
    {
        if ($rawValue === null || $rawValue === '') {
            return '—';
        }

        return match ($field->field_type) {
            'boolean' => (is_bool($rawValue) ? $rawValue : in_array(strtolower((string) $rawValue), ['1', 'true', 'oui', 'yes', 'on'], true)) ? 'Oui' : 'Non',
            'date'    => $rawValue ? date('d/m/Y', strtotime($rawValue)) : '—',
            'select'  => is_array($rawValue) ? implode(', ', $rawValue) : (string) $rawValue,
            'number'  => is_numeric($rawValue) ? number_format((float) $rawValue, 2, ',', "\xc2\xa0") : (string) $rawValue,
            default   => (string) $rawValue,
        };
    }
}
