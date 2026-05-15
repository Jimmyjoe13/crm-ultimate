<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function record(string $event, Model $model, array $oldValues, array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $this->onlyRelevantValues($oldValues),
            'new_values' => $this->onlyRelevantValues($newValues),
            'created_at' => now(),
        ]);
    }

    private function onlyRelevantValues(array $values): array
    {
        return collect($values)
            ->except(['created_at', 'updated_at', 'password'])
            ->all();
    }
}
