# Règles de collaboration — Claude Code & Gemini

> Ce fichier est la **source de vérité** pour la cohabitation entre les deux agents IA sur ce projet.  
> Tout agent (Claude Code ou Gemini) doit le lire et le respecter **avant de toucher au code**.

---

## Partage des responsabilités

| Domaine | Agent responsable | Jamais l'autre |
|---|---|---|
| PHP / Laravel (Controllers, Models, Jobs, Migrations, Services, Tests) | **Claude Code** | Gemini ne touche pas au PHP |
| Blade templates, Alpine.js, CSS Tailwind, Vite | **Gemini** | Claude Code ne touche pas aux vues sauf urgence bloquante |
| Routes (`routes/web.php`) | **Claude Code** | Gemini ne modifie pas les routes |
| Tests PHPUnit | **Claude Code** | Gemini ne crée pas de tests PHP |
| `GEMINI.md`, `CLAUDE.md`, `collaboration.md` | Lecture : les deux / Écriture : le rédacteur original | Ne pas modifier sans concertation |

---

## Règles absolues

### 1. Pas de conflit de fichiers
- Chaque agent ne modifie **que ses fichiers**. Si une urgence impose un écart, le noter en commentaire dans le fichier ET informer l'autre via le fichier `handoff.md`.
- En cas de doute sur la propriété d'un fichier : **ne pas toucher, demander**.

### 2. Contrats d'interface backend ↔ frontend
- Claude Code définit les routes, les JSON retournés, et les structures de données.
- Gemini consomme ces contrats **tel quel** — sans les modifier ni les contourner.
- Si un contrat semble insuffisant, Gemini le signale dans `handoff.md` section "À résoudre" et attend.

### 3. Pas de réinvention
- Gemini n'ajoute pas de packages JS non documentés dans `CLAUDE.md`.
- Claude Code n'ajoute pas de classes CSS maison hors des tokens Tailwind déjà définis.
- Toujours utiliser les patterns existants : `btn`, `card`, `chip`, Alpine (`x-data`, `x-show`, `x-text`).

### 4. Handoff explicite
- Après chaque livraison, le fichier `handoff.md` (Claude Code) ou `GEMINI_handoff.md` (Gemini) est mis à jour.
- Format minimal : ce qui a été livré, ce qui reste, ce qui est bloquant.

### 5. Tests avant déploiement
- Claude Code : tous les tests PHPUnit doivent passer (`php artisan test`) avant tout déploiement.
- Gemini : validation visuelle dans le navigateur (golden path + cas limites) avant de déclarer une vue terminée.

### 6. Sécurité
- Gemini n'écrit jamais de CSRF token en dur dans le HTML. Toujours `document.querySelector('meta[name=csrf-token]')?.content`.
- Claude Code ne passe jamais d'entrée utilisateur brute à `Artisan::call()` ou `shell_exec()`.
- Aucun secret (clés API, mots de passe) dans les fichiers versionnés.

### 7. Déploiement
- Seul Claude Code déploie sur le VPS (SCP → build → up -d → migrate → route:cache).
- Gemini ne se connecte pas au VPS et ne modifie pas les scripts de déploiement.

---

## Workflow type d'une feature

```
1. Claude Code livre : migration + model + controller + route + tests ✅
2. Claude Code note dans handoff.md : "Backend prêt, Gemini peut implémenter la vue"
3. Gemini implémente la vue Blade/Alpine correspondante
4. Gemini note dans GEMINI_handoff.md : "Vue livrée, à valider"
5. Claude Code déploie + valide en prod
```

---

## En cas de conflit ou d'ambiguïté

- Priorité au **code existant** (ne rien casser).
- Priorité à la **sécurité** (ne rien exposer).
- Priorité aux **tests** (ne pas les faire passer en les supprimant).
- En dernier recours : laisser un `TODO:` explicite et en informer l'utilisateur.
