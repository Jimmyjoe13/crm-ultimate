# Intégrations tierces — Spec fonctionnelle

> **Statut** : v4.0 planifié, non démarré

## Apps proposées

| Priorité | App | Valeur CRM | Effort | Complexité |
|----------|-----|-----------|--------|------------|
| ⭐⭐⭐ | Google Calendar | Réunions liées aux deals/contacts | ~4h | OAuth2 + API Google |
| ⭐⭐⭐ | Gmail | Sync emails sur fiches contacts | ~5h | OAuth2 + Gmail API |
| ⭐⭐ | Outlook / Microsoft 365 | Alternative Microsoft | ~3h | MSAL OAuth2 |
| ⭐⭐ | Calendly | Lien prise de RDV → activité auto | ~2h | Webhook entrant |
| ⭐ | Stripe | CA réel vs CA estimé | ~3h | API REST Stripe |
| ⭐ | Zapier / Make | Webhooks sortants génériques | ~2h | Endpoint POST |

## Phase 1 — Google Calendar

### Fonctionnalités
- Connecter compte Google depuis `/settings/integrations`
- Activités `call`/`meeting` → événement Google Calendar
- Événements Google avec contact CRM → timeline du contact
- Bouton "Planifier une réunion" sur fiche deal

### Architecture
```
users
  └─ google_access_token  (text, encrypted)
  └─ google_refresh_token (text, encrypted)
  └─ google_token_expires_at (timestamp)

GoogleCalendarService
  ├── redirectToGoogle()
  ├── handleCallback()
  ├── refreshTokenIfNeeded()
  ├── createEvent(User, title, startAt, endAt, description, attendeeEmail?)
  └── listUpcomingEvents(User, Contact)

GoogleCalendarController
  ├── GET  /settings/integrations/google/connect
  ├── GET  /settings/integrations/google/callback
  └── DELETE /settings/integrations/google
```

### Fichiers à créer
- Migration : tokens Google sur `users`
- `app/Services/GoogleCalendarService.php`
- `app/Http/Controllers/Web/Settings/IntegrationController.php`
- `resources/views/pages/settings/integrations.blade.php` (UI)
- `config/services.php` : clés Google
- `composer require google/apiclient:^2.0`

### Variables .env
```
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://crm.nana-intelligence.fr/settings/integrations/google/callback
```

### Prérequis
1. Google Cloud Console : projet + Calendar API + Gmail API
2. Credentials OAuth2 (Web application)
3. URI de redirection autorisée

## Phase 2 — Gmail

### Fonctionnalités
- OAuth2 Google étendu (scope `gmail.readonly`)
- Commande `gmail:sync-contact {contactId}` → emails → activités `email_sent`/`email_replied`
- Bouton "Sync Gmail" sur fiche contact

### Architecture
```
GmailService
  ├── searchByEmail(User, email, maxResults=20)
  └── messageToActivity(User, Contact, message) → Activity|null

Commande : gmail:sync-contact {contact_id} {--limit=20} {--dry-run}
Job : SyncGmailContactJob (ShouldQueue, timeout=120)
```

## Phase 3 — Calendly

### Fonctionnalités
- Lien "Prendre RDV" sur fiche contact → Calendly avec email pré-rempli
- Webhook `invitee.created` → activité `meeting`
- Badge "RDV planifié" sur fiche contact

### Architecture
```
POST /api/webhooks/calendly → CalendlyWebhookController
  ├── Vérifie signature HMAC
  ├── Cherche contact par invitee.email
  └── Crée Activity(type='meeting', due_date=event_start)
```

### Config
```
CALENDLY_WEBHOOK_SECRET=...
```

### Prérequis
- Compte Calendly Pro minimum (webhooks)
- Subscription webhook sur `https://crm.nana-intelligence.fr/api/webhooks/calendly`

## Phase 4 — Webhooks sortants (Zapier / Make)

### Fonctionnalités
- Admin configure des webhooks sortants (URL + événements)
- Événements : `deal.won`, `contact.created`, `email.replied`, `ai.alert`
- POST JSON à chaque événement

### Architecture
```
outgoing_webhooks
  ├── id, user_id, url, events (json), secret, is_active

OutgoingWebhookService::dispatch(string $event, array $payload)
  → OutgoingWebhook::where('is_active', true)
      ->whereJsonContains('events', $event)->get()
  → foreach → Http::post($webhook->url, $payload)
```

## Ordre d'implémentation

| # | Feature | Effort | Session |
|---|---------|--------|---------|
| 1 | Google Calendar | ~4h | v4.1 |
| 2 | Gmail sync | ~5h | v4.2 |
| 3 | Calendly webhook | ~2h | v4.3 |
| 4 | Webhooks sortants | ~3h | v4.4 |
| 5 | Outlook / MS365 | ~3h | v4.5 |

> **Note** : Google Calendar et Gmail partagent le même OAuth2 → implémenter Calendar d'abord.
