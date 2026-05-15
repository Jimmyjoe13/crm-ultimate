<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasFactory;

    public const TYPE_NOTE = 'note';

    public const TYPE_TASK = 'task';

    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    protected $fillable = [
        'type',
        'title',
        'body',
        'status',
        'due_at',
        'completed_at',
        'subject_type',
        'subject_id',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
