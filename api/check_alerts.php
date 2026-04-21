<?php
// API to check and send alerts, return updated alerts
header('Content-Type: application/json');

// Définir le fuseau horaire d'Abidjan (GMT+0)
date_default_timezone_set('Africa/Abidjan');
require_once __DIR__ . '/../auth.php';
requireAuth();

// Initialisation base de données et utilisateur par défaut
require_once __DIR__ . '/../db.php';
init_db();
$user_id = getCurrentUserId();

// =============================
// Notification et Telegram Bot
// =============================
require_once __DIR__ . '/../telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

// Check and send alerts
checkAndSendAlerts($user_id);

// Fetch updated alerts
$alerts = fetchAlerts($user_id);

// Return as JSON
echo json_encode($alerts);
?>
