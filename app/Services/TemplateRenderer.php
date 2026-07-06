<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;

/**
 * Rendu des modèles d'email : substitution de variables {{cle}} par des valeurs.
 *
 * Substitution de chaîne PURE (pas de Blade, pas d'eval) → aucune exécution de code
 * possible depuis le contenu d'un template (anti-injection).
 */
class TemplateRenderer
{
    /**
     * Remplace les {{cle}} présents dans $text par $vars[cle].
     * Les variables inconnues sont laissées telles quelles (l'utilisateur les repère).
     *
     * @param  array<string, scalar|null>  $vars
     */
    public function render(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z_]+)\s*\}\}/', function ($m) use ($vars) {
            $key = strtolower($m[1]);

            return array_key_exists($key, $vars) ? (string) $vars[$key] : $m[0];
        }, $text);
    }

    /**
     * Variables disponibles par contexte (pour l'aide à la saisie côté UI).
     *
     * @return array<string, array<int, string>>
     */
    public function availableVariables(): array
    {
        return [
            'contact' => ['first_name', 'last_name', 'full_name', 'email', 'phone', 'job_title', 'company', 'owner_name', 'today'],
            'deal' => ['deal_name', 'amount', 'currency', 'stage', 'company', 'contact_name', 'owner_name', 'today'],
        ];
    }

    /**
     * Construit le jeu de variables à partir d'un contact.
     *
     * @return array<string, scalar|null>
     */
    public function contactVars(Contact $contact): array
    {
        $company = $contact->relationLoaded('companies') ? $contact->companies->first() : $contact->companies()->first();

        return [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'job_title' => $contact->job_title,
            'company' => $company?->name,
            'owner_name' => $contact->owner?->name,
            'today' => now()->locale('fr')->isoFormat('D MMMM YYYY'),
        ];
    }

    /**
     * Construit le jeu de variables à partir d'un deal.
     *
     * @return array<string, scalar|null>
     */
    public function dealVars(Deal $deal): array
    {
        $company = $deal->relationLoaded('companies') ? $deal->companies->first() : $deal->companies()->first();
        $contact = $deal->relationLoaded('contacts') ? $deal->contacts->first() : $deal->contacts()->first();

        return [
            'deal_name' => $deal->name,
            'amount' => $deal->amount !== null ? number_format((float) $deal->amount, 0, ',', "\xc2\xa0") : null,
            'currency' => $deal->currency,
            'stage' => $deal->stage?->name,
            'company' => $company?->name,
            'contact_name' => $contact ? trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) : null,
            'owner_name' => $deal->owner?->name,
            'today' => now()->locale('fr')->isoFormat('D MMMM YYYY'),
        ];
    }
}
