# Plan de modifications — patch_savings_dashboard.md

## Information Gathered
- Fichier cible : `index.php`
- Le patch demande 2 modifications dans `index.php`
- Variables déjà disponibles : `$remaining_budget`, `$budgets`, `$monthly_budget`, `$total_expenses`, `$daily_average`, `$days_remaining`, `$current_savings`, `$expenses`, `formatCurrency()`

## Plan détaillé

### ✅ Étape 1 : Modification 1 — Carte "Reste" dans le dashboard
- **Localisation** : Dans le tab `#dashboard`, la 3ème stat card (`col-6 col-md-3`) avec le label "Reste"
- **Action** : Ajouter après le `div.stat-value` un bloc conditionnel affichant la marge de sécurité (`max($monthly_budget - array_sum($budgets), 0)`) avec icône shield, uniquement si > 0

### ✅ Étape 2 : Modification 2 — Onglet Épargne : remplacer la barre par la carte "Santé financière"
- **Localisation** : Dans le tab `#savings`, le premier `<div class="col-md-6">` contenant la carte "Objectif d'Épargne Mensuel"
- **Action** : Remplacer l'intégralité de ce bloc par la nouvelle carte "Santé financière du mois" avec :
  1. Taux d'épargne réel (barre + badge)
  2. Ratio 50/30 besoins/envies (barre bicolore)
  3. Projection fin de cycle (barre + badge)
  4. Marge de sécurité non allouée (conditionnelle)

## Fichiers dépendants
- Aucun autre fichier à modifier

## Follow-up steps
- ✅ Vérifier le rendu dans le navigateur
- ✅ S'assurer qu'aucune variable n'est manquante
