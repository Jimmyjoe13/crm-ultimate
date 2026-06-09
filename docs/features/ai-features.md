# Fonctionnalités IA — Spec fonctionnelle

> **Statut** : ✅ v2.8 déployé en production
> **Provider** : OpenRouter API (Claude Haiku 4.5)

## Architecture

```
AiInsightService (logique partagée Web ↔ API)
  ├── summarizeDeal(dealId, fresh)            → brief 4-6 lignes
  ├── nextActionDeal(dealId, fresh)            → JSON {action, rationale, priority}
  ├── scoreDeal(dealId, fresh)                 → JSON {score, trend, reasons, green_flags, red_flags, recommendations}
  ├── summarizeContact(contactId, fresh)       → brief texte (avec tendances + RAG notes)
  ├── summarizeCompany(companyId, fresh)       → brief texte
  ├── dailySuggestions(userId, fresh)          → JSON {suggestions, alerts, priorities} (cache 6h)
  ├── scoreContact(contactId, fresh)           → JSON {score, rationale} (cache 24h)
  ├── batchScoreContacts(contacts)             → batch de 10 contacts / appel LLM
  ├── draftEmail(contactId/dealId, intent)     → JSON {subject, body}
  ├── analyzeSentiment(text)                   → JSON {sentiment, score, summary}
  └── analyzeReports(reportData)               → JSON {insights, alerts, recommendations} (cache 1h)
```

## Parser JSON robuste

`LlmService` envoie `response_format: json_object` pour les endpoints JSON. Le nouveau `parseJsonStrict()` gère :
- Blocs markdown ```json ... ```
- Texte autour du JSON
- Objets imbriqués
- Fallback tableau `[...]`

## Cache adaptatif

| Méthode | TTL si open/actif | TTL si won/lost/figé |
|---------|-------------------|---------------------|
| summarizeDeal | 6h (1j si close <7j) | 24h |
| scoreDeal | 6h (1j si close <7j) | 24h |
| nextActionDeal | 1h | 24h |
| summarizeContact | 12h | — |
| scoreContact | 24h | — |
| dailySuggestions | 6h | — |
| analyzeReports | 1h | — |

`?fresh=1` bypass (admin/manager).

## System prompts spécialisés

Chaque type de tâche a son prompt dédié :
- **score** : analyste commercial senior, scoring exigeant (>70 = top 20%)
- **draft** : rédacteur B2B expert, max 200 mots, objectif = obtenir une réponse
- **sentiment** : psychologue communication commerciale, positif seulement si intérêt explicite
- **suggestions** : directeur commercial, brief du matin, urgent vs important
- **analyze** : analyste CRM, tendances et anomalies

## Contexte enrichi

Le LLM reçoit maintenant des **métriques agrégées SQL** en plus des activités :
- Comparaison 30j vs 30j précédents (hausse/baisse/stable)
- Engagement email sur 7j
- Tendance lisible : "📈 Forte hausse d'activité (+45%) — 💬 8 interactions email cette semaine"

## RAG léger

Recherche full-text dans les notes/activités via index GIN PostgreSQL. Les 3 notes les plus récentes sont injectées dans le contexte.

## Alertes proactives

Job `AiProactiveAlertsJob` toutes les 2h, stocké en Redis :
- Deals qui refroidissent (score <40)
- Deals à clôturer bientôt sans activité (3j)
- Réponses Emelia négatives (< 6h, sentiment < -0.3)
- Tâches overdue (> 24h)
- Pipeline stagnant (0 deal gagné cette semaine)

Endpoint `GET /web/ai/proactive-alerts` pour le front.

## Batching scoring

`batchScoreContacts()` : 1 appel LLM pour 10 contacts. Gain : 10× plus rapide pour le batch nightly.
Commande : `ai:score-contacts --limit=50` (5 appels au lieu de 50).

## Surfaces IA dans l'UI

| Surface | Composant | Emplacement |
|---------|-----------|-------------|
| Synthèse deal | `<x-ai-insight-card>` | Fiche deal |
| Synthèse contact | `<x-ai-insight-card>` | Fiche contact |
| Synthèse société | `<x-ai-insight-card>` | Fiche société |
| Suggestions quotidiennes | `<x-ai-insight-card>` | Dashboard |
| Insights rapports | Section async | Page `/reports` |
| Score IA | Badge coloré | Index contacts + fiche |
| Rédaction email | Modal Alpine | Fiche contact + fiche deal |
| Sentiment reply | Icône 😊/😐/😟 | Timeline activités |

## Score IA contacts (B1)

### Migration
```sql
ALTER TABLE contacts ADD COLUMN ai_score SMALLINT DEFAULT NULL;
ALTER TABLE contacts ADD COLUMN ai_score_updated_at TIMESTAMP DEFAULT NULL;
```

### Signaux du score
- Lifecycle : lead=10, mql=30, sql=60, customer=100
- Emelia replied : ×20 pts, opened : ×5, sent : ×1
- Activité CRM dans les 30j : ×15
- Deal associé ouvert : ×20

### Commande
```bash
php artisan ai:score-contacts {--limit=50} {--all} {--dry-run}
# Schedule : 04:00 daily
```

### UI
- Badge coloré HSL : `hsl(score, 70%, 45%)`
- Colonne triable `ai_score DESC NULLS LAST`

## Rédaction email (B2)

### Endpoint
```
POST /web/ai/draft-email
Params : contact_id|deal_id, intent (relance|proposition|suivi|remerciement|custom)
```

### UI
- Bouton "Rédiger un email" dans fiche contact (barre d'action) et fiche deal (panneau IA)
- Modal Alpine : sélecteur d'intent (5 presets), génération, champs éditables
- Boutons copie individuelle + "Tout copier"

## Analyse sentiment (B3)

### Job
```php
AnalyzeReplySentiment::dispatch($activity)
// ShouldQueue, tries=2
// Déclenché par EmeliaEventDispatcher sur TYPE_EMAIL_REPLIED
```

### Résultat stocké dans metadata
```json
{
  "sentiment": "positive",
  "score": 0.8,
  "summary": "Intérêt marqué pour la solution"
}
```

### UI
- Icône sentiment sur activités `email_replied` : 😊 (positive) / 😐 (neutre) / 😟 (négative)

## Préchargement cache (A3)

```bash
php artisan ai:precompute {--limit=50} {--contacts} {--dry-run}
# Schedule : 03:00 daily (avant score-contacts à 04:00)
```

## Insights Rapports (v3.3)

- Analyse les 4 datasets déjà en cache Redis (aucune requête SQL supplémentaire)
- Cache 1h clé `ai.report_insights`
- Chargement async côté client (Alpine fetch au montage)

## UI Alertes proactives

### Dashboard — Bandeau alertes IA
- Composant Alpine `aiAlerts()` qui fetch `GET /web/ai/proactive-alerts` au chargement
- Alertes critiques toujours visibles (rouge)
- Alertes warning/info expandables (bouton "Tout voir")
- Bouton dismiss pour masquer
- Badge compteur + badge critique

### Sidebar — Badge alertes IA
- Badge orange (accent) sur l'icône Contacts quand alertes critiques > 0
- Se place à côté du badge Emelia existant (rouge)
- Fetch au chargement de la page

## Prochaines étapes IA

- [ ] C1. Détection contacts "froids" (widget dashboard)
- [ ] C2. Digest hebdomadaire par email (commande `ai:weekly-digest`, lundi 8h)
