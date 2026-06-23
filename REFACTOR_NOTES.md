# Refactor Notes

## Phase 1 - Stabilisation et sécurisation

### Objectifs
- restaurer la stabilité visible de l'application ;
- réduire la surface de risque ;
- préparer la réorganisation progressive du projet.

### Changements effectués
- correction du rendu des graphiques dans `index.php` :
  - sérialisation des datasets côté PHP avec `json_encode` ;
  - ajout d'un fallback visuel si `Chart.js` n'est pas disponible.
- suppression d'un fragment PHP parasite dans le script final de `admin.php`.
- sécurisation de `config.php` :
  - retrait des secrets hardcodés ;
  - lecture via variables d'environnement `MM_BOT_TOKEN`, `MM_CHAT_ID`, `MM_CURRENCY` ;
  - support optionnel d'un fichier local `config.local.php` non versionné.
- chargement explicite de `config.php` dans `telegram_bot.php` pour conserver le fallback de configuration.
- nettoyage de `.gitignore` :
  - ajout des chemins sensibles et générés ;
  - arrêt de l'exclusion globale des fichiers `*.md`.
- archivage des scripts legacy/debug hors de la racine active vers `archive/legacy/`.

### Fichiers archivés
- `cleanup.php`
- `cleanup_final.php`
- `debug_db.php`
- `check_category_expenses.php`
- `insert_varied_expenses.php`
- `set_budgets.php`
- `scripts/dedupe_savings_category.php`

### Points encore prévus
- extraire la logique métier hors de `db.php` ;
- découper `index.php` en contrôleurs / vues / services ;
- introduire une structure applicative simple (`app/`, `config/`, `views/`, `storage/`) ;
- centraliser la configuration et la connexion SQLite ;
- réécrire la documentation d'installation et d'exploitation.

## Phase 2 - Socle applicatif

### Objectifs
- introduire une structure de projet plus claire sans casser les points d'entrée historiques ;
- centraliser la configuration ;
- préparer l'extraction progressive de la logique métier.

### Changements effectués
- ajout d'un bootstrap commun : `bootstrap/app.php`
- ajout d'un autoloader PSR-4 simple maison :
  - `app/Support/Autoloader.php`
- ajout d'une configuration centralisée :
  - `config/app.php`
  - `config/database.php`
  - `config.local.example.php`
- ajout d'une connexion SQLite centralisée :
  - `app/Support/Database.php`
- ajout d'un registre de config léger :
  - `app/Support/Config.php`
- création des premiers repositories :
  - `UserRepository`
  - `BudgetRepository`
  - `ExpenseRepository`
- création des premiers services :
  - `AuthService`
  - `DashboardService`
- raccordement progressif de l'existant :
  - `db.php` utilise désormais le bootstrap et la connexion centralisée ;
  - `auth.php` utilise le bootstrap et stocke les sessions dans `storage/sessions` ;
  - `attemptLogin()` passe par `AuthService`.

### Structure ajoutée
- `app/Controllers/`
- `app/Models/`
- `app/Repositories/`
- `app/Services/`
- `app/Middleware/`
- `app/Helpers/`
- `bootstrap/`
- `config/`
- `database/migrations/`
- `database/seeders/`
- `routes/`
- `views/layouts/`
- `views/pages/`
- `views/partials/`
- `storage/logs/`
- `storage/sessions/`

### Étape suivante recommandée
- brancher progressivement `index.php`, `admin.php`, `setup.php` et les endpoints API sur les repositories/services nouvellement créés ;
- commencer à sortir les blocs HTML vers `views/partials` puis `views/pages`.

## Phase 3 - Premiers raccordements métier

### Objectifs
- réduire le volume de logique métier présente directement dans `index.php` ;
- stabiliser les parties les plus sensibles (`dashboard`, `budgets`, `épargne`) derrière des services dédiés.

### Changements effectués
- ajout d'un `MetaRepository` pour centraliser l'accès à la table `meta` ;
- ajout d'un `SavingGoalRepository` et d'un `SavingGoalService` ;
- enrichissement de `DashboardService` avec une méthode `buildViewData()` qui prépare :
  - dépenses ;
  - budgets ;
  - cycle budgétaire ;
  - indicateurs dashboard ;
  - top dépenses ;
  - datasets des graphiques.
- `index.php` n'assemble plus lui-même tout le tableau de bord :
  - il délègue la préparation des données à `DashboardService` ;
  - il délègue les objectifs d'épargne à `SavingGoalService`.

### Effet concret
- `index.php` reste encore volumineux, mais une partie importante de la logique métier a quitté le fichier ;
- les régressions autour des budgets et de l'épargne sont plus faciles à contenir ;
- la prochaine extraction pourra viser les vues/partials sans avoir à recasser les calculs du dashboard.

## Phase 4 - Premiers partials de vue

### Objectifs
- réduire le volume HTML directement embarqué dans `index.php` ;
- préparer la migration progressive vers `views/pages` et `views/partials`.

### Changements effectués
- extraction de plusieurs blocs du dashboard en partials :
  - `views/partials/dashboard_banner.php`
  - `views/partials/dashboard_stat_cards.php`
  - `views/partials/dashboard_recent_expenses.php`
  - `views/partials/dashboard_top_expenses.php`
  - `views/partials/dashboard_budget_progress.php`
- `index.php` inclut désormais ces partials au lieu de contenir tout le markup inline.

### Effet concret
- `index.php` devient plus lisible ;
- le dashboard est maintenant découpé en blocs réutilisables ;
- la prochaine étape pourra extraire d'autres sections (`budgets`, `historique`, `modals`, `header`).

### Extension de la phase 4
- extraction supplémentaire :
  - `views/partials/budgets_tab.php`
  - `views/partials/expenses_tab.php`

### Effet concret supplémentaire
- les onglets `Budgets` et `Historique` ne sont plus directement codés dans `index.php` ;
- le fichier principal conserve encore la page entière, mais sa responsabilité visuelle commence à se réduire de façon visible.

### Extension supplémentaire de la phase 4
- extraction additionnelle :
  - `views/partials/reports_tab.php`
  - `views/partials/savings_tab.php`
  - `views/partials/admin_alerts.php`
  - `views/partials/admin_sidebar.php`
  - `views/partials/admin_users_table.php`
  - `views/partials/admin_modals.php`
- ajout d'un `AdminService` pour centraliser :
  - création / modification / suppression de comptes ;
  - impersonation ;
  - configuration Telegram ;
  - assemblage des données de la page admin.
- `UserRepository` gère maintenant la création, la mise à jour, la suppression et le comptage des administrateurs.
- `MetaRepository` gère désormais les clés utilisateur pour Telegram.

### Effet concret supplémentaire
- `admin.php` ne porte plus toute la logique métier d'administration ;
- l'interface admin est découpée en blocs plus simples à maintenir ;
- le point d'entrée historique est conservé, mais il s'appuie désormais sur une couche applicative plus propre.

## Phase 5 - Setup initial et API

### Objectifs
- alléger `setup.php` ;
- réduire la logique métier du dossier `api/` ;
- préparer l'introduction future de vrais contrôleurs sans casser les endpoints historiques.

### Changements effectués
- ajout de `SetupService` pour :
  - préparer les données du premier paramétrage budget ;
  - valider et appliquer la configuration initiale.
- ajout de `ApiService` pour centraliser :
  - budget vs dépenses ;
  - répartition des catégories ;
  - évolution des dépenses ;
  - dépenses hebdomadaires ;
  - vérification des alertes ;
  - archivage manuel du cycle ;
  - création d'une alerte de test.
- `setup.php` délègue maintenant ses calculs et sa validation à `SetupService`.
- les endpoints du dossier `api/` ne portent plus leur logique métier principale.
- correction d'un include fragile dans `api/trigger-test-alert.php` avec usage de `__DIR__`.

### Effet concret
- le comportement HTTP reste identique ;
- le code devient plus simple à tester et à relire ;
- les prochains contrôleurs pourront se brancher sur des services déjà en place au lieu de repartir de pages monolithiques.

## Phase 6 - Désencombrement ciblé de db.php

### Objectifs
- réduire encore la responsabilité de `db.php` ;
- conserver les fonctions historiques comme couche de compatibilité ;
- déplacer les accès SQL simples vers des repositories dédiés.

### Changements effectués
- extension de `ExpenseRepository` avec :
  - création ;
  - mise à jour ;
  - suppression.
- ajout de `AlertRepository`.
- ajout de `DebtRepository`.
- ajout de `BudgetTemplateService` pour :
  - l'utilisateur source `localuser` ;
  - les ratios de catégories ;
  - les suggestions automatiques de budgets ;
  - la détection de setup initial ;
  - la cohérence du meta `monthly_budget`.
- les wrappers historiques de `db.php` délèguent maintenant :
  - dépenses ;
  - alertes ;
  - dettes ;
  - logique de template budget.

### Effet concret
- `db.php` reste compatible avec l’existant, mais il est beaucoup moins propriétaire du SQL courant ;
- la suite du nettoyage peut désormais viser les archives, les utilitaires restants et les derniers helpers globaux.

## Phase 7 - Extraction de l'archivage

### Objectifs
- sortir la logique d’archivage mensuel du fichier `db.php` ;
- garder les fonctions globales historiques pour compatibilité ;
- fiabiliser les endpoints et écrans qui reposent dessus.

### Changements effectués
- ajout de `ArchiveRepository`.
- ajout de `ArchiveService`.
- délégation depuis `db.php` de :
  - `saveArchive()`
  - `fetchArchives()`
  - `getArchiveCycleBounds()`
  - `findArchiveForCycle()`
  - `archiveCurrentCycle()`
  - `buildArchiveSummaryMessage()`
  - `getPreviousMonthSavings()`

### Effet concret
- la logique d’archivage n’est plus enfouie dans le gros fichier utilitaire ;
- l’API d’archivage et le dashboard continuent d’utiliser les mêmes points d’entrée ;
- la prochaine passe peut se concentrer sur le nettoyage final, les helpers résiduels et la cohérence dépôt/doc.

## Phase 8 - Nettoyage final du dépôt

### Objectifs
- sortir de la racine active les notes de patch et scripts de secours non branchés ;
- réduire les risques de confusion et d’exposition involontaire ;
- préparer un dépôt plus lisible pour la maintenance.

### Changements effectués
- archivage de documents de travail vers `archive/legacy/notes/` et `archive/legacy/` :
  - `TODO.md`
  - `patch_telegram.md`
  - `patch_savings_dashboard.md`
- archivage de `send_alerts_hosted.php` :
  - script non branché dans l’application courante ;
  - présence d’une clé secrète d’exemple en dur dans la version racine.
- mise à jour de `.gitignore` pour exclure `data.sqlite` à la racine.
- petite solidification supplémentaire de `db.php` sur :
  - `safeQuery()`
  - `calculateCategoryExpenses()`
  - `migrateSessionDataToDb()`

### Effet concret
- la racine du projet est plus propre ;
- les artefacts de patch et variantes legacy sont conservés sans rester exposés dans le flux principal ;
- le dépôt se rapproche d’un état de refactorisation stabilisé.

## Phase 9 - Clôture et audit final

### Vérifications réalisées
- lint PHP global sur les fichiers actifs ;
- vérifications ciblées sur :
  - `index.php`
  - `admin.php`
  - `setup.php`
  - `send_alerts.php`
  - endpoints API principaux
  - services et repositories extraits
- smoke tests backend sur :
  - dashboard ;
  - setup ;
  - admin ;
  - API ;
  - archivage ;
  - helpers critiques de `db.php`.

### Résultat
- aucun échec de syntaxe détecté sur les fichiers PHP actifs vérifiés ;
- plus de scripts debug/cleanup actifs repérés à la racine ;
- faux positif d'encodage identifié dans `BudgetService` :
  - les chaînes mojibake restantes servent volontairement d'alias de normalisation pour récupérer d'anciennes données corrompues sur `Épargne`.

### Points résiduels à arbitrer séparément
- `e-learning-website/` ;
- `gh-cli/` ;
- éventuels artefacts purement locaux non branchés au runtime principal.

### Conclusion
- la refactorisation est fonctionnellement stabilisée côté structure ;
- le dépôt est nettement plus maintenable ;
- les tâches restantes relèvent surtout de l'exploitation, du tri de contenu non applicatif ou d'une future phase de rationalisation complémentaire.
