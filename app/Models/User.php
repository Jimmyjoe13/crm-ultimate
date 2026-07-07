<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_SALES = 'commercial';

    // `role` et `manager_id` sont volontairement HORS de $fillable : ce sont des
    // champs sensibles (escalade de privilège). Ils ne doivent jamais être assignés
    // en masse depuis une requête HTTP (`$request->all()`). Pour les assigner depuis
    // du code interne de confiance (seeders, tests, administration), utiliser
    // User::createWithRole() ou un forceFill explicite.
    protected $fillable = [
        'name',
        'email',
        'password',
        'emelia_replies_last_seen',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'emelia_replies_last_seen' => 'datetime',
        ];
    }

    /**
     * Crée un utilisateur en autorisant explicitement l'assignation des champs
     * sensibles `role` et `manager_id` (hors $fillable). Réservé au code interne
     * de confiance (seeders, tests, provisioning admin) — JAMAIS alimenté par une
     * requête HTTP non filtrée.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithRole(array $attributes): self
    {
        $user = new self;
        $user->forceFill($attributes);
        $user->save();

        return $user;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Commerciaux rattachés à ce manager (users.manager_id = id).
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /**
     * Liste des owner_id que cet utilisateur a le droit de voir/modifier (cloisonnement des données).
     *
     * - admin       : null  → accès total, aucun filtrage.
     * - manager     : son propre id + ceux des commerciaux dont il est le manager.
     * - commercial  : uniquement son propre id.
     *
     * @return array<int>|null null = aucun filtre (voit tout)
     */
    public function accessibleOwnerIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        if ($this->isManager()) {
            // Son équipe (commerciaux rattachés) + lui-même.
            return $this->subordinates()->pluck('id')->push($this->id)->unique()->values()->all();
        }

        // Commercial : strictement ses propres enregistrements.
        return [$this->id];
    }
}
