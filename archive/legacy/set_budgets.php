<?php
require_once 'db.php';
init_db();
$user_id = ensure_default_user();
$budgets = [
    'Alimentation' => 50000,
    'Transport' => 30000,
    'Loisirs/Sortie' => 20000,
    'Mode' => 15000,
    'Aide proche' => 10000,
    'Abonnement mensuel' => 25000,
    'Épargne' => 50000
];
setBudgets($user_id, $budgets);
setMeta('monthly_budget', 200000);
echo 'Budgets and monthly budget set\n';
?>
