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

class Contact extends Model
{
    use Auditable;
    use HasFactory;
    use HasLifecycle;
    use ScopesToOwner;
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();
        $flush = fn() => Cache::tags(['contacts.index'])->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'emelia_contact_id',
        'emelia_campaign_id',
        'emelia_campaign_name',
        'phone',
        'job_title',
        'lifecycle_stage',
        'lead_status',
        'blacklisted_at',
        'blacklist_reason',
        'owner_id',
        'custom_values',
        'ai_score',
        'ai_score_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_values'       => 'array',
            'blacklisted_at'      => 'datetime',
            'ai_score_updated_at' => 'datetime',
        ];
    }

    public function scopeBlacklisted($query): mixed
    {
        return $query->whereNotNull('blacklisted_at');
    }

    public function scopeContactable($query): mixed
    {
        return $query->whereNull('blacklisted_at');
    }

    /**
     * Marque le contact en blacklist. Idempotent : retourne false si déjà blacklisté.
     */
    public function blacklist(string $reason): bool
    {
        if ($this->blacklisted_at !== null) {
            return false;
        }
        $this->forceFill(['blacklisted_at' => now(), 'blacklist_reason' => $reason])->save();
        return true;
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'contact_company')
            ->withPivot('role', 'is_primary')
            ->withTimestamps();
    }

    public function primaryCompany(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'contact_company')
            ->withPivot('role', 'is_primary')
            ->withTimestamps()
            ->wherePivot('is_primary', true);
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class, 'deal_contact')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function emeliaCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(EmeliaCampaign::class, 'contact_emelia_campaign')
            ->withPivot(['emelia_contact_id', 'status', 'first_event_at', 'last_event_at'])
            ->withTimestamps();
    }
}
