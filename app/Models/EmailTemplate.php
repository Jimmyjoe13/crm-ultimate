<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopesToOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use Auditable;
    use HasFactory;
    use ScopesToOwner;
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'subject',
        'body',
        'category',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Modèles utilisables par $user : ceux de son périmètre owner OU partagés (is_shared).
     * - admin : voit tout.
     * - manager/commercial : son périmètre + tous les partagés.
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $ownerIds = $user->accessibleOwnerIds();

        // admin → accès total.
        if ($ownerIds === null) {
            return $query;
        }

        return $query->where(function ($q) use ($ownerIds) {
            $q->whereIn($this->getTable().'.owner_id', $ownerIds)
                ->orWhere($this->getTable().'.is_shared', true);
        });
    }
}
