# MoneyMinder

MoneyMinder est une application PHP/SQLite de gestion budgétaire personnelle avec suivi des dépenses, budgets par catégorie, objectifs d'épargne, administration multi-utilisateur et alertes Telegram.

## Fonctionnalités

- tableau de bord avec indicateurs mensuels, progression des budgets et graphiques ;
- gestion des dépenses par catégorie ;
- budgets calculés à partir du profil de l'utilisateur connecté ;
- objectifs d'épargne et suivi de progression ;
- alertes applicatives et notifications Telegram ;
- espace d'administration pour gérer les comptes et la configuration Telegram par utilisateur ;
- archivage mensuel et historique des cycles.

## Stack

- PHP 7.4+ ;
- SQLite ;
- Bootstrap 5 ;
- Chart.js ;
- Font Awesome.

## Installation locale

1. Place le projet dans ton dossier web, par exemple `C:\xampp\htdocs\moneyminder`.
2. Vérifie que PHP a bien les extensions `pdo_sqlite`, `sqlite3` et `mbstring`.
3. Assure-toi que les dossiers suivants sont accessibles en écriture :
   - `storage/sessions/`
   - `data/` si tu conserves une base SQLite dedans
4. Copie `config.local.example.php` vers `config.local.php` si tu veux une configuration locale non versionnée.
5. Ouvre l’application via `http://localhost/moneyminder/login.php`.

## Configuration

L’application charge :

- `config/app.php`
- `config/database.php`
- `config.local.php` si présent

### Variables utiles

Tu peux définir la configuration sensible soit dans `config.local.php`, soit via variables d’environnement :

- `MM_BOT_TOKEN`
- `MM_CHAT_ID`
- `MM_CURRENCY`

Exemple minimal dans `config.local.php` :

```php
<?php

return [
    'app' => [
        'currency' => 'FCFA',
    ],
    'telegram' => [
        'bot_token' => 'ton-token',
        'default_chat_id' => 'ton-chat-id',
    ],
];
```

## Structure actuelle

```text
moneyminder/
├── admin.php
├── auth.php
├── db.php
├── index.php
├── login.php
├── send_alerts.php
├── bootstrap/
├── app/
│   ├── Controllers/
│   ├── Helpers/
│   ├── Middleware/
│   ├── Models/
│   ├── Repositories/
│   ├── Services/
│   └── Support/
├── config/
├── database/
├── routes/
├── storage/
│   ├── logs/
│   └── sessions/
├── views/
│   ├── layouts/
│   ├── pages/
│   └── partials/
└── archive/
    └── legacy/
```

## Compte administrateur

L’application peut créer un compte par défaut selon l’état de la base. Si un compte admin de démonstration existe encore en environnement réel :

1. connecte-toi ;
2. change immédiatement son mot de passe ;
3. vérifie la liste des administrateurs ;
4. supprime tout compte de démo inutile.

## Telegram

La configuration Telegram peut maintenant être faite depuis l’interface d’administration, utilisateur par utilisateur.

Pour récupérer un `chat_id` :

1. crée un bot via [@BotFather](https://t.me/BotFather) ;
2. envoie un message au bot ;
3. ouvre `https://api.telegram.org/botTON_TOKEN/getUpdates` ;
4. récupère `chat.id`.

## Cron Debian 13

Pour déclencher les alertes côté serveur Debian 13 avec l’utilisateur web :

```bash
sudo crontab -u www-data -e
```

Exemple de tâche toutes les 5 minutes :

```cron
*/5 * * * * /usr/bin/php /var/www/html/moneyminder/send_alerts.php >> /var/www/html/moneyminder/storage/logs/cron_alerts.log 2>&1
```

À prévoir sur le serveur :

- créer `storage/logs/` si nécessaire ;
- donner les droits d’écriture à `www-data` ;
- vérifier aussi les droits sur le dossier de sessions si tu déploies sous Linux.

## Sécurité

- protection CSRF côté formulaires ;
- validation serveur des saisies principales ;
- requêtes préparées PDO ;
- sessions centralisées dans `storage/sessions` ;
- configuration sensible sortie du code applicatif principal ;
- scripts legacy/debug déplacés dans `archive/legacy/`.

## Vérifications rapides après installation

- connexion / déconnexion ;
- ajout, modification et suppression de dépense ;
- mise à jour des budgets ;
- affichage du dashboard ;
- affichage des graphiques ;
- accès admin ;
- mise à jour des identifiants admin ;
- test Telegram si configuré ;
- exécution manuelle de `send_alerts.php`.

## Fichiers importants

- [index.php](/C:/xampp/htdocs/moneyminder/index.php)
- [admin.php](/C:/xampp/htdocs/moneyminder/admin.php)
- [db.php](/C:/xampp/htdocs/moneyminder/db.php)
- [bootstrap/app.php](/C:/xampp/htdocs/moneyminder/bootstrap/app.php)
- [app/Services](/C:/xampp/htdocs/moneyminder/app/Services)
- [app/Repositories](/C:/xampp/htdocs/moneyminder/app/Repositories)
- [views/partials](/C:/xampp/htdocs/moneyminder/views/partials)
- [REFACTOR_NOTES.md](/C:/xampp/htdocs/moneyminder/REFACTOR_NOTES.md)

## Refactorisation

Les changements de nettoyage et d’architecture sont documentés dans [REFACTOR_NOTES.md](/C:/xampp/htdocs/moneyminder/REFACTOR_NOTES.md).
