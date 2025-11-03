<?php
// Trigger daily limit alert

date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . '/db.php';
init_db();
$user_id = ensure_default_user();

require_once __DIR__ . '/telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

// Clear sent alerts
setMeta('telegram_sent_alerts', json_encode([]));

// Reset last_daily_check to allow immediate trigger
setMeta('last_daily_check', time() - 14401); // Just over 4 hours ago

// Insert expenses to exceed daily limit
$expenses = [
    ['date' => date('Y-m-d'), 'category' => 'Test', 'description' => 'Exp1', 'amount' => 6000],
    ['date' => date('Y-m-d'), 'category' => 'Test', 'description' => 'Exp2', 'amount' => 5000],
];

foreach ($expenses as $exp) {
    insertExpense($user_id, $exp);
}

echo "Triggering daily_limit alert...\n";
checkDailyExpenses($__nikolaii);
echo "Done. Check Telegram for the message.\n";
?>
