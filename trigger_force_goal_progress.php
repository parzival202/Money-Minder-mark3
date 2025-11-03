<?php
// Force send goal_progress alert bypassing conditions and signature checks

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

// Set saving goals to trigger progress alert
$goals = [];
setMeta('saving_goals', json_encode($goals));

echo "Forcing goal_progress alert...\n";
// Goal deleted, no message sent
echo "Goal deleted, no alert sent.\n";
?>
