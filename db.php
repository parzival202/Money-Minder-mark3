<?php
// db.php - wrapper SQLite pour ton app de budget
// Emplacement du fichier DB : ./data/app.db
// Assure-toi que le dossier ./data existe et est inscriptible par PHP.

// Constantes de l'application
define('APP_NAME', 'MoneyMinder');
define('MONTHLY_SAVING_GOAL', 50000); // Budget mensuel d'épargne par défaut en FCFA
define('ANNUAL_SAVING_GOAL', 600000);  // Budget annuel d'épargne par défaut en FCFA

$DB_FILE = __DIR__ . '/data/app.db';

if (!is_dir(dirname($DB_FILE))) {
    mkdir(dirname($DB_FILE), 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . $DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Activer foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (Exception $e) {
    die('Erreur DB: ' . $e->getMessage());
}

/**
 * Init DB: crée les tables si elles n'existent pas.
 */
function init_db() {
    global $pdo;

    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS budgets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        category TEXT NOT NULL,
        amount REAL NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        date TEXT NOT NULL, -- YYYY-MM-DD
        category TEXT NOT NULL,
        description TEXT,
        amount REAL NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS alerts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT,
        message TEXT,
        seen INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS archives (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        month_year TEXT NOT NULL, -- e.g. 2025-08
        data_json TEXT NOT NULL,  -- JSON snapshot
        total_expenses REAL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS meta (
        key TEXT PRIMARY KEY,
        value TEXT
    );
    ";

    // Exécute toutes les commandes
    $pdo->exec($sql);

    // Migration automatique des données de session vers DB si première initialisation
    if (!getMeta('db_initialized')) {
        migrateSessionDataToDb(ensure_default_user());
        setMeta('db_initialized', '1');
    }
}

/**
 * Ensure default local user (single user app). Returns user_id.
 */
function ensure_default_user() {
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int)$row['id'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->execute(['localuser', password_hash('changeme', PASSWORD_DEFAULT)]);
    return (int)$pdo->lastInsertId();
}

/* -------------------------
   Expenses CRUD
   ------------------------- */

function insertExpense($userId, $expense) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $expense['date'],
        $expense['category'],
        $expense['description'] ?? null,
        $expense['amount']
    ]);
    return (int)$pdo->lastInsertId();
}

function fetchExpenses($userId, $monthYear = null) {
    global $pdo;
    if ($monthYear) {
        // monthYear format YYYY-MM
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND strftime('%Y-%m', date) = ? ORDER BY date DESC, id DESC");
        $stmt->execute([$userId, $monthYear]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC, id DESC");
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateExpense($id, $fields) {
    global $pdo;
    $sets = [];
    $params = [];
    foreach ($fields as $k => $v) {
        $sets[] = "$k = ?";
        $params[] = $v;
    }
    if (empty($sets)) return false;
    $params[] = $id;
    $sql = "UPDATE expenses SET " . implode(', ', $sets) . ", updated_at = datetime('now') WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function deleteExpense($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    return $stmt->execute([$id]);
}

/* -------------------------
   Budgets
   ------------------------- */

function getBudgets($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT category, amount FROM budgets WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) $out[$r['category']] = (float)$r['amount'];
    return $out;
}

function setBudgets($userId, $budgetsAssoc) {
    global $pdo;
    $pdo->beginTransaction();
    // remove old
    $stmt = $pdo->prepare("DELETE FROM budgets WHERE user_id = ?");
    $stmt->execute([$userId]);
    // insert new
    $ins = $pdo->prepare("INSERT INTO budgets (user_id, category, amount) VALUES (?, ?, ?)");
    foreach ($budgetsAssoc as $cat => $amt) {
        $ins->execute([$userId, $cat, $amt]);
    }
    $pdo->commit();
    return true;
}

/* -------------------------
   Alerts
   ------------------------- */

function insertAlert($userId, $type, $message, $seen = 0) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO alerts (user_id, type, message, seen) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $message, $seen ? 1 : 0]);
    return (int)$pdo->lastInsertId();
}

function fetchAlerts($userId, $onlyUnseen = false) {
    global $pdo;
    if ($onlyUnseen) {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE user_id = ? AND seen = 0 ORDER BY created_at DESC");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markAlertSeen($alertId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE alerts SET seen = 1 WHERE id = ?");
    return $stmt->execute([$alertId]);
}

function clearAllAlerts($userId) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM alerts WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

function markAllAlertsSeen($userId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE alerts SET seen = 1 WHERE user_id = ? AND seen = 0");
    return $stmt->execute([$userId]);
}

/* -------------------------
   Archives
   ------------------------- */

function saveArchive($userId, $monthYear, $dataArray, $totalExpenses) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO archives (user_id, month_year, data_json, total_expenses) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $monthYear, json_encode($dataArray), $totalExpenses]);
    return (int)$pdo->lastInsertId();
}

function fetchArchives($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM archives WHERE user_id = ? ORDER BY month_year DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['data'] = json_decode($r['data_json'], true);
    }
    return $rows;
}

function getArchiveById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM archives WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) $r['data'] = json_decode($r['data_json'], true);
    return $r;
}

/* -------------------------
   Migration: session -> DB
   ------------------------- */

function migrateSessionDataToDb($userId) {
    global $pdo;

    // Si la table expenses contient déjà des lignes on n'importe pas (prévention double import)
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM expenses WHERE user_id = ?");
    $stmt->execute([$userId]);
    $c = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];
    if ($c > 0) return false; // déjà des données -> skip

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    $pdo->beginTransaction();
    try {
        if (!empty($_SESSION['expenses']) && is_array($_SESSION['expenses'])) {
            foreach ($_SESSION['expenses'] as $e) {
                // s'attend à ['date','category','description','amount'] ou ['amount' => ...]
                $expense = [
                    'date' => $e['date'] ?? ($e['created_at'] ?? date('Y-m-d')),
                    'category' => $e['category'] ?? 'Divers',
                    'description' => $e['description'] ?? ($e['desc'] ?? ''),
                    'amount' => (float)($e['amount'] ?? 0)
                ];
                insertExpense($userId, $expense);
            }
        }
        if (!empty($_SESSION['budgets']) && is_array($_SESSION['budgets'])) {
            setBudgets($userId, $_SESSION['budgets']);
        }
        if (!empty($_SESSION['alerts']) && is_array($_SESSION['alerts'])) {
            foreach ($_SESSION['alerts'] as $a) {
                insertAlert($userId, $a['type'] ?? 'info', $a['message'] ?? '', $a['seen'] ?? 0);
            }
        }
        // Pas d'archivage automatique ici — archives seront recréées via checkAndArchiveMonth()
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Migration session->DB failed: " . $e->getMessage());
        return false;
    }
}

/* -------------------------
   Archivage mensuel automatique
   - Si le mois précédent n'est pas en archives, on le snapshot.
   ------------------------- */

/**
 * ARCHIVE LOGIC UPDATED FOR 27→27 PERIOD
 * Helper function to get the period range (27th to 26th) for a given date.
 * Returns array with 'start' and 'end' dates in Y-m-d format.
 */
function getPeriodRange($date) {
    $dt = new DateTime($date);
    $year = $dt->format('Y');
    $month = $dt->format('m');
    $day = (int)$dt->format('d');

    if ($day >= 27) {
        // Period starts on 27th of current month
        $start = new DateTime("$year-$month-27");
        $end = clone $start;
        $end->modify('+30 days -1 day'); // Ends on 26th of next month
    } else {
        // Period starts on 27th of previous month
        $prevMonth = new DateTime("$year-$month-01");
        $prevMonth->modify('-1 month');
        $start = new DateTime($prevMonth->format('Y-m-27'));
        $end = clone $start;
        $end->modify('+30 days -1 day');
    }

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d')
    ];
}

function checkAndArchiveMonth($userId) {
    global $pdo;

    // ARCHIVE LOGIC UPDATED FOR 27→27 PERIOD
    // Trigger only when current date is the 27th
    if (date('d') != '27') {
        return false;
    }

    // Calculate previous period start (27th of last month)
    $prev_start = date('Y-m-d', strtotime(date('Y-m-27', strtotime('first day of last month'))));
    // End is 29 days later (26th of current month)
    $prev_end = date('Y-m-d', strtotime($prev_start . ' +29 days'));
    // month_year is YYYY-MM of the start date
    $prev = date('Y-m', strtotime($prev_start));

    // Vérifier s'il existe déjà
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM archives WHERE user_id = ? AND month_year = ?");
    $stmt->execute([$userId, $prev]);
    $c = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];
    if ($c > 0) return false; // déjà archivé

    // ARCHIVE LOGIC UPDATED FOR 27→27 PERIOD
    // Récupérer dépenses de la période précédente (27th to 26th)
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date DESC, id DESC");
    $stmt->execute([$userId, $prev_start, $prev_end]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($expenses)) return false; // rien à archiver

    // Récupérer budgets actuels comme snapshot
    $budgets = getBudgets($userId);

    // Calcul total
    $total = 0;
    foreach ($expenses as $e) $total += (float)$e['amount'];

    $data = [
        'expenses' => $expenses,
        'budgets' => $budgets
    ];

    try {
        saveArchive($userId, $prev, $data, $total);
        return true;
    } catch (Exception $e) {
        error_log("Archive save failed: " . $e->getMessage());
        return false;
    }
}

/* -------------------------
   Petit utilitaire : exécution sûre de code DB
   ------------------------- */

function safeQuery($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/* -------------------------
   Fonctions utilitaires manquantes
   ------------------------- */

/**
 * Récupère une valeur meta depuis la table meta.
 */
function getMeta($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM meta WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

/**
 * Définit une valeur meta dans la table meta.
 */
function setMeta($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("REPLACE INTO meta (key, value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

/**
 * Formate un montant en FCFA avec séparateurs.
 */
function formatCurrency($amount) {
    $num = (float) $amount;
    return number_format($num, 0, ',', ' ') . ' FCFA';
}

/**
 * Calcule le total des dépenses pour une catégorie spécifique.
 */
function calculateCategoryExpenses($category, $user_id = null) {
    global $pdo;
    if ($user_id === null) {
        $user_id = ensure_default_user();
    }
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND category = ?");
    $stmt->execute([$user_id, $category]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float)($row['total'] ?? 0);
}

// Helper function to get previous month's savings for a user
function getPreviousMonthSavings($user_id) {
    global $pdo;
    // Determine previous month in format YYYY-MM
    $now = new DateTime();
    $now->modify('first day of last month');
    $prev_month = $now->format('Y-m');

    // Try to fetch archived data for previous month
    $stmt = $pdo->prepare("SELECT data_json FROM archives WHERE user_id = ? AND month_year = ?");
    $stmt->execute([$user_id, $prev_month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $archive_data = json_decode($row['data_json'], true);
        if (isset($archive_data['budgets']['Épargne'])) {
            return floatval($archive_data['budgets']['Épargne']);
        }
    }
    // If no archive found, fallback to 0
    return 0;
}
