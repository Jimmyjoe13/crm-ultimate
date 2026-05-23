# Handoff — CRM Ultimate (Gemini Redesign Session)

## 1. Objectif

Améliorer l'interface utilisateur (UI) et l'expérience utilisateur (UX) des pages de détails (Contacts, Sociétés, Deals) en mettant en place un layout modernisé, équilibré et à trois colonnes, inspiré des meilleures pratiques des CRM modernes (type HubSpot).

**v3.0 (Cette Session - Courante) :**

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

Déploiement et activation réussis de la version **v3.0** sur le VPS de production (`51.38.99.226`). Les fichiers ont été synchronisés par SFTP, le projet a été reconstruit (`npm run build`), les fichiers copiés dans le conteneur de prod `crm-app`, les caches Laravel rechargés et les permissions du dossier `public/build` corrigées à `755` pour corriger une erreur 500 liée au manifest Vite. Validation QA automatisée 100% réussie en prod.

---

## 3. Fichiers concernés (v3.0)

### Logique & Vues

| Fichier                                         | Rôle                                                                              |
| ----------------------------------------------- | --------------------------------------------------------------------------------- |
| `resources/views/components/form-field.blade.php` | Enveloppement automatique et icône calendrier pour tous les champs de type `date` |
| `resources/views/pages/deals/show.blade.php`    | Modale d'édition des propriétés du Deal avec boutons présélections Alpine.js      |
| `resources/views/pages/deals/edit.blade.php`    | Page d'édition complète du Deal avec boutons présélections Alpine.js              |
| `resources/css/app.css`                         | Charte graphique et styles personnalisés Flatpickr (JetBrains Mono, z-index 99999) |

### Assets Compilés

| Fichier                                | Rôle                                                         |
| -------------------------------------- | ------------------------------------------------------------ |
| `public/build/assets/app-C9nu5FtS.css` | Styles CSS d'application compilés incluant le style Flatpickr|
| `public/build/assets/app-C67st75E.js`  | Scripts JS d'application compilés                            |
| `public/build/manifest.json`           | Manifeste Vite mis à jour                                    |

---

## 4. Ce qui a échoué / Points d'attention

- **Permissions d'accès public/build en production :** Le build initial a généré le répertoire `public/build` avec des permissions restreintes (`700`), ce qui a provoqué une erreur 500 temporaire ("Vite manifest not found") pour l'utilisateur de serveur web `www-data`. Un `chmod -R 755 public/build` a été appliqué et a résolu définitivement le problème.
- **Tests unitaires en production :** Les dépendances de développement (y compris `phpunit`) ne sont pas installées sur l'environnement de production (déploiement sans `--dev`), ce qui empêche de lancer les tests PHPUnit natifs en prod. En revanche, les tests QA automatisés de bout en bout avec le navigateur simulé ont validé à 100% le bon fonctionnement.

---

## 5. État du déploiement production

### Production — URL : https://crm.nana-intelligence.fr

- Les conteneurs tournent correctement.
- Les caches Laravel ont été vidés et régénérés (`config:cache`, `route:cache`, `view:cache`, `view:clear`).
- **Datepicker & Presets** : Activés et testés avec succès en direct (connexion de test effectuée, modification de date via le preset `+30j` et enregistrement en base de données validés).

---

## 6. Backlog de la prochaine session

- **Présélections et raccourcis d'édition rapides :** Étendre l'approche ergonomique des presets rapides de date à d'autres propriétés clés du CRM (ex: boutons rapides de changement de statut ou de pipeline stage dans les fiches).
- **Optimisation des performances d'assets :** Suivre les performances de chargement de Flatpickr et s'assurer que le z-index très élevé n'entre pas en collision avec d'autres bibliothèques tierces futures.

