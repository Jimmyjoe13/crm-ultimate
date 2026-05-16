<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasLifecycle
{
    public const LIFECYCLE_STAGES = [
        'lead' => 'Lead',
        'mql' => 'MQL',
        'sql' => 'SQL',
        'opportunity' => 'Opportunité',
        'customer' => 'Client',
        'evangelist' => 'Évangéliste',
        'other' => 'Autre',
    ];

    public const LEAD_STATUSES = [
        'new' => 'Nouveau',
        'open' => 'Ouvert',
        'in_progress' => 'En cours',
        'connected' => 'Connecté',
        'unqualified' => 'Non qualifié',
        'bad_fit' => 'Hors cible',
    ];

    public function scopeLifecycle(Builder $query, string $stage): Builder
    {
        return $query->where('lifecycle_stage', $stage);
    }

    public function scopeLeadStatus(Builder $query, string $status): Builder
    {
        return $query->where('lead_status', $status);
    }
}
