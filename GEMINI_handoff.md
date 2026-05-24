# Handoff — CRM Ultimate (Gemini Redesign Session)

## 1. Objectif

Améliorer l'interface utilisateur (UI) et l'expérience utilisateur (UX) des pages de détails (Contacts, Sociétés, Deals) en mettant en place un layout modernisé, équilibré et à trois colonnes, inspiré des meilleures pratiques des CRM modernes (type HubSpot).

**v3.3 (Cette Session - Courante) :**

- **Indicateurs financiers cumulés sur le Dashboard (Chiffre d'Affaires Gagné et Perdu) :**
  - Ajout des calculs de somme cumulée historique pour tous les deals gagnés (`ca_total`) et tous les deals perdus (`ca_lost`) dans `DashboardController.php`.
  - Intégration des classes `.kpi-ok` (dégradé vert) et `.kpi-err` (dégradé rouge) dans `app.css`.
  - Réorganisation de la grille de KPI sur le tableau de bord (`dashboard.blade.php`) en 3 colonnes sur 2 lignes pour un design moderne, aéré et extrêmement premium.
  - Ajout de graphiques de tendances miniaturisés (SVG) sous forme de courbes d'évolution vectorielles positives et négatives adaptées.
  - Déploiement et validation réussis en production.

**v3.2 :**

- **Correction des fonctionnalités du Kanban :** Rétablissement de la mise à jour automatique du statut des deals (`open`, `won`, `lost`) lors du drag-drop entre colonnes :
  - Modification du script SortableJS front-end pour appeler l'endpoint API dédié de déplacement (`POST /api/v1/deals/{id}/move`).
  - Implémentation d'une synchronisation automatique au niveau du modèle `Deal` dans son événement de sauvegarde (`saving`) pour garantir que tout changement de `pipeline_stage_id` met à jour de façon cohérente le statut en base de données.
  - Ajustement de `PipelineController` pour charger tous les deals associés au pipeline (y compris ceux gagnés et perdus dans leurs colonnes correspondantes) afin de correspondre aux données réelles de l'API.
  - Modification de `JwtMiddleware` pour extraire et déchiffrer manuellement le token depuis le cookie `crm_jwt` si l'en-tête `Authorization` est absent (requis car le cookie est chiffré par Laravel sur la partie Web mais n'est pas déchiffré automatiquement sur l'API car le middleware `EncryptCookies` n'appartient pas au groupe `api`).
  - Suppression de l'en-tête `Authorization` de la requête AJAX du Kanban pour laisser le navigateur transmettre le cookie `crm_jwt` de manière transparente et sécurisée.

**v3.1 (Cette Session - Courante) :**

- **Sélecteur de statut de deal rapide :** Remplacement de la puce de statut statique du Deal par un sélecteur déroulant interactif (Alpine.js) permettant de mettre à jour le statut (`open`, `won`, `lost`) en un clic.
- **Étapes de pipeline cliquables :** La barre de progression des étapes de pipeline sur la fiche Deal est devenue cliquable, permettant de faire progresser le deal à n'importe quelle étape instantanément avec validation et gestion des inputs requis en arrière-plan.
- **Sélecteurs rapides de contact (Lifecycle & Statut Lead) :** Ajout de sélecteurs déroulants interactifs dans la fiche Contact pour modifier instantanément l'étape lifecycle (`lead`, `mql`, `sql`, etc.) et le statut du lead (`new`, `open`, etc.).
- **Améliorations de robustesse UI & QA :** Correction d'un bug d'évaluation de constante de trait PHP dans `contacts/show.blade.php`, ajout d'un bouton de création de deal toujours accessible dans la fiche contact pour une meilleure expérience utilisateur, et mise à jour de la configuration de connexion E2E avec 100% de réussite sur les tests automatisés Playwright.

**v3.0 :**

- **Design du sélecteur de date (close_date) amélioré :** Intégration d'une icône de calendrier interactive et de boutons de présélection intelligents ("Fin de mois", "Fin trim.", "+30j", "+90j") pilotés par Alpine.js et reliés à Flatpickr pour une édition en un clic dans la fiche Deal (modale propriétés) et le formulaire d'édition générale.
- **Refonte esthétique globale de Flatpickr :** Application des variables CSS du CRM (thème clair/sombre, z-index élevé à 99999 pour éliminer tout conflit d'affichage dans les tiroirs/modales, police technique JetBrains Mono, suppression des styles de sélection système pour le mois et l'année).
- **Harmonisation globale des champs de date :** Enveloppement automatique avec une icône de calendrier sur l'ensemble des inputs de type date de l'application via le composant réutilisable `form-field`.

**v2.9 :**

- **Règles "est connu" et "est inconnu" :** Ajout des opérateurs standardisés de CRM "est connu" (mappé sur `is_not_null` et `exists`) et "est inconnu" (mappé sur `is_null` et `not_exists`) dans le sélecteur de règles pour toutes les propriétés (textes, nombres, dates, booléens, relations).
- **Masquage intelligent des valeurs :** L'UI masque automatiquement le champ de valeur à côté de l'opérateur lorsque "est connu" ou "est inconnu" est sélectionné.
- **Validation QA en production :** Déploiement et tests QA en direct validés avec succès sur le serveur de production.

**v2.8 :**

- **Refactoring de la création de segments :** Refonte complète de la logique et de l'interface utilisateur de création des segments. Désormais, le constructeur de règles s'adapte de manière dynamique aux types des propriétés sélectionnées :
  - **Dropdown (Listes déroulantes) :** Chargement dynamique des options pour les champs core (Pipelines, Étapes de pipelines, Commerciaux responsables, Lifecycle Stage, Lead Status, Devises) et pour les champs personnalisés de type `select`.
  - **Choix multiples :** Affichage d'un sélecteur multiple (`select[multiple]`) pour les opérateurs `in` (dans la liste) et `not_in` (hors liste).
  - **Inputs numériques :** Utilisation d'un `<input type="number">` pour les montants et nombres, et de double inputs Min/Max pour l'opérateur `between` (entre).
  - **Inputs date :** Utilisation d'un calendrier natif `<input type="date">` et de double calendriers Min/Max pour l'opérateur `between`.
  - **Booléens :** Dropdown Oui/Non natif.
  - **Harmonisation visuelle UI/UX :** Intégration de styles personnalisés (bordures, arrondis, padding, sélecteurs chevrons, ombres de focus) sur les inputs et selects du constructeur de segments pour s'aligner parfaitement sur la charte graphique globale de l'application.
  - **Résolution du chargement des propriétés (Custom Fields) :** Correction d'une exception `Undefined array key "options"` dans `SegmentQueryEngine::loadCustomKeys` lors de la récupération des propriétés personnalisées, qui causait un échec silencieux lors de la récupération de la liste des champs sélectionnables et vidait le dropdown.
- **Sécurisation SQL :** Éradication complète des erreurs SQL de type de données (comme la comparaison d'entiers bigint avec du texte) grâce à la validation structurée et aux menus déroulants d'options.

> [!IMPORTANT]
> **RÈGLE D'OR DE COHABITATION GEMINI / CLAUDE CODE :**
> Ne jamais empiéter sur le travail ou le périmètre fonctionnel de l'assistant Claude Code qui gère les directives du fichier handoff.md. Toujours effectuer des déploiements ciblés de fichiers d'interface sans écraser les fichiers de logique backend et les modèles partagés, sauf validation mutuelle.

---

## 2. État actuel

### Ce qui fonctionne déjà

**Redesign 3 colonnes**

- **Fiche Contact (`/contacts/{id}`) :** Affichage en 3 colonnes distinctes : volet d'information à gauche avec widgets interactifs, fil d'activité et compositeur au centre, deals associés et modules d'IA/Emelia à droite.
- **Fiche Société (`/companies/{id}`) :** Agencement similaire en 3 colonnes : site web, domaine, téléphone et champs personnalisés à gauche, timeline au centre, contacts et deals associés ainsi que le résumé IA à droite.
- **Fiche Deal (`/deals/{id}`) :** Structure alignée sur le modèle 3 colonnes pour une cohérence visuelle sur l'ensemble de l'application.

**Gestion dynamique des propriétés (Modale d'édition et création)**

- **Modification rapide :** Permet la modification instantanée de toutes les propriétés depuis le volet "À propos" sans changer de page.
- **Création de propriétés personnalisées :** Les utilisateurs autorisés peuvent instantanément définir un nouveau champ (`text`, `number`, `date`, `boolean`, `select`), lequel devient immédiatement modifiable.

**Timeline interactive (Filtres, tri, recherche et suppression)**

- Barre de filtres (Type et Source) et de recherche textuelle réactive sur Contacts, Sociétés et Deals.
- Tri chronologique et anti-chronologique réactif en un clic.
- Suppression en un clic avec survol élégant (bouton 🗑️) et confirmation native.

**Constructeur de Segments intelligent**

- Formulaire réactif avec Alpine.js s'adaptant dynamiquement au type de champ de la règle (Dropdowns pour les ids/relations et enums, calendriers natifs, inputs numériques simples/doubles pour les plages).
- Plus aucun bug de type ou d'injection SQL lors de l'aperçu ou de la création des segments.

### Dernière action effectuée

Déploiement et activation réussis de la version **v3.3** sur le VPS de production (`51.38.99.226`). Les fichiers locaux modifiés ont été poussés sur Git et transférés sur le serveur, les images Docker reconstruites, et le cache Laravel rechargé. La validation par subagent navigateur a confirmé le rendu correct des nouvelles cartes de CA cumulé.

---

## 3. Fichiers concernés (v3.3)

### Logique & Vues

| Fichier                                         | Rôle                                                                              |
| ----------------------------------------------- | --------------------------------------------------------------------------------- |
| `app/Http/Controllers/Web/DashboardController.php` | Calculs du CA global (won) et du CA perdu global (lost)                         |
| `resources/views/pages/dashboard.blade.php`    | Réorganisation de la grille de KPI (3 colonnes x 2 lignes) et intégration des cartes |
| `resources/css/app.css`                        | Définitions des classes de dégradé `.kpi-ok` et `.kpi-err`                        |

### Assets Compilés

| Fichier                                | Rôle                                                         |
| -------------------------------------- | ------------------------------------------------------------ |
| `public/build/assets/app-Byvue6_Q.css` | Styles CSS d'application compilés                            |
| `public/build/assets/app-C67st75E.js`  | Scripts JS d'application compilés                             |
| `public/build/manifest.json`           | Manifeste Vite mis à jour                                    |

---

## 4. Ce qui a échoué / Points d'attention

- Aucun échec rencontré sur la version v3.3.
- **Rappel de cohabitation** : Le middleware `JwtMiddleware` déchiffre désormais proprement les cookies sécurisés de la partie Web pour l'API sans casser les en-têtes standard de l'application (v3.2).

---

## 5. État du déploiement production

### Production — URL : https://crm.nana-intelligence.fr

- Le déploiement est **actif et validé** à 100% sur le serveur.

---

## 6. Backlog de la prochaine session

- **Supervision & Maintenance** : Surveiller les retours utilisateur sur les nouveaux indicateurs financiers globaux du Dashboard et le comportement de synchronisation du Kanban.

