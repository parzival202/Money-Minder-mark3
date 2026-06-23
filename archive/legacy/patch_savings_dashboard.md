# Deux modifications dans `index.php`

---

## Modification 1 — Carte "Reste" dans le dashboard

### Où
Dans le tab `#dashboard`, trouve la stat card "Reste".

### Remplace par

Ajouter un bloc conditionnel affichant la marge de sécurité (`max($monthly_budget - array_sum($budgets), 0)`) avec icône shield, uniquement si > 0.

---

## Modification 2 — Onglet Épargne : remplacer la barre par la carte "Santé financière"

### Où
Dans le tab `#savings`, trouve le bloc `col-md-6` de gauche qui contient
la carte "Objectif d'Épargne Mensuel".

### Remplace l'intégralité de ce `col-md-6`

Par une carte "Santé financière du mois" avec :

- taux d'épargne réel ;
- ratio besoins vs envies ;
- projection fin de cycle ;
- marge de sécurité non allouée.
