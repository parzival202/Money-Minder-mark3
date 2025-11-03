<?php
// Trigger large expense alert

date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . '/db.php';
init_db();
$user_id = ensure_default_user();

require_once __DIR__ . '/telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

// Clear sent alerts to allow sending
setMeta('telegram_sent_alerts', json_encode([]));

$testExpense = [
    'id' => 1,
    'date' => date('Y-m-d'),
    'category' => 'Test Category',
    'description' => 'Test large expense',
    'amount' => 15000
];

echo "Triggering large_expense alert...\n";
$__nikolaii->largeExpense($testExpense);
echo "Done. Check Telegram for the message.\n";
?>
