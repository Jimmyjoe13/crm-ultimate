<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLifecycle;
use App\Models\Concerns\ScopesToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Company extends Model
{
    use Auditable;
    use HasFactory;
    use HasLifecycle;
    use ScopesToOwner;
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();
        $flush = fn () => Cache::tags(['companies.index'])->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'name',
        'domain',
        'industry',
        'phone',
        'website',
        'city',
        'country',
        'lifecycle_stage',
        'lead_status',
        'owner_id',
        'custom_values',
    ];

    protected function casts(): array
    {
        return [
            'custom_values' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_company')
            ->withPivot('role', 'is_primary')
            ->withTimestamps();
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class, 'deal_company')
            ->withPivot('role', 'is_primary')
            ->withTimestamps();
    }
}
