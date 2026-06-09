# Intégration Emelia — Spec fonctionnelle

> **Statut** : ✅ v2.3–v3.5 déployés en production

## Architecture globale

```
Emelia ──POST──▶ n8n webhook ──POST──▶ CRM /api/webhooks/emelia
                n8n webhook ──POST──▶ CRM /api/webhooks/emelia-intent

CRM ──GraphQL──▶ Emelia API (ajout/retrait contacts)
CRM ──REST─────▶ Emelia API (liste campagnes, events)
```

## API Emelia — Points critiques

- `POST /campaigns/{id}/contacts` **n'existe pas** (404 Express)
- Utiliser la **GraphQL mutation** : `addContactToCampaignHook(id: ID!, contact: JSON!)`
- La mutation n'est **pas idempotente** → gérer "already included"
- Timestamps Emelia en **millisecondes** → `Carbon::createFromTimestampMs()`
- Events Emelia en **UPPERCASE** sans préfixe (`SENT`, `OPENED`, `FIRST_OPEN`…)

## Campagnes connues

| Nom | UUID |
|-----|------|
| acquisition-agence-marketing | `69eb1cca5033df0a8663a88e` |
| conseiller-consultant-acquisition | `69ebb7281762b60a8169f625` |

## Workflow n8n

- **URL** : https://n8n.nana-intelligence.fr/workflow/q4GXMH5Qzjz9H6AZ
- **ID** : `q4GXMH5Qzjz9H6AZ`
- **Statut** : `active: true`

```
Emelia Trigger1 (camp. 69eb1cca) ─┐
                                   ├──▶ Normalize Event ──▶ Forward to CRM
Emelia Trigger  (camp. 69ebb728) ─┘
```

### Normalize Event — Mapping des champs

Le payload Emelia réel est **structurellement différent** de ce qui était supposé :

```json
{
  "event": "OPENED",
  "campaign": "conseiller-consultant-acquisition",
  "sender": "jonathan@first-lead.fr",
  "contact": {
    "firstName": "Magali",
    "lastName": "Fernando",
    "email": "magali.fernando@goetic.fr",
    "company": "GOETIC"
  },
  "date": "2026-05-23T19:12:39.046Z",
  "step": 3
}
```

**Mapping corrigé :**
| Champ CRM | Chemin dans payload |
|-----------|-------------------|
| email | `d.contact?.email \|\| d.email` |
| event_id | Généré : `${email}_${event}_${date}` |
| campaign_id | Mapping nom → UUID via `campaignMap` |
| campaign_name | `d.campaign` (nom string) |
| preview | `d.preview \|\| d.previewText \|\| null` |

## Webhook standard (`/api/webhooks/emelia`)

**Fichier** : `app/Http/Controllers/Api/EmeliaWebhookController.php`

- HMAC SHA256 optionnel
- Idempotence via `external_id`
- Contact léger auto sur orphelin (si email vide)
- 6 types Activity : `email_sent/opened/clicked/replied/bounced/unsubscribed`

## Webhook Intent (`/api/webhooks/emelia-intent`)

**Fichier** : `app/Http/Controllers/Api/EmeliaIntentWebhookController.php`

- HMAC optionnel, idempotence `event_id`
- 4 intents :

| Intent | Action CRM |
|--------|-----------|
| `stop` | Blacklist contact + job `RemoveFromEmeliaCampaign` |
| `interested` | Tâche urgente (4h) + bump lifecycle → sql |
| `not_interested` | Note + bump lifecycle → lead |
| `out_of_office` | Tâche différée (7j) |

## Sync CRM → Emelia

### Commandes Artisan

```bash
# Sync une campagne
php artisan emelia:sync-campaign {campaign_id} {--dry-run} {--only-linked}

# Sync toutes les campagnes (schedule quotidien minuit)
php artisan emelia:sync-all-campaigns --only-linked

# Polling events Emelia → activités
php artisan emelia:sync-contact-events {--only-linked} {--contact=} {--dry-run}
```

### Schedule
```
emelia:sync-all-campaigns --only-linked  →  minuit daily, withoutOverlapping()
```

### Résolution emelia_contact_id
- `EmeliaSyncContactEvents` résout l'ID via `getContactByEmail()` avant polling
- 1016 contacts ont désormais leur `emelia_contact_id` résolu (contre 138 avant)

## UI Fiche contact

- **Onglets Tout / Emelia** au-dessus du fil d'activité
- **Badge `sync`/`live`** sur les activités Emelia
- **Bouton "Sync"** → `POST /contacts/{contact}/emelia/sync`
- **Panel Emelia** : stats (sent/opened/clicked/replied), campagnes liées

## Badge admin replies non lues

- Badge rouge sur l'icône Contacts dans la sidebar
- Compte les `email_replied` créées après `emelia_replies_last_seen`
- `POST /notifications/emelia-replies/seen` au clic sur le lien Contacts
