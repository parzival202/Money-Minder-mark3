<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
requireAuth();
require_once __DIR__ . '/../db.php';

init_db();
$user_id = getCurrentUserId();
$budgets = getBudgets($user_id);
$distribution = [];
$totalBudget = array_sum($budgets);

foreach ($budgets as $category => $amount) {
    $amount = (float)$amount;
    $distribution[] = [
        'category' => $category,
        'amount' => $amount,
        'percentage' => $totalBudget > 0 ? round(($amount / $totalBudget) * 100, 2) : 0,
    ];
}

echo json_encode([
    'success' => true,
    'total_budget' => (float)$totalBudget,
    'distribution' => $distribution,
]);
?>
