<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cloisonnement des données par propriétaire (owner_id).
 *
 * À utiliser sur tout modèle possédant une colonne `owner_id`.
 * Le scope `visibleTo` restreint la requête aux enregistrements que l'utilisateur
 * a le droit de voir/modifier selon son rôle (voir User::accessibleOwnerIds()).
 */
trait ScopesToOwner
{
    /**
     * Restreint la requête aux enregistrements accessibles à $user.
     *
     * - admin       : aucun filtre (voit tout).
     * - manager     : owner_id dans (lui + son équipe).
     * - commercial  : owner_id = son id.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        // Pas d'utilisateur résolu → on ne renvoie rien par sécurité (fail-closed).
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $ownerIds = $user->accessibleOwnerIds();

        // null = admin → accès total, pas de filtrage.
        if ($ownerIds === null) {
            return $query;
        }

        return $query->whereIn($this->getTable().'.owner_id', $ownerIds);
    }
}
