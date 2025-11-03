<?php
require_once 'db.php';
init_db();
$user_id = ensure_default_user();

$budgets = getBudgets($user_id);
echo "Budgets:\n";
foreach ($budgets as $cat => $amt) {
    $spent = calculateCategoryExpenses($cat, $user_id);
    echo "$cat: Budget $amt, Spent $spent\n";
}

$total_spent = 0;
foreach ($budgets as $cat => $amt) {
    $total_spent += calculateCategoryExpenses($cat, $user_id);
}
echo "Total Spent: $total_spent\n";
?>
