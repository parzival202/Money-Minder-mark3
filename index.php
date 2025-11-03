<?php
// Définir le fuseau horaire d'Abidjan (GMT+0)
date_default_timezone_set('Africa/Abidjan');
// Démarrage de la session PHP (important pour $_SESSION)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialisation base de données et utilisateur par défaut
require_once __DIR__ . '/db.php';
init_db();
$user_id = ensure_default_user();

// =============================
// Notification et Telegram Bot
// =============================
require_once __DIR__ . '/telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

// Archivage automatique à la fin du cycle (du 27 au 26)
$now = new DateTime();
$day = (int)$now->format('d');
$hour = (int)$now->format('H');
$minute = (int)$now->format('i');
// Si on est le 26 et il est 23:59 ou plus
if ($day == 26 && ($hour > 23 || ($hour == 23 && $minute >= 59))) {
    // Détermine la période du cycle à archiver : du 27 du mois précédent au 26 du mois courant
    $start = (clone $now)->modify('first day of this month')->modify('-1 month')->setDate($now->format('Y'), $now->format('m')-1 <= 0 ? 12 : $now->format('m')-1, 27);
    $end = (clone $now)->setDate($now->format('Y'), $now->format('m'), 26);
    // Format pour l'archive : 2025-09 (mois de fin du cycle)
    $cycle_label = $end->format('Y-m');
    // Vérifie si l'archive existe déjà
    $existing_archives = fetchArchives($user_id);
    $already_archived = false;
    foreach ($existing_archives as $arc) {
        if ($arc['month_year'] === $cycle_label) {
            $already_archived = true;
            break;
        }
    }
    if (!$already_archived) {
        // Récupère toutes les dépenses du cycle
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date DESC, id DESC");
        $stmt->execute([$user_id, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $budgets = getBudgets($user_id);
        $total_expenses = 0;
        $savings = isset($budgets['Épargne']) ? $budgets['Épargne'] : 0;
        foreach ($expenses as $e) $total_expenses += floatval($e['amount']);
        $archive_data = [
            'budgets' => $budgets,
            'expenses' => $expenses
        ];
        saveArchive($user_id, $cycle_label, $archive_data, $total_expenses);
        // Réinitialise les budgets (remet à 0 sauf Épargne)
        foreach ($budgets as $cat => &$amt) {
            if ($cat !== 'Épargne') $amt = 0;
        }
        setBudgets($user_id, $budgets);
        // Supprime toutes les dépenses du cycle
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND date >= ? AND date <= ?");
        $stmt->execute([$user_id, $start->format('Y-m-d'), $end->format('Y-m-d')]);

        // Message Telegram (aléatoire parmi 5)
        $month_label = $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
        $emojis = ['🎉','📦','✅','📖','🥳'];
        $emoji = $emojis[array_rand($emojis)];
        $messages = [
            "Mois archivé ! $month_label : " . formatCurrency($total_expenses) . " dépensés, " . formatCurrency($savings) . " épargnés. $emoji",
            "C'est dans la boîte ! $month_label archivé. Dépenses : " . formatCurrency($total_expenses) . ", Épargne : " . formatCurrency($savings) . ". $emoji",
            "Nikolaii valide l'archivage ! $month_label : " . formatCurrency($total_expenses) . " dépensés, " . formatCurrency($savings) . " sauvegardés. $emoji",
            "Chapitre terminé ! $month_label archivé. Performance : " . formatCurrency($total_expenses) . "/" . formatCurrency($savings) . ". $emoji",
            "🥳 C'est la fin du cycle! $month_label est terminé et archivé.\n Tu as dépensé " . formatCurrency($total_expenses) . " et économisé " . formatCurrency($savings) . ". $emoji"
        ];
        $msg = $messages[array_rand($messages)];
        $__nikolaii->sendMessage($msg);
    }
}

// =====================
// SÉCURITÉ : garantir la définition des variables utilisées dans le JS pour les graphiques
if (!isset($expenses) || !is_array($expenses)) {
    $expenses = [];
}
if (!isset($budgets) || !is_array($budgets)) {
    $budgets = [];
}
if (!isset($weekDays) || !is_array($weekDays)) {
    $weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
}
if (!isset($weekExpenses) || !is_array($weekExpenses)) {
    $weekExpenses = array_fill(0, 7, 0);
}
if (!isset($todayLabelFr)) {
    $todayLabel = date('l');
    $todayLabelFr = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche',
    ][$todayLabel] ?? '';
}
if (!isset($todayExpense)) {
    $todayExpense = 0;
}
// Gestion des requêtes
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Suppression d'une catégorie de budget
    if (isset($_POST['delete_budget_category'])) {
        $cat = $_POST['delete_budget_category'];
        $budgets = getBudgets($user_id);
        if (isset($budgets[$cat])) {
            // Supprimer toutes les dépenses associées à cette catégorie
            global $pdo;
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND category = ?");
            $stmt->execute([$user_id, $cat]);
            // Supprimer la catégorie du budget
            unset($budgets[$cat]);
            setBudgets($user_id, $budgets);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=dashboard');
        exit;
    }

    // Renommage d'une catégorie de budget
    if (isset($_POST['rename_budget_category'])) {
        $old = trim($_POST['old_category_name']);
        $new = trim($_POST['new_category_name']);
        $budgets = getBudgets($user_id);
        if ($old !== '' && $new !== '' && isset($budgets[$old])) {
            // Met à jour toutes les dépenses existantes dans la base
            global $pdo;
            $stmt = $pdo->prepare("UPDATE expenses SET category = ? WHERE user_id = ? AND category = ?");
            $stmt->execute([$new, $user_id, $old]);
            // Met à jour le budget
            $budgets[$new] = $budgets[$old];
            unset($budgets[$old]);
            setBudgets($user_id, $budgets);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=dashboard');
        exit;
    }

    // Ajout d'une nouvelle catégorie de budget
    if (isset($_POST['add_budget_category'])) {
        $new_cat = trim($_POST['new_budget_category']);
        $new_amt = floatval($_POST['new_budget_amount']);
        if ($new_cat !== '' && $new_amt > 0) {
            $budgets = getBudgets($user_id);
            $budgets[$new_cat] = $new_amt;
            setBudgets($user_id, $budgets);
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=budgets');
        exit;
    }

    // Ajout
    if (isset($_POST['add_expense'])) {
        $amount      = floatval($_POST['amount']);
        $category    = $_POST['category'];
        $description = trim($_POST['description']);
        $date        = $_POST['date'] ?: date('Y-m-d');

        // Anti-doublon exact (dans la base)
        $existing = fetchExpenses($user_id);
        $isDuplicate = false;
        foreach ($existing as $e) {
            if ($e['amount'] == $amount && $e['category'] === $category
                && $e['description'] === $description && $e['date'] === $date) {
                $isDuplicate = true;
                break;
            }
        }

        if (!$isDuplicate) {
            $new_expense = [
                'date'        => $date,
                'category'    => $category,
                'description' => $description,
                'amount'      => $amount
            ];
            insertExpense($user_id, $new_expense);

            // Déclencher les alertes avec la nouvelle logique DB
            checkAndSendAlerts();

            header('Location: ' . $_SERVER['PHP_SELF'] . '?added=1');
            exit;
        }
    }

    // Mise à jour budgets
    if (isset($_POST['update_budgets'])) {
        $budgets = [];
        foreach ($_POST['budgets'] as $category => $amount) {
            $budgets[$category] = floatval($amount);
        }
        setBudgets($user_id, $budgets);
        if (isset($_POST['monthly_budget'])) {
            setMeta('monthly_budget', floatval($_POST['monthly_budget']));
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?budgets_updated=1&tab=budgets');
        exit;
    }

    // Suppression dépense
    if (isset($_POST['delete_expense'])) {
        $id = $_POST['delete_expense'];
        deleteExpense($id);

        // Déclencher les alertes avec la nouvelle logique DB
        checkAndSendAlerts();

        header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1&tab=' . urlencode($_POST['current_tab'] ?? 'dashboard'));
        exit;
    }

    // Edition dépense
    if (isset($_POST['edit_expense'])) {
        $expense_id  = $_POST['edit_expense_id'];
        $amount      = floatval($_POST['edit_amount']);
        $category    = $_POST['edit_category'];
        $description = trim($_POST['edit_description']);
        $date        = $_POST['edit_date'];

        $fields = [
            'amount'      => $amount,
            'category'    => $category,
            'description' => $description,
            'date'        => $date
        ];
        updateExpense($expense_id, $fields);

        // Déclencher les alertes avec la nouvelle logique DB
        checkAndSendAlerts();

        header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1&tab=' . urlencode($_POST['current_tab'] ?? 'dashboard'));
        exit;
    }

    // Suppression alerte unique
    if (isset($_POST['delete_alert'])) {
        $alert_id = intval($_POST['delete_alert']);
        // On suppose que l'ID correspond à l'ID de la table alerts
        markAlertSeen($alert_id); // ou supprimer si tu veux vraiment effacer
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=alerts');
        exit;
    }

    // Suppression toutes dépenses
    if (isset($_POST['delete_all_expenses'])) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE user_id = ?");
        $stmt->execute([$user_id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted_all=1&tab=expenses');
        exit;
    }

    // Suppression toutes alertes
    if (isset($_POST['clear_all_alerts'])) {
        clearAllAlerts($user_id);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?alerts_cleared=1&tab=alerts');
        exit;
    }

    // Marquer toutes les alertes comme vues
    if (isset($_POST['mark_alerts_seen'])) {
        markAllAlertsSeen($user_id);
        exit;
    }

    // Archiver manuellement le mois en cours
    if (isset($_POST['archive_current_month'])) {
        // Déterminer la période du cycle à archiver : du 27 du mois précédent au 26 du mois courant
        $start = (new DateTime())->modify('first day of this month')->modify('-1 month')->setDate((new DateTime())->format('Y'), (new DateTime())->format('m')-1 <= 0 ? 12 : (new DateTime())->format('m')-1, 27);
        $end = (new DateTime())->setDate((new DateTime())->format('Y'), (new DateTime())->format('m'), 26);
        // Format pour l'archive : YYYY-MM du mois de fin du cycle
        $cycle_label = $end->format('Y-m');
        // Vérifie si l'archive existe déjà
        $existing_archives = fetchArchives($user_id);
        $already_archived = false;
        foreach ($existing_archives as $arc) {
            if ($arc['month_year'] === $cycle_label) {
                $already_archived = true;
                break;
            }
        }
        if (!$already_archived) {
            // Récupère toutes les dépenses du cycle
            global $pdo;
            $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date DESC, id DESC");
            $stmt->execute([$user_id, $start->format('Y-m-d'), $end->format('Y-m-d')]);
            $expenses_cycle = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $budgets_snapshot = getBudgets($user_id);
            $total_expenses_cycle = 0;
            $savings = isset($budgets_snapshot['Épargne']) ? $budgets_snapshot['Épargne'] : 0;
            foreach ($expenses_cycle as $e) $total_expenses_cycle += floatval($e['amount']);
            $archive_data = [
                'budgets' => $budgets_snapshot,
                'expenses' => $expenses_cycle
            ];
            saveArchive($user_id, $cycle_label, $archive_data, $total_expenses_cycle);
            // Réinitialise les budgets (remet à 0 sauf Épargne)
            foreach ($budgets_snapshot as $cat => &$amt) {
                if ($cat !== 'Épargne') $amt = 0;
            }
            setBudgets($user_id, $budgets_snapshot);
            // Supprime toutes les dépenses du cycle
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND date >= ? AND date <= ?");
            $stmt->execute([$user_id, $start->format('Y-m-d'), $end->format('Y-m-d')]);

            // Message Telegram (aléatoire parmi 5)
            $month_label = $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
            $emojis = ['🎉','📦','✅','📖','🥳'];
            $emoji = $emojis[array_rand($emojis)];
            $messages = [
                "Mois archivé ! $month_label : " . formatCurrency($total_expenses_cycle) . " dépensés, " . formatCurrency($savings) . " épargnés. $emoji",
                "C'est dans la boîte ! $month_label archivé. Dépenses : " . formatCurrency($total_expenses_cycle) . ", Épargne : " . formatCurrency($savings) . ". $emoji",
                "Nikolaii valide l'archivage ! $month_label : " . formatCurrency($total_expenses_cycle) . " dépensés, " . formatCurrency($savings) . " sauvegardés. $emoji",
                "Chapitre terminé ! $month_label archivé. Performance : " . formatCurrency($total_expenses_cycle) . "/" . formatCurrency($savings) . ". $emoji",
                "🥳 C'est la fin du cycle! $month_label est terminé et archivé.\n Tu as dépensé " . formatCurrency($total_expenses_cycle) . " et économisé " . formatCurrency($savings) . ". $emoji"
            ];
            $msg = $messages[array_rand($messages)];
            $__nikolaii->sendMessage($msg);

            header('Location: ' . $_SERVER['PHP_SELF'] . '?archived=1');
            exit;
        } else {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?already_archived=1');
            exit;
        }
    }
}

// =====================
// Chargement des données depuis la base
// =====================

// Dépenses
$expenses = fetchExpenses($user_id);
// Budgets
$budgets = getBudgets($user_id);
// Ajout automatique de la catégorie spéciale 'Épargne' si absente
if (!isset($budgets['Épargne'])) {
    $budgets['Épargne'] = 50000;
    setBudgets($user_id, $budgets);
}
// Alertes
$alerts = fetchAlerts($user_id);

// =====================
// Calcul Dépenses Journalières (Semaine en cours)
// =====================
$weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
$weekExpenses = array_fill(0, 7, 0);
// Trouver le lundi de la semaine courante
$today = new DateTime();
$monday = clone $today;
if ($today->format('N') != 1) {
    $monday->modify('last monday');
}
for ($i = 0; $i < 7; $i++) {
    $date = clone $monday;
    $date->modify("+{$i} days");
    $dateStr = $date->format('Y-m-d');
    foreach ($expenses as $expense) {
        if (!empty($expense['date']) && $expense['date'] === $dateStr) {
            $weekExpenses[$i] += floatval($expense['amount']);
        }
    }
}

// Calculs
$total_expenses = 0;
foreach ($expenses as $e) $total_expenses += floatval($e['amount']);
$remaining_budget = isset($budgets) ? array_sum($budgets) - $total_expenses : 0;
$savings_percentage = ($remaining_budget > 0 && defined('MONTHLY_SAVING_GOAL') && MONTHLY_SAVING_GOAL > 0) ? ($remaining_budget / MONTHLY_SAVING_GOAL) * 100 : 0;

// Catégorie la plus dépensière
$top_spending = ['category' => '', 'percentage' => 0];
foreach ($budgets as $cat => $budget) {
    if ($budget > 0) {
        $spent = 0;
        foreach ($expenses as $e) if ($e['category'] === $cat) $spent += floatval($e['amount']);
        $pct = ($spent / $budget) * 100;
        if ($pct > $top_spending['percentage']) {
            $top_spending = ['category' => $cat, 'percentage' => $pct];
        }
    }
}

$daily_average = (date('j') > 0) ? ($total_expenses / date('j')) : 0;

// =====================
// Rendu HTML
// =====================

// Gestion persistante des objectifs d'épargne
function getSavingGoals($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM meta WHERE key = ?");
    $stmt->execute(["saving_goals_".$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? json_decode($row['value'], true) : [];
}
function setSavingGoals($user_id, $goals) {
    global $pdo;
    $stmt = $pdo->prepare("REPLACE INTO meta (key, value) VALUES (?, ?)");
    $stmt->execute(["saving_goals_".$user_id, json_encode($goals)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_goals'])) {
    if (isset($_POST['goals'])) {
        setSavingGoals($user_id, $_POST['goals']);
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?goals_updated=1&tab=reports");
    exit();
}

$saving_goals = getSavingGoals($user_id);

?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="expenses_filters.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        :root{
            --primary:#6D28D9; --secondary:#F472B6; --success:#60A5FA; --danger:#e74c3c;
            --warning:#f39c12; --info:#1abc9c; --light:#EEF2FF; --dark:#6B46C1;
        }
        body{ background:#EEF2FF; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; transition: background-color 0.3s ease;}
        .navbar{ background:var(--primary); transition: background-color 0.3s ease;}
        .card{ border:none; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,.12); margin-bottom:24px; transition: box-shadow 0.3s ease, transform 0.3s ease;}
        .card:hover{ box-shadow:0 12px 30px rgba(0,0,0,.18); transform: translateY(-6px);}
        .stat-card{ text-align:center; padding:24px; transition:transform .4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow .4s cubic-bezier(0.4, 0, 0.2, 1);}
        .stat-card:hover{ transform:translateY(-8px); box-shadow: 0 10px 25px rgba(0,0,0,0.15);}
        .stat-value{ font-size:2rem; font-weight:600; margin:12px 0; color: #333333;}
        .stat-label{ font-weight: 600; color: #555555; margin-bottom: 6px; }
        .btn, .btn-floating-badge, .nav-link {
            font-weight: 500 !important;
            border-radius: 10px !important;
            padding: 0.5rem 1rem !important;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }
        .btn:hover, .btn-floating-badge:hover, .nav-link.active {
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }
        .btn-floating-badge {
            position: fixed;
            bottom: 24px;
            right: 24px;
            border-radius: 12px;
            z-index: 1060;
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            transition: box-shadow 0.3s ease;
        }
        .btn-floating-badge:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .goal-card { transition: transform 0.3s ease, box-shadow 0.3s ease; border-radius: 12px; }
        .goal-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.12); }
        .savings-progress { height: 24px; border-radius: 12px; transition: height 0.3s ease;}
        .progress-bar { transition: width 0.6s ease, background-color 0.3s ease; border-radius: 12px;}
        .chart-container, .small-chart-container, .evolution-chart-container {
            position: relative;
            height: 300px; /* Reduced and unified height */
            width: 100%;
        }
        
        /* Styles pour les filtres */

        #filtersCollapse .card-body { padding: 1.5rem; }
        .btn-outline-primary.active { background-color: #5a3ebf; border-color: #5a3ebf; color: white; transition: background-color 0.3s ease, border-color 0.3s ease;}
        .dropdown-item.sort-btn:hover { background-color: #f1f3f7; transition: background-color 0.3s ease;}
        .dropdown-item.sort-btn:active { background-color: #e0e3eb; transition: background-color 0.3s ease;}

        /* Smooth transitions for alerts */
        .alert-dismissible {
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .alert-dismissible.fade.show {
            opacity: 1;
            transform: translateY(0);
        }
        .alert-dismissible.fade {
            opacity: 0;
            transform: translateY(-20px);
        }

        /* Rounded dots for budgets */
        .rounded-full {
            border-radius: 50%;
        }


    </style>

</head>
<body>

<?php
?>
<!-- Notification container for small notifications -->
<div id="notification-container" style="position: fixed; top: 1rem; right: 1rem; z-index: 1100;"></div>

<script>
function showNotification(message, type = 'success', duration = 4000) {
    const container = document.getElementById('notification-container');
    if (!container) return;

    const notification = document.createElement('div');
    notification.className = 'toast align-items-center text-bg-' + type + ' border-0 show';
    notification.style.minWidth = '250px';
    notification.style.marginBottom = '0.5rem';
    notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    notification.style.borderRadius = '0.375rem';
    notification.style.padding = '0.75rem 1rem';
    notification.style.display = 'flex';
    notification.style.alignItems = 'center';
    notification.style.justifyContent = 'space-between';

    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle me-2"></i>
            <div>${message}</div>
        </div>
        <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
    `;

    const closeBtn = notification.querySelector('button');
    closeBtn.addEventListener('click', () => {
        container.removeChild(notification);
    });

    container.appendChild(notification);

    setTimeout(() => {
        if (container.contains(notification)) {
            container.removeChild(notification);
        }
    }, duration);
}

// Show notifications based on URL parameters
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('added')) {
        showNotification('Dépense ajoutée avec succès!');
    }
    if (urlParams.has('budgets_updated')) {
        showNotification('Budgets mis à jour avec succès!');
    }
    if (urlParams.has('deleted')) {
        showNotification('Dépense supprimée!');
    }
    if (urlParams.has('updated')) {
        showNotification('Dépense modifiée!');
    }
    if (urlParams.has('alerts_cleared')) {
        showNotification('Toutes les alertes ont été effacées.', 'info');
    }
    if (urlParams.has('goals_updated') && urlParams.get('goals_updated') == '1') {
        showNotification('Objectifs d\'épargne mis à jour avec succès!', 'info');
    }
    // Removed forced tab switching to prevent unwanted navigation
});
</script>
<?php
?>



<header class="bg-light border-bottom shadow-sm mb-4">
    <div class="container d-flex justify-content-between align-items-center py-1">
        <div class="d-flex align-items-center">
            <img src="assets/logo2.png" alt="Logo" height="75" class="me-1">
            <div>
                <h5 class="mb-0 fw-bold" style="color: #6537F3;">Money Minder</h5>
                <small class="text-muted">Période actuelle: <?php echo date('F Y'); ?> • Budget: <?php echo formatCurrency(getMeta('monthly_budget')); ?></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="archives.php" class="btn btn-outline-secondary btn-sm">Archives <i class="fas fa-archive ms-1"></i></a>
        <?php
        // Fetch previous savings for comparison
        $previous_savings = getPreviousMonthSavings($user_id);
        $current_savings = $budgets['Épargne'] ?? 0;
        $color_class = 'bg-success'; // default green

        if ($current_savings < $previous_savings) {
            $decrease_ratio = ($previous_savings - $current_savings) / max($previous_savings, 1);
            if ($decrease_ratio > 0.2) {
                $color_class = 'bg-danger'; // red for large decrease
            } else {
                $color_class = 'bg-warning'; // orange for small decrease
            }
        }
        ?>
        <span class="badge <?php echo $color_class; ?> fs-6 py-2 px-3 rounded">Épargne: <?php echo formatCurrency($current_savings); ?></span>
        </div>
    </div>
</header>

<!-- Bouton flottant "Nouvelle dépense" -->
<div class="floating-badge-container">
    <button class="btn btn-primary btn-floating-badge" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
        <i class="fas fa-plus me-2"></i> Nouvelle dépense
    </button>
</div>

<div class="container mt-4">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">Tableau de bord</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="budgets-tab" data-bs-toggle="tab" data-bs-target="#budgets" type="button" role="tab">Budgets</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses" type="button" role="tab">Historique de dépenses</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">Rapports</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="savings-tab" data-bs-toggle="tab" data-bs-target="#savings" type="button" role="tab">Épargne</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link position-relative" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">Alertes</button>
        </li>
        
    </ul>

    <div class="tab-content mt-3" id="myTabContent">
        <!-- Tableau de bord -->
        <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
            <div class="row">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="stat-label">Budget Mensuel</div>
                        <div class="stat-value text-primary"><?php echo formatCurrency(getMeta('monthly_budget')); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="stat-label">Dépenses</div>
                        <div class="stat-value text-danger">-<?php echo formatCurrency($total_expenses); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="stat-label">Reste</div>
                        <div class="stat-value text-success"><?php echo formatCurrency($remaining_budget); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="stat-label">Dépense moyenne/jour</div>
                        <div class="stat-value"><?php echo formatCurrency(round($daily_average)); ?></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                <div class="card">
    <div class="card-header">Répartition des Dépenses</div>
    <div class="card-body">
        <div class="chart-container">
            <canvas id="expensesChart"></canvas>
        </div>
    </div>
</div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Dépenses Récentes</div>
                        <div class="card-body" id="recent-expenses">
                            <?php
                            $recent_expenses = $expenses;
                            usort($recent_expenses, function($a, $b) {
                                $ta = isset($a['created_at']) ? strtotime($a['created_at']) : (isset($a['date']) ? strtotime($a['date']) : 0);
                                $tb = isset($b['created_at']) ? strtotime($b['created_at']) : (isset($b['date']) ? strtotime($b['date']) : 0);
                                return $tb - $ta;
                            });
                            $recent = array_slice($recent_expenses, 0, 2);
                        ?>
                            <?php if (!empty($recent)): foreach ($recent as $expense): ?>
                            <div class="recent-expense mb-3 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($expense['description']); ?></div>
                                        <div class="text-muted"><?php echo htmlspecialchars($expense['category']); ?></div>
                                        <div class="text-danger">-<?php echo formatCurrency($expense['amount']); ?></div>
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($expense['date'])); ?></small>
                                    </div>
                                    <div class="btn-group ms-2">
<button class="btn btn-sm btn-outline-secondary"
        data-bs-toggle="modal" data-bs-target="#editExpenseModal"
        data-id="<?php echo $expense['id']; ?>"
        data-description="<?php echo htmlspecialchars($expense['description']); ?>"
        data-amount="<?php echo $expense['amount']; ?>"
        data-category="<?php echo htmlspecialchars($expense['category']); ?>"
        data-date="<?php echo $expense['date']; ?>">
    <i class="fas fa-edit"></i>
</button>
<form method="POST" class="d-inline">
    <input type="hidden" name="delete_expense" value="<?php echo $expense['id']; ?>">
    <input type="hidden" name="current_tab" value="dashboard">
    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette dépense ?')">
        <i class="fas fa-trash"></i>
    </button>
</form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                                <p class="text-muted">Aucune dépense récente</p>
                            <?php endif; ?>
                        </div>
                    </div>


                </div>
            </div>
        </div>

        <!-- Budgets -->
        <div class="tab-pane fade" id="budgets" role="tabpanel" style="color: black;">
            <div class="row">
                <div class="col-md-8">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3" style="color: #4B5563;">
                            <i class="fas fa-chart-line me-2"></i>Progression Budget
                        </h5>
                        <p class="text-muted mb-4" style="font-size: 0.9rem;">Utilisation des budgets par catégorie</p>
                        <?php
                        $sorted_budgets = $budgets;
                        ksort($sorted_budgets, SORT_NATURAL | SORT_FLAG_CASE);
                        foreach ($sorted_budgets as $category => $budget):
                            if (floatval($budget) > 0):
                                $spent = calculateCategoryExpenses($category);
                                $used_percent = $budget > 0 ? round(($spent / $budget) * 100, 1) : 0;
                                $remaining = floatval($budget) - $spent;

                                // Determine dot color (keep original colors)
                                $colors = [
                                    'Alimentation' => '#1E40AF', // blue (same as home screen graph)
                                    'Transport' => '#3b82f6', // blue
                                    'Loisirs/Sortie' => '#8b5cf6', // purple
                                    'Mode' => '#ec4899', // pink
                                    'Aide proche' => '#10b981', // green
                                    'Abonnement mensuel' => '#f59e0b', // orange
                                    'Épargne' => '#b91c1c', // dark red
                                ];
                                $dot_color = isset($colors[$category]) ? $colors[$category] : '#6b7280'; // gray default
                        ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="rounded-full" style="display:inline-block; width:12px; height:12px; background-color: <?= $dot_color ?>;"></span>
                                    <span class="fw-semibold" style="color: #374151;"><?= htmlspecialchars($category) ?></span>
                                </div>
                                <div class="text-sm text-gray-600" style="font-size: 0.875rem; color: #4b5563;">
                                    <?= number_format($spent, 0, ',', ' ') ?> FCFA / <?= number_format($budget, 0, ',', ' ') ?> FCFA
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div></div>
                                <div class="text-xs text-gray-500" style="font-size: 0.75rem; color: #6b7280;">
                                    <?= number_format($used_percent, 1) ?>% utilisé
                                </div>
                            </div>
                            <div class="progress" style="height: 8px; border-radius: 4px; background-color: #e5e7eb;">
                                <div class="progress-bar" role="progressbar" style="width: <?= min(100, $used_percent) ?>%; background-color: <?= $dot_color ?>;" aria-valuenow="<?= $used_percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editBudgetsModal">
                                <i class="fas fa-edit me-2"></i>Modifier les budgets
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBudgetCategoryModal">
                                <i class="fas fa-plus me-2"></i>Ajouter une catégorie
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                <div class="card">
    <div class="card-header">Budget vs Dépenses</div>
    <div class="card-body">
        <div class="chart-container">
            <canvas id="budgetComparisonChart"></canvas>
        </div>
    </div>
</div>
                </div>
            </div>
        </div>

        <!-- Dépenses -->
      
<!-- Dépenses -->
<div class="tab-pane fade" id="expenses" role="tabpanel">
    <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
            <span>Liste des Dépenses</span>
            <div>
                <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                    <i class="fas fa-filter me-1"></i>Filtres
                </button>
                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteAllExpensesModal">
                    <i class="fas fa-trash me-1"></i>Supprimer tout
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Section Filtres -->
            <div class="collapse mb-4" id="filtersCollapse">
                <div class="card card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Catégorie</label>
                            <select class="form-select" id="filterCategory">
                                <option value="">Toutes les catégories</option>
                                <?php foreach (array_keys($budgets) as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="filterDateStart">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="filterDateEnd">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Montant min</label>
                            <input type="number" class="form-control" id="filterAmountMin" placeholder="0" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Montant max</label>
                            <input type="number" class="form-control" id="filterAmountMax" placeholder="Max">
                        </div>
                        <div class="col-md-12">
                            <button class="btn btn-primary me-2" id="applyFilters">
                                <i class="fas fa-check me-1"></i>Appliquer
                            </button>
                            <button class="btn btn-outline-secondary" id="resetFilters">
                                <i class="fas fa-times me-1"></i>Réinitialiser
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- En-tête du tableau avec tri -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
<span class="text-muted" id="expensesCount">
    <?php echo count($expenses); ?> dépense(s)
</span>
                </div>
              
            </div>

            <!-- Tableau des dépenses -->
            <div class="table-responsive">
                <table class="table table-striped" id="expensesTable">
                    <thead>
                        <tr>
                            <th><button class="btn btn-link p-0 sort-btn" data-sort="date-desc">Date <i class="fas fa-sort ms-1"></i></button></th>
                            <th><button class="btn btn-link p-0 sort-btn" data-sort="category">Catégorie <i class="fas fa-sort ms-1"></i></button></th>
                            <th>Description</th>
                            <th class="text-end"><button class="btn btn-link p-0 sort-btn" data-sort="amount-desc">Montant <i class="fas fa-sort ms-1"></i></button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTableBody">
                        <?php 
                        // Trier les dépenses par date décroissante (plus récent en premier)
                        $expenses_sorted = $expenses;
                        usort($expenses_sorted, function($a, $b) {
                            $ta = isset($a['created_at']) ? strtotime($a['created_at']) : (isset($a['date']) ? strtotime($a['date']) : 0);
                            $tb = isset($b['created_at']) ? strtotime($b['created_at']) : (isset($b['date']) ? strtotime($b['date']) : 0);
                            return $tb - $ta;
                        });
                        
                        foreach ($expenses_sorted as $expense): ?>
                        <tr>
                            <td data-sort="<?php echo isset($expense['created_at']) ? strtotime($expense['created_at']) : strtotime($expense['date']); ?>">
                                <?php echo isset($expense['created_at']) ? date('d/m/Y H:i', strtotime($expense['created_at'])) : htmlspecialchars($expense['date']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($expense['category']); ?></td>
                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td class="text-end" data-sort="<?php echo $expense['amount']; ?>">
                                -<?php echo formatCurrency($expense['amount']); ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary me-1"
                                        data-bs-toggle="modal" data-bs-target="#editExpenseModal"
                                        data-id="<?php echo $expense['id']; ?>"
                                        data-description="<?php echo htmlspecialchars($expense['description']); ?>"
                                        data-amount="<?php echo $expense['amount']; ?>"
                                        data-category="<?php echo htmlspecialchars($expense['category']); ?>"
                                        data-date="<?php echo $expense['date']; ?>"
                                        title="Modifier">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="delete_expense" value="<?php echo $expense['id']; ?>">
                                    <input type="hidden" name="current_tab" value="expenses">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette dépense ?')" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Message si aucune dépense ne correspond aux filtres -->
            <div id="noExpensesMessage" class="alert alert-info d-none">
                <i class="fas fa-info-circle me-2"></i>Aucune dépense ne correspond à vos critères de filtrage.
            </div>
        </div>
    </div>
</div>

<!-- Rapports -->
<div class="tab-pane fade" id="reports" role="tabpanel">
    <div class="row">
        <!-- Répartition par Catégorie -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">Répartition par Catégorie</div>
                <div class="card-body">
                    <div class="small-chart-container">
                        <canvas id="categoryDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dépenses Journalières (Semaine en cours) -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">Dépenses Journalières (Semaine en cours)</div>
                <div class="card-body">
                    <div class="chart-container" style="overflow-x:auto; overflow-y:visible;">
                        <canvas id="dailyExpensesChart" width="300" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Évolution des Dépenses dans le Temps -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">Évolution des Dépenses</div>
                <div class="card-body">
                    <div class="evolution-chart-container">
                        <canvas id="expensesEvolutionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Épargne -->
        <div class="tab-pane fade" id="savings" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Objectif d'Épargne Mensuel</span>
                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#savingGoalsModal">
                                <i class="fas fa-bullseye me-1"></i> Gérer
                            </button>
                        </div>
                        <div class="card-body">
                            <h5>Ce mois:</h5>
                            <div class="fw-bold fs-4"><?php echo formatCurrency($remaining_budget); ?></div>
                            
                            <h5 class="mt-3">Objectif mensuel:</h5>
                            <div class="fw-bold"><?php echo formatCurrency(MONTHLY_SAVING_GOAL); ?></div>
                            
                            <div class="progress savings-progress mt-3">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min($savings_percentage, 100); ?>%"></div>
                            </div>
                            <div class="text-center mt-2"><?php echo number_format($savings_percentage, 1); ?>%</div>
                            
                            <div class="mt-3">
                                <small>Objectif annuel: <?php echo formatCurrency(ANNUAL_SAVING_GOAL); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>🎯 Mes Objectifs d'Épargne</span>
                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#savingGoalsModal">
                                <i class="fas fa-cog me-1"></i> Modifier
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($saving_goals)): ?>
                                <?php foreach ($saving_goals as $key => $goal): ?>
                                    <?php
                                    $percentage = $goal['target'] > 0 ? min(($goal['current'] / $goal['target']) * 100, 100) : 0;
                                    $remaining = $goal['target'] - $goal['current'];
                                    $today = new DateTime();
                                    $deadline = new DateTime($goal['deadline']);
                                    $interval = $today->diff($deadline);
                                    $months_remaining = max(($interval->y * 12) + $interval->m, 1);
                                    $weeks_remaining = max(ceil($interval->days / 7), 1);
                                    $monthly_saving = $remaining > 0 ? ceil($remaining / $months_remaining) : 0;
                                    $weekly_saving = $remaining > 0 ? ceil($remaining / $weeks_remaining) : 0;
                                    $progressClass = $percentage >= 100 ? 'bg-success' : ($percentage >= 75 ? 'bg-warning' : ($percentage >= 50 ? 'bg-info' : 'bg-primary'));
                                    ?>
                                    
                                    <div class="goal-card mb-3 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($goal['name']); ?></h6>
                                            <span class="badge <?php echo $percentage >= 100 ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo round($percentage); ?>%
                                            </span>
                                        </div>
                                        <div class="progress mb-2" style="height: 12px;">
                                            <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Épargné:</small>
                                                <div class="fw-bold"><?php echo formatCurrency($goal['current']); ?></div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Objectif:</small>
                                                <div class="fw-bold"><?php echo formatCurrency($goal['target']); ?></div>
                                            </div>
                                        </div>
                                        <?php if ($percentage < 100): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <small class="d-block"><i class="fas fa-calendar me-1"></i><strong>Pour atteindre ton objectif:</strong></small>
                                                <?php if ($months_remaining >= 2): ?>
                                                <small class="d-block mt-1"><i class="fas fa-money-bill-wave me-1"></i><?php echo formatCurrency($monthly_saving); ?> / mois <span class="text-muted">(pendant <?php echo $months_remaining; ?> mois)</span></small>
                                                <?php endif; ?>
                                                <small class="d-block mt-1"><i class="fas fa-coins me-1"></i><?php echo formatCurrency($weekly_saving); ?> / semaine <span class="text-muted">(pendant <?php echo $weeks_remaining; ?> semaines)</span></small>
                                                <small class="d-block mt-1 text-muted"><i class="fas fa-hourglass-end me-1"></i>Échéance: <?php echo date('d/m/Y', strtotime($goal['deadline'])); ?></small>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-2 p-2 bg-success bg-opacity-10 rounded">
                                                <small class="text-success"><i class="fas fa-check-circle me-1"></i>Objectif atteint ! 🎉</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-bullseye fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">Aucun objectif défini</p>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#savingGoalsModal">
                                        <i class="fas fa-plus me-1"></i> Créer un objectif
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertes -->
        <div class="tab-pane fade" id="alerts" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Alertes</span>
                    <form method="POST" onsubmit="return confirm('Tout effacer ?')">
                        <button type="submit" name="clear_all_alerts" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-bell-slash me-1"></i>Tout effacer
                        </button>
                    </form>
                </div>
                <div class="card-body">
<?php if (empty($alerts)): ?>
    <p class="text-muted">Aucune alerte pour le moment.</p>
<?php else: ?>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?php echo ($alert['type']==='budget_exceeded' || $alert['type']==='global_budget_exceeded') ? 'danger' : (($alert['type']==='budget_warning'||$alert['type']==='large_expense') ? 'warning' : 'info'); ?> d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo ucfirst(str_replace('_',' ', $alert['type'])); ?>:</strong>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const alertsTab = document.getElementById('alerts-tab');
    <?php
    $unseenAlertsCount = 0;
    foreach ($alerts as $alert) {
        if (isset($alert['seen']) && $alert['seen'] == 0) {
            $unseenAlertsCount++;
        }
    }
    ?>
    const unseenCount = <?php echo $unseenAlertsCount; ?>;
    if (alertsTab && unseenCount > 0) {
        const badge = document.createElement('span');
        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
        badge.style.minWidth = '20px';
        badge.style.height = '20px';
        badge.style.fontSize = '0.75rem';
        badge.style.lineHeight = '20px';
        badge.style.zIndex = '1050';
        badge.textContent = unseenCount;
        alertsTab.appendChild(badge);

        // Marquer les alertes comme vues lors du clic sur l'onglet Alertes
        alertsTab.addEventListener('click', () => {
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'mark_alerts_seen=1'
            }).then(() => {
                badge.remove();
            }).catch(() => {
                // En cas d'erreur, on peut garder le badge
            });
        }, { once: true });
    }
});
</script>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Les Modals -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nouvelle dépense</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
      <div class="mb-3">
  <label class="form-label">Date</label>
  <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>">
</div>
<div class="mb-3">
  <label class="form-label">Catégorie</label>
    <select class="form-select" name="category" required>
        <?php
        // Calculer si la catégorie 'Épargne' doit être affichée
        $expenses = fetchExpenses($user_id);
        $total_spent = 0;
        $all_others_spent = true;
        foreach ($budgets as $cat => $budget) {
                if ($cat === 'Épargne') continue;
                $spent = calculateCategoryExpenses($cat);
                $total_spent += $spent;
                if ($spent < floatval($budget)) {
                        $all_others_spent = false;
                }
        }
        $show_savings = $all_others_spent || ($total_spent >= 140000);
        foreach (array_keys($budgets) as $cat):
                if ($cat === 'Épargne' && !$show_savings) continue;
        ?>
            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="mb-3">
  <label class="form-label" >Montant (FCFA)</label>
  <input type="number" class="form-control" name="amount" min="0" step="100" required>
</div>
<div class="mb-3">
<label class="form-label">Description</label>
<input type="text" class="form-control" name="description" id="description" placeholder="Description claire de la dépense">
</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="add_expense" class="btn btn-primary">Ajouter</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editExpenseModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Modifier la dépense</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit_expense_id" name="edit_expense_id">
        <div class="mb-3">
          <label class="form-label">Montant (FCFA)</label>
          <input type="number" class="form-control" id="edit_amount" name="edit_amount" min="0" step="100" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Catégorie</label>
          <select class="form-select" id="edit_category" name="edit_category" required>
            <?php foreach (array_keys($budgets) as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <input type="text" class="form-control" id="edit_description" name="edit_description" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label">Date</label>
          <input type="date" class="form-control" id="edit_date" name="edit_date">
        </div>
      </div>
      <div class="modal-footer">
        <input type="hidden" name="current_tab" value="expenses">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="edit_expense" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editBudgetsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Modifier les budgets</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="monthly_budget" class="form-label">Budget Mensuel Global (FCFA)</label>
          <input type="number" class="form-control" id="monthly_budget" name="monthly_budget" value="<?php echo getMeta('monthly_budget'); ?>" required>
        </div>
        <h6 class="mt-4">Budgets par Catégorie</h6>
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
    </form>
  </div>
</div>

<!-- Modal pour ajouter une nouvelle catégorie de budget -->
<div class="modal fade" id="addBudgetCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une catégorie de budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nom de la catégorie</label>
                    <input type="text" class="form-control" name="new_budget_category" required placeholder="Ex: Loisirs, Transport...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Montant (FCFA)</label>
                    <input type="number" class="form-control" name="new_budget_amount" min="0" step="1000" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="add_budget_category" class="btn btn-success">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal pour gérer les objectifs d'épargne -->
<div class="modal fade" id="savingGoalsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🎯 Objectifs d'épargne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <button type="button" class="btn btn-success btn-sm" onclick="addNewGoal()">
                            <i class="fas fa-plus me-1"></i> Nouvel objectif
                        </button>
                    </div>

                    <div id="goals-container">
                        <?php foreach ($saving_goals as $key => $goal): ?>
                        <div class="goal-item card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="mb-2">
                                            <label class="form-label">Nom de l'objectif</label>
                                            <input type="text" class="form-control" name="goals[<?php echo $key; ?>][name]" value="<?php echo htmlspecialchars($goal['name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-2">
                                            <label class="form-label">Montant cible (FCFA)</label>
                                            <input type="number" class="form-control" name="goals[<?php echo $key; ?>][target]" value="<?php echo $goal['target']; ?>" min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-2">
                                            <label class="form-label">Épargné actuel (FCFA)</label>
                                            <input type="number" class="form-control" name="goals[<?php echo $key; ?>][current]" value="<?php echo $goal['current']; ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-2">
                                            <label class="form-label">Date limite</label>
                                            <input type="date" class="form-control" name="goals[<?php echo $key; ?>][deadline]" value="<?php echo $goal['deadline']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="mb-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGoal(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Barre de progression -->
                                <?php
                                $percentage = $goal['target'] > 0 ? min(($goal['current'] / $goal['target']) * 100, 100) : 0;
                                $progressClass = $percentage >= 100 ? 'bg-success' : ($percentage >= 75 ? 'bg-warning' : ($percentage >= 50 ? 'bg-info' : 'bg-primary'));
                                ?>
                                <div class="progress mt-2" style="height: 20px;">
                                    <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                        <?php echo round($percentage); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
    <?php echo formatCurrency($goal['current']); ?> / <?php echo formatCurrency($goal['target']); ?>
    <?php if ($goal['deadline']): ?>
    - Échéance: <?php echo date('d/m/Y', strtotime($goal['deadline'])); ?>

    <?php
    // Calcul des économies nécessaires
    $remaining = $goal['target'] - $goal['current'];
    $today = new DateTime();
    $deadline = new DateTime($goal['deadline']);
    $interval = $today->diff($deadline);
    $months_remaining = max(($interval->y * 12) + $interval->m, 1);
    $monthly_saving = $remaining > 0 ? ceil($remaining / $months_remaining) : 0;
    ?>

    <br><small class="text-info">
        <i class="fas fa-lightbulb me-1"></i>
        <?php echo formatCurrency($monthly_saving); ?> / mois nécessaire
    </small>
    <?php endif; ?>
</small>
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
        </div>
    </div>
</div>

<!-- Modal de confirmation pour supprimer toutes les dépenses -->
<div class="modal fade" id="deleteAllExpensesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmer la suppression
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Êtes-vous sûr de vouloir supprimer <strong>toutes les dépenses</strong> ?</p>
                <div class="alert alert-danger">
                    <i class="fas fa-warning me-2"></i>
                    <strong>Attention :</strong> Cette action est irréversible. Toutes les données de dépenses seront supprimées définitivement.
                </div>
                <p class="text-muted small">Cette action n'affecte pas les budgets ou les archives.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="POST" class="d-inline">
                    <button type="submit" name="delete_all_expenses" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Supprimer tout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function addNewGoal() {
    const container = document.getElementById('goals-container');
    const newId = 'goal_' + Date.now();
    
    const newGoal = `
        <div class="goal-item card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="mb-2">
                            <label class="form-label">Nom de l'objectif</label>
                            <input type="text" class="form-control" name="goals[${newId}][name]" value="Nouvel objectif" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-2">
                            <label class="form-label">Montant cible (FCFA)</label>
                            <input type="number" class="form-control" name="goals[${newId}][target]" value="100000" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-2">
                            <label class="form-label">Épargné actuel (FCFA)</label>
                            <input type="number" class="form-control" name="goals[${newId}][current]" value="0" min="0">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-2">
                            <label class="form-label">Date limite</label>
                            <input type="date" class="form-control" name="goals[${newId}][deadline]" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="mb-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGoal(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="progress mt-2" style="height: 20px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: 0%">0%</div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', newGoal);
}

function removeGoal(button) {
    const goalItem = button.closest('.goal-item');
    goalItem.remove();
}

</script>

<?php
$diverseColors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#8AC926', '#1982C4', '#F472B6', '#60A5FA', '#34D399', '#FBBF24'];
$chartColors = [];
$colorIndex = 0;

// Define the blue color to assign to "Alimentation"
$blueColor = '#1E40AF'; // Darker blue color from the palette
// Define the lighter color to swap with (currently assigned to Alimentation)
$lighterColor = '#36A2EB'; // The lighter blue in $diverseColors

foreach (array_keys($budgets) as $cat) {
    if ($cat === 'Épargne') {
        $chartColors[] = '#DC3545'; // red for Épargne
    } elseif ($cat === 'Alimentation') {
        // Assign the darker blue color to Alimentation (Food)
        $chartColors[] = $blueColor;
    } else {
        // For other categories, assign colors from diverseColors, but swap the lighter blue with the lighterColor
        $color = $diverseColors[$colorIndex % count($diverseColors)];
        // If the color is the blue lighterColor, replace it with the lighterColor (swap)
        if ($color === $blueColor) {
            $color = $lighterColor;
        }
        $chartColors[] = $color;
        $colorIndex++;
    }
}
?>

<!-- Inclusion unique de Bootstrap JS (doit être juste avant </body>) -->

<script>
const diverseColors = [
  '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
  '#9966FF', '#FF9F40', '#8AC926', '#1982C4',
  '#F472B6', '#60A5FA', '#34D399', '#FBBF24'
];

document.addEventListener('DOMContentLoaded', function() {
  // Configuration commune pour tous les graphiques
  const commonChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom'
      }
    }
  };

  // Pie: répartition des dépenses (Tableau de bord)
  const expensesCtx = document.getElementById('expensesChart');
  if (expensesCtx) {
    new Chart(expensesCtx, {
      type: 'pie',
      data: {
        labels: [<?php echo implode(',', array_map(fn($c)=>"'".addslashes($c)."'", array_keys($budgets))); ?>],
        datasets: [{
          data: [<?php echo implode(',', array_map(fn($c)=>calculateCategoryExpenses($c), array_keys($budgets))); ?>],
          backgroundColor: [<?php echo "'" . implode("','", $chartColors) . "'"; ?>]
        }]
      },
      options: commonChartOptions
    });
  }

  // Bar: Budget vs Dépenses (Budgets)
  const budgetCtx = document.getElementById('budgetComparisonChart');
  if (budgetCtx) {
    const labels = [<?php
      $labels = [];
      foreach ($budgets as $category => $budget) {
        if (floatval($budget) > 0) $labels[] = $category;
      }
      echo implode(',', array_map(fn($c)=>"'".addslashes($c)."'", $labels));
    ?>];
    const budgetData = [<?php
      $data = [];
      foreach ($budgets as $category => $budget) {
        if (floatval($budget) > 0) $data[] = floatval($budget);
      }
      echo implode(',', $data);
    ?>];
    const spentData = [<?php
      $data = [];
      foreach ($budgets as $category => $budget) {
        if (floatval($budget) > 0) $data[] = calculateCategoryExpenses($category);
      }
      echo implode(',', $data);
    ?>];

    new Chart(budgetCtx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Budget',
            data: budgetData,
            backgroundColor: '#60A5FA' // light blue for Budget
          },
          {
            label: 'Dépensé',
            data: spentData,
            backgroundColor: '#e74c3c' // red for Dépensé
          }
        ]
      },
      options: {
        ...commonChartOptions,
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });
  }

  // Graphique de répartition par catégorie (Rapports)
  const catCtx = document.getElementById('categoryDistributionChart');
  if (catCtx) {
    new Chart(catCtx, {
      type: 'doughnut',
      data: {
        labels: [<?php
          $categories = array_keys($budgets);
          echo implode(',', array_map(function($c) {
            return "'" . addslashes($c) . "'";
          }, $categories));
        ?>],
        datasets: [{
          data: [<?php
            echo implode(',', array_map(function($c) {
              return calculateCategoryExpenses($c);
            }, $categories));
          ?>],
          backgroundColor: [<?php echo "'" . implode("','", $chartColors) . "'"; ?>]
        }]
      },
      options: commonChartOptions
    });
  }

  // Graphique des dépenses journalières (semaine en cours) - Rapports
  const dailyCtx = document.getElementById('dailyExpensesChart');
  if (dailyCtx) {
    const weekData = [<?php echo implode(',', $weekExpenses); ?>];
    const weekLabels = [<?php echo '"' . implode('","', $weekDays) . '"'; ?>];
    new Chart(dailyCtx, {
      type: 'bar',
      data: {
        labels: weekLabels,
        datasets: [{
          label: 'Dépenses (FCFA)',
          data: weekData,
          backgroundColor: diverseColors[1]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            title: { display: true, text: 'Montant (FCFA)' }
          },
          x: {
            title: { display: true, text: 'Jours de la semaine' }
          }
        }
      }
    });
  }

  // Graphique d'évolution des dépenses dans le temps (30 derniers jours) - Rapports
  const evolutionCtx = document.getElementById('expensesEvolutionChart');
  if (evolutionCtx) {
    <?php
    $last30Days = [];
    $dailyTotals = [];
    for ($i = 29; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days"));
      $last30Days[] = $date;
      $dailyTotals[$date] = 0;
    }
    if (isset($expenses) && is_array($expenses)) {
      foreach ($expenses as $expense) {
        $expenseDate = $expense['date'];
        if (isset($dailyTotals[$expenseDate])) {
          $dailyTotals[$expenseDate] += floatval($expense['amount']);
        }
      }
    }
    $jsLabels = [];
    $jsData = [];
    foreach ($last30Days as $date) {
      $jsLabels[] = date('d/m', strtotime($date));
      $jsData[] = $dailyTotals[$date];
    }
    ?>
    const chartLabels = <?php echo json_encode($jsLabels); ?>;
    const chartData = <?php echo json_encode($jsData); ?>;
    new Chart(evolutionCtx, {
      type: 'line',
      data: {
        labels: chartLabels,
        datasets: [{
          label: 'Dépenses quotidiennes',
          data: chartData,
          borderColor: diverseColors[0],
          backgroundColor: 'rgba(255, 99, 132, 0.1)',
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, title: { display: true, text: 'Montant (FCFA)' } },
          x: { title: { display: true, text: 'Date' } }
        }
      }
    });
  }

  // Pré-remplir le modal d'édition
  const editModal = document.getElementById('editExpenseModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      document.getElementById('edit_expense_id').value = button.getAttribute('data-id');
      document.getElementById('edit_description').value = button.getAttribute('data-description');
      document.getElementById('edit_amount').value = button.getAttribute('data-amount');
      document.getElementById('edit_category').value = button.getAttribute('data-category');
      document.getElementById('edit_date').value = button.getAttribute('data-date');
    });
  }
});
</script>







