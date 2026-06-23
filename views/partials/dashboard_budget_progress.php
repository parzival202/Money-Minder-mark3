<div class="card px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-semibold text-muted">Consommation du budget mensuel</span>
        <span class="fw-bold"><?php echo number_format($budget_used_percent, 1); ?>%</span>
    </div>
    <div class="progress mb-1" style="height:12px;border-radius:6px;">
        <div class="progress-bar <?php echo $bar_color; ?>" style="width:<?php echo $budget_used_percent; ?>%;"></div>
    </div>
    <div class="d-flex justify-content-between">
        <small class="text-muted">0 FCFA</small>
        <small class="text-muted"><?php echo formatCurrency($monthly_budget); ?></small>
    </div>
    <hr class="my-2">
    <div class="d-flex justify-content-between">
        <small class="text-muted">
            <i class="fas fa-calendar-alt me-1"></i>
            Cycle : <?php echo $cycle_start->format('d/m'); ?> → <?php echo $cycle_end->format('d/m/Y'); ?>
        </small>
        <small class="text-muted">
            Jour <strong><?php echo $days_elapsed; ?></strong>/<?php echo $days_total; ?>
            — <strong><?php echo $days_remaining; ?></strong> restant(s)
        </small>
    </div>
</div>
