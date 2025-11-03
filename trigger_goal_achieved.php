<?php
// Trigger goal achieved alert

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

// Set saving goals
$goals = [
    [
        'name' => 'Vacances d\'été',
        'target' => 500000,
        'current' => 500000
    ]
];
setMeta('saving_goals', json_encode($goals));

echo "Triggering goal_achieved alert...\n";
checkSavingGoals($__nikolaii);
echo "Done. Check Telegram for the message.\n";
?>
