<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmeliaCampaign extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'emelia_id',
        'name',
        'status',
        'client_name',
        'objective',
        'tags',
        'owner_id',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tags'           => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_emelia_campaign')
            ->withPivot(['emelia_contact_id', 'status', 'first_event_at', 'last_event_at'])
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'emelia_campaign_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
