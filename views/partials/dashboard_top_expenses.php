<div class="card p-4">
    <h6 class="fw-bold text-muted mb-3"><i class="fas fa-fire me-2 text-danger"></i>Top dépenses du mois</h6>
    <?php if (!empty($top3) && $total_expenses > 0): ?>
        <?php foreach ($top3 as $cat => $d):
            $bc = $d['percent'] < 60 ? 'bg-success' : ($d['percent'] < 85 ? 'bg-warning' : 'bg-danger'); ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
                <span class="fw-semibold"><?php echo htmlspecialchars($cat); ?></span>
                <span class="text-muted small"><?php echo formatCurrency($d['spent']); ?> / <?php echo formatCurrency($d['budget']); ?></span>
            </div>
            <div class="progress" style="height:8px;">
                <div class="progress-bar <?php echo $bc; ?>" style="width:<?php echo min($d['percent'],100); ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted small mb-0">Aucune dépense enregistrée ce mois-ci.</p>
    <?php endif; ?>
</div>
