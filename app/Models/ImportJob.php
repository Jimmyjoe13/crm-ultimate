<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_type',
        'filename',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'duplicates_skipped',
        'mapping',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'mapping' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
