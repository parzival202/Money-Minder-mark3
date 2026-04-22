<?php
// ============================================================
// db.php — Base de données SQLite + fonctions CRUD
// ============================================================

define('APP_NAME',           'MoneyMinder');
define('MONTHLY_SAVING_GOAL', 50000);
define('ANNUAL_SAVING_GOAL',  600000);

$DB_FILE = __DIR__ . '/data/app.db';

if (!is_dir(dirname($DB_FILE))) {
    mkdir(dirname($DB_FILE), 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . $DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (Exception $e) {
    die('Erreur DB: ' . $e->getMessage());
}

// ============================================================
// INITIALISATION
// ============================================================
function init_db() {
    global $pdo;

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT    NOT NULL UNIQUE,
        first_name    TEXT    NOT NULL DEFAULT '',
        last_name     TEXT    NOT NULL DEFAULT '',
        password_hash TEXT    NOT NULL,
        is_admin      INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT    DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS budgets (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        category   TEXT    NOT NULL,
        amount     REAL    NOT NULL,
        created_at TEXT    DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS expenses (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL,
        date        TEXT    NOT NULL,
        category    TEXT    NOT NULL,
        description TEXT,
        amount      REAL    NOT NULL,
        created_at  TEXT    DEFAULT (datetime('now')),
        updated_at  TEXT    DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS alerts (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        type       TEXT,
        message    TEXT,
        seen       INTEGER DEFAULT 0,
        created_at TEXT    DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS archives (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id        INTEGER NOT NULL,
        month_year     TEXT    NOT NULL,
        data_json      TEXT    NOT NULL,
        total_expenses REAL,
        created_at     TEXT    DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS meta (
        key   TEXT PRIMARY KEY,
        value TEXT
    );

    CREATE TABLE IF NOT EXISTS debts (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL,
        label        TEXT    NOT NULL,
        total_amount REAL    NOT NULL DEFAULT 0,
        amount_paid  REAL    NOT NULL DEFAULT 0,
        note         TEXT    DEFAULT '',
        status       TEXT    NOT NULL DEFAULT 'active',
        created_at   TEXT    DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS login_attempts (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        username     TEXT NOT NULL,
        ip           TEXT NOT NULL,
        attempted_at TEXT DEFAULT (datetime('now'))
    );
    ");

    // ── Migrations ────────────────────────────────────────────
    // Ajoute is_admin si la colonne n'existe pas encore (upgrade depuis ancienne version)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0");
    } catch (Exception $e) {
        // Colonne déjà présente — on ignore
    }

    // S'assure qu'il existe au moins un admin (le premier user créé)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN first_name TEXT NOT NULL DEFAULT ''");
    } catch (Exception $e) {
        // Colonne deja presente - on ignore
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_name TEXT NOT NULL DEFAULT ''");
    } catch (Exception $e) {
        // Colonne deja presente - on ignore
    }
    $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    if ($admin_count === 0) {
        $pdo->exec("UPDATE users SET is_admin = 1 WHERE id = (SELECT MIN(id) FROM users)");
    }

    // Migration session → DB (première initialisation)
    if (!getMeta('db_initialized')) {
        migrateSessionDataToDb(ensure_default_user());
        setMeta('db_initialized', '1');
    }
}

// ============================================================
// USERS
// ============================================================

/**
 * Retourne l'ID du seul user local (mode single-user legacy).
 * Utilisé uniquement avant que le système de login soit actif.
 */
function ensure_default_user() {
    global $pdo;
    $row = $pdo->query("SELECT id FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int)$row['id'];

    $pdo->prepare("INSERT INTO users (username, first_name, last_name, password_hash, is_admin) VALUES (?, ?, ?, ?, 1)")
        ->execute(['admin', 'Admin', '', password_hash('changeme', PASSWORD_BCRYPT, ['cost' => 12])]);
    return (int)$pdo->lastInsertId();
}

/**
 * Récupère tous les utilisateurs (pour le panneau admin).
 */
function fetchAllUsers() {
    global $pdo;
    return $pdo->query("SELECT id, username, first_name, last_name, is_admin, created_at FROM users ORDER BY created_at ASC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Crée un nouvel utilisateur. Retourne l'ID ou false si le username existe déjà.
 */
function createUser($username, $password, $is_admin = 0, $first_name = '', $last_name = '') {
    global $pdo;
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    try {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, password_hash, is_admin) VALUES (?, ?, ?, ?, ?)")
            ->execute([trim($username), trim($first_name), trim($last_name), $hash, $is_admin ? 1 : 0]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        return false; // username already taken
    }
}

function fetchUserById($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, is_admin, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getUserDisplayName($user): string {
    if (!$user) return '';
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    return $fullName !== '' ? $fullName : (string)($user['username'] ?? '');
}

function updateUserAccount($user_id, array $fields) {
    global $pdo;
    $allowed = ['username', 'first_name', 'last_name', 'password_hash', 'is_admin'];
    $sets = [];
    $params = [];
    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed, true)) continue;
        $sets[] = $key . ' = ?';
        $params[] = $value;
    }
    if (empty($sets)) return false;
    $params[] = $user_id;
    try {
        return $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Supprime un utilisateur et toutes ses données (CASCADE).
 */
function deleteUser($user_id) {
    global $pdo;
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
}

// ============================================================
// AUTH HELPERS
// ============================================================

/**
 * Compte les tentatives de connexion échouées pour un username+IP dans les 15 dernières minutes.
 */
function countRecentAttempts($username, $ip) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE (username = ? OR ip = ?)
        AND attempted_at >= datetime('now', '-15 minutes')
    ");
    $stmt->execute([$username, $ip]);
    return (int)$stmt->fetchColumn();
}

/**
 * Enregistre une tentative de connexion échouée.
 */
function recordFailedAttempt($username, $ip) {
    global $pdo;
    $pdo->prepare("INSERT INTO login_attempts (username, ip) VALUES (?, ?)")
        ->execute([$username, $ip]);
}

/**
 * Supprime les tentatives enregistrées pour un username (après login réussi).
 */
function clearLoginAttempts($username) {
    global $pdo;
    $pdo->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);
}

// ============================================================
// EXPENSES CRUD
// ============================================================

function insertExpense($userId, $expense) {
    global $pdo;
    $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount) VALUES (?, ?, ?, ?, ?)")
        ->execute([$userId, $expense['date'], $expense['category'], $expense['description'] ?? null, $expense['amount']]);
    return (int)$pdo->lastInsertId();
}

function fetchExpenses($userId, $monthYear = null) {
    global $pdo;
    if ($monthYear) {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND strftime('%Y-%m', date) = ? ORDER BY date DESC, id DESC");
        $stmt->execute([$userId, $monthYear]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC, id DESC");
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateExpense($userId, $id, $fields) {
    global $pdo;
    $sets = []; $params = [];
    foreach ($fields as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
    if (empty($sets)) return false;
    $params[] = $id;
    $params[] = $userId;
    return $pdo->prepare("UPDATE expenses SET " . implode(', ', $sets) . ", updated_at = datetime('now') WHERE id = ? AND user_id = ?")
               ->execute($params);
}

function deleteExpense($userId, $id) {
    global $pdo;
    return $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
}

// ============================================================
// BUDGETS
// ============================================================

function getBudgets($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT category, amount FROM budgets WHERE user_id = ?");
    $stmt->execute([$userId]);
    $out = [];
    $savingsAliases = ['Épargne', 'Ã‰pargne', 'Ãƒâ€°pargne', '?pargne', '??pargne', '?????pargne'];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $category = $r['category'];
        $amount = (float)$r['amount'];
        if (in_array($category, $savingsAliases, true)) {
            $out['Épargne'] = ($out['Épargne'] ?? 0) + $amount;
            continue;
        }
        $out[$category] = $amount;
    }
    foreach ($savingsAliases as $alias) {
        if ($alias !== 'Épargne' && isset($out[$alias])) {
            unset($out[$alias]);
        }
    }
    return $out;
}

function setBudgets($userId, $budgetsAssoc) {
    global $pdo;
    $savingsAliases = ['Ã‰pargne', 'Ãƒâ€°pargne', '?pargne', '??pargne', '?????pargne'];
    foreach ($savingsAliases as $alias) {
        if (isset($budgetsAssoc[$alias])) {
            $budgetsAssoc['Épargne'] = ($budgetsAssoc['Épargne'] ?? 0) + (float)$budgetsAssoc[$alias];
            unset($budgetsAssoc[$alias]);
        }
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    $pdo->prepare("DELETE FROM budgets WHERE user_id = ?")->execute([$userId]);
    $ins = $pdo->prepare("INSERT INTO budgets (user_id, category, amount) VALUES (?, ?, ?)");
    foreach ($budgetsAssoc as $cat => $amt) $ins->execute([$userId, $cat, $amt]);
    if ($ownsTransaction) {
        $pdo->commit();
    }
    return true;
}

// ============================================================
// ALERTS
// ============================================================

function insertAlert($userId, $type, $message, $seen = 0) {
    global $pdo;
    $pdo->prepare("INSERT INTO alerts (user_id, type, message, seen) VALUES (?, ?, ?, ?)")
        ->execute([$userId, $type, $message, $seen ? 1 : 0]);
    return (int)$pdo->lastInsertId();
}

function fetchAlerts($userId, $onlyUnseen = false) {
    global $pdo;
    if ($onlyUnseen) {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE user_id = ? AND seen = 0 ORDER BY created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE user_id = ? ORDER BY created_at DESC");
    }
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markAlertSeen($userId, $alertId) {
    global $pdo;
    return $pdo->prepare("UPDATE alerts SET seen = 1 WHERE id = ? AND user_id = ?")->execute([$alertId, $userId]);
}

function markAllAlertsSeen($userId) {
    global $pdo;
    return $pdo->prepare("UPDATE alerts SET seen = 1 WHERE user_id = ?")->execute([$userId]);
}

function clearAllAlerts($userId) {
    global $pdo;
    return $pdo->prepare("DELETE FROM alerts WHERE user_id = ?")->execute([$userId]);
}

// ============================================================
// ARCHIVES
// ============================================================

function saveArchive($userId, $monthYear, $data, $totalExpenses) {
    global $pdo;
    $pdo->prepare("INSERT INTO archives (user_id, month_year, data_json, total_expenses) VALUES (?, ?, ?, ?)")
        ->execute([$userId, $monthYear, json_encode($data), $totalExpenses]);
}

function fetchArchives($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM archives WHERE user_id = ? ORDER BY month_year DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function decodeArchiveData(array $archive): array {
    if (empty($archive['data_json'])) {
        return [];
    }
    $data = json_decode($archive['data_json'], true);
    return is_array($data) ? $data : [];
}

function getArchiveCycleBounds($referenceDate = 'now'): array {
    $dt = $referenceDate instanceof DateTime ? clone $referenceDate : new DateTime((string)$referenceDate);
    $day = (int)$dt->format('d');

    if ($day >= 27) {
        $start = new DateTime($dt->format('Y-m-27'));
    } else {
        $prev = new DateTime($dt->format('Y-m-01'));
        $prev->modify('-1 month');
        $start = new DateTime($prev->format('Y-m-27'));
    }

    $end = (clone $start)->modify('+30 days');
    return [
        'start' => $start,
        'end' => $end,
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'month_year' => $start->format('Y-m'),
        'legacy_month_year' => $end->format('Y-m'),
        'display_label' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
    ];
}

function findArchiveForCycle($userId, array $cycle) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM archives
        WHERE user_id = ?
          AND month_year IN (?, ?)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $cycle['month_year'], $cycle['legacy_month_year']]);
    $archive = $stmt->fetch(PDO::FETCH_ASSOC);
    return $archive ?: null;
}

function archiveCurrentCycle($userId, $referenceDate = 'now'): array {
    global $pdo;

    $cycle = getArchiveCycleBounds($referenceDate);
    $existingArchive = findArchiveForCycle($userId, $cycle);
    if ($existingArchive) {
        return [
            'success' => false,
            'status' => 'already_archived',
            'message' => 'Cette période est déjà archivée.',
            'cycle' => $cycle,
            'archive' => $existingArchive,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT * FROM expenses
        WHERE user_id = ? AND date >= ? AND date <= ?
        ORDER BY date DESC, id DESC
    ");
    $stmt->execute([$userId, $cycle['start_date'], $cycle['end_date']]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expenses)) {
        return [
            'success' => false,
            'status' => 'no_expenses',
            'message' => 'Aucune dépense à archiver pour cette période.',
            'cycle' => $cycle,
        ];
    }

    $budgets = getBudgets($userId);
    $monthlyBudget = (float)getMeta('monthly_budget', array_sum($budgets), $userId);
    $totalExpenses = 0.0;
    foreach ($expenses as $expense) {
        $totalExpenses += (float)$expense['amount'];
    }

    $archiveData = [
        'period_start' => $cycle['start_date'],
        'period_end' => $cycle['end_date'],
        'display_label' => $cycle['display_label'],
        'monthly_budget' => $monthlyBudget,
        'budgets' => $budgets,
        'expenses' => $expenses,
    ];

    $resetBudgets = $budgets;
    foreach ($resetBudgets as $category => &$amount) {
        if ($category !== 'Épargne') {
            $amount = 0;
        }
    }
    unset($amount);

    $pdo->beginTransaction();
    try {
        saveArchive($userId, $cycle['month_year'], $archiveData, $totalExpenses);
        setBudgets($userId, $resetBudgets);
        $deleteStmt = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND date >= ? AND date <= ?");
        $deleteStmt->execute([$userId, $cycle['start_date'], $cycle['end_date']]);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Échec de l’archivage: ' . $e->getMessage(),
            'cycle' => $cycle,
        ];
    }

    return [
        'success' => true,
        'status' => 'archived',
        'message' => 'Période archivée avec succès.',
        'cycle' => $cycle,
        'total_expenses' => $totalExpenses,
        'savings_amount' => (float)($budgets['Épargne'] ?? 0),
        'monthly_budget' => $monthlyBudget,
        'budgets' => $budgets,
        'expenses' => $expenses,
    ];
}

function buildArchiveSummaryMessage(array $archiveResult): string {
    $cycle = $archiveResult['cycle'];
    $total = formatCurrency($archiveResult['total_expenses'] ?? 0);
    $savings = formatCurrency($archiveResult['savings_amount'] ?? 0);
    return "Mois archivé ! {$cycle['display_label']} : {$total} dépensés, {$savings} épargnés.";
}

// ============================================================
// DEBTS
// ============================================================

function fetchDebts($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM debts WHERE user_id = ? ORDER BY status ASC, created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function insertDebt($user_id, $label, $total_amount, $note = '') {
    global $pdo;
    $pdo->prepare("INSERT INTO debts (user_id, label, total_amount, amount_paid, note) VALUES (?, ?, ?, 0, ?)")
        ->execute([$user_id, $label, $total_amount, $note]);
    return (int)$pdo->lastInsertId();
}

function addDebtPayment($user_id, $debt_id, $payment_amount) {
    global $pdo;
    $pdo->prepare("UPDATE debts SET amount_paid = MIN(amount_paid + ?, total_amount) WHERE id = ? AND user_id = ?")
        ->execute([$payment_amount, $debt_id, $user_id]);
    $pdo->prepare("UPDATE debts SET status = 'settled' WHERE id = ? AND user_id = ? AND amount_paid >= total_amount")
        ->execute([$debt_id, $user_id]);
}

function deleteDebt($user_id, $debt_id) {
    global $pdo;
    $pdo->prepare("DELETE FROM debts WHERE id = ? AND user_id = ?")->execute([$debt_id, $user_id]);
}

// ============================================================
// META
// ============================================================

function buildMetaKey($key, $userId = null) {
    if ($userId === null || $userId === '' || !is_numeric($userId)) {
        return $key;
    }
    return $key . '_user_' . intval($userId);
}

function getMeta($key, $default = '', $userId = null) {
    global $pdo;
    $storageKey = buildMetaKey($key, $userId);
    $stmt = $pdo->prepare("SELECT value FROM meta WHERE key = ?");
    $stmt->execute([$storageKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

function setMeta($key, $value, $userId = null) {
    global $pdo;
    $storageKey = buildMetaKey($key, $userId);
    $pdo->prepare("REPLACE INTO meta (key, value) VALUES (?, ?)")->execute([$storageKey, $value]);
}

function getBudgetTemplateSourceUserId() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['localuser']);
    $userId = $stmt->fetchColumn();
    if ($userId) return (int)$userId;
    return ensure_default_user();
}

function getBudgetTemplateRatios() {
    $sourceUserId = getBudgetTemplateSourceUserId();
    $budgets = getBudgets($sourceUserId);

    if (empty($budgets)) {
        $budgets = [
            'Alimentation' => 50000,
            'Transport' => 30000,
            'Loisirs/Sortie' => 20000,
            'Mode' => 15000,
            'Aide proche' => 10000,
            'Abonnement mensuel' => 25000,
            'Épargne' => 50000,
        ];
    }

    $savings = max((float)($budgets['Épargne'] ?? 0), 0);
    $nonSavings = $budgets;
    unset($nonSavings['Épargne']);
    $baseTotal = array_sum($nonSavings);
    if ($baseTotal <= 0) {
        $baseTotal = 1;
    }

    $categoryRatios = [];
    foreach ($nonSavings as $category => $amount) {
        $categoryRatios[$category] = max((float)$amount, 0) / $baseTotal;
    }

    return [
        'source_user_id' => $sourceUserId,
        'source_budgets' => $budgets,
        'savings_ratio' => ($baseTotal + $savings) > 0 ? ($savings / ($baseTotal + $savings)) : 0,
        'category_ratios' => $categoryRatios,
    ];
}

function suggestBudgetsFromMonthlyTarget(float $monthlyBudget, float $savingsAmount): array {
    $template = getBudgetTemplateRatios();
    $allocatable = max($monthlyBudget - $savingsAmount, 0);
    $budgets = [];
    $runningTotal = 0;
    $categoryRatios = $template['category_ratios'];
    $categories = array_keys($categoryRatios);
    $lastCategory = end($categories);

    foreach ($categoryRatios as $category => $ratio) {
        $amount = ($category === $lastCategory)
            ? max($allocatable - $runningTotal, 0)
            : round($allocatable * $ratio);
        $budgets[$category] = (float)$amount;
        $runningTotal += $amount;
    }

    $budgets['Épargne'] = max($savingsAmount, 0);
    return $budgets;
}

function userNeedsBudgetSetup(int $userId): bool {
    $monthlyBudget = (float)getMeta('monthly_budget', 0, $userId);
    $budgets = getBudgets($userId);
    return $monthlyBudget <= 0 || empty($budgets);
}

function ensureUserBudgetMetaConsistency(int $userId): void {
    $monthlyBudget = (float)getMeta('monthly_budget', 0, $userId);
    if ($monthlyBudget > 0) return;
    $budgets = getBudgets($userId);
    if (!empty($budgets)) {
        setMeta('monthly_budget', array_sum($budgets), $userId);
    }
}

function getContextUserId() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!empty($_SESSION['is_admin']) && !empty($_SESSION['impersonate_user_id'])) {
            return (int)$_SESSION['impersonate_user_id'];
        }
        if (!empty($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
    }
    return ensure_default_user();
}

// ============================================================
// UTILITAIRES
// ============================================================

function formatCurrency($amount) {
    return number_format((float)$amount, 0, ',', ' ') . ' FCFA';
}

function calculateCategoryExpenses($category, $user_id = null) {
    global $pdo;
    if ($user_id === null) $user_id = getContextUserId();
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND category = ?");
    $stmt->execute([$user_id, $category]);
    return (float)($stmt->fetchColumn() ?? 0);
}

function getPreviousMonthSavings($user_id) {
    $reference = new DateTime('first day of last month');
    $archive = findArchiveForCycle($user_id, getArchiveCycleBounds($reference));
    if ($archive) {
        $data = decodeArchiveData($archive);
        if (isset($data['budgets']['Épargne'])) return floatval($data['budgets']['Épargne']);
    }
    return 0;
}

function getPeriodRange($date) {
    $dt   = new DateTime($date);
    $day  = (int)$dt->format('d');
    $year = $dt->format('Y');
    $month = $dt->format('m');
    if ($day >= 27) {
        $start = new DateTime("$year-$month-27");
    } else {
        $prev  = (new DateTime("$year-$month-01"))->modify('-1 month');
        $start = new DateTime($prev->format('Y-m-27'));
    }
    $end = (clone $start)->modify('+30 days -1 day');
    return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
}

function safeQuery($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function migrateSessionDataToDb($userId) {
    global $pdo;
    $c = (int)$pdo->prepare("SELECT COUNT(*) FROM expenses WHERE user_id = ?")
                  ->execute([$userId]) ? $pdo->query("SELECT COUNT(*) FROM expenses WHERE user_id = $userId")->fetchColumn() : 0;
    if ($c > 0) return false;

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $pdo->beginTransaction();
    try {
        if (!empty($_SESSION['expenses'])) {
            foreach ($_SESSION['expenses'] as $e) {
                insertExpense($userId, [
                    'date'        => $e['date'] ?? ($e['created_at'] ?? date('Y-m-d')),
                    'category'    => $e['category'] ?? 'Divers',
                    'description' => $e['description'] ?? '',
                    'amount'      => (float)($e['amount'] ?? 0),
                ]);
            }
        }
        if (!empty($_SESSION['budgets'])) setBudgets($userId, $_SESSION['budgets']);
        if (!empty($_SESSION['alerts'])) {
            foreach ($_SESSION['alerts'] as $a) {
                insertAlert($userId, $a['type'] ?? 'info', $a['message'] ?? '', $a['seen'] ?? 0);
            }
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Migration session->DB failed: " . $e->getMessage());
        return false;
    }
}
init_db();
