<?php
// Force send daily_warning alert bypassing conditions and signature checks

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

echo "Forcing daily_warning alert...\n";
$text = $__nikolaii->renderTemplate('daily_warning', [
    'daily_total' => '9 000 FCFA'
]);
$__nikolaii->sendMessage($text);
echo "Done. Check Telegram for the message.\n";
?>
