<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsoleRun extends Model
{
    protected $fillable = [
        'user_id',
        'command_key',
        'command_label',
        'status',
        'output',
        'exit_code',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function durationMs(): ?int
    {
        if (!$this->started_at || !$this->finished_at) {
            return null;
        }
        return (int) $this->started_at->diffInMilliseconds($this->finished_at);
    }
}
