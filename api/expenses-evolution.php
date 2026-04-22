<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
requireAuth();
require_once __DIR__ . '/../db.php';

init_db();
$user_id = getCurrentUserId();
$expenses = fetchExpenses($user_id);
$evolution = [];

foreach ($expenses as $expense) {
    $date = $expense['date'];
    if (!isset($evolution[$date])) {
        $evolution[$date] = 0.0;
    }
    $evolution[$date] += (float)$expense['amount'];
}

ksort($evolution);

$points = [];
foreach ($evolution as $date => $amount) {
    $points[] = [
        'date' => $date,
        'amount' => $amount,
    ];
}

echo json_encode([
    'success' => true,
    'points' => $points,
]);
?>
