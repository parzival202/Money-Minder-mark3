<?php
$recents = $expenses;
usort($recents, fn($a,$b) => strtotime($b['created_at'] ?? $b['date']) - strtotime($a['created_at'] ?? $a['date']));
$recents = array_slice($recents, 0, 2);
?>
<div class="card">
    <div class="card-header fw-semibold">Dépenses Récentes</div>
    <div class="card-body">
        <?php if (!empty($recents)): foreach ($recents as $exp): ?>
        <div class="mb-3 p-3 border rounded">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold"><?php echo htmlspecialchars($exp['description']); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($exp['category']); ?></div>
                    <div class="text-danger fw-semibold">-<?php echo formatCurrency($exp['amount']); ?></div>
                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($exp['date'])); ?></small>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#editExpenseModal"
                        data-id="<?php echo $exp['id']; ?>"
                        data-description="<?php echo htmlspecialchars($exp['description']); ?>"
                        data-amount="<?php echo $exp['amount']; ?>"
                        data-category="<?php echo htmlspecialchars($exp['category']); ?>"
                        data-date="<?php echo $exp['date']; ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="delete_expense" value="<?php echo $exp['id']; ?>">
                        <input type="hidden" name="current_tab" value="dashboard">
                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; else: ?>
            <p class="text-muted small">Aucune dépense récente.</p>
        <?php endif; ?>
    </div>
</div>
