<?php
// ============================================================
// recalibrate_budget_modal.php
// Modal de recalibrage budgétaire 50/30/20
//
// INSTRUCTIONS D'INTÉGRATION :
// 1. Dans index.php, ajoute en haut du fichier :
//    require_once __DIR__ . '/budget_algorithm.php';
//
// 2. Dans le bloc POST de index.php, ajoute :
//    if (isset($_POST['recalibrate_budget'])) {
//        $new_monthly  = floatval($_POST['recal_monthly_budget']);
//        $new_savings  = floatval($_POST['recal_savings_goal']);
//        if ($new_monthly > 0) {
//            applyOptimalBudgets($user_id, $new_monthly, $new_savings, true);
//        }
//        header('Location: ' . $_SERVER['PHP_SELF'] . '?recalibrated=1&tab=budgets'); exit;
//    }
//
// 3. Dans le bloc toast JS de index.php, ajoute :
//    if (p.has('recalibrated')) showToast('Budgets recalibrés avec la règle 50/30/20 !');
//
// 4. Dans le header ou l'onglet Budgets, ajoute le bouton déclencheur :
//    <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#recalibrateBudgetModal">
//        <i class="fas fa-sliders-h me-1"></i>Recalibrer
//    </button>
//
// 5. Include ce fichier juste avant </body> dans index.php :
//    <?php include __DIR__ . '/recalibrate_budget_modal.php'; ?>

<!-- Modal Recalibrage Budgétaire -->
<div class="modal fade" id="recalibrateBudgetModal" tabindex="-1" aria-labelledby="recalibrateBudgetLabel">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content" id="recalibrateForm">
      <div class="modal-header" style="background:linear-gradient(135deg,#6D28D9,#4C1D95);">
        <div>
          <h5 class="modal-title text-white fw-bold" id="recalibrateBudgetLabel">
            <i class="fas fa-sliders-h me-2"></i>Recalibrer les budgets
          </h5>
          <small class="text-white opacity-75">Règle 50 / 30 / 20</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- Explication de la règle -->
        <div class="alert alert-info d-flex gap-3 align-items-start mb-4 py-3">
          <i class="fas fa-info-circle fa-lg mt-1 flex-shrink-0"></i>
          <div style="font-size:.9rem;">
            <strong>Comment ça marche ?</strong><br>
            L'algorithme répartit ton budget selon la règle 50/30/20 :
            <span class="badge bg-danger ms-1">20% Épargne</span>
            <span class="badge bg-primary ms-1">50% Besoins</span>
            <span class="badge bg-warning text-dark ms-1">30% Envies</span><br>
            <small class="text-muted mt-1 d-block">
              Tes catégories personnalisées seront préservées.
              Les dettes actives sont prises en compte automatiquement.
            </small>
          </div>
        </div>

        <div class="row g-3">

          <!-- Budget mensuel -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">
              Budget mensuel (FCFA)
              <small class="text-muted fw-normal">— montant total disponible</small>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-wallet text-muted"></i></span>
              <input
                type="number"
                class="form-control"
                name="recal_monthly_budget"
                id="recalMonthlyBudget"
                value="<?php echo getMeta('monthly_budget', 0, $user_id); ?>"
                min="10000"
                step="5000"
                required
                oninput="updatePreview()">
            </div>
          </div>

          <!-- Objectif d'épargne -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">
              Objectif d'épargne (FCFA)
              <small class="text-muted fw-normal">— laisser 0 pour appliquer les 20% auto</small>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-piggy-bank text-muted"></i></span>
              <input
                type="number"
                class="form-control"
                name="recal_savings_goal"
                id="recalSavingsGoal"
                value="0"
                min="0"
                step="5000"
                oninput="updatePreview()">
            </div>
          </div>
        </div>

        <!-- Prévisualisation -->
        <div class="mt-4">
          <h6 class="fw-bold text-muted mb-3">
            <i class="fas fa-eye me-2"></i>Prévisualisation de la répartition
          </h6>
          <div id="previewContainer">
            <!-- Généré par JS -->
          </div>
        </div>

        <!-- Avertissement dettes -->
        <?php
        $active_debts_for_modal = array_filter(fetchDebts($user_id), fn($d) => ($d['status'] ?? 'active') === 'active');
        $total_debt_remaining_modal = 0;
        foreach ($active_debts_for_modal as $d) {
            $total_debt_remaining_modal += max(0, floatval($d['total_amount']) - floatval($d['amount_paid']));
        }
        ?>
        <?php if ($total_debt_remaining_modal > 0): ?>
        <div class="alert alert-warning d-flex gap-2 align-items-center mt-3 py-2" style="font-size:.85rem;">
          <i class="fas fa-exclamation-triangle flex-shrink-0"></i>
          <span>
            Tu as <strong><?php echo formatCurrency($total_debt_remaining_modal); ?></strong> de dettes actives.
            L'algorithme prévoira automatiquement une mensualité de remboursement.
          </span>
        </div>
        <?php endif; ?>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="recalibrate_budget" class="btn btn-primary">
          <i class="fas fa-magic me-2"></i>Appliquer la répartition
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Données pour la prévisualisation ──────────────────────────
const DEBT_REMAINING    = <?php echo $total_debt_remaining_modal; ?>;
const DEFAULT_CATS = [
    { name: 'Alimentation',       group: 'needs', weight: 50 },
    { name: 'Transport',          group: 'needs', weight: 30 },
    { name: 'Abonnement mensuel', group: 'needs', weight: 20 },
    { name: 'Loisirs/Sortie',     group: 'wants', weight: 50 },
    { name: 'Mode',               group: 'wants', weight: 33 },
    { name: 'Aide proche',        group: 'wants', weight: 17 },
];

const GROUP_COLORS = {
    savings : { bg: '#DC2626', label: 'Épargne' },
    debts   : { bg: '#F97316', label: 'Remboursement dettes' },
    needs   : { bg: '#2563EB', label: 'Besoin essentiel (50%)' },
    wants   : { bg: '#7C3AED', label: 'Envie / Loisir (30%)' },
};

function round100(n) {
    return Math.round(n / 100) * 100;
}

function updatePreview() {
    const monthly   = parseFloat(document.getElementById('recalMonthlyBudget').value) || 0;
    const savingsIn = parseFloat(document.getElementById('recalSavingsGoal').value)   || 0;

    if (monthly <= 0) {
        document.getElementById('previewContainer').innerHTML =
            '<p class="text-muted small">Saisissez un budget pour voir la prévisualisation.</p>';
        return;
    }

    // Épargne
    const savings = savingsIn > 0
        ? Math.min(savingsIn, monthly * 0.40)
        : Math.round(monthly * 0.20);

    // Provision dettes
    let debtProvision = 0;
    if (DEBT_REMAINING > 0) {
        const estimated = DEBT_REMAINING / 6;
        const maxProv   = monthly * 0.15;
        debtProvision   = Math.round(Math.min(estimated, maxProv));
    }

    // Budget distribuable
    const distributable = Math.max(monthly - savings - debtProvision, 0);
    const needsBudget   = distributable * 0.50;
    const wantsBudget   = distributable * 0.30;

    // Poids totaux
    const totalNeedsW = DEFAULT_CATS.filter(c => c.group === 'needs').reduce((s, c) => s + c.weight, 0);
    const totalWantsW = DEFAULT_CATS.filter(c => c.group === 'wants').reduce((s, c) => s + c.weight, 0);

    // Construire les lignes
    const lines = [];

    lines.push({ name: 'Épargne', amount: Math.round(savings), group: 'savings' });

    if (debtProvision > 0) {
        lines.push({ name: 'Remboursement', amount: debtProvision, group: 'debts' });
    }

    DEFAULT_CATS.forEach(cat => {
        const raw = cat.group === 'needs'
            ? (cat.weight / totalNeedsW) * needsBudget
            : (cat.weight / totalWantsW) * wantsBudget;
        lines.push({ name: cat.name, amount: round100(raw), group: cat.group });
    });

    // Rendu HTML
    let html = '<div class="row g-2">';
    lines.forEach(line => {
        const pct    = monthly > 0 ? ((line.amount / monthly) * 100).toFixed(1) : 0;
        const color  = GROUP_COLORS[line.group]?.bg || '#6b7280';
        const gLabel = GROUP_COLORS[line.group]?.label || '';

        html += `
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="d-flex align-items-center gap-2">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};flex-shrink:0;"></span>
                    <span class="fw-semibold" style="font-size:.9rem;">${line.name}</span>
                    <small class="text-muted">${gLabel}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">${pct}%</small>
                    <strong style="font-size:.9rem;">${line.amount.toLocaleString('fr-FR')} FCFA</strong>
                </div>
            </div>
            <div class="progress" style="height:6px;border-radius:4px;">
                <div class="progress-bar" style="width:${Math.min(pct, 100)}%;background:${color};border-radius:4px;"></div>
            </div>
        </div>`;
    });

    // Total alloué
    const totalAllocated = lines.reduce((s, l) => s + l.amount, 0);
    const remaining = monthly - totalAllocated;
    html += `
    <div class="col-12 mt-2 pt-2 border-top">
        <div class="d-flex justify-content-between">
            <small class="text-muted">Total alloué</small>
            <small class="fw-semibold">${totalAllocated.toLocaleString('fr-FR')} FCFA</small>
        </div>
        <div class="d-flex justify-content-between">
            <small class="text-muted">Marge de sécurité non allouée</small>
            <small class="fw-semibold text-success">+${Math.max(remaining, 0).toLocaleString('fr-FR')} FCFA</small>
        </div>
    </div>`;

    html += '</div>';
    document.getElementById('previewContainer').innerHTML = html;
}

// Déclencher la prévisualisation à l'ouverture du modal
document.getElementById('recalibrateBudgetModal')
    .addEventListener('shown.bs.modal', updatePreview);
</script>