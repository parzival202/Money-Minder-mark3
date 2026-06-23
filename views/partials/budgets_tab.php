<div class="tab-pane fade" id="budgets" role="tabpanel">
    <div class="row">
        <div class="col-md-8">
            <div class="card p-4">
                <h5 class="fw-bold mb-1" style="color:#4B5563;"><i class="fas fa-chart-line me-2"></i>Progression Budget</h5>
                <p class="text-muted mb-4" style="font-size:.88rem;">Utilisation des budgets par catégorie</p>
                <?php
                $dot_colors = ['Alimentation'=>'#1E40AF','Transport'=>'#3b82f6','Loisirs/Sortie'=>'#8b5cf6','Mode'=>'#ec4899','Aide proche'=>'#10b981','Abonnement mensuel'=>'#f59e0b','Épargne'=>'#b91c1c'];
                $sorted_budgets = $budgets;
                ksort($sorted_budgets, SORT_NATURAL | SORT_FLAG_CASE);
                foreach ($sorted_budgets as $category => $budget):
                    if (floatval($budget) <= 0) continue;
                    $spent     = calculateCategoryExpenses($category, $user_id);
                    $used_pct  = $budget > 0 ? round(($spent / $budget) * 100, 1) : 0;
                    $dot_color = $dot_colors[$category] ?? '#6b7280';
                ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-dot" style="background:<?php echo $dot_color; ?>;"></span>
                            <span class="fw-semibold" style="color:#374151;"><?php echo htmlspecialchars($category); ?></span>
                        </div>
                        <span style="font-size:.85rem;color:#4b5563;"><?php echo number_format($spent,0,',',' '); ?> / <?php echo number_format($budget,0,',',' '); ?> FCFA</span>
                    </div>
                    <div class="d-flex justify-content-end mb-1">
                        <small style="color:#6b7280;"><?php echo number_format($used_pct,1); ?>% utilisé</small>
                    </div>
                    <div class="progress" style="height:8px;border-radius:4px;background:#e5e7eb;">
                        <div class="progress-bar" style="width:<?php echo min(100,$used_pct); ?>%;background:<?php echo $dot_color; ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editBudgetsModal"><i class="fas fa-edit me-1"></i>Modifier</button>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addBudgetCategoryModal"><i class="fas fa-plus me-1"></i>Ajouter</button>
                    <button class="btn btn-outline-info btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#recalibrateBudgetModal">
                            <i class="fas fa-sliders-h me-1"></i>Recalibrer 50/30/20
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header fw-semibold">Budget vs Dépenses</div>
                <div class="card-body"><div class="chart-container"><canvas id="budgetComparisonChart"></canvas></div></div>
            </div>
        </div>
    </div>
</div>
