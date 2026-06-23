<div class="row g-3 mb-2">
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Budget Mensuel</div>
            <div class="stat-value text-primary"><?php echo formatCurrency($monthly_budget); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Dépenses</div>
            <div class="stat-value text-danger">-<?php echo formatCurrency($total_expenses); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Reste</div>
            <div class="stat-value <?php echo $remaining_budget >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo formatCurrency($remaining_budget); ?>
            </div>
            <?php
            $allocated_total = array_sum($budgets);
            $safety_margin = max($monthly_budget - $allocated_total, 0);
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
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="stat-label">Moy / jour</div>
            <div class="stat-value"><?php echo formatCurrency(round($daily_average)); ?></div>
        </div>
    </div>
</div>
