<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use Auditable;
    use HasFactory;
    use HasLifecycle;
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
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
}
