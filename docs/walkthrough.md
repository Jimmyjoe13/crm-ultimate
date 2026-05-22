# Walkthrough — Refonte de la création des Deals et Déploiement

Voici les détails de la refonte du système de création des Deals du CRM Ultimate et le déploiement sur le VPS.

## 1. Nouvelle Vision Interactive des Deals
- **Création exclusive depuis le profil Contact** : Le bouton de création de deal global a été retiré des pages Deals et Pipeline. Les deals se créent désormais via un modal interactif directement depuis la fiche d'un contact.
- **Titre auto-généré** : Par défaut, le champ nom du deal est pré-rempli au format : `[Titre à remplir] - {{Nom du Contact}} de {{Nom de l'Entreprise}}` (ou seulement `[Titre à remplir] - {{Nom du Contact}}` si aucune entreprise n'est associée).
- **Association automatique** : L'entreprise et le contact sont automatiquement liés au deal lors de sa création.
- **Redirection Pipeline** : Dès la création, l'utilisateur est redirigé vers la Pipeline (Kanban) où le deal apparaît immédiatement.

## 2. Déploiements sur le VPS
- Le script `deploy_sync.py` a été utilisé pour synchroniser l'ensemble des fichiers modifiés de manière isolée sur le VPS (`51.38.99.226`).
- Le serveur local et le VPS ont été migrés et alimentés en base de données de test (`migrate:fresh --seed` + `db:seed --class=DemoSeeder`).

## 3. Tests de Qualité
- **Tests unitaires et d'intégration (PHPUnit)** : Les tests de `WebDealControllerTest` et `EmeliaWebhookTest` sont tous à 100% verts (local et VPS).
- **Tests E2E (Playwright)** : Les 4 tests de flux deal complet et d'accès direct passent avec succès en local comme sur le VPS, grâce à des ajustements de sélecteurs et de temps d'attente (gestion des animations et de l'initialisation d'Alpine.js).
