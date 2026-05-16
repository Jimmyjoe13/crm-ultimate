<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AssociationAuditor
{
    public static function recordAttach(Model $parent, string $relation, int $childId, string $childType, array $pivot = []): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'event' => 'associated',
            'auditable_type' => $parent::class,
            'auditable_id' => $parent->id,
            'old_values' => [],
            'new_values' => [
                'relation' => $relation,
                'child_type' => $childType,
                'child_id' => $childId,
                'pivot' => $pivot,
            ],
        ]);
    }

    public static function recordDetach(Model $parent, string $relation, int $childId, string $childType): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'event' => 'dissociated',
            'auditable_type' => $parent::class,
            'auditable_id' => $parent->id,
            'old_values' => [
                'relation' => $relation,
                'child_type' => $childType,
                'child_id' => $childId,
            ],
            'new_values' => [],
        ]);
    }
}
