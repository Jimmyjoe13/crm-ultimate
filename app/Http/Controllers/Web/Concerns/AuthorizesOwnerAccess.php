<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Cloisonnement par owner pour les contrôleurs Web utilisant le route-model binding.
 *
 * Le binding charge l'enregistrement avant le contrôleur, sans tenir compte du périmètre.
 * Cette méthode vérifie a posteriori que l'utilisateur courant a le droit de le voir,
 * et renvoie 404 sinon (ne leak pas l'existence).
 */
trait AuthorizesOwnerAccess
{
    protected function ensureVisible(Model $model, ?User $user): void
    {
        // Pas d'utilisateur → fail-closed.
        if (! $user) {
            abort(404);
        }

        $ownerIds = $user->accessibleOwnerIds();

        // null = admin → accès total.
        if ($ownerIds === null) {
            return;
        }

        // owner_id absent du périmètre → 404.
        if (! in_array((int) $model->getAttribute('owner_id'), $ownerIds, true)) {
            abort(404);
        }
    }

    /**
     * Liste des propriétaires (users) visibles par $user, pour alimenter un filtre.
     * - admin   : tous les utilisateurs.
     * - manager : lui + son équipe.
     * - commercial : lui seul.
     *
     * @return Collection<int, User>
     */
    protected function visibleOwners(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $ownerIds = $user->accessibleOwnerIds();

        return User::query()
            ->when($ownerIds !== null, fn ($q) => $q->whereIn('id', $ownerIds))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
