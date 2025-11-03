<?php
// Trigger daily warning alert

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

// Insert expenses for warning
$expenses = [
    ['date' => date('Y-m-d'), 'category' => 'Test', 'description' => 'Exp1', 'amount' => 5000],
    ['date' => date('Y-m-d'), 'category' => 'Test', 'description' => 'Exp2', 'amount' => 4000],
];

foreach ($expenses as $exp) {
    insertExpense($user_id, $exp);
}

echo "Triggering daily_warning alert...\n";
checkDailyExpenses($__nikolaii);
echo "Done. Check Telegram for the message.\n";
?>
