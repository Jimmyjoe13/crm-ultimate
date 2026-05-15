<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => app(AuditLogger::class)->record('created', $model, [], $model->getAttributes()));

        static::updated(function ($model): void {
            app(AuditLogger::class)->record('updated', $model, $model->getOriginal(), $model->getChanges());
        });

        static::deleted(fn ($model) => app(AuditLogger::class)->record('deleted', $model, $model->getOriginal(), []));
    }
}
