# 💰 MoneyMinder - Gestionnaire Budgétaire Intelligent

[![Version](https://img.shields.io/badge/version-3.0.0-blue.svg)](https://github.com/your-repo/moneyminder)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![SQLite](https://img.shields.io/badge/SQLite-3.x-green.svg)](https://sqlite.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

> Une application web moderne et intelligente pour maîtriser vos finances personnelles. Suivez vos dépenses, gérez vos budgets, recevez des alertes personnalisées et visualisez vos habitudes financières avec des rapports détaillés.

## ✨ Vue d'ensemble

MoneyMinder révolutionne la gestion budgétaire en combinant simplicité d'utilisation et puissance analytique. Grâce à son système d'alertes échelonnées et son archivage automatique, elle vous aide à maintenir une discipline financière sans vous submerger de notifications.

### 🎯 Cas d'usage
- **Étudiants** : Contrôler les dépenses quotidiennes et atteindre les objectifs d'épargne
- **Familles** : Gérer les budgets familiaux avec suivi en temps réel
- **Freelancers** : Séparer dépenses professionnelles et personnelles
- **Tout utilisateur** : Améliorer la conscience financière et optimiser l'épargne

## 🚀 Fonctionnalités Principales

### 💰 Gestion Budgétaire Avancée
- **Budgets par catégorie** : Définissez des limites personnalisées pour chaque catégorie (Alimentation, Transport, Loisirs, etc.)
- **Suivi en temps réel** : Visualisez instantanément l'utilisation de vos budgets avec des barres de progression colorées et dynamiques
- **Budget global mensuel** : Fixez un objectif de dépense mensuel global avec calcul automatique du reste disponible
- **Épargne automatique** : Catégorie spéciale "Épargne" avec objectif annuel et suivi de progression

### 📊 Tableaux de Bord et Rapports Interactifs
- **Tableau de bord intelligent** : Vue d'ensemble avec statistiques clés, tendances et insights personnalisés
- **Graphiques visuels avancés** :
  - Répartition des dépenses par camembert interactif
  - Évolution temporelle sur 30 jours avec courbes lissées
  - Dépenses hebdomadaires avec comparaisons
- **Rapports détaillés** : Analyse approfondie par catégorie et période avec export possible
- **Archives mensuelles** : Historique complet des cycles budgétaires (du 27 au 26) avec recherche et filtrage

### 🔔 Système d'Alertes Intelligent et Échelonné
- **Alertes échelonnées uniques** : Système rotatif sophistiqué pour éviter la surcharge de notifications
- **Alertes immédiates stratégiques** : Pour les dépassements critiques et objectifs atteints seulement
- **Notifications Telegram intégrées** : Bot Telegram personnalisé pour recevoir les alertes en temps réel
- **Types d'alertes optimisés** :
  - 🚨 Dépassement de budget (80%, 100%) - Alertes progressives
  - 💰 Dépenses importantes (>10,000 FCFA) - Seulement les grosses dépenses
  - ⚠️ Limites journalières (>8,000 FCFA, >10,000 FCFA) - Contrôle quotidien
  - 🎯 Objectifs d'épargne atteints - Motivations positives
  - 😴 Inactivité prolongée (>7 jours) - Rappels doux
  - 🌟 Encouragements pour faible dépense - Récompenses positives

### 🎯 Objectifs d'Épargne Personnalisés
- **Objectifs flexibles** : Créez des objectifs d'épargne avec échéances personnalisées
- **Suivi de progression visuel** : Barres de progression animées et calculs automatiques précis
- **Conseils intelligents** : Suggestions de montants mensuels/hebdomadaires basés sur vos habitudes
- **Récompenses intégrées** : Notifications spéciales lors d'objectifs atteints

### 📱 Interface Utilisateur Moderne et Intuitive
- **Design épuré** : Interface Bootstrap 5 avec thème violet élégant et professionnel
- **Responsive parfaite** : Expérience optimale sur mobile, tablette et desktop
- **Navigation par onglets fluide** : Dashboard, Budgets, Historique, Rapports, Épargne, Alertes
- **Filtres avancés** : Recherche intelligente et filtrage multi-critères (date, catégorie, montant)
- **UX optimisée** : Transitions fluides, feedback visuel, et accessibilité améliorée

## 🛠️ Installation et Configuration

### 📋 Prérequis Système
- **Serveur web** : Apache/Nginx avec PHP 7.4+ (recommandé 8.0+)
- **Base de données** : SQLite 3.x (inclus, aucune installation requise)
- **Extensions PHP** : PDO, PDO_SQLite (généralement incluses)
- **Navigateur** : Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Bot Telegram** (optionnel pour les notifications push)

### ⚡ Installation Rapide (5 minutes)

#### Méthode 1: Installation Automatisée (Recommandée)
```bash
# Clonez le dépôt
git clone https://github.com/your-repo/moneyminder.git
cd moneyminder

# Définissez les permissions
chmod 755 data/
chmod 644 data/.gitkeep

# Lancez l'installation
php install.php
```

#### Méthode 2: Installation Manuelle
1. **Téléchargement** : Téléchargez et extrayez l'archive dans `htdocs/moneyminder/`

2. **Permissions** : Configurez les droits d'accès
   ```bash
   # Sur Linux/Mac
   chmod 755 data/
   chown www-data:www-data data/

   # Sur Windows (avec XAMPP)
   # Les permissions sont généralement OK par défaut
   ```

3. **Premier accès** : Ouvrez `http://localhost/moneyminder/index.php`

4. **Configuration initiale** :
   - ✅ Base de données créée automatiquement
   - ✅ Utilisateur par défaut configuré
   - ✅ Tables initialisées
   - 🔧 Configurez vos budgets dans l'onglet "Budgets"
   - 💰 Ajoutez vos premières dépenses

### 🔧 Configuration Avancée

#### Configuration Telegram (Notifications Push)
```bash
# 1. Créez votre bot
# Visitez https://t.me/botfather et créez un nouveau bot
# Obtenez votre BOT_TOKEN et CHAT_ID

# 2. Configurez MoneyMinder
nano telegram_bot.php
# Remplacez les valeurs :
private $botToken = 'YOUR_BOT_TOKEN_HERE';
private $chatId = 'YOUR_CHAT_ID_HERE';
```

#### Configuration des Alertes Automatisées

##### Sur Windows (Planificateur de tâches)
```batch
# Créez send_alerts.bat dans le répertoire racine
@echo off
"C:\xampp\php\php.exe" "C:\xampp\htdocs\moneyminder\send_alerts.php"
```

Puis configurez le Planificateur :
1. `taskschd.msc` → Créer tâche
2. Nom : "MoneyMinder Alertes"
3. Déclencheur : Quotidien, répéter toutes les heures
4. Action : Démarrer un programme → `send_alerts.bat`

##### Sur Linux (Cron)
```bash
# Éditez crontab
crontab -e

# Ajoutez cette ligne pour exécution horaire
0 * * * * /usr/bin/php /var/www/moneyminder/send_alerts.php
```

##### Sur macOS (Launchd)
```xml
<!-- /Library/LaunchDaemons/com.moneyminder.alerts.plist -->
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.moneyminder.alerts</string>
    <key>ProgramArguments</key>
    <array>
        <string>/usr/bin/php</string>
        <string>/path/to/moneyminder/send_alerts.php</string>
    </array>
    <key>StartInterval</key>
    <integer>3600</integer>
</dict>
</plist>
```

### 🌐 Configuration Serveur Web

#### Apache (.htaccess fourni)
```apache
# .htaccess déjà inclus
RewriteEngine On
RewriteRule ^api/(.*)$ api/$1 [L]
```

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/moneyminder;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /api/ {
        try_files $uri $uri/ /api/$1;
    }
}
```

### 🔐 Sécurité
- **Chiffrement des mots de passe** : Utilise `password_hash()` avec bcrypt
- **Protection XSS** : Échappement automatique des sorties
- **Protection CSRF** : Tokens de session pour les formulaires
- **Accès base de données** : Requêtes préparées PDO

## 📁 Structure du Projet

```
moneyminder/
├── index.php              # Page principale avec interface utilisateur
├── db.php                 # Configuration base de données et fonctions CRUD
├── telegram_bot.php       # Gestion des notifications Telegram
├── send_alerts.php        # Script d'envoi des alertes (planifié)
├── archives.php           # Interface d'archivage et historique
├── expenses_filters.js    # Filtres JavaScript pour les dépenses
├── data/
│   └── app.db            # Base de données SQLite
├── api/                   # API REST pour données externes
│   ├── budget-vs-spent.php
│   ├── category-distribution.php
│   ├── expenses-evolution.php
│   ├── week-expenses.php
│   └── ...
├── assets/                # Ressources statiques
│   ├── logo.png
│   └── logo2.png
├── scripts/               # Scripts utilitaires
└── trigger_*.php          # Scripts de déclenchement d'alertes
```

## 🗄️ Base de Données

### Tables Principales
- **users** : Utilisateurs (mode single-user par défaut)
- **expenses** : Dépenses avec date, catégorie, montant, description
- **budgets** : Budgets par catégorie
- **alerts** : Système d'alertes avec statut vu/non-vu
- **archives** : Archives mensuelles (cycles du 27 au 26)
- **meta** : Données de configuration

### Archivage Automatique
- **Cycle budgétaire** : Du 27 du mois au 26 du mois suivant
- **Déclenchement** : Automatique le 26 à 23h59
- **Réinitialisation** : Budgets remis à zéro (sauf Épargne)
- **Notification** : Message Telegram avec résumé

## 🔧 Technologies Utilisées

- **Backend** : PHP 7.4+ avec PDO
- **Base de données** : SQLite
- **Frontend** : HTML5, CSS3, JavaScript ES6
- **Framework CSS** : Bootstrap 5.3
- **Graphiques** : Chart.js
- **Icônes** : Font Awesome 6
- **Notifications** : Bot Telegram API

## 📡 API REST

L'application expose plusieurs endpoints API pour l'intégration :

- `GET /api/budget-vs-spent.php` : Comparaison budget/dépenses
- `GET /api/category-distribution.php` : Répartition par catégorie
- `GET /api/expenses-evolution.php` : Évolution des dépenses (30 jours)
- `GET /api/week-expenses.php` : Dépenses hebdomadaires
- `GET /api/check_alerts.php` : Vérification des alertes

## 🎨 Personnalisation

### Thème
Le thème violet peut être modifié dans `index.php` (variables CSS :root)

### Constantes
Modifiez les constantes dans `db.php` :
```php
define('MONTHLY_SAVING_GOAL', 50000);  // Objectif épargne mensuel
define('ANNUAL_SAVING_GOAL', 600000);  // Objectif épargne annuel
```

## 🐛 Dépannage et Support

### 🔍 Diagnostic Automatique
Utilisez le script de diagnostic intégré :
```bash
php diagnostics.php
```
Ce script vérifie :
- ✅ Permissions des fichiers
- ✅ Configuration PHP
- ✅ Connexion base de données
- ✅ Intégrité des tables
- ✅ Configuration Telegram

### 🚨 Problèmes Courants et Solutions

#### Base de Données
```bash
# Erreur "database is locked"
# Solution : Fermez tous les processus PHP et relancez
pkill -f php
systemctl restart apache2

# Tables corrompues
php scripts/repair_database.php

# Migration de données
php scripts/migrate_data.php
```

#### Interface Utilisateur
- **Graphiques ne s'affichent pas** :
  - Vérifiez la console navigateur (F12 → Console)
  - Assurez-vous que Chart.js est chargé
  - Vérifiez les permissions des fichiers API

- **Tabulations qui se réinitialisent** :
  - Videz le cache navigateur (Ctrl+F5)
  - Vérifiez les erreurs JavaScript dans la console

#### Alertes Telegram
```bash
# Test des notifications
php scripts/test_telegram.php

# Debug du bot
php scripts/debug_telegram.php
```

#### Performance
- **Application lente** :
  - Optimisez la base de données : `VACUUM;` dans SQLite
  - Activez la compression Gzip dans Apache/Nginx
  - Utilisez un cache opcode (OPcache)

### 📊 Monitoring et Logs

#### Activation des Logs Détaillés
```php
// Dans config.php
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/app.log');
```

#### Fichiers de Log Importants
```
logs/
├── app.log          # Erreurs générales
├── telegram.log     # Communications Telegram
├── alerts.log       # Système d'alertes
└── database.log     # Requêtes base de données
```

#### Métriques de Performance
- **Temps de réponse API** : < 200ms
- **Taille base de données** : Monitorer avec `PRAGMA page_count;`
- **Utilisation mémoire** : < 50MB par requête

### 🆘 Support et Communauté

#### 📖 Documentation Complète
- [Guide utilisateur](docs/user-guide.md)
- [API Reference](docs/api-reference.md)
- [Guide développeur](docs/developer-guide.md)

#### 💬 Obtenir de l'Aide
1. **Forum communautaire** : [Discussions GitHub](https://github.com/your-repo/moneyminder/discussions)
2. **Issues GitHub** : [Signaler un bug](https://github.com/your-repo/moneyminder/issues)
3. **Discord** : [Serveur communautaire](https://discord.gg/moneyminder)
4. **Email** : support@moneyminder.app

#### 🐛 Signaler un Bug
```markdown
**Description du bug :**
[Description claire et concise]

**Étapes pour reproduire :**
1. Aller sur '...'
2. Cliquer sur '....'
3. Voir l'erreur

**Comportement attendu :**
[Description de ce qui devrait se passer]

**Captures d'écran :**
[Si applicable]

**Environnement :**
- OS: [Windows/Linux/macOS]
- Navigateur: [Chrome/Firefox/Safari]
- Version PHP: [7.4/8.0/8.1]
- Version MoneyMinder: [3.0.0]
```

### 🔄 Mises à Jour et Migration

#### Mise à Jour Automatique
```bash
# Depuis la version 2.x vers 3.x
php update.php

# Vérification post-mise à jour
php scripts/post_update_check.php
```

#### Migration de Données
```bash
# Export des données
php scripts/export_data.php --format=json

# Import dans nouvelle installation
php scripts/import_data.php --file=backup.json
```

## 🤝 Contribution

### 🚀 Comment Contribuer

#### Pour les Débutants
1. **⭐ Star** le projet sur GitHub
2. **🐛 Signaler** les bugs rencontrés
3. **💡 Proposer** des idées d'amélioration
4. **📖 Améliorer** la documentation

#### Pour les Développeurs
1. **🍴 Fork** le projet
2. **🌿 Créez** une branche : `git checkout -b feature/amazing-feature`
3. **💻 Commitez** vos changements : `git commit -m 'Add amazing feature'`
4. **📤 Pushez** vers la branche : `git push origin feature/amazing-feature`
5. **🔄 Ouvrez** une Pull Request

### 📋 Standards de Code

#### PHP
```php
// Utilisez PSR-12
class MoneyMinder
{
    public function calculateBudget(array $expenses): float
    {
        // Code ici
    }
}
```

#### JavaScript
```javascript
// Utilisez ESLint avec configuration standard
const calculateTotal = (expenses) => {
    return expenses.reduce((total, expense) => total + expense.amount, 0);
};
```

#### Tests
```bash
# Exécuter tous les tests
composer test

# Tests avec couverture
composer test:coverage
```

### 🎯 Roadmap et Fonctionnalités Futures

#### Version 3.1 (Q1 2026)
- [ ] Synchronisation multi-appareils
- [ ] Export PDF des rapports
- [ ] Mode hors ligne

#### Version 3.2 (Q2 2026)
- [ ] Intelligence artificielle pour prédictions
- [ ] Intégration bancaires automatiques
- [ ] Mode multi-utilisateurs

#### Version 4.0 (2026)
- [ ] Application mobile native
- [ ] API GraphQL
- [ ] Microservices architecture

## 📄 Licence

```
MIT License

Copyright (c) 2024 MoneyMinder

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## 🙏 Remerciements

### 🛠️ Technologies et Bibliothèques
- **PHP** : Pour la robustesse backend
- **SQLite** : Base de données légère et fiable
- **Bootstrap 5** : Framework CSS moderne
- **Chart.js** : Graphiques interactifs
- **Font Awesome** : Icônes vectorielles
- **Telegram Bot API** : Notifications push

### 👥 Contributeurs
- **Équipe Core** : Développement principal
- **Communauté** : Tests, feedback, traductions
- **Open Source** : Inspirations et contributions

### 🤖 Assistants IA
- **ChatGPT** : Aide à la génération de code et documentation
- **BlackBox AI** : Optimisations et débogage
- **GitHub Copilot** : Suggestions de code intelligentes

### 📚 Ressources
- [PHP Documentation](https://php.net/docs)
- [SQLite Manual](https://sqlite.org/docs.html)
- [Bootstrap Docs](https://getbootstrap.com/docs)
- [Chart.js Guide](https://www.chartjs.org/docs)

---

<div align="center">

**MoneyMinder** - Maîtrisez vos finances, libérez votre potentiel

[🌟 Star us on GitHub](https://github.com/your-repo/moneyminder) • [📖 Documentation](docs/) • [🐛 Report Issues](https://github.com/your-repo/moneyminder/issues) • [💬 Discussions](https://github.com/your-repo/moneyminder/discussions)

*Développé avec ❤️ pour une gestion budgétaire simple et efficace*

</div>
