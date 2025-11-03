<?php
// Trigger budget warning alert

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

echo "Triggering budget_warning alert...\n";
$__nikolaii->budgetWarning('Test Category', 85.5);
echo "Done. Check Telegram for the message.\n";
?>
