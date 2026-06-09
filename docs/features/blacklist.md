# Blacklist contacts — Spec fonctionnelle

> **Version** : v3.5 (backend) + v3.9 (UI)
> **Statut** : ✅ Déployé en production

## Fonctionnement

Un contact peut être blacklisté pour ne plus recevoir de campagnes email (Emelia). Le blacklistage peut être :
- **Automatique** : via webhook Emelia intent (réponse STOP)
- **Manuel** : via l'interface (à implémenter)

## Backend (Claude Code)

### Migration
```php
// 2026_05_25_000001_add_blacklist_to_contacts.php
Schema::table('contacts', function (Blueprint $table) {
    $table->timestamp('blacklisted_at')->nullable()->index();
    $table->string('blacklist_reason', 255)->nullable();
});
```

### Model Contact
```php
// Scopes
Contact::blacklisted()   // whereNotNull('blacklisted_at')
Contact::contactable()  // whereNull('blacklisted_at')

// Méthode
$contact->blacklist(string $reason = null)
$contact->unblacklist()
$contact->isBlacklisted() : bool
```

### Protections
- `EmeliaSyncCampaign` : refuse d'ajouter un contact blacklisté
- `EmeliaController::addContact()` : vérifie `isBlacklisted()` avant ajout

## UI (Gemini / OWL)

### Fiche contact (`contacts/show.blade.php`)
- Badge `.chip.err` rouge **"Blacklisté"** dans l'en-tête
- Affiché si `$contact->blacklisted_at !== null`
- Si `$contact->blacklist_reason` → affiché en `title` au survol

### Liste contacts (`contacts/index.blade.php`)
- Checkbox "Masquer les blacklistés (N)" dans la toolbar
- **Par défaut : masqués** (`@checked(request('hide_blacklisted', '1') === '1')`)
- Soumission auto au changement (`@change="$el.form.submit()"`)
- Badge `.chip.err.sm` "Blacklisté" inline dans la table
- Paramètres `sort`/`dir` préservés via inputs hidden

## Webhook Emelia Intent

Voir [docs/features/emelia-integration.md](./emelia-integration.md) pour le détail du webhook multi-intent.

### Flux STOP
```
Contact reply STOP → n8n Detect Intent → POST /api/webhooks/emelia-intent
  → EmeliaIntentWebhookController
  → Contact::blacklist('STOP via Emelia reply')
  → RemoveFromEmeliaCampaign::dispatch()
```

## Prochaines étapes

- [ ] Filtrage effectif `hide_blacklisted` dans `ContactController::index()` (scope `contactable()`)
- [ ] Bouton "Blacklist" / "Unblacklist" manuel sur la fiche contact
- [ ] Historique des blacklistages dans la timeline
