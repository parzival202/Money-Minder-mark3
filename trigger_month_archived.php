<?php
// Trigger month archived alert

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

// Archive previous month (simulate)
$prev_month = date('Y-m', strtotime('first day of last month'));
$expenses = 150000;
$savings = 50000;
$emoji = '😊';

echo "Triggering month_archived alert...\n";
$__nikolaii->monthArchived($prev_month);
echo "Done. Check Telegram for the message.\n";
?>
