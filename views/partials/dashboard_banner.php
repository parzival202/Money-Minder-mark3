<?php if ($banner_type): ?>
<div class="alert alert-<?php echo $banner_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
    <i class="fas <?php echo $banner_icon; ?> me-2"></i>
    <div><?php echo $banner_message; ?></div>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($living_budget)): ?>
<div class="alert alert-light border d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="fas fa-wallet text-primary"></i>
    <div>
        Il te reste <strong><?php echo formatCurrency($living_budget['recommended_daily_max'] ?? 0); ?></strong> par jour
        et <strong><?php echo formatCurrency($living_budget['remaining_month_budget'] ?? 0); ?></strong> pour le mois.
        <?php if (($living_budget['projected_overrun'] ?? 0) > 0): ?>
            <span class="text-danger">Attention : à ce rythme, tu dépasseras ton budget de <?php echo formatCurrency($living_budget['projected_overrun']); ?>.</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (($money_guard['status'] ?? '') === 'black'): ?>
<div class="alert alert-dark d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="fas fa-ban"></i>
    <div><strong>Stop dépenses aujourd’hui.</strong> Toute nouvelle dépense non essentielle risque d’aggraver ton mois.</div>
</div>
<?php endif; ?>
