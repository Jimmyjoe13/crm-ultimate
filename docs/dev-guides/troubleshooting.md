# Troubleshooting — Bugs connus & solutions

## Bugs résolus

### CSRF dans les tests AJAX Web
**Symptôme** : 419 sur les requêtes POST de test.
**Fix** : `->post($url, ['_token' => 'test'])->withSession(['_token' => 'test'])`

### `assertSee()` avec apostrophes
**Symptôme** : Test échoue malgré le texte présent.
**Fix** : `assertSeeText()` (compare le texte décodé, pas le HTML encodé).

### `assertSame` sur custom_values numériques post-DB
**Symptôme** : `7500.0` devient `int(7500)` après JSON round-trip.
**Fix** : `assertEquals()` au lieu de `assertSame()`.

### Bug cache custom fields à l'import (résolu v2.2)
**Symptôme** : Custom fields mappés silencieusement ignorés en prod, tests verts.
**Cause** : `CustomValueValidator::cast()` lisait un cache Redis stale. `Cache::flush()` dans les tests masquait le problème.
**Fix** : Model events `saved`/`deleted` sur `CustomField` → `Cache::forget()`.

### API Emelia REST `POST /campaigns/{id}/contacts` inexistante (résolu v2.3)
**Symptôme** : 100% des contacts échouaient avec "Cannot POST".
**Cause** : Endpoint REST n'existe pas dans l'API Emelia.
**Fix** : Mutation GraphQL `addContactToCampaignHook(id, contact)`.

### `subject_type` polymorphique doit être un FQCN (résolu v2.3)
**Symptôme** : 500 sur le dashboard après webhooks Emelia.
**Fix** : `'subject_type' => Contact::class` (= `'App\Models\Contact'`).

### Events Emelia en UPPERCASE (résolu v2.3)
**Symptôme** : Webhooks reçus mais aucune Activity créée (422).
**Fix** : `match(strtoupper($request->input('event', '')))`.

### Contacts sans `emelia_contact_id` invisibles au polling (résolu v2.5)
**Symptôme** : 924/1062 contacts ignorés par `sync-contact-events`.
**Cause** : Chemin "already included" ne sauvegardait pas `emelia_contact_id`.
**Fix** : Résolution via `getContactByEmail()` avant polling.

### Timestamps Emelia en millisecondes (résolu v2.5)
**Symptôme** : `getContactEvents()` ne créait aucune activité.
**Cause** : `Carbon::parse(1778837804955)` → date en l'an 57000.
**Fix** : `Carbon::createFromTimestampMs((int) $ms)`.

### `attachContact` n'auto-linkait pas la company (résolu v2.5)
**Symptôme** : Company du contact non liée au deal via "Associer un contact".
**Fix** : Ajout de l'auto-association company dans `DealController::attachContact()`.

### Webhook Emelia race condition (résolu v3.4)
**Symptôme** : `UniqueConstraintViolationException` quand webhook arrivait juste après sync.
**Fix** : try/catch + `updateExistingPivot()` dans `EmeliaWebhookController::attach()`.

### Vite manifest permissions (résolu 2026-05-23)
**Symptôme** : 500 sur le manifest Vite.
**Fix** : `chmod -R 755 /var/www/html/public/build` ajouté au `CMD` de `Dockerfile.prod`.

### `route:cache` et closure dans `routes/api.php`
**Symptôme** : `Route::get('/', fn() => ...)` empêche le cache de routes.
**Fix** : Déplacé dans `Api\InfoController::index()`. Impact actuel : nul (~1-2ms/requête).

### Normalize Event n8n produit un payload vide (résolu 2026-05-23)
**Symptôme** : Tous les champs sauf `event` et `date` étaient `undefined`.
**Cause** : Le code lisait `d.email` (inexistant) au lieu de `d.contact.email`, `d.campaignId` (inexistant) au lieu de `d.campaign` (nom).
**Fix** : Voir [docs/features/emelia-integration.md](./features/emelia-integration.md) pour le code corrigé.

## Points d'attention permanents

- **Ne jamais SCP** les fichiers du périmètre de l'autre assistant.
- **Toujours rebuild Docker** après modification de code PHP/Blade.
- **Toujours vider les caches** après déploiement (view, config, cache, route).
- **Emelia webhook** : non disponible sur le plan actuel → utiliser bouton "Sync" + polling daily.
