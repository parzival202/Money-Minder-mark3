<?php
// ============================================================
// db.php — Base de données SQLite + fonctions CRUD
// ============================================================

require_once __DIR__ . '/bootstrap/app.php';

$DB_FILE = (string)config('database.path', __DIR__ . '/data/app.db');
$pdo = App\Support\Database::connection();

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
    return (new App\Repositories\UserRepository())->all();
}

/**
 * Crée un nouvel utilisateur. Retourne l'ID ou false si le username existe déjà.
 */
function createUser($username, $password, $is_admin = 0, $first_name = '', $last_name = '') {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    return (new App\Repositories\UserRepository())->create(
        (string)$username,
        $hash,
        (bool)$is_admin,
        (string)$first_name,
        (string)$last_name
    );
}

function fetchUserById($user_id) {
    return (new App\Repositories\UserRepository())->findById((int)$user_id);
}

function getUserDisplayName($user): string {
    if (!$user) return '';
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    return $fullName !== '' ? $fullName : (string)($user['username'] ?? '');
}

function updateUserAccount($user_id, array $fields) {
    return (new App\Repositories\UserRepository())->update((int)$user_id, $fields);
}

/**
 * Supprime un utilisateur et toutes ses données (CASCADE).
 */
function deleteUser($user_id) {
    (new App\Repositories\UserRepository())->delete((int)$user_id);
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
    return (new App\Repositories\ExpenseRepository())->create((int)$userId, $expense);
}

function fetchExpenses($userId, $monthYear = null) {
    return (new App\Repositories\ExpenseRepository())->allForUser((int)$userId, $monthYear ? (string)$monthYear : null);
}

function updateExpense($userId, $id, $fields) {
    return (new App\Repositories\ExpenseRepository())->update((int)$userId, (int)$id, $fields);
}

function deleteExpense($userId, $id) {
    return (new App\Repositories\ExpenseRepository())->delete((int)$userId, (int)$id);
}

// ============================================================
// BUDGETS
// ============================================================

function getBudgets($userId) {
    return (new App\Repositories\BudgetRepository())->mapForUser((int)$userId);
}

function setBudgets($userId, $budgetsAssoc) {
    (new App\Repositories\BudgetRepository())->replaceForUser((int)$userId, $budgetsAssoc);
    return true;
}

// ============================================================
// ALERTS
// ============================================================

function insertAlert($userId, $type, $message, $seen = 0) {
    return (new App\Repositories\AlertRepository())->create((int)$userId, (string)$type, (string)$message, (bool)$seen);
}

function fetchAlerts($userId, $onlyUnseen = false) {
    return (new App\Repositories\AlertRepository())->allForUser((int)$userId, (bool)$onlyUnseen);
}

function markAlertSeen($userId, $alertId) {
    return (new App\Repositories\AlertRepository())->markSeen((int)$userId, (int)$alertId);
}

function markAllAlertsSeen($userId) {
    return (new App\Repositories\AlertRepository())->markAllSeen((int)$userId);
}

function clearAllAlerts($userId) {
    return (new App\Repositories\AlertRepository())->clearAll((int)$userId);
}

// ============================================================
// ARCHIVES
// ============================================================

function saveArchive($userId, $monthYear, $data, $totalExpenses) {
    (new App\Repositories\ArchiveRepository())->save((int)$userId, (string)$monthYear, (array)$data, (float)$totalExpenses);
}

function fetchArchives($userId) {
    return (new App\Repositories\ArchiveRepository())->allForUser((int)$userId);
}

function decodeArchiveData(array $archive): array {
    if (empty($archive['data_json'])) {
        return [];
    }
    $data = json_decode($archive['data_json'], true);
    return is_array($data) ? $data : [];
}

function getArchiveCycleBounds($referenceDate = 'now'): array {
    return (new App\Services\ArchiveService())->cycleBounds($referenceDate);
}

function findArchiveForCycle($userId, array $cycle) {
    return (new App\Services\ArchiveService())->findForCycle((int)$userId, $cycle);
}

function archiveCurrentCycle($userId, $referenceDate = 'now'): array {
    return (new App\Services\ArchiveService())->archiveCurrentCycle((int)$userId, $referenceDate);
}

function buildArchiveSummaryMessage(array $archiveResult): string {
    return (new App\Services\ArchiveService())->buildSummaryMessage($archiveResult);
}

// ============================================================
// DEBTS
// ============================================================

function fetchDebts($user_id) {
    return (new App\Repositories\DebtRepository())->allForUser((int)$user_id);
}

function insertDebt($user_id, $label, $total_amount, $note = '') {
    return (new App\Repositories\DebtRepository())->create((int)$user_id, (string)$label, (float)$total_amount, (string)$note);
}

function addDebtPayment($user_id, $debt_id, $payment_amount) {
    (new App\Repositories\DebtRepository())->addPayment((int)$user_id, (int)$debt_id, (float)$payment_amount);
}

function deleteDebt($user_id, $debt_id) {
    (new App\Repositories\DebtRepository())->delete((int)$user_id, (int)$debt_id);
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
    return (new App\Services\BudgetTemplateService())->sourceUserId();
}

function getBudgetTemplateRatios() {
    return (new App\Services\BudgetTemplateService())->ratios();
}

function suggestBudgetsFromMonthlyTarget(float $monthlyBudget, float $savingsAmount): array {
    return (new App\Services\BudgetTemplateService())->suggestFromMonthlyTarget($monthlyBudget, $savingsAmount);
}

function userNeedsBudgetSetup(int $userId): bool {
    return (new App\Services\BudgetTemplateService())->userNeedsSetup($userId);
}

function ensureUserBudgetMetaConsistency(int $userId): void {
    (new App\Services\BudgetTemplateService())->ensureUserBudgetMetaConsistency($userId);
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
    if ($user_id === null) $user_id = getContextUserId();
    return (new App\Repositories\ExpenseRepository())->totalForCategory((int)$user_id, (string)$category);
}

function getPreviousMonthSavings($user_id) {
    return (new App\Services\ArchiveService())->previousMonthSavings((int)$user_id);
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
    $stmt = App\Support\Database::connection()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function migrateSessionDataToDb($userId) {
    $pdo = App\Support\Database::connection();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE user_id = ?');
    $stmt->execute([(int)$userId]);
    $existingExpenses = (int)$stmt->fetchColumn();
    if ($existingExpenses > 0) {
        return false;
    }

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
?>
