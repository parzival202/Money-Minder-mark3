# MoneyMinder - Gestionnaire Budgétaire Intelligent

MoneyMinder est une application web PHP moderne pour la gestion budgétaire personnelle. Elle offre un suivi détaillé des dépenses, une gestion intelligente des budgets par catégorie, des alertes automatisées via Telegram, et des rapports visuels pour une meilleure compréhension de vos habitudes financières.

## 🚀 Fonctionnalités Principales

### 💰 Gestion Budgétaire
- **Budgets par catégorie** : Définissez des limites pour chaque catégorie (Alimentation, Transport, Loisirs, etc.)
- **Suivi en temps réel** : Visualisez l'utilisation de vos budgets avec des barres de progression colorées
- **Budget global mensuel** : Fixez un objectif de dépense mensuel global
- **Épargne automatique** : Catégorie spéciale "Épargne" avec objectif annuel

### 📊 Tableaux de Bord et Rapports
- **Tableau de bord interactif** : Vue d'ensemble avec statistiques clés
- **Graphiques visuels** : Répartition des dépenses, évolution temporelle, dépenses hebdomadaires
- **Rapports détaillés** : Analyse par catégorie et période
- **Archives mensuelles** : Historique complet des cycles budgétaires (du 27 au 26)

### 🔔 Système d'Alertes Intelligent
- **Alertes échelonnées** : Système rotatif pour éviter la surcharge de notifications
- **Alertes immédiates** : Pour les dépassements critiques et objectifs atteints
- **Notifications Telegram** : Intégration bot Telegram pour recevoir les alertes
- **Types d'alertes** :
  - Dépassement de budget (80%, 100%)
  - Dépenses importantes (>10,000 FCFA)
  - Limites journalières (>8,000 FCFA, >10,000 FCFA)
  - Objectifs d'épargne atteints
  - Inactivité prolongée
  - Encouragements pour faible dépense

### 🎯 Objectifs d'Épargne
- **Objectifs personnalisés** : Créez des objectifs d'épargne avec échéances
- **Suivi de progression** : Barres de progression et calculs automatiques
- **Conseils personnalisés** : Suggestions de montants mensuels/hebdomadaires

### 📱 Interface Utilisateur
- **Design moderne** : Interface Bootstrap 5 avec thème violet personnalisé
- **Responsive** : Compatible mobile et desktop
- **Navigation par onglets** : Dashboard, Budgets, Historique, Rapports, Épargne, Alertes
- **Filtres avancés** : Recherche et filtrage des dépenses par date, catégorie, montant

## 🛠️ Installation et Configuration

### Prérequis
- **Serveur web** : Apache/Nginx avec PHP 7.4+
- **Base de données** : SQLite (inclus)
- **Extensions PHP** : PDO, PDO_SQLite
- **Bot Telegram** (optionnel pour les alertes)

### Installation Rapide
1. **Clonez ou téléchargez** les fichiers dans votre répertoire web (ex: `htdocs/moneyminder`)

2. **Permissions** : Assurez-vous que PHP peut écrire dans le dossier `data/`
   ```bash
   chmod 755 data/
   ```

3. **Accès web** : Ouvrez `http://localhost/moneyminder/index.php` dans votre navigateur

4. **Configuration initiale** :
   - L'application s'initialise automatiquement avec un utilisateur par défaut
   - Configurez vos budgets via l'onglet "Budgets"
   - Ajoutez vos premières dépenses

### Configuration Telegram (Optionnel)
1. Créez un bot Telegram via [@BotFather](https://t.me/botfather)
2. Obtenez votre TOKEN_API
3. Modifiez `telegram_bot.php` :
   ```php
   private $botToken = 'VOTRE_TOKEN_API';
   private $chatId = 'VOTRE_CHAT_ID';
   ```

### Configuration des Alertes Automatisées (Windows)
1. Ouvrez le Planificateur de tâches Windows (`taskschd.msc`)
2. Créez une nouvelle tâche : "MoneyMinder Alertes"
3. Configurez pour exécuter quotidiennement, répétition toutes les heures
4. Programme : `C:\xampp\htdocs\moneyminder\send_alerts.bat`

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

## 🐛 Dépannage

### Problèmes Courants
- **Base de données inaccessible** : Vérifiez les permissions du dossier `data/`
- **Alertes non reçues** : Vérifiez la configuration Telegram
- **Graphiques ne s'affichent pas** : Vérifiez la console navigateur pour les erreurs JavaScript

### Logs et Debug
- Activez les logs PHP pour le débogage
- Utilisez `debug_db.php` pour inspecter la base de données
- Vérifiez les logs du Planificateur de tâches Windows

## 🤝 Contribution

1. Fork le projet
2. Créez une branche pour votre fonctionnalité (`git checkout -b feature/nouvelle-fonction`)
3. Committez vos changements (`git commit -am 'Ajout nouvelle fonctionnalité'`)
4. Pushez vers la branche (`git push origin feature/nouvelle-fonction`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.

## 🙏 Remerciements

- Interface inspirée des meilleures pratiques UX/UI
- Icônes Font Awesome
- Framework Bootstrap
- Bibliothèque Chart.js

---

**Développé avec ❤️ pour une gestion budgétaire simple et efficace**
