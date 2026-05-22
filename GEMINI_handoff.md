# Handoff — CRM Ultimate (Gemini Redesign Session)

## 1. Objectif

Améliorer l'interface utilisateur (UI) et l'expérience utilisateur (UX) des pages de détails (Contacts, Sociétés, Deals) en mettant en place un layout modernisé, équilibré et à trois colonnes, inspiré des meilleures pratiques des CRM modernes (type HubSpot).

**v2.5 (Cette Session) :**

- Refonte complète des pages de détails des contacts, sociétés et deals avec une structure équilibrée à 3 colonnes (`3/12 - 6/12 - 3/12`).
- Remplacement du système d'onglets au centre par un flux direct toujours visible.
- Réorganisation et restructuration du composant `<x-custom-fields-show>` pour un affichage vertical ("stacked") afin d'éviter la cassure de la grille sur les longs textes et descriptions.
- Ajout de widgets de copie rapide avec retours visuels interactifs (Alpine.js) pour les emails, téléphones et sites web.
- Compilation des assets Tailwind et déploiement ciblé et vérifié sur le VPS de production.

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

**Champs personnalisés verticaux (Stacked)**

- `<x-custom-fields-show>` : Affiche désormais les propriétés personnalisées de l'entité sous forme de liste verticale scannable. Les longs textes (ex: biographies, notes) ne brisent plus l'alignement horizontal.

**Interactions d'en-tête et Copie Rapide**

- Les emails, numéros de téléphone et URL de sites web possèdent un bouton de copie rapide au survol.
- L'utilisation d'Alpine.js permet d'afficher une coche verte `✓` temporaire à la place de l'icône de copie lors du clic, confirmant visuellement l'action.

### Dernière action effectuée

Déploiement réussi sur le VPS de production (`51.38.99.226`) : transfert ciblé des fichiers de templates, recompilation des assets via `npm run build` et reconstruction de l'image Docker de production. L'application répond avec un code d'état `200` sur `https://crm.nana-intelligence.fr/login`.

---

## 3. Fichiers concernés

### Vues — composants & pages

| Fichier                                                   | Rôle                                                                                 |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `resources/views/components/custom-fields-show.blade.php` | Affichage stacked vertical des champs custom                                         |
| `resources/views/pages/contacts/show.blade.php`           | Page de détails du Contact (layout 3 colonnes, widgets de copie)                     |
| `resources/views/pages/companies/show.blade.php`          | Page de détails de la Société (layout 3 colonnes, localisation et site web enrichis) |
| `resources/views/pages/deals/show.blade.php`              | Page de détails du Deal (layout 3 colonnes harmonisé)                                |

### Assets Compilés

| Fichier                                | Rôle                                                         |
| -------------------------------------- | ------------------------------------------------------------ |
| `public/build/assets/app-yoXKPE-I.css` | Styles CSS Tailwind incluant les nouvelles classes de grille |
| `public/build/assets/app-DXvoAPE1.js`  | Scripts JS d'application compilés                            |
| `public/build/manifest.json`           | Manifeste Vite mis à jour                                    |

---

## 4. Ce qui a échoué

### Problème de rendu vertical sur le VPS (Résolu)

- **Symptôme :** Lors de la première visite de vérification sur le VPS, les 3 colonnes s'affichaient empilées verticalement au lieu d'être côte à côte.
- **Cause :** Les classes utilitaires Tailwind CSS (`grid-cols-12`, `lg:col-span-3`, `lg:col-span-6`) n'étaient pas présentes dans l'ancien bundle CSS de production.
- **Fix :** Exécuter localement `npm run build` pour forcer Vite à compiler les nouvelles classes dans les fichiers d'assets, transférer le nouveau bundle sur le VPS, puis exécuter `docker compose build app` et redémarrer le conteneur.

### Erreur d'encodage 'charmap' de la commande SSH en local (Résolu)

- **Symptôme :** Exception Python `charmap codec can't encode character '\u2713'` lors de l'exécution du script de déploiement en local.
- **Cause :** Standard output de Windows CP1252 ne parvenait pas à afficher le symbole `✓` généré par les logs Docker.
- **Fix :** Ajout de wrappers `sys.stdout = io.TextIOWrapper(...)` utilisant l'encodage `utf-8` dans nos scripts de déploiement.

---

## 5. État du déploiement production

### Production — URL : https://crm.nana-intelligence.fr

- Les conteneurs tournent correctement.
- Les caches Laravel de configuration et des vues ont été vidés (`config:clear`, `view:clear`) sur le conteneur `crm-app` pour garantir la prise en compte immédiate des nouveaux layouts.

---

## 6. Backlog de la prochaine session

- **Tests visuels complémentaires :** Vérification sur mobile et tablette (grâce aux classes responsives `col-span-12 lg:col-span-*`, les colonnes s'empilent proprement sur les petits écrans).
- **Harmonisation continue :** S'assurer que les futurs composants UI développés par Claude ou Gemini respectent la charte graphique et la structure à 3 colonnes sur les nouvelles pages de détails.
