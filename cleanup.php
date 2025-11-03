<?php
require_once 'db.php';
init_db();
global $pdo;
$pdo->exec('DELETE FROM expenses WHERE description = "Test"');
$pdo->exec('DELETE FROM budgets WHERE category = "Test Category"');
setMeta('saving_goals', '');
setMeta('monthly_budget', '');
setMeta('current_alert_rotation', '0');
echo 'Cleanup done\n';
?>
