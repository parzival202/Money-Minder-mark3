<?php
require_once 'db.php';
init_db();
$user_id = ensure_default_user();
echo "User ID: " . $user_id . "\n";

$expenses = fetchExpenses($user_id);
echo "Expenses count: " . count($expenses) . "\n";
if (count($expenses) > 0) {
    echo "Sample expense: " . json_encode($expenses[0]) . "\n";
}

$budgets = getBudgets($user_id);
echo "Budgets: " . json_encode($budgets) . "\n";

$monthly_budget = getMeta('monthly_budget');
echo "Monthly Budget Meta: " . ($monthly_budget ?: 'Not set') . "\n";

$total_expenses = 0;
foreach ($expenses as $e) {
    $total_expenses += floatval($e['amount']);
}
echo "Calculated Total Expenses: " . $total_expenses . "\n";

$remaining = array_sum($budgets) - $total_expenses;
echo "Remaining Budget: " . $remaining . "\n";

$daily_average = (date('j') > 0) ? ($total_expenses / date('j')) : 0;
echo "Daily Average: " . $daily_average . "\n";

echo "DB File: " . $DB_FILE . "\n";
if (file_exists($DB_FILE)) {
    echo "DB exists, size: " . filesize($DB_FILE) . " bytes\n";
} else {
    echo "DB does not exist\n";
}
?>
