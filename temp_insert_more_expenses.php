<?php
require_once 'db.php';
init_db();
$user_id = ensure_default_user();
for($i=0; $i<20; $i++) {
    insertExpense($user_id, [
        'date' => date('Y-m-d'),
        'category' => 'Test Category',
        'description' => 'Test',
        'amount' => 1000
    ]);
}
echo 'More expenses inserted\n';
?>
