<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
requireAuth();
require_once __DIR__ . '/../db.php';

init_db();
$user_id = getCurrentUserId();
$expenses = fetchExpenses($user_id);
$today = new DateTime('today');
$start = (clone $today)->modify('-6 days');
$days = [];

for ($cursor = clone $start; $cursor <= $today; $cursor->modify('+1 day')) {
    $key = $cursor->format('Y-m-d');
    $days[$key] = 0.0;
}

foreach ($expenses as $expense) {
    $date = $expense['date'];
    if (isset($days[$date])) {
        $days[$date] += (float)$expense['amount'];
    }
}

$series = [];
foreach ($days as $date => $amount) {
    $series[] = [
        'date' => $date,
        'amount' => $amount,
    ];
}

echo json_encode([
    'success' => true,
    'series' => $series,
]);
?>
