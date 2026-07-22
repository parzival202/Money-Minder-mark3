<?php
// ============================================================
// CONFIGURATION
// ============================================================
date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . '/auth.php'; // démarre la session + fournit requireAuth()
requireAuth();                       // redirige vers login.php si non connecté

require_once __DIR__ . '/db.php';
init_db();
$user_id = getCurrentUserId();       // respecte l'impersonation admin
$current_user = fetchUserById($user_id);
$current_user_display_name = getUserDisplayName($current_user);
if (!isReadOnlyUserView()) {
    ensureUserBudgetMetaConsistency($user_id);
}
if (!isReadOnlyUserView() && userNeedsBudgetSetup($user_id)) {
    header('Location: setup.php');
    exit;
}

require_once __DIR__ . '/budget_algorithm.php';

require_once __DIR__ . '/telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

$dashboardService = new App\Services\DashboardService();
$savingGoalService = new App\Services\SavingGoalService();
$strictModeService = new App\Services\StrictModeService();
$dailyCheckinService = new App\Services\DailyCheckinService();
$purchaseAdvisorService = new App\Services\PurchaseAdvisorService();
$moneyGuardService = new App\Services\MoneyGuardService();
$categoryRepository = new App\Repositories\CategoryRepository();

// ============================================================
// ARCHIVAGE AUTOMATIQUE (26 à 23h59)
// ============================================================
$now    = new DateTime();
$day    = (int)$now->format('d');
$hour   = (int)$now->format('H');
$minute = (int)$now->format('i');

if (!isReadOnlyUserView() && $day == 26 && ($hour > 23 || ($hour == 23 && $minute >= 59))) {
    $archiveResult = archiveCurrentCycle($user_id, $now);
    if ($archiveResult['success']) {
        $__nikolaii->sendMessage(buildArchiveSummaryMessage($archiveResult));
    }
}

// ============================================================
// GESTION DES REQUÊTES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 // Déconnexion
    if (isset($_POST['logout_action'])) {
        logout();
        header('Location: login.php'); exit;
    }

    // Arrêter l'impersonation (bouton dans la bannière)
    if (isset($_POST['stop_impersonate'])) {
        unset($_SESSION['impersonate_user_id']);
        header('Location: index.php'); exit;
    }

    // La consultation d'un autre compte par un administrateur est strictement
    // en lecture seule. Ce controle serveur protege aussi contre un POST forge.
    if (isReadOnlyUserView()) {
        http_response_code(403);
        $_SESSION['read_only_notice'] = 'Consultation en lecture seule : aucune modification n’a été enregistrée.';
        header('Location: index.php?read_only=1');
        exit;
    }
    if (isset($_POST['delete_budget_category'])) {
        $cat = $_POST['delete_budget_category'];
        $budgets = getBudgets($user_id);
        if (isset($budgets[$cat])) {
            global $pdo;
            $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND category = ?")->execute([$user_id, $cat]);
            unset($budgets[$cat]);
            setBudgets($user_id, $budgets);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=dashboard'); exit;
    }

    if (isset($_POST['rename_budget_category'])) {
        $old = trim($_POST['old_category_name']);
        $new = trim($_POST['new_category_name']);
        $budgets = getBudgets($user_id);
        if ($old !== '' && $new !== '' && isset($budgets[$old])) {
            global $pdo;
            $pdo->prepare("UPDATE expenses SET category = ? WHERE user_id = ? AND category = ?")->execute([$new, $user_id, $old]);
            $budgets[$new] = $budgets[$old];
            unset($budgets[$old]);
            setBudgets($user_id, $budgets);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=dashboard'); exit;
    }

    if (isset($_POST['add_budget_category'])) {
        $new_cat = trim($_POST['new_budget_category']);
        $new_amt = floatval($_POST['new_budget_amount']);
        if ($new_cat !== '' && $new_amt > 0) {
            $budgets = getBudgets($user_id);
            $budgets[$new_cat] = $new_amt;
            setBudgets($user_id, $budgets);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=budgets'); exit;
    }

    if (isset($_POST['add_expense'])) {
        $amount      = floatval($_POST['amount']);
        $category    = $_POST['category'];
        $description = trim($_POST['description']);
        $date        = $_POST['date'] ?: date('Y-m-d');
        $purchaseType = ($_POST['purchase_type'] ?? 'need') === 'want' ? 'want' : 'need';
        $existing    = fetchExpenses($user_id);
        $isDuplicate = false;
        foreach ($existing as $e) {
            if ($e['amount'] == $amount && $e['category'] === $category
                && $e['description'] === $description && $e['date'] === $date) {
                $isDuplicate = true; break;
            }
        }
        if (!$isDuplicate) {
            $riskReview = $moneyGuardService->requiresJustification($user_id, $category, $amount);
            if ($riskReview['required'] && empty($_POST['expense_justification'])) {
                $_SESSION['pending_risky_expense'] = [
                    'date' => $date,
                    'category' => $category,
                    'description' => $description,
                    'amount' => $amount,
                    'purchase_type' => $purchaseType,
                ];
                header('Location: ' . $_SERVER['PHP_SELF'] . '?risk_review=1');
                exit;
            }
            insertExpense($user_id, [
                'date' => $date,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'purchase_type' => $purchaseType,
                'justification' => trim($_POST['expense_justification'] ?? ''),
                'acknowledged_risk' => !empty($_POST['acknowledge_risk']) ? 1 : 0,
            ]);
            checkAndSendAlerts($user_id);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?added=1'); exit;
    }

    if (isset($_POST['confirm_risky_expense'])) {
        $pendingExpense = $_SESSION['pending_risky_expense'] ?? null;
        if ($pendingExpense) {
            insertExpense($user_id, [
                'date' => $pendingExpense['date'],
                'category' => $pendingExpense['category'],
                'description' => $pendingExpense['description'],
                'amount' => $pendingExpense['amount'],
                'purchase_type' => $pendingExpense['purchase_type'] ?? 'want',
                'justification' => trim($_POST['expense_justification'] ?? ''),
                'acknowledged_risk' => !empty($_POST['acknowledge_risk']) ? 1 : 0,
            ]);
            unset($_SESSION['pending_risky_expense']);
            checkAndSendAlerts($user_id);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?added=1&risk_saved=1');
            exit;
        }
    }

    if (isset($_POST['toggle_strict_mode'])) {
        $strictModeService->setEnabled($user_id, ($_POST['strict_mode_enabled'] ?? '0') === '1');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?strict_mode_updated=1');
        exit;
    }

    if (isset($_POST['daily_checkin_status'])) {
        if (!$dailyCheckinService->hasTodayCheckin($user_id)) {
            $dailyCheckinService->createToday(
                $user_id,
                trim($_POST['daily_checkin_status']),
                trim($_POST['daily_checkin_note'] ?? '')
            );
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?checkin_done=1');
        exit;
    }

    if (isset($_POST['purchase_advisor_submit'])) {
        $advisorResult = $purchaseAdvisorService->evaluate($user_id, [
            'amount' => $_POST['advisor_amount'] ?? 0,
            'category' => $_POST['advisor_category'] ?? '',
            'type' => $_POST['advisor_type'] ?? 'need',
            'urgency' => $_POST['advisor_urgency'] ?? 'faible',
            'description' => $_POST['advisor_description'] ?? '',
        ], true);
        $_SESSION['purchase_advisor_result'] = $advisorResult;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?advisor_done=1');
        exit;
    }

    if (isset($_POST['update_budgets'])) {
        $budgets = [];
        foreach ($_POST['budgets'] as $category => $amount) $budgets[$category] = floatval($amount);
        setBudgets($user_id, $budgets);
        if (isset($_POST['monthly_budget'])) setMeta('monthly_budget', floatval($_POST['monthly_budget']), $user_id);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=budgets'); exit;
    }

    if (isset($_POST['recalibrate_budget'])) {
    $new_monthly = floatval($_POST['recal_monthly_budget']);
    $new_savings = floatval($_POST['recal_savings_goal']);
    if ($new_monthly > 0) {
        applyOptimalBudgets($user_id, $new_monthly, $new_savings, true);
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?recalibrated=1&tab=budgets'); exit;
}

    if (isset($_POST['delete_expense'])) {
        deleteExpense($user_id, $_POST['delete_expense']);
        checkAndSendAlerts($user_id);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1&tab=' . urlencode($_POST['current_tab'] ?? 'dashboard')); exit;
    }

    if (isset($_POST['edit_expense'])) {
        updateExpense($user_id, $_POST['edit_expense_id'], [
            'amount'      => floatval($_POST['edit_amount']),
            'category'    => $_POST['edit_category'],
            'description' => trim($_POST['edit_description']),
            'date'        => $_POST['edit_date'],
        ]);
        checkAndSendAlerts($user_id);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1&tab=' . urlencode($_POST['current_tab'] ?? 'dashboard')); exit;
    }

    if (isset($_POST['delete_alert'])) {
        markAlertSeen($user_id, intval($_POST['delete_alert']));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=alerts'); exit;
    }

    if (isset($_POST['delete_all_expenses'])) {
        global $pdo;
        $pdo->prepare("DELETE FROM expenses WHERE user_id = ?")->execute([$user_id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted_all=1&tab=expenses'); exit;
    }

    if (isset($_POST['clear_all_alerts'])) {
        clearAllAlerts($user_id);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?alerts_cleared=1&tab=alerts'); exit;
    }

    if (isset($_POST['mark_alerts_seen'])) {
        markAllAlertsSeen($user_id); exit;
    }

    if (isset($_POST['archive_current_month'])) {
        $archiveResult = archiveCurrentCycle($user_id);
        if ($archiveResult['success']) {
            $__nikolaii->sendMessage(buildArchiveSummaryMessage($archiveResult));
            header('Location: ' . $_SERVER['PHP_SELF'] . '?archived=1'); exit;
        }
        if (($archiveResult['status'] ?? '') === 'already_archived') {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?already_archived=1'); exit;
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=dashboard'); exit;
    }

    // ── Dettes ──────────────────────────────────────────────
    if (isset($_POST['add_debt'])) {
        $label        = trim($_POST['debt_label']);
        $total_amount = floatval($_POST['debt_total_amount']);
        $note         = trim($_POST['debt_note'] ?? '');
        if ($label !== '' && $total_amount > 0) {
            insertDebt($user_id, $label, $total_amount, $note);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?debt_added=1&tab=debts'); exit;
    }

    if (isset($_POST['pay_debt'])) {
        $debt_id        = intval($_POST['debt_id']);
        $payment_amount = floatval($_POST['payment_amount']);
        $payment_date   = $_POST['payment_date'] ?: date('Y-m-d');

        if ($debt_id > 0 && $payment_amount > 0) {
            global $pdo;
            $row = $pdo->prepare("SELECT label FROM debts WHERE id = ?");
            $row->execute([$debt_id]);
            $debt  = $row->fetch(PDO::FETCH_ASSOC);
            $label = $debt ? $debt['label'] : 'Remboursement';

            $budgets = getBudgets($user_id);
            if (!isset($budgets['Remboursement'])) {
                $budgets['Remboursement'] = 0;
                setBudgets($user_id, $budgets);
            }

            insertExpense($user_id, [
                'date'        => $payment_date,
                'category'    => 'Remboursement',
                'description' => 'Remboursement — ' . $label,
                'amount'      => $payment_amount,
            ]);
            addDebtPayment($user_id, $debt_id, $payment_amount);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?debt_paid=1&tab=debts'); exit;
    }

    if (isset($_POST['delete_debt'])) {
        deleteDebt($user_id, intval($_POST['delete_debt']));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?debt_deleted=1&tab=debts'); exit;
    }

    // ── Objectifs épargne ────────────────────────────────────
    if (isset($_POST['update_goals'])) {
        if (isset($_POST['goals'])) $savingGoalService->saveForUser($user_id, $_POST['goals']);
        header("Location: " . $_SERVER['PHP_SELF'] . "?goals_updated=1&tab=savings"); exit();
    }
}

// ============================================================
// CHARGEMENT DES DONNÉES
// ============================================================
$dashboardData = $dashboardService->buildViewData($user_id);
extract($dashboardData, EXTR_OVERWRITE);
$debts = fetchDebts($user_id);
$alerts = fetchAlerts($user_id);
$saving_goals = $savingGoalService->allForUser($user_id);
$needsDailyCheckin = !$dailyCheckinService->hasTodayCheckin($user_id);
$pendingRiskyExpense = $_SESSION['pending_risky_expense'] ?? null;
$purchaseAdvisorResult = $_SESSION['purchase_advisor_result'] ?? null;
unset($_SESSION['purchase_advisor_result']);

$previous_savings    = getPreviousMonthSavings($user_id);
$current_savings     = $budgets['Épargne'] ?? 0;
$savings_badge_class = 'bg-success';
if ($current_savings < $previous_savings) {
    $ratio = ($previous_savings - $current_savings) / max($previous_savings, 1);
    $savings_badge_class = $ratio > 0.2 ? 'bg-danger' : 'bg-warning';
}
$quickDecisionAlert = $purchaseAdvisorResult;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="expenses_filters.js"></script>
    <style>
        :root {
            --primary:#6D28D9; --secondary:#F472B6;
            --success:#60A5FA; --danger:#e74c3c;
            --warning:#f39c12; --light:#EEF2FF; --dark:#6B46C1;
        }
        body { background:#EEF2FF; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        header { background:#fff; }
        .card { border:none; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.08); margin-bottom:20px; transition:box-shadow .3s,transform .3s; }
        .card:hover { box-shadow:0 8px 24px rgba(0,0,0,.14); transform:translateY(-4px); }
        .stat-card { text-align:center; padding:20px; }
        .stat-value { font-size:1.8rem; font-weight:700; margin:10px 0; color:#1f2937; }
        .stat-label { font-weight:600; color:#6b7280; font-size:.85rem; margin-bottom:4px; }
        .nav-tabs { flex-wrap:nowrap; overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
        .nav-tabs::-webkit-scrollbar { display:none; }
        .nav-item { flex-shrink:0; }
        .chart-container,.small-chart-container,.evolution-chart-container { position:relative; height:280px; width:100%; }
        .progress-bar { border-radius:8px; transition:width .6s ease; }
        .savings-progress { height:20px; border-radius:10px; }
        .btn-floating-expand {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1060;
    border-radius: 50px;
    padding: 14px 16px;
    box-shadow: 0 6px 18px rgba(109,40,217,.4);
    display: flex;
    align-items: center;
    gap: 0;
    overflow: hidden;
    transition: padding .3s ease, box-shadow .2s ease, gap .3s ease;
    white-space: nowrap;
}
.btn-floating-expand .btn-floating-text {
    max-width: 0;
    opacity: 0;
    overflow: hidden;
    transition: max-width .35s ease, opacity .25s ease, margin .3s ease;
    font-size: .95rem;
    font-weight: 600;
    margin-left: 0;
}
.btn-floating-expand:hover {
    padding: 14px 20px;
    gap: 8px;
    box-shadow: 0 10px 28px rgba(109,40,217,.5);
}
.btn-floating-expand:hover .btn-floating-text {
    max-width: 160px;
    opacity: 1;
    margin-left: 2px;
}

@media (max-width: 768px) {
    .btn-floating-expand {
        bottom: 16px;
        right: 16px;
        padding: 12px 14px;
    }
}
        .btn-floating:hover { box-shadow:0 10px 28px rgba(109,40,217,.5); transform:translateY(-2px); }
        .rounded-dot { display:inline-block; width:12px; height:12px; border-radius:50%; }
        .goal-card { border-radius:10px; transition:transform .3s,box-shadow .3s; }
        .goal-card:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,.1); }
        @media (max-width:768px) {
            .stat-value { font-size:1.25rem; }
            .stat-card { padding:14px; }
            .chart-container,.small-chart-container,.evolution-chart-container { height:200px; }
            .btn-floating { bottom:16px; right:16px; font-size:.85rem; }
            header img { height:44px !important; }
        }
    </style>
</head>
<body>

<!-- TOASTS -->
<div id="toast-container" style="position:fixed;top:1rem;right:1rem;z-index:1100;"></div>
<script>
function showToast(msg, type = 'success') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = 'toast align-items-center text-bg-' + type + ' border-0 show mb-2';
    t.style.cssText = 'min-width:240px;border-radius:10px;padding:.7rem 1rem;box-shadow:0 4px 12px rgba(0,0,0,.15);display:flex;justify-content:space-between;';
    t.innerHTML = `<div><i class="fas fa-check-circle me-2"></i>${msg}</div>
                   <button class="btn-close btn-close-white ms-2" onclick="this.closest('.toast').remove()"></button>`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
document.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(location.search);
    if (p.has('added'))           showToast('Dépense ajoutée !');
    if (p.has('deleted'))         showToast('Dépense supprimée.', 'warning');
    if (p.has('updated'))         showToast('Dépense modifiée.');
    if (p.has('budgets_updated')) showToast('Budgets mis à jour.');
    if (p.has('alerts_cleared'))  showToast('Alertes effacées.', 'info');
    if (p.has('goals_updated'))   showToast('Objectifs mis à jour.', 'info');
    if (p.has('debt_added'))      showToast('Dette ajoutée !');
    if (p.has('debt_paid'))       showToast('Remboursement enregistré !');
    if (p.has('debt_deleted'))    showToast('Dette supprimée.', 'warning');
    if (p.has('recalibrated')) showToast('Budgets recalibrés avec la règle 50/30/20 ! ✨')
});
</script>

<?php if (isImpersonating()): ?>
<div class="bg-warning text-dark text-center py-2" style="font-size:.85rem;font-weight:500;">
    <i class="fas fa-eye me-2"></i>
    Consultation en lecture seule du compte de <strong><?php echo htmlspecialchars(getImpersonatedUsername()); ?></strong> — aucune modification n’est autorisée.
    <form method="POST" class="d-inline ms-3">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <button name="stop_impersonate" class="btn btn-sm btn-dark py-0 px-2" style="font-size:.8rem;">
            <i class="fas fa-times me-1"></i>Revenir à mon compte
        </button>
    </form>
</div>
<?php endif; ?>

<!-- HEADER -->
<header class="border-bottom shadow-sm mb-4">
    <div class="container py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <img src="assets/logo2.png" alt="Logo" height="52">
                <div>
                    <div class="fw-bold" style="color:#6537F3;font-size:1.05rem;">Money Minder</div>
                    <small class="text-muted" style="font-size:.75rem;">
                        <?php echo date('F Y'); ?> &bull; Budget : <?php echo formatCurrency($monthly_budget); ?>
                    </small>
                    <div><small class="text-muted" style="font-size:.75rem;">Compte : <?php echo htmlspecialchars($current_user_display_name); ?></small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="archives.php" class="btn btn-outline-secondary btn-sm">
                    Archives <i class="fas fa-archive ms-1"></i>
                </a>
                <?php if ($_SESSION['is_admin']): ?>
<a href="admin.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-shield-alt me-1"></i>Admin
</a>
<?php endif; ?>

<form method="POST" class="d-inline">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <button name="logout_action" class="btn btn-outline-danger btn-sm">
        <i class="fas fa-sign-out-alt me-1"></i>Déco
    </button>
</form>
                <span class="badge <?php echo $savings_badge_class; ?> fs-6 py-2 px-3">
                    Épargne : <?php echo formatCurrency($current_savings); ?>
                </span>
            </div>
        </div>
    </div>
</header>

<!-- BOUTON FLOTTANT -->
<button class="btn btn-primary btn-floating-expand" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
    <i class="fas fa-plus"></i>
    <span class="btn-floating-text">Nouvelle dépense</span>
</button>

<!-- NAVIGATION -->
<div class="container">
<ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dashboard" type="button">Tableau de bord</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#budgets"   type="button">Budgets</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#expenses"  type="button">Historique</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reports"   type="button">Rapports</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#savings"   type="button">Épargne</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#debts"     type="button">Dettes</button></li>
    <li class="nav-item"><button class="nav-link position-relative" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button">Alertes</button></li>
</ul>

<div class="tab-content mt-3" id="myTabContent">

<!-- ══════════════════ TAB : DASHBOARD ══════════════════ -->
<div class="tab-pane fade show active" id="dashboard" role="tabpanel">

    <?php include __DIR__ . '/views/partials/dashboard_banner.php'; ?>

    <?php include __DIR__ . '/views/partials/dashboard_stat_cards.php'; ?>

    <!-- Graphiques -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header fw-semibold">Répartition des Dépenses</div>
                <div class="card-body">
                    <?php if ($total_expenses > 0): ?>
                        <div class="chart-container"><canvas id="expensesChart"></canvas></div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-chart-pie fa-3x mb-3 opacity-25"></i>
                            <p class="fw-semibold mb-1">Aucune dépense ce mois-ci</p>
                            <small>Ajoutez une dépense pour voir la répartition.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <?php include __DIR__ . '/views/partials/dashboard_recent_expenses.php'; ?>
        </div>
    </div>

    <?php include __DIR__ . '/views/partials/dashboard_top_expenses.php'; ?>

    <?php include __DIR__ . '/views/partials/dashboard_budget_progress.php'; ?>

</div><!-- /#dashboard -->

<?php include __DIR__ . '/views/partials/budgets_tab.php'; ?>

<?php include __DIR__ . '/views/partials/expenses_tab.php'; ?>

<?php include __DIR__ . '/views/partials/reports_tab.php'; ?>

<?php include __DIR__ . '/views/partials/savings_tab.php'; ?>

<!-- ══════════════════ TAB : DETTES ══════════════════ -->
<div class="tab-pane fade" id="debts" role="tabpanel">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    Mes Dettes en cours
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDebtModal">
                        <i class="fas fa-plus me-1"></i>Nouvelle dette
                    </button>
                </div>
                <div class="card-body">
                    <?php
                    $active_debts  = array_filter($debts, fn($d) => $d['status'] === 'active');
                    $settled_debts = array_filter($debts, fn($d) => $d['status'] === 'settled');
                    ?>
                    <?php if (empty($active_debts)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-handshake fa-3x mb-3 opacity-25"></i>
                            <p class="fw-semibold mb-1">Aucune dette en cours</p>
                            <small>Cliquez sur "Nouvelle dette" pour en ajouter une.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_debts as $debt):
                            $remaining = $debt['total_amount'] - $debt['amount_paid'];
                            $pct       = $debt['total_amount'] > 0 ? round(($debt['amount_paid'] / $debt['total_amount']) * 100, 1) : 0;
                            $bar_col   = $pct < 40 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                        ?>
                        <div class="mb-4 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="fw-bold fs-6"><?php echo htmlspecialchars($debt['label']); ?></span>
                                    <?php if (!empty($debt['note'])): ?><small class="text-muted ms-2"><?php echo htmlspecialchars($debt['note']); ?></small><?php endif; ?>
                                </div>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-success"
                                        data-bs-toggle="modal" data-bs-target="#payDebtModal"
                                        data-debt-id="<?php echo $debt['id']; ?>"
                                        data-debt-label="<?php echo htmlspecialchars($debt['label']); ?>"
                                        data-debt-remaining="<?php echo $remaining; ?>">
                                        <i class="fas fa-money-bill-wave me-1"></i>Payer
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette dette ?')">
                                        <input type="hidden" name="delete_debt" value="<?php echo $debt['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Remboursé : <?php echo formatCurrency($debt['amount_paid']); ?></small>
                                <small class="text-muted">Reste : <strong class="text-danger"><?php echo formatCurrency($remaining); ?></strong></small>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar <?php echo $bar_col; ?>" style="width:<?php echo $pct; ?>%;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">0</small>
                                <small class="text-muted"><?php echo formatCurrency($debt['total_amount']); ?> — <?php echo $pct; ?>% remboursé</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header fw-semibold">Résumé</div>
                <div class="card-body">
                    <?php
                    $total_owed     = array_sum(array_column(array_values($active_debts), 'total_amount'));
                    $total_paid_all = array_sum(array_column(array_values($active_debts), 'amount_paid'));
                    $total_rem      = $total_owed - $total_paid_all;
                    ?>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Dettes actives</span><strong><?php echo count($active_debts); ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total dû</span><strong class="text-danger"><?php echo formatCurrency($total_rem); ?></strong></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-muted">Déjà remboursé</span><strong class="text-success"><?php echo formatCurrency($total_paid_all); ?></strong></div>
                    <?php if ($total_owed > 0): ?>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:<?php echo round(($total_paid_all / $total_owed) * 100); ?>%;"></div>
                    </div>
                    <small class="text-muted"><?php echo round(($total_paid_all / max($total_owed,1)) * 100); ?>% remboursé au total</small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($settled_debts)): ?>
            <div class="card mt-3">
                <div class="card-header fw-semibold text-success"><i class="fas fa-check-circle me-2"></i>Dettes soldées</div>
                <div class="card-body p-2">
                    <?php foreach ($settled_debts as $debt): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 px-1 border-bottom">
                        <div>
                            <span class="fw-semibold"><?php echo htmlspecialchars($debt['label']); ?></span>
                            <small class="text-muted ms-2"><?php echo formatCurrency($debt['total_amount']); ?></small>
                        </div>
                        <div class="d-flex gap-1 align-items-center">
                            <span class="badge bg-success">Soldée ✅</span>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="delete_debt" value="<?php echo $debt['id']; ?>">
                                <button class="btn btn-sm btn-outline-secondary border-0"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div><!-- /#debts -->

<!-- ══════════════════ TAB : ALERTES ══════════════════ -->
<div class="tab-pane fade" id="alerts" role="tabpanel">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
            Alertes
            <form method="POST" onsubmit="return confirm('Tout effacer ?')">
                <button name="clear_all_alerts" class="btn btn-sm btn-outline-danger"><i class="fas fa-bell-slash me-1"></i>Tout effacer</button>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($alerts)): ?>
                <p class="text-muted"><i class="fas fa-check-circle me-2 text-success"></i>Aucune alerte pour le moment.</p>
            <?php else: ?>
                <?php foreach ($alerts as $alert):
                    $alert_class = in_array($alert['type'], ['budget_exceeded','global_budget_exceeded']) ? 'danger'
                        : (in_array($alert['type'], ['budget_warning','large_expense']) ? 'warning' : 'info');
                ?>
                <div class="alert alert-<?php echo $alert_class; ?> d-flex justify-content-between align-items-center py-2">
                    <div>
                        <strong><?php echo ucfirst(str_replace('_',' ',$alert['type'])); ?> :</strong>
                        <?php echo htmlspecialchars($alert['message']); ?>
                        <small class="text-muted ms-2"><?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?></small>
                    </div>
                    <form method="POST" class="ms-2">
                        <input type="hidden" name="delete_alert" value="<?php echo $alert['id']; ?>">
                        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div><!-- /#alerts -->

</div><!-- /tab-content -->
</div><!-- /container -->

<!-- ══════════════════════════════════════════
     MODALS
     ══════════════════════════════════════════ -->

<!-- Ajouter une dépense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nouvelle dépense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="mb-3">
            <label class="form-label">Catégorie</label>
            <select class="form-select" name="category" required>
                <?php
                $all_others_spent = true; $total_spent = 0;
                foreach ($budgets as $cat => $budget) {
                    if ($cat === 'Épargne') continue;
                    $s = calculateCategoryExpenses($cat, $user_id); $total_spent += $s;
                    if ($s < floatval($budget)) $all_others_spent = false;
                }
                $show_savings = $all_others_spent || ($total_spent >= 140000);
                foreach (array_keys($budgets) as $cat):
                    if ($cat === 'Épargne' && !$show_savings) continue;
                ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Montant (FCFA)</label><input type="number" class="form-control" name="amount" min="0" step="100" required></div>
        <div class="mb-3"><label class="form-label">Description</label><input type="text" class="form-control" name="description" placeholder="Description de la dépense"></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="add_expense" class="btn btn-primary">Ajouter</button>
    </div>
  </form></div>
</div>

<!-- Modifier une dépense -->
<div class="modal fade" id="editExpenseModal" tabindex="-1">
  <div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Modifier la dépense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="edit_expense_id" name="edit_expense_id">
        <div class="mb-3"><label class="form-label">Montant (FCFA)</label><input type="number" class="form-control" id="edit_amount" name="edit_amount" min="0" step="100" required></div>
        <div class="mb-3"><label class="form-label">Catégorie</label>
            <select class="form-select" id="edit_category" name="edit_category" required>
                <?php foreach (array_keys($budgets) as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Description</label><input type="text" class="form-control" id="edit_description" name="edit_description" maxlength="100"></div>
        <div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" id="edit_date" name="edit_date"></div>
    </div>
    <div class="modal-footer">
        <input type="hidden" name="current_tab" value="expenses">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="edit_expense" class="btn btn-primary">Enregistrer</button>
    </div>
  </form></div>
</div>

<!-- Modifier les budgets -->
<div class="modal fade" id="editBudgetsModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><form method="POST" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Modifier les budgets</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Budget Mensuel Global (FCFA)</label><input type="number" class="form-control" name="monthly_budget" value="<?php echo getMeta('monthly_budget', '', $user_id); ?>" required></div>
        <h6 class="mt-3">Budgets par Catégorie</h6>
        <?php foreach ($budgets as $category => $budget): ?>
        <div class="row mb-2">
            <div class="col-6"><label class="form-label"><?php echo htmlspecialchars($category); ?></label></div>
            <div class="col-6"><input type="number" class="form-control" name="budgets[<?php echo htmlspecialchars($category); ?>]" value="<?php echo $budget; ?>" min="0" step="1000"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="update_budgets" class="btn btn-primary">Enregistrer</button>
    </div>
  </form></div>
</div>

<!-- Ajouter une catégorie -->
<div class="modal fade" id="addBudgetCategoryModal" tabindex="-1">
  <div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Ajouter une catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Nom</label><input type="text" class="form-control" name="new_budget_category" required placeholder="Ex: Loisirs, Santé..."></div>
        <div class="mb-3"><label class="form-label">Montant (FCFA)</label><input type="number" class="form-control" name="new_budget_amount" min="0" step="1000" required></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="add_budget_category" class="btn btn-success">Ajouter</button>
    </div>
  </form></div>
</div>

<!-- Objectifs d'épargne -->
<div class="modal fade" id="savingGoalsModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">🎯 Objectifs d'épargne</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST">
    <div class="modal-body">
        <button type="button" class="btn btn-success btn-sm mb-3" onclick="addNewGoal()"><i class="fas fa-plus me-1"></i>Nouvel objectif</button>
        <div id="goals-container">
            <?php foreach ($saving_goals as $key => $goal):
                $pct_g = $goal['target'] > 0 ? min(($goal['current'] / $goal['target']) * 100, 100) : 0;
                $pc_g  = $pct_g >= 100 ? 'bg-success' : ($pct_g >= 75 ? 'bg-warning' : ($pct_g >= 50 ? 'bg-info' : 'bg-primary'));
                $rem_g = $goal['target'] - $goal['current'];
                $dl_g  = new DateTime($goal['deadline']);
                $iv_g  = (new DateTime())->diff($dl_g);
                $mo_g  = max(($iv_g->y * 12) + $iv_g->m, 1);
                $ms_g  = $rem_g > 0 ? ceil($rem_g / $mo_g) : 0;
            ?>
            <div class="goal-item card mb-3">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-5"><label class="form-label small">Nom</label><input type="text" class="form-control form-control-sm" name="goals[<?php echo $key; ?>][name]" value="<?php echo htmlspecialchars($goal['name']); ?>" required></div>
                        <div class="col-md-3"><label class="form-label small">Cible (FCFA)</label><input type="number" class="form-control form-control-sm" name="goals[<?php echo $key; ?>][target]" value="<?php echo $goal['target']; ?>" min="0" required></div>
                        <div class="col-md-3"><label class="form-label small">Épargné (FCFA)</label><input type="number" class="form-control form-control-sm" name="goals[<?php echo $key; ?>][current]" value="<?php echo $goal['current']; ?>" min="0"></div>
                        <div class="col-md-3"><label class="form-label small">Échéance</label><input type="date" class="form-control form-control-sm" name="goals[<?php echo $key; ?>][deadline]" value="<?php echo $goal['deadline']; ?>"></div>
                        <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGoal(this)"><i class="fas fa-trash"></i></button></div>
                    </div>
                    <div class="progress mt-2" style="height:16px;">
                        <div class="progress-bar <?php echo $pc_g; ?>" style="width:<?php echo $pct_g; ?>%;"><?php echo round($pct_g); ?>%</div>
                    </div>
                    <small class="text-muted"><?php echo formatCurrency($goal['current']); ?> / <?php echo formatCurrency($goal['target']); ?>
                    <?php if ($goal['deadline']): ?> — Échéance : <?php echo date('d/m/Y', strtotime($goal['deadline'])); ?>
                    <br><span class="text-info"><i class="fas fa-lightbulb me-1"></i><?php echo formatCurrency($ms_g); ?> / mois nécessaire</span><?php endif; ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="update_goals" class="btn btn-primary">Enregistrer</button>
    </div>
    </form>
  </div></div>
</div>

<!-- Supprimer toutes les dépenses -->
<div class="modal fade" id="deleteAllExpensesModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Confirmer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p>Supprimer <strong>toutes les dépenses</strong> du mois ?</p>
        <div class="alert alert-danger"><i class="fas fa-warning me-2"></i>Action irréversible.</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <form method="POST" class="d-inline"><button type="submit" name="delete_all_expenses" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Supprimer tout</button></form>
    </div>
  </div></div>
</div>

<!-- Ajouter une dette -->
<div class="modal fade" id="addDebtModal" tabindex="-1">
  <div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-handshake me-2"></i>Nouvelle dette</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Créancier <small class="text-muted">(qui tu rembourses)</small></label><input type="text" class="form-control" name="debt_label" placeholder="Ex: Maman, Ami Kofi..." required></div>
        <div class="mb-3"><label class="form-label">Montant total à rembourser (FCFA)</label><input type="number" class="form-control" name="debt_total_amount" min="0" step="100" required></div>
        <div class="mb-3"><label class="form-label">Note <small class="text-muted">(optionnel)</small></label><input type="text" class="form-control" name="debt_note" placeholder="Ex: Prêt pour téléphone..."></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="add_debt" class="btn btn-primary">Ajouter</button>
    </div>
  </form></div>
</div>

<!-- Payer une dette -->
<div class="modal fade" id="payDebtModal" tabindex="-1">
  <div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Enregistrer un remboursement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" name="debt_id" id="payDebtId">
        <div class="mb-3"><label class="form-label">Créancier</label><input type="text" class="form-control" id="payDebtLabel" readonly></div>
        <div class="mb-3"><label class="form-label">Montant restant</label><input type="text" class="form-control" id="payDebtRemaining" readonly></div>
        <div class="mb-3"><label class="form-label">Montant à rembourser (FCFA)</label><input type="number" class="form-control" name="payment_amount" min="1" step="100" required></div>
        <div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>"></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="pay_debt" class="btn btn-success">Enregistrer</button>
    </div>
  </form></div>
</div>

<?php if (!empty($quickDecisionAlert)): ?>
<div class="container mb-3">
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="fas fa-lightbulb"></i>
        <div><strong><?php echo htmlspecialchars($quickDecisionAlert['decision']); ?></strong> — <?php echo htmlspecialchars($quickDecisionAlert['reason']); ?></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
        if (form.querySelector('[name="stop_impersonate"], [name="logout_action"]')) {
            return;
        }
        form.querySelectorAll('input, select, textarea, button').forEach(function (control) {
            control.disabled = true;
        });
    });
});
</script>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════ -->
<div class="modal fade" id="dailyCheckinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Check-in quotidien obligatoire</h5>
            </div>
            <div class="modal-body">
                <p class="fw-semibold">As-tu dépensé aujourd'hui ?</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" type="submit" name="daily_checkin_status" value="spent_today">Oui, j'ajoute maintenant</button>
                    <button class="btn btn-outline-success" type="submit" name="daily_checkin_status" value="no_spend_day">Non, journée sans dépense</button>
                    <button class="btn btn-outline-warning" type="submit" name="daily_checkin_status" value="forgot_yesterday">J'ai oublié une dépense d'hier</button>
                </div>
                <div class="mt-3">
                    <label class="form-label">Note optionnelle</label>
                    <textarea class="form-control" name="daily_checkin_note" rows="3" placeholder="Contexte du jour, oubli, difficulté, etc."></textarea>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="purchaseAdvisorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Je veux acheter quelque chose</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Montant</label>
                        <input type="number" class="form-control" name="advisor_amount" min="0" step="1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Catégorie</label>
                        <select class="form-select" name="advisor_category" required>
                            <?php foreach (array_keys($budgets) as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Besoin ou envie</label>
                        <select class="form-select" name="advisor_type">
                            <option value="need">Besoin</option>
                            <option value="want">Envie</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Urgence</label>
                        <select class="form-select" name="advisor_urgency">
                            <option value="faible">Faible</option>
                            <option value="moyenne">Moyenne</option>
                            <option value="élevée">Élevée</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="advisor_description" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-dark" type="submit" name="purchase_advisor_submit" value="1">Analyser l'achat</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($pendingRiskyExpense)): ?>
<div class="modal fade" id="riskyExpenseReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmation obligatoire</h5>
            </div>
            <div class="modal-body">
                <p class="fw-semibold">Cette dépense risque de déséquilibrer ton mois. Pourquoi veux-tu vraiment la faire ?</p>
                <div class="alert alert-warning small">
                    <strong><?php echo htmlspecialchars($pendingRiskyExpense['category']); ?></strong> —
                    <?php echo formatCurrency($pendingRiskyExpense['amount']); ?> :
                    <?php echo htmlspecialchars($pendingRiskyExpense['description'] ?: 'Sans description'); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Justification obligatoire</label>
                    <textarea class="form-control" name="expense_justification" rows="4" required></textarea>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acknowledge_risk" value="1" id="ackRisk" required>
                    <label class="form-check-label" for="ackRisk">J'assume cette dépense malgré l'avertissement</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" type="submit" name="confirm_risky_expense" value="1">Enregistrer quand même</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const needsDailyCheckin = <?php echo $needsDailyCheckin ? 'true' : 'false'; ?>;
    if (needsDailyCheckin && typeof bootstrap !== 'undefined') {
        const checkinModal = document.getElementById('dailyCheckinModal');
        if (checkinModal) {
            bootstrap.Modal.getOrCreateInstance(checkinModal, { backdrop: 'static', keyboard: false }).show();
        }
    }

    const shouldShowRiskModal = <?php echo !empty($pendingRiskyExpense) ? 'true' : 'false'; ?>;
    if (shouldShowRiskModal && typeof bootstrap !== 'undefined') {
        const riskModal = document.getElementById('riskyExpenseReviewModal');
        if (riskModal) {
            bootstrap.Modal.getOrCreateInstance(riskModal, { backdrop: 'static', keyboard: false }).show();
        }
    }

    // Badge alertes non lues
    const alertsTab = document.getElementById('alerts-tab');
    const unseenCount = <?php
        $unseen = 0;
        foreach ($alerts as $a) { if (isset($a['seen']) && $a['seen'] == 0) $unseen++; }
        echo $unseen;
    ?>;
    if (alertsTab && unseenCount > 0) {
        const badge = document.createElement('span');
        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
        badge.style.cssText = 'min-width:18px;height:18px;font-size:.7rem;line-height:18px;z-index:1050;';
        badge.textContent = unseenCount;
        alertsTab.appendChild(badge);
        alertsTab.addEventListener('click', () => {
            fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'mark_alerts_seen=1' })
                .then(() => badge.remove());
        }, { once: true });
    }

    // Pré-remplir modal édition dépense
    const editModal = document.getElementById('editExpenseModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('edit_expense_id').value  = b.dataset.id;
            document.getElementById('edit_description').value = b.dataset.description;
            document.getElementById('edit_amount').value      = b.dataset.amount;
            document.getElementById('edit_category').value    = b.dataset.category;
            document.getElementById('edit_date').value        = b.dataset.date;
        });
    }

    // Pré-remplir modal paiement dette
    const payDebtModal = document.getElementById('payDebtModal');
    if (payDebtModal) {
        payDebtModal.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('payDebtId').value        = b.dataset.debtId;
            document.getElementById('payDebtLabel').value     = b.dataset.debtLabel;
            document.getElementById('payDebtRemaining').value = parseFloat(b.dataset.debtRemaining).toLocaleString('fr-FR') + ' FCFA';
        });
    }

    // ── Graphiques ────────────────────────────────────────
    const opts = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } };

    function renderChartFallback(canvas, labels, data, colors, title = 'Aperçu') {
        if (!canvas) return;
        const wrapper = canvas.parentElement;
        if (!wrapper) return;

        const total = data.reduce((sum, value) => sum + Number(value || 0), 0);
        const items = labels.map((label, index) => ({
            label,
            value: Number(data[index] || 0),
            color: colors[index] || '#60A5FA',
        }));

        const rows = items.map((item) => {
            const ratio = total > 0 ? Math.max((item.value / total) * 100, item.value > 0 ? 2 : 0) : 0;
            const safeLabel = String(item.label)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            return `
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center small mb-1">
                        <span><span class="rounded-dot me-2" style="background:${item.color};"></span>${safeLabel}</span>
                        <strong>${item.value.toLocaleString('fr-FR')} FCFA</strong>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar" style="width:${ratio}%;background:${item.color};"></div>
                    </div>
                </div>
            `;
        }).join('');

        wrapper.innerHTML = `
            <div class="p-3 h-100 overflow-auto">
                <div class="small text-muted mb-3">${title}</div>
                ${rows || '<div class="text-muted small">Aucune donnée à afficher.</div>'}
            </div>
        `;
    }

    function buildChart(canvas, config, fallbackTitle) {
        if (!canvas) return;
        if (typeof Chart === 'undefined') {
            const dataset = (config.data && config.data.datasets && config.data.datasets[0]) || { data: [], backgroundColor: [] };
            const fallbackColors = Array.isArray(dataset.backgroundColor)
                ? dataset.backgroundColor
                : Array.from({ length: (dataset.data || []).length }, () => dataset.backgroundColor || '#60A5FA');
            renderChartFallback(
                canvas,
                config.data?.labels || [],
                dataset.data || [],
                fallbackColors,
                fallbackTitle
            );
            return;
        }
        new Chart(canvas, config);
    }

    // Pie — répartition dépenses
    const expCtx = document.getElementById('expensesChart');
    if (expCtx && <?php echo $total_expenses > 0 ? 'true' : 'false'; ?>) {
        buildChart(expCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($expenseChartLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{ data: <?php echo json_encode($expenseChartData); ?>, backgroundColor: <?php echo json_encode($chartColors); ?> }]
            },
            options: opts
        }, 'Répartition des dépenses');
    }

    // Bar — budget vs dépenses
    const budCtx = document.getElementById('budgetComparisonChart');
    if (budCtx) {
        buildChart(budCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($budgetChartLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [
                    { label:'Budget',  data: <?php echo json_encode($budgetChartData); ?>, backgroundColor:'#60A5FA' },
                    { label:'Dépensé', data: <?php echo json_encode($spentChartData); ?>, backgroundColor:'#e74c3c' }
                ]
            },
            options: { ...opts, scales:{ y:{ beginAtZero:true } } }
        }, 'Comparaison budget / dépenses');
    }

    // Doughnut — répartition rapports
    const catCtx = document.getElementById('categoryDistributionChart');
    if (catCtx) {
        buildChart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($expenseChartLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{ data: <?php echo json_encode($expenseChartData); ?>, backgroundColor: <?php echo json_encode($chartColors); ?> }]
            },
            options: opts
        }, 'Répartition par catégorie');
    }

    // Bar — dépenses journalières
    const dayCtx = document.getElementById('dailyExpensesChart');
    if (dayCtx) {
        buildChart(dayCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weekDays, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{ label:'FCFA', data: <?php echo json_encode($weekExpenses); ?>, backgroundColor: <?php echo json_encode(array_fill(0, count($weekDays), '#36A2EB')); ?> }]
            },
            options: { ...opts, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
        }, 'Dépenses journalières');
    }

    // Line — évolution 30 jours
    const evoCtx = document.getElementById('expensesEvolutionChart');
    if (evoCtx) {
        <?php
        $last30 = []; $totals = [];
        for ($i = 29; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $last30[] = $d; $totals[$d] = 0; }
        foreach ($expenses as $e) { if (isset($totals[$e['date']])) $totals[$e['date']] += floatval($e['amount']); }
        $jl = []; $jd = [];
        foreach ($last30 as $d) { $jl[] = date('d/m', strtotime($d)); $jd[] = $totals[$d]; }
        ?>
        buildChart(evoCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($jl); ?>,
                datasets: [{ label:'Dépenses', data:<?php echo json_encode($jd); ?>, borderColor:'#FF6384', backgroundColor:'rgba(255,99,132,.1)', fill:true, tension:.4 }]
            },
            options: { ...opts, scales:{ y:{ beginAtZero:true } } }
        }, 'Évolution des dépenses');
    }
});

// Objectifs d'épargne
function addNewGoal() {
    const id = 'goal_' + Date.now();
    const tpl = `
    <div class="goal-item card mb-3"><div class="card-body">
        <div class="row g-2">
            <div class="col-md-5"><label class="form-label small">Nom</label><input type="text" class="form-control form-control-sm" name="goals[${id}][name]" value="Nouvel objectif" required></div>
            <div class="col-md-3"><label class="form-label small">Cible (FCFA)</label><input type="number" class="form-control form-control-sm" name="goals[${id}][target]" value="100000" min="0" required></div>
            <div class="col-md-3"><label class="form-label small">Épargné (FCFA)</label><input type="number" class="form-control form-control-sm" name="goals[${id}][current]" value="0" min="0"></div>
            <div class="col-md-3"><label class="form-label small">Échéance</label><input type="date" class="form-control form-control-sm" name="goals[${id}][deadline]" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>"></div>
            <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGoal(this)"><i class="fas fa-trash"></i></button></div>
        </div>
        <div class="progress mt-2" style="height:16px;"><div class="progress-bar bg-info" style="width:0%;">0%</div></div>
    </div></div>`;
    document.getElementById('goals-container').insertAdjacentHTML('beforeend', tpl);
}
function removeGoal(btn) { btn.closest('.goal-item').remove(); }
</script>

<?php include __DIR__ . '/recalibrate_budget_modal.php'; ?>

</body>
</html>
