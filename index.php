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
ensureUserBudgetMetaConsistency($user_id);
if (userNeedsBudgetSetup($user_id)) {
    header('Location: setup.php');
    exit;
}

require_once __DIR__ . '/budget_algorithm.php';

require_once __DIR__ . '/telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

// ============================================================
// ARCHIVAGE AUTOMATIQUE (26 à 23h59)
// ============================================================
$now    = new DateTime();
$day    = (int)$now->format('d');
$hour   = (int)$now->format('H');
$minute = (int)$now->format('i');

if ($day == 26 && ($hour > 23 || ($hour == 23 && $minute >= 59))) {
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
        $existing    = fetchExpenses($user_id);
        $isDuplicate = false;
        foreach ($existing as $e) {
            if ($e['amount'] == $amount && $e['category'] === $category
                && $e['description'] === $description && $e['date'] === $date) {
                $isDuplicate = true; break;
            }
        }
        if (!$isDuplicate) {
            insertExpense($user_id, ['date' => $date, 'category' => $category, 'description' => $description, 'amount' => $amount]);
            checkAndSendAlerts($user_id);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?added=1'); exit;
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
        if (isset($_POST['goals'])) setSavingGoals($user_id, $_POST['goals']);
        header("Location: " . $_SERVER['PHP_SELF'] . "?goals_updated=1&tab=savings"); exit();
    }
}

// ============================================================
// CHARGEMENT DES DONNÉES
// ============================================================
$expenses = fetchExpenses($user_id);
$budgets  = getBudgets($user_id);
if (isset($budgets['Ã‰pargne'])) { $budgets['Épargne'] = ($budgets['Épargne'] ?? 0) + (float)$budgets['Ã‰pargne']; unset($budgets['Ã‰pargne']); setBudgets($user_id, $budgets); }
if (!isset($budgets['Épargne'])) { $budgets['Épargne'] = 50000; setBudgets($user_id, $budgets); }
$debts    = fetchDebts($user_id);
$alerts = fetchAlerts($user_id);

// Dépenses de la semaine
$weekDays     = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
$weekExpenses = array_fill(0, 7, 0);
$today_dt     = new DateTime();
$monday       = clone $today_dt;
if ($today_dt->format('N') != 1) $monday->modify('last monday');
for ($i = 0; $i < 7; $i++) {
    $d = (clone $monday)->modify("+{$i} days")->format('Y-m-d');
    foreach ($expenses as $e) {
        if (!empty($e['date']) && $e['date'] === $d) $weekExpenses[$i] += floatval($e['amount']);
    }
}

// Calculs de base
$total_expenses     = array_sum(array_column($expenses, 'amount'));
$remaining_budget   = array_sum($budgets) - $total_expenses;
$daily_average      = date('j') > 0 ? $total_expenses / date('j') : 0;
$savings_percentage = ($remaining_budget > 0 && defined('MONTHLY_SAVING_GOAL') && MONTHLY_SAVING_GOAL > 0)
    ? ($remaining_budget / MONTHLY_SAVING_GOAL) * 100 : 0;

// ── Variables dashboard ───────────────────────────────────────
$monthly_budget      = floatval(getMeta('monthly_budget', '', $user_id));
$budget_used_percent = $monthly_budget > 0 ? min(($total_expenses / $monthly_budget) * 100, 100) : 0;
$bar_color           = $budget_used_percent < 60 ? 'bg-success' : ($budget_used_percent < 85 ? 'bg-warning' : 'bg-danger');

if ($budget_used_percent >= 100) {
    $banner_type    = 'danger';
    $banner_icon    = 'fa-triangle-exclamation';
    $banner_message = 'Budget mensuel dépassé ! Tu as dépensé ' . formatCurrency($total_expenses) . ' sur ' . formatCurrency($monthly_budget) . '.';
} elseif ($budget_used_percent >= 80) {
    $banner_type    = 'warning';
    $banner_icon    = 'fa-bell';
    $banner_message = 'Attention : ' . number_format($budget_used_percent, 1) . '% du budget consommé. Il te reste ' . formatCurrency($monthly_budget - $total_expenses) . '.';
} else {
    $banner_type = $banner_icon = $banner_message = null;
}

// Cycle indicator
$now_c = new DateTime();
$day_c = (int)$now_c->format('d');
$mon_c = (int)$now_c->format('m');
$yr_c  = (int)$now_c->format('Y');
if ($day_c >= 27) {
    $cycle_start = new DateTime("{$yr_c}-{$mon_c}-27");
} else {
    $pm = $mon_c - 1; $py = $yr_c;
    if ($pm === 0) { $pm = 12; $py--; }
    $cycle_start = new DateTime("{$py}-{$pm}-27");
}
$cycle_end      = (clone $cycle_start)->modify('+30 days');
$days_elapsed   = (int)$cycle_start->diff($now_c)->days + 1;
$days_total     = 30;
$days_remaining = max($days_total - $days_elapsed, 0);

// Top 3 catégories
$cat_spending = [];
foreach ($budgets as $cat => $budget) {
    if (floatval($budget) > 0) {
        $s = calculateCategoryExpenses($cat, $user_id);
        $cat_spending[$cat] = ['spent' => $s, 'budget' => floatval($budget), 'percent' => round(($s / floatval($budget)) * 100, 1)];
    }
}
uasort($cat_spending, fn($a, $b) => $b['spent'] <=> $a['spent']);
$top3 = array_slice($cat_spending, 0, 3, true);

// Objectifs d'épargne
function getSavingGoals($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM meta WHERE key = ?");
    $stmt->execute(["saving_goals_" . $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? json_decode($row['value'], true) : [];
}
function setSavingGoals($user_id, $goals) {
    global $pdo;
    $pdo->prepare("REPLACE INTO meta (key, value) VALUES (?, ?)")->execute(["saving_goals_" . $user_id, json_encode($goals)]);
}
$saving_goals = getSavingGoals($user_id);

// Couleurs graphiques
$diverseColors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#8AC926','#1982C4','#F472B6','#60A5FA','#34D399','#FBBF24'];
$chartColors   = [];
$colorIndex    = 0;
foreach (array_keys($budgets) as $cat) {
    if ($cat === 'Épargne') { $chartColors[] = '#DC3545'; }
    elseif ($cat === 'Alimentation') { $chartColors[] = '#1E40AF'; }
    else { $chartColors[] = $diverseColors[$colorIndex % count($diverseColors)]; $colorIndex++; }
}

// Badge épargne
$previous_savings    = getPreviousMonthSavings($user_id);
$current_savings     = $budgets['Épargne'] ?? 0;
$savings_badge_class = 'bg-success';
if ($current_savings < $previous_savings) {
    $ratio = ($previous_savings - $current_savings) / max($previous_savings, 1);
    $savings_badge_class = $ratio > 0.2 ? 'bg-danger' : 'bg-warning';
}
$expenseChartLabels = array_values(array_keys($budgets));
$expenseChartData   = array_map(fn($category) => calculateCategoryExpenses($category, $user_id), $expenseChartLabels);
$budgetChartLabels  = [];
$budgetChartData    = [];
$spentChartData     = [];
foreach ($budgets as $category => $budgetAmount) {
    if ((float)$budgetAmount <= 0) continue;
    $budgetChartLabels[] = $category;
    $budgetChartData[]   = (float)$budgetAmount;
    $spentChartData[]    = calculateCategoryExpenses($category, $user_id);
}
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
        .btn-floating { position:fixed; bottom:24px; right:24px; border-radius:14px; z-index:1060; box-shadow:0 6px 18px rgba(109,40,217,.4); transition:box-shadow .3s,transform .2s; }
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
    Vous consultez le compte de <strong><?php echo htmlspecialchars(getImpersonatedUsername()); ?></strong>
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
<button class="btn btn-primary btn-floating" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
    <i class="fas fa-plus me-2"></i>Nouvelle dépense
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

    <?php if ($banner_type): ?>
    <div class="alert alert-<?php echo $banner_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="fas <?php echo $banner_icon; ?> me-2"></i>
        <div><?php echo $banner_message; ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- 4 stat cards -->
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
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card">
                <div class="stat-label">Moy / jour</div>
                <div class="stat-value"><?php echo formatCurrency(round($daily_average)); ?></div>
            </div>
        </div>
    </div>

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
            <div class="card">
                <div class="card-header fw-semibold">Dépenses Récentes</div>
                <div class="card-body">
                    <?php
                    $recents = $expenses;
                    usort($recents, fn($a,$b) => strtotime($b['created_at'] ?? $b['date']) - strtotime($a['created_at'] ?? $a['date']));
                    $recents = array_slice($recents, 0, 2);
                    ?>
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
        </div>
    </div>

    <!-- Top 3 -->
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

    <!-- Barre consommation + cycle (EN BAS) -->
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

</div><!-- /#dashboard -->

<!-- ══════════════════ TAB : BUDGETS ══════════════════ -->
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
</div><!-- /#budgets -->

<!-- ══════════════════ TAB : HISTORIQUE ══════════════════ -->
<div class="tab-pane fade" id="expenses" role="tabpanel">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Liste des Dépenses</span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#filtersCollapse"><i class="fas fa-filter me-1"></i>Filtres</button>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAllExpensesModal"><i class="fas fa-trash me-1"></i>Tout supprimer</button>
            </div>
        </div>
        <div class="card-body">
            <div class="collapse mb-3" id="filtersCollapse">
                <div class="card card-body bg-light">
                    <div class="row g-2">
                        <div class="col-md-3 col-6">
                            <label class="form-label small">Catégorie</label>
                            <select class="form-select form-select-sm" id="filterCategory">
                                <option value="">Toutes</option>
                                <?php foreach (array_keys($budgets) as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-6"><label class="form-label small">Date début</label><input type="date" class="form-control form-control-sm" id="filterDateStart"></div>
                        <div class="col-md-3 col-6"><label class="form-label small">Date fin</label><input type="date" class="form-control form-control-sm" id="filterDateEnd"></div>
                        <div class="col-md-3 col-6"><label class="form-label small">Montant min</label><input type="number" class="form-control form-control-sm" id="filterAmountMin" placeholder="0"></div>
                        <div class="col-md-3 col-6"><label class="form-label small">Montant max</label><input type="number" class="form-control form-control-sm" id="filterAmountMax" placeholder="Max"></div>
                        <div class="col-12 mt-1">
                            <button class="btn btn-primary btn-sm me-2" id="applyFilters">Appliquer</button>
                            <button class="btn btn-outline-secondary btn-sm" id="resetFilters">Réinitialiser</button>
                        </div>
                    </div>
                </div>
            </div>
            <small class="text-muted d-block mb-2" id="expensesCount"><?php echo count($expenses); ?> dépense(s)</small>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="expensesTable">
                    <thead class="table-light">
                        <tr>
                            <th><button class="btn btn-link p-0 text-decoration-none sort-btn" data-sort="date-desc">Date <i class="fas fa-sort ms-1"></i></button></th>
                            <th><button class="btn btn-link p-0 text-decoration-none sort-btn" data-sort="category">Catégorie <i class="fas fa-sort ms-1"></i></button></th>
                            <th>Description</th>
                            <th class="text-end"><button class="btn btn-link p-0 text-decoration-none sort-btn" data-sort="amount-desc">Montant <i class="fas fa-sort ms-1"></i></button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTableBody">
                        <?php
                        $sorted_exp = $expenses;
                        usort($sorted_exp, fn($a,$b) => strtotime($b['created_at'] ?? $b['date']) - strtotime($a['created_at'] ?? $a['date']));
                        foreach ($sorted_exp as $exp):
                        ?>
                        <tr>
                            <td data-sort="<?php echo strtotime($exp['created_at'] ?? $exp['date']); ?>">
                                <?php echo isset($exp['created_at']) ? date('d/m/Y H:i', strtotime($exp['created_at'])) : htmlspecialchars($exp['date']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($exp['category']); ?></td>
                            <td><?php echo htmlspecialchars($exp['description']); ?></td>
                            <td class="text-end text-danger fw-semibold" data-sort="<?php echo $exp['amount']; ?>">-<?php echo formatCurrency($exp['amount']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary me-1"
                                    data-bs-toggle="modal" data-bs-target="#editExpenseModal"
                                    data-id="<?php echo $exp['id']; ?>"
                                    data-description="<?php echo htmlspecialchars($exp['description']); ?>"
                                    data-amount="<?php echo $exp['amount']; ?>"
                                    data-category="<?php echo htmlspecialchars($exp['category']); ?>"
                                    data-date="<?php echo $exp['date']; ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="delete_expense" value="<?php echo $exp['id']; ?>">
                                    <input type="hidden" name="current_tab" value="expenses">
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="noExpensesMessage" class="alert alert-info d-none">
                <i class="fas fa-info-circle me-2"></i>Aucune dépense ne correspond aux filtres.
            </div>
        </div>
    </div>
</div><!-- /#expenses -->

<!-- ══════════════════ TAB : RAPPORTS ══════════════════ -->
<div class="tab-pane fade" id="reports" role="tabpanel">
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header fw-semibold">Répartition par Catégorie</div>
                <div class="card-body"><div class="small-chart-container"><canvas id="categoryDistributionChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header fw-semibold">Dépenses Journalières (semaine)</div>
                <div class="card-body"><div class="chart-container"><canvas id="dailyExpensesChart"></canvas></div></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header fw-semibold">Évolution des Dépenses (30 jours)</div>
        <div class="card-body"><div class="evolution-chart-container"><canvas id="expensesEvolutionChart"></canvas></div></div>
    </div>
</div><!-- /#reports -->

<!-- ══════════════════ TAB : ÉPARGNE ══════════════════ -->
<div class="tab-pane fade" id="savings" role="tabpanel">
    <div class="row">
        <div class="col-md-6">
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
                    Objectif d'Épargne Mensuel
                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#savingGoalsModal"><i class="fas fa-bullseye me-1"></i>Gérer</button>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-1">Épargne ce mois</p>
                    <div class="fw-bold fs-4 mb-3"><?php echo formatCurrency($remaining_budget); ?></div>
                    <p class="text-muted small mb-1">Objectif mensuel</p>
                    <div class="fw-bold mb-3"><?php echo formatCurrency(MONTHLY_SAVING_GOAL); ?></div>
                    <div class="progress savings-progress">
                        <div class="progress-bar bg-success" style="width:<?php echo min($savings_percentage,100); ?>%;"></div>
                    </div>
                    <div class="text-center mt-2 small"><?php echo number_format($savings_percentage,1); ?>%</div>
                    <div class="mt-3"><small class="text-muted">Objectif annuel : <?php echo formatCurrency(ANNUAL_SAVING_GOAL); ?></small></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
                    🎯 Mes Objectifs d'Épargne
                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#savingGoalsModal"><i class="fas fa-cog me-1"></i>Modifier</button>
                </div>
                <div class="card-body">
                    <?php if (!empty($saving_goals)): foreach ($saving_goals as $key => $goal):
                        $pct_goal = $goal['target'] > 0 ? min(($goal['current'] / $goal['target']) * 100, 100) : 0;
                        $rem_goal = $goal['target'] - $goal['current'];
                        $dl       = new DateTime($goal['deadline']);
                        $iv       = (new DateTime())->diff($dl);
                        $mo_rem   = max(($iv->y * 12) + $iv->m, 1);
                        $wk_rem   = max(ceil($iv->days / 7), 1);
                        $mo_save  = $rem_goal > 0 ? ceil($rem_goal / $mo_rem) : 0;
                        $wk_save  = $rem_goal > 0 ? ceil($rem_goal / $wk_rem) : 0;
                        $pc       = $pct_goal >= 100 ? 'bg-success' : ($pct_goal >= 75 ? 'bg-warning' : ($pct_goal >= 50 ? 'bg-info' : 'bg-primary'));
                    ?>
                    <div class="goal-card mb-3 p-3 border rounded">
                        <div class="d-flex justify-content-between mb-2">
                            <h6 class="mb-0"><?php echo htmlspecialchars($goal['name']); ?></h6>
                            <span class="badge <?php echo $pct_goal >= 100 ? 'bg-success' : 'bg-secondary'; ?>"><?php echo round($pct_goal); ?>%</span>
                        </div>
                        <div class="progress mb-2" style="height:10px;">
                            <div class="progress-bar <?php echo $pc; ?>" style="width:<?php echo $pct_goal; ?>%;"></div>
                        </div>
                        <div class="row text-center mb-2">
                            <div class="col-6"><small class="text-muted d-block">Épargné</small><strong><?php echo formatCurrency($goal['current']); ?></strong></div>
                            <div class="col-6"><small class="text-muted d-block">Objectif</small><strong><?php echo formatCurrency($goal['target']); ?></strong></div>
                        </div>
                        <?php if ($pct_goal < 100): ?>
                        <div class="p-2 bg-light rounded small">
                            <div class="fw-semibold mb-1"><i class="fas fa-lightbulb me-1 text-warning"></i>Pour atteindre ton objectif :</div>
                            <?php if ($mo_rem >= 2): ?><div><?php echo formatCurrency($mo_save); ?> / mois (<?php echo $mo_rem; ?> mois)</div><?php endif; ?>
                            <div><?php echo formatCurrency($wk_save); ?> / semaine (<?php echo $wk_rem; ?> semaines)</div>
                            <div class="text-muted mt-1"><i class="fas fa-hourglass-end me-1"></i>Échéance : <?php echo date('d/m/Y', strtotime($goal['deadline'])); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="p-2 bg-success bg-opacity-10 rounded small text-success"><i class="fas fa-check-circle me-1"></i>Objectif atteint ! 🎉</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-bullseye fa-2x mb-2 opacity-50"></i>
                        <p>Aucun objectif défini</p>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#savingGoalsModal"><i class="fas fa-plus me-1"></i>Créer un objectif</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div><!-- /#savings -->

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

<!-- ══════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

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

    // Pie — répartition dépenses
    const expCtx = document.getElementById('expensesChart');
    if (expCtx && <?php echo $total_expenses > 0 ? 'true' : 'false'; ?>) {
        new Chart(expCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($expenseChartLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{ data: <?php echo json_encode($expenseChartData); ?>, backgroundColor: <?php echo json_encode($chartColors); ?> }]
            },
            options: opts
        });
    }

    // Bar — budget vs dépenses
    const budCtx = document.getElementById('budgetComparisonChart');
    if (budCtx) {
        new Chart(budCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($budgetChartLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [
                    { label:'Budget',  data: <?php echo json_encode($budgetChartData); ?>, backgroundColor:'#60A5FA' },
                    { label:'Dépensé', data: <?php echo json_encode($spentChartData); ?>, backgroundColor:'#e74c3c' }
                ]
            },
            options: { ...opts, scales:{ y:{ beginAtZero:true } } }
        });
    }

    // Doughnut — répartition rapports
    const catCtx = document.getElementById('categoryDistributionChart');
    if (catCtx) {
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($expenseChartLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{ data: <?php echo json_encode($expenseChartData); ?>, backgroundColor: <?php echo json_encode($chartColors); ?> }]
            },
            options: opts
        });
    }

    // Bar — dépenses journalières
    const dayCtx = document.getElementById('dailyExpensesChart');
    if (dayCtx) {
        new Chart(dayCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weekDays, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{ label:'FCFA', data: <?php echo json_encode($weekExpenses); ?>, backgroundColor:'#36A2EB' }]
            },
            options: { ...opts, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
        });
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
        new Chart(evoCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($jl); ?>,
                datasets: [{ label:'Dépenses', data:<?php echo json_encode($jd); ?>, borderColor:'#FF6384', backgroundColor:'rgba(255,99,132,.1)', fill:true, tension:.4 }]
            },
            options: { ...opts, scales:{ y:{ beginAtZero:true } } }
        });
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
