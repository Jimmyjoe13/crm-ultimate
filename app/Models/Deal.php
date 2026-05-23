<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Deal extends Model
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();
        $flush = fn() => Cache::tags(['deals.index'])->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'name',
        'amount',
        'currency',
        'close_date',
        'status',
        'pipeline_id',
        'pipeline_stage_id',
        'owner_id',
        'custom_values',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'close_date' => 'date',
            'custom_values' => 'array',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'deal_company')
            ->withPivot('role', 'is_primary')
            ->withTimestamps();
    }

    public function primaryCompany(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'deal_company')
            ->withPivot('role', 'is_primary')
            ->withTimestamps()
            ->wherePivot('is_primary', true);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'deal_contact')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function primaryContact(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'deal_contact')
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivot('role', 'primary');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
