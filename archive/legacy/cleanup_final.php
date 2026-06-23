<?php
require_once 'db.php';
init_db();
global $pdo;
$pdo->exec('DELETE FROM expenses WHERE description = "Test"');
$pdo->exec('DELETE FROM expenses WHERE description = "Exp1"');
$pdo->exec('DELETE FROM expenses WHERE description = "Exp2"');
setMeta('saving_goals', '');
setMeta('monthly_budget', '');
setMeta('current_alert_rotation', '0');
echo 'Cleanup done\n';
?>
