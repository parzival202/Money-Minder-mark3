# MoneyMinder - Gestionnaire Budgétaire Intelligent

[![Version](https://img.shields.io/badge/version-3.0.0-blue.svg)](https://github.com/your-repo/moneyminder)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![SQLite](https://img.shields.io/badge/SQLite-3.x-green.svg)](https://sqlite.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

> Une application web pour gérer vos budgets et dépenses personnelles avec des alertes et rapports.

## Fonctionnalités Principales

- Gestion budgétaire par catégorie
- Suivi des dépenses en temps réel
- Alertes intelligentes (Telegram)
- Rapports et graphiques
- Archivage automatique mensuel

## Installation

### Prérequis
- PHP 7.4+ avec PDO
- SQLite 3.x
- Serveur web (Apache/Nginx)

### Étapes
1. Clonez le dépôt : `git clone https://github.com/your-repo/moneyminder.git`
2. Placez dans `htdocs/moneyminder/`
3. Ouvrez `http://localhost/moneyminder/index.php`
4. Configurez vos budgets et dépenses

## Structure du Projet

```
moneyminder/
├── index.php          # Interface principale
├── db.php             # Base de données
├── telegram_bot.php   # Notifications
├── send_alerts.php    # Alertes automatiques
├── archives.php       # Historique
├── api/               # Endpoints API
├── assets/            # Ressources
└── data/app.db        # Base de données SQLite
```

## Technologies

- Backend : PHP 7.4+ avec PDO
- Base de données : SQLite
- Frontend : HTML5, CSS3, JavaScript, Bootstrap 5
- Graphiques : Chart.js
- Notifications : Telegram Bot API

## Support

Pour les bugs et assistance, utilisez les issues GitHub : [Signaler un problème](https://github.com/your-repo/moneyminder/issues)

## Licence

MIT License - voir le fichier LICENSE pour plus de détails.
