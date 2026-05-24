<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class PipelineStage extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();
        $flush = function () {
            Cache::forget('pipeline.stages.active');
            Cache::forget('pipeline.stages.all');
            Cache::forget('pipeline.stage.won');
            Cache::forget('pipeline.stage.lost');
        };
        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'pipeline_id',
        'name',
        'position',
        'probability',
        'is_won',
        'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'is_won'       => 'bool',
            'is_lost'      => 'bool',
            'probability'  => 'int',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }
}
