# Deux modifications dans `index.php`

---

## Modification 1 — Carte "Reste" dans le dashboard

### Où
Dans le tab `#dashboard`, trouve la stat card "Reste" :

```php
<div class="col-6 col-md-3">
    <div class="card stat-card">
        <div class="stat-label">Reste</div>
        <div class="stat-value <?php echo $remaining_budget >= 0 ? 'text-success' : 'text-danger'; ?>">
            <?php echo formatCurrency($remaining_budget); ?>
        </div>
    </div>
</div>
```

### Remplace par

```php
<div class="col-6 col-md-3">
    <div class="card stat-card">
        <div class="stat-label">Reste</div>
        <div class="stat-value <?php echo $remaining_budget >= 0 ? 'text-success' : 'text-danger'; ?>">
            <?php echo formatCurrency($remaining_budget); ?>
        </div>
        <?php
        // Marge de sécurité = 20% du budget distribuable non alloué aux catégories
        $allocated_total    = array_sum($budgets);
        $safety_margin      = max($monthly_budget - $allocated_total, 0);
        if ($safety_margin > 0):
        ?>
        <div class="mt-2 pt-2 border-top">
            <small class="text-muted d-flex align-items-center justify-content-center gap-1">
                <i class="fas fa-shield-alt text-success" style="font-size:.7rem;"></i>
                dont <strong class="text-success mx-1"><?php echo formatCurrency($safety_margin); ?></strong> marge
            </small>
        </div>
        <?php endif; ?>
    </div>
</div>
```

---

## Modification 2 — Onglet Épargne : remplacer la barre par la carte "Santé financière"

### Où
Dans le tab `#savings`, trouve le bloc `col-md-6` de gauche qui contient
la carte "Objectif d'Épargne Mensuel". Il ressemble à :

```html
<div class="col-md-6">
    <div class="card mt-3">
        <div class="card-header ...">
            Objectif d'Épargne Mensuel
            ...
        </div>
        <div class="card-body">
            <p class="text-muted small mb-1">Épargne ce mois</p>
            <div class="fw-bold fs-4 mb-3">...</div>
            ...
            <div class="progress savings-progress">
                <div class="progress-bar bg-success" style="width:...%"></div>
            </div>
            <div class="text-center mt-2 small">...%</div>
            ...
        </div>
    </div>
</div>
```

### Remplace l'intégralité de ce `col-md-6` par

```php
<div class="col-md-6">

    <!-- Carte Santé Financière du mois -->
    <div class="card mt-3">
        <div class="card-header fw-semibold d-flex align-items-center gap-2">
            <i class="fas fa-heartbeat text-danger"></i>
            Santé financière du mois
        </div>
        <div class="card-body">
            <?php
            // ── Calculs pour les 3 indicateurs ──────────────────
            $allocated_total = array_sum($budgets);
            $safety_margin   = max($monthly_budget - $allocated_total, 0);

            // 1. Taux d'épargne réel
            $real_savings_rate = $monthly_budget > 0
                ? ($current_savings / $monthly_budget) * 100
                : 0;
            if ($real_savings_rate >= 20) {
                $savings_score = 'good';
                $savings_label = 'Excellent';
                $savings_color = '#16a34a';
                $savings_icon  = 'fa-circle-check';
            } elseif ($real_savings_rate >= 10) {
                $savings_score = 'ok';
                $savings_label = 'Correct';
                $savings_color = '#d97706';
                $savings_icon  = 'fa-circle-minus';
            } else {
                $savings_score = 'bad';
                $savings_label = 'Insuffisant';
                $savings_color = '#dc2626';
                $savings_icon  = 'fa-circle-xmark';
            }

            // 2. Ratio besoins vs envies (basé sur les dépenses réelles)
            $needs_cats  = ['Alimentation', 'Transport', 'Abonnement mensuel'];
            $wants_cats  = ['Loisirs/Sortie', 'Mode', 'Aide proche'];
            $needs_spent = 0; $wants_spent = 0;
            foreach ($expenses as $e) {
                if (in_array($e['category'], $needs_cats)) $needs_spent += floatval($e['amount']);
                if (in_array($e['category'], $wants_cats)) $wants_spent += floatval($e['amount']);
            }
            $needs_pct = $monthly_budget > 0 ? round(($needs_spent / $monthly_budget) * 100, 1) : 0;
            $wants_pct = $monthly_budget > 0 ? round(($wants_spent / $monthly_budget) * 100, 1) : 0;
            $ratio_ok  = ($needs_pct <= 52 && $wants_pct <= 32);
            $ratio_color = $ratio_ok ? '#16a34a' : '#d97706';
            $ratio_icon  = $ratio_ok ? 'fa-circle-check' : 'fa-circle-minus';
            $ratio_label = $ratio_ok ? 'Équilibré' : 'À surveiller';

            // 3. Projection fin de mois
            $projected_end = $total_expenses + ($daily_average * $days_remaining);
            if ($projected_end <= $monthly_budget * 0.80) {
                $proj_color = '#16a34a'; $proj_icon = 'fa-circle-check'; $proj_label = 'Bonne trajectoire';
            } elseif ($projected_end <= $monthly_budget) {
                $proj_color = '#d97706'; $proj_icon = 'fa-circle-minus'; $proj_label = 'Limite acceptable';
            } else {
                $proj_color = '#dc2626'; $proj_icon = 'fa-circle-xmark'; $proj_label = 'Dépassement probable';
            }
            ?>

            <!-- Indicateur 1 : Taux d'épargne -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-piggy-bank" style="color:<?php echo $savings_color; ?>;"></i>
                        <span class="fw-semibold" style="font-size:.9rem;">Taux d'épargne</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold" style="color:<?php echo $savings_color; ?>;">
                            <?php echo number_format($real_savings_rate, 1); ?>%
                        </span>
                        <i class="fas <?php echo $savings_icon; ?>" style="color:<?php echo $savings_color; ?>;"></i>
                        <span class="badge rounded-pill" style="background:<?php echo $savings_color; ?>;font-size:.72rem;">
                            <?php echo $savings_label; ?>
                        </span>
                    </div>
                </div>
                <div class="progress" style="height:8px;border-radius:6px;background:#e5e7eb;">
                    <div class="progress-bar" style="width:<?php echo min($real_savings_rate, 100); ?>%;background:<?php echo $savings_color; ?>;border-radius:6px;"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted" style="font-size:.72rem;">Objectif recommandé : 20%</small>
                    <small class="text-muted" style="font-size:.72rem;"><?php echo formatCurrency($current_savings); ?> épargnés</small>
                </div>
            </div>

            <!-- Indicateur 2 : Ratio besoins/envies -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-scale-balanced" style="color:<?php echo $ratio_color; ?>;"></i>
                        <span class="fw-semibold" style="font-size:.9rem;">Ratio 50/30</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas <?php echo $ratio_icon; ?>" style="color:<?php echo $ratio_color; ?>;"></i>
                        <span class="badge rounded-pill" style="background:<?php echo $ratio_color; ?>;font-size:.72rem;">
                            <?php echo $ratio_label; ?>
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-1" style="height:8px;border-radius:6px;overflow:hidden;">
                    <div style="width:<?php echo min($needs_pct, 60); ?>%;background:#2563EB;border-radius:6px 0 0 6px;" title="Besoins : <?php echo $needs_pct; ?>%"></div>
                    <div style="width:<?php echo min($wants_pct, 40); ?>%;background:#7C3AED;" title="Envies : <?php echo $wants_pct; ?>%"></div>
                    <div style="flex:1;background:#e5e7eb;border-radius:0 6px 6px 0;"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small style="color:#2563EB;font-size:.72rem;"><i class="fas fa-circle me-1" style="font-size:.55rem;"></i>Besoins <?php echo $needs_pct; ?>% (max 50%)</small>
                    <small style="color:#7C3AED;font-size:.72rem;"><i class="fas fa-circle me-1" style="font-size:.55rem;"></i>Envies <?php echo $wants_pct; ?>% (max 30%)</small>
                </div>
            </div>

            <!-- Indicateur 3 : Projection fin de mois -->
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-chart-line" style="color:<?php echo $proj_color; ?>;"></i>
                        <span class="fw-semibold" style="font-size:.9rem;">Projection fin de cycle</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas <?php echo $proj_icon; ?>" style="color:<?php echo $proj_color; ?>;"></i>
                        <span class="badge rounded-pill" style="background:<?php echo $proj_color; ?>;font-size:.72rem;">
                            <?php echo $proj_label; ?>
                        </span>
                    </div>
                </div>
                <div class="progress" style="height:8px;border-radius:6px;background:#e5e7eb;">
                    <?php $proj_pct = $monthly_budget > 0 ? min(($projected_end / $monthly_budget) * 100, 100) : 0; ?>
                    <div class="progress-bar" style="width:<?php echo $proj_pct; ?>%;background:<?php echo $proj_color; ?>;border-radius:6px;"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted" style="font-size:.72rem;">Projection : <?php echo formatCurrency(round($projected_end)); ?></small>
                    <small class="text-muted" style="font-size:.72rem;">Budget : <?php echo formatCurrency($monthly_budget); ?></small>
                </div>
            </div>

            <!-- Marge de sécurité -->
            <?php if ($safety_margin > 0): ?>
            <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                <small class="text-muted d-flex align-items-center gap-1">
                    <i class="fas fa-shield-alt text-success"></i>
                    Marge de sécurité non allouée
                </small>
                <span class="fw-bold text-success"><?php echo formatCurrency($safety_margin); ?></span>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>
```

---

## Résultat attendu

**Dashboard** — La carte "Reste" affiche désormais sous le montant :
`dont 37 000 FCFA marge 🛡️` (uniquement si la marge est > 0)

**Onglet Épargne** — La grosse barre est remplacée par 3 indicateurs :
1. **Taux d'épargne** — barre colorée + badge Excellent/Correct/Insuffisant
2. **Ratio 50/30** — barre bicolore besoins vs envies vs objectif
3. **Projection fin de cycle** — barre + badge Bonne trajectoire / Dépassement probable

Et en bas de la carte, la **marge de sécurité** affichée clairement.
