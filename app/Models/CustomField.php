<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CustomField extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();
        $bust = fn (self $m) => Cache::forget('custom_fields.' . $m->entity_type);
        static::saved($bust);
        static::deleted($bust);
    }

    protected $fillable = [
        'entity_type',
        'key',
        'label',
        'field_type',
        'options',
        'is_required',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'bool',
        ];
    }
}
