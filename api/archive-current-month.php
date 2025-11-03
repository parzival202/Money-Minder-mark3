<?php
// api/archive-current-month.php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../telegram_bot.php';

date_default_timezone_set('Africa/Abidjan');

init_db();
$user_id = ensure_default_user();

global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

// Calculate current period (27th to 26th)
$now = new DateTime();
$day = (int)$now->format('d');

if ($day >= 27) {
    // Current period starts 27th of current month
    $start = new DateTime($now->format('Y-m-27'));
    $end = clone $start;
    $end->modify('+30 days -1 day'); // Ends 26th of next month
    $month_year = $start->format('Y-m');
} else {
    // Current period starts 27th of previous month
    $prev = clone $now;
    $prev->modify('first day of last month');
    $start = new DateTime($prev->format('Y-m-27'));
    $end = clone $start;
    $end->modify('+30 days -1 day');
    $month_year = $start->format('Y-m');
}

// Check if already archived
global $pdo;
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM archives WHERE user_id = ? AND month_year = ?");
$stmt->execute([$user_id, $month_year]);
$c = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];
if ($c > 0) {
    echo json_encode(['success' => false, 'message' => 'Current month already archived.']);
    exit;
}

// Fetch expenses for the period
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date DESC, id DESC");
$stmt->execute([$user_id, $start->format('Y-m-d'), $end->format('Y-m-d')]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($expenses)) {
    echo json_encode(['success' => false, 'message' => 'No expenses to archive for current month.']);
    exit;
}

// Get budgets
$budgets = getBudgets($user_id);

// Calculate total
$total = 0;
foreach ($expenses as $e) $total += (float)$e['amount'];

$data = [
    'expenses' => $expenses,
    'budgets' => $budgets
];

// Save archive
try {
    saveArchive($user_id, $month_year, $data, $total);
    // Delete archived expenses from current expenses
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND date >= ? AND date <= ?");
    $stmt->execute([$user_id, $start->format('Y-m-d'), $end->format('Y-m-d')]);
    // Send alert
    $__nikolaii->monthArchived($month_year);
    echo json_encode(['success' => true, 'message' => 'Current month archived successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to archive: ' . $e->getMessage()]);
}
?>
