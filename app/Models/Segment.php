<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segment extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'description',
        'entity_type',
        'rules',
        'created_by',
        'last_count',
        'last_computed_at',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'last_computed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
