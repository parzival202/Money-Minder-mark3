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

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="stat-label">Reste à vivre / jour</div>
            <div class="stat-value text-primary"><?php echo formatCurrency($living_budget['recommended_daily_max'] ?? 0); ?></div>
            <div class="small text-muted mt-2">Jusqu'à la fin du cycle</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100" style="border-left:6px solid <?php echo htmlspecialchars($money_guard['color'] ?? '#16a34a'); ?>;">
            <div class="stat-label">Statut du jour</div>
            <div class="stat-value" style="color:<?php echo htmlspecialchars($money_guard['color'] ?? '#16a34a'); ?>;">
                <?php echo htmlspecialchars($money_guard['label'] ?? 'Vert'); ?>
            </div>
            <div class="small mt-2"><?php echo htmlspecialchars($money_guard['message'] ?? ''); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="stat-label">Dépense du jour</div>
            <div class="stat-value text-danger"><?php echo formatCurrency($money_guard['today_spent'] ?? 0); ?></div>
            <div class="small text-muted mt-2">Limite : <?php echo formatCurrency($money_guard['daily_limit'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="stat-label">Catégorie la plus dangereuse</div>
            <div class="fw-bold mt-2"><?php echo htmlspecialchars($money_guard['danger_category']['category'] ?? 'RAS'); ?></div>
            <div class="small text-muted mt-2">
                <?php echo isset($money_guard['danger_category']['percent']) ? number_format($money_guard['danger_category']['percent'], 1) . '% du budget' : 'Aucune alerte catégorie'; ?>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#purchaseAdvisorModal">
        <i class="fas fa-hand-holding-dollar me-1"></i>Je veux acheter quelque chose
    </button>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
        <i class="fas fa-plus me-1"></i>Ajouter une dépense
    </button>
    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#dailyCheckinModal">
        <i class="fas fa-calendar-check me-1"></i>Journée sans dépense
    </button>
    <form method="POST" class="d-inline-flex align-items-center gap-2 ms-auto">
        <input type="hidden" name="toggle_strict_mode" value="1">
        <input type="hidden" name="strict_mode_enabled" value="<?php echo $strict_mode_enabled ? '0' : '1'; ?>">
        <button class="btn btn-sm <?php echo $strict_mode_enabled ? 'btn-dark' : 'btn-outline-secondary'; ?>">
            <i class="fas fa-shield-halved me-1"></i><?php echo $strict_mode_enabled ? 'Mode strict actif' : 'Activer le mode strict'; ?>
        </button>
    </form>
</div>
