<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_type',
        'name',
        'filters',
        'sort',
        'columns',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sort' => 'array',
            'columns' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
