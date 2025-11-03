<?php
// Trigger goal progress alert

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

// Reset last_goals_check to allow immediate trigger
setMeta('last_goals_check', time() - 86401); // Just over 24 hours ago

// Set saving goals to trigger progress alert (80% progress)
$goals = [
    [
        'name' => 'Test Goal',
        'target' => 10000,
        'current' => 8000
    ]
];
setMeta('saving_goals', json_encode($goals));

echo "Triggering goal_progress alert...\n";
checkSavingGoals($__nikolaii);
echo "Done. Check Telegram for the message.\n";
?>
