<?php
date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . '/auth.php';
requireAuth();

require_once __DIR__ . '/db.php';
init_db();

$user_id = getCurrentUserId();
$current_user = fetchUserById($user_id);
ensureUserBudgetMetaConsistency($user_id);

if (!userNeedsBudgetSetup($user_id)) {
    header('Location: index.php');
    exit;
}

$template = getBudgetTemplateRatios();
$sourceBudgets = $template['source_budgets'];
$sourceMonthlyBudget = array_sum($sourceBudgets);
$sourceSavings = (float)($sourceBudgets['Épargne'] ?? 0);
$defaultMonthlyBudget = $sourceMonthlyBudget > 0 ? $sourceMonthlyBudget : 200000;
$defaultSavings = $sourceSavings > 0 ? $sourceSavings : round($defaultMonthlyBudget * 0.25);

$error = null;
$submittedMonthlyBudget = isset($_POST['monthly_budget']) ? (float)$_POST['monthly_budget'] : $defaultMonthlyBudget;
$submittedSavings = isset($_POST['saving_amount']) ? (float)$_POST['saving_amount'] : $defaultSavings;
$suggestedBudgets = suggestBudgetsFromMonthlyTarget($submittedMonthlyBudget, $submittedSavings);
$budgetInputs = $suggestedBudgets;

if (!empty($_POST['budgets']) && is_array($_POST['budgets'])) {
    foreach ($_POST['budgets'] as $category => $amount) {
        $budgetInputs[$category] = max((float)$amount, 0);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        $monthlyBudget = max((float)($_POST['monthly_budget'] ?? 0), 0);
        $savingAmount = max((float)($_POST['saving_amount'] ?? 0), 0);
        $finalBudgets = [];
        foreach (($template['category_ratios'] + ['Épargne' => 0]) as $category => $_ratio) {
            $finalBudgets[$category] = max((float)($_POST['budgets'][$category] ?? 0), 0);
        }
        $finalBudgets['Épargne'] = $savingAmount;
        $budgetTotal = array_sum($finalBudgets);

        if ($monthlyBudget <= 0) {
            $error = 'Veuillez définir un budget mensuel supérieur à 0.';
        } elseif ($savingAmount > $monthlyBudget) {
            $error = 'L’épargne ne peut pas dépasser le budget mensuel.';
        } elseif (abs($budgetTotal - $monthlyBudget) > 1) {
            $error = 'La somme des catégories doit correspondre au budget total.';
        } else {
            setMeta('monthly_budget', $monthlyBudget, $user_id);
            setBudgets($user_id, $finalBudgets);
            header('Location: index.php?budgets_updated=1');
            exit;
        }
        $budgetInputs = $finalBudgets;
    }
}

$csrf = generateCsrfToken();
$currentName = getUserDisplayName($current_user);
$categoryRatiosJson = json_encode($template['category_ratios'], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration initiale - MoneyMinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #eef2ff 0%, #ffffff 45%, #f8fafc 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 2rem 1rem 3rem;
        }
        .setup-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(76, 29, 149, 0.12);
            overflow: hidden;
        }
        .setup-hero {
            background: linear-gradient(135deg, #6d28d9, #4c1d95);
            color: #fff;
            padding: 2rem;
        }
        .hint-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
        }
    </style>
</head>
<body>
<div class="setup-shell">
    <div class="setup-card bg-white">
        <div class="setup-hero">
            <h2 class="fw-bold mb-2">Bienvenue <?php echo htmlspecialchars($currentName); ?></h2>
            <p class="mb-0">Définissons ton budget du mois. MoneyMinder propose une répartition basée sur le profil de référence de localuser, puis tu peux l’ajuster avant validation.</p>
        </div>
        <div class="p-4 p-md-5">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="hint-card p-3 mb-4">
                <div class="fw-semibold mb-1">Base de calcul actuelle</div>
                <small class="text-muted">Référence utilisée : `localuser`, avec une structure de catégories conservée et une épargne de référence de <?php echo number_format($sourceSavings, 0, ',', ' '); ?> FCFA sur un total de <?php echo number_format($sourceMonthlyBudget, 0, ',', ' '); ?> FCFA.</small>
            </div>

            <form method="POST" id="setupBudgetForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Budget mensuel total</label>
                        <input type="number" class="form-control" min="0" step="1" name="monthly_budget" id="monthlyBudgetInput" value="<?php echo htmlspecialchars((string)round($submittedMonthlyBudget)); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Montant à épargner</label>
                        <input type="number" class="form-control" min="0" step="1" name="saving_amount" id="savingAmountInput" value="<?php echo htmlspecialchars((string)round($submittedSavings)); ?>" required>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="fw-semibold">Répartition proposée</div>
                        <small class="text-muted">Le reste du budget est ventilé automatiquement selon les pourcentages observés chez localuser.</small>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="recalculateBtn">Recalculer</button>
                </div>

                <div class="row g-3 mb-4" id="budgetRows">
                    <?php foreach ($budgetInputs as $category => $amount): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo htmlspecialchars($category); ?></label>
                        <input
                            type="number"
                            class="form-control budget-category-input"
                            min="0"
                            step="1"
                            data-category="<?php echo htmlspecialchars($category); ?>"
                            name="budgets[<?php echo htmlspecialchars($category); ?>]"
                            value="<?php echo htmlspecialchars((string)round($amount)); ?>"
                            <?php echo $category === 'Épargne' ? 'readonly' : ''; ?>>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-light border d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Contrôle du total</div>
                        <small class="text-muted">La somme des catégories doit correspondre au budget mensuel.</small>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Écart</div>
                        <div class="fw-bold" id="budgetDifference">0 FCFA</div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary px-4">Valider et ouvrir le dashboard</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const categoryRatios = <?php echo $categoryRatiosJson; ?>;
const monthlyBudgetInput = document.getElementById('monthlyBudgetInput');
const savingAmountInput = document.getElementById('savingAmountInput');
const budgetDifference = document.getElementById('budgetDifference');
const recalculateBtn = document.getElementById('recalculateBtn');

function formatFcfa(value) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(value)) + ' FCFA';
}

function getCategoryInputs() {
    return Array.from(document.querySelectorAll('.budget-category-input'));
}

function recalculateBudgets() {
    const monthly = Math.max(parseFloat(monthlyBudgetInput.value || '0'), 0);
    const saving = Math.max(parseFloat(savingAmountInput.value || '0'), 0);
    const allocatable = Math.max(monthly - saving, 0);
    const inputs = getCategoryInputs().filter((input) => input.dataset.category !== 'Épargne');
    let used = 0;

    inputs.forEach((input, index) => {
        const ratio = categoryRatios[input.dataset.category] || 0;
        const isLast = index === inputs.length - 1;
        const amount = isLast ? Math.max(allocatable - used, 0) : Math.round(allocatable * ratio);
        input.value = amount;
        used += amount;
    });

    const savingInput = document.querySelector('[data-category="Épargne"]');
    if (savingInput) savingInput.value = Math.round(saving);
    updateDifference();
}

function updateDifference() {
    const monthly = Math.max(parseFloat(monthlyBudgetInput.value || '0'), 0);
    const total = getCategoryInputs().reduce((sum, input) => sum + Math.max(parseFloat(input.value || '0'), 0), 0);
    const diff = Math.round(monthly - total);
    budgetDifference.textContent = formatFcfa(diff);
    budgetDifference.className = diff === 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
}

recalculateBtn.addEventListener('click', recalculateBudgets);
monthlyBudgetInput.addEventListener('input', updateDifference);
savingAmountInput.addEventListener('input', () => {
    const savingInput = document.querySelector('[data-category="Épargne"]');
    if (savingInput) savingInput.value = Math.round(Math.max(parseFloat(savingAmountInput.value || '0'), 0));
    updateDifference();
});
getCategoryInputs().forEach((input) => input.addEventListener('input', updateDifference));
updateDifference();
</script>
</body>
</html>
