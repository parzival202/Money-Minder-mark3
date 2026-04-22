<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
requireAuth();
require_once __DIR__ . '/../db.php';

init_db();
$user_id = getCurrentUserId();
$budgets = getBudgets($user_id);
$expensesByCategory = [];
$totalBudget = 0.0;
$totalSpent = 0.0;

foreach ($budgets as $category => $amount) {
    $budgetAmount = (float)$amount;
    $spentAmount = calculateCategoryExpenses($category, $user_id);
    $expensesByCategory[$category] = [
        'budget' => $budgetAmount,
        'spent' => $spentAmount,
        'remaining' => $budgetAmount - $spentAmount,
    ];
    $totalBudget += $budgetAmount;
    $totalSpent += $spentAmount;
}

echo json_encode([
    'success' => true,
    'total_budget' => $totalBudget,
    'total_spent' => $totalSpent,
    'categories' => $expensesByCategory,
]);
?>
