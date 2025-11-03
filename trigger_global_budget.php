<?php
// Trigger global budget alert

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

echo "Triggering global_budget alert...\n";
$__nikolaii->globalBudget(110000, 100000);
echo "Done. Check Telegram for the message.\n";
?>
