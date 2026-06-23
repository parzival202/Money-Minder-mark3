<?php
// =============================
// Script d'envoi automatique des alertes via Telegram (version hébergée)
// Archivé : cette variante exposait une clé secrète hardcodée en GET.
// Préférer send_alerts.php côté cron local/serveur.
// =============================

$expected_key = 'CHANGE_THIS_SECRET_KEY_IN_PRODUCTION';
if (!isset($_GET['key']) || $_GET['key'] !== $expected_key) {
    http_response_code(403);
    die('Accès refusé');
}

date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . '/../../db.php';
init_db();

require_once __DIR__ . '/../../telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

$users = fetchAllUsers();
foreach ($users as $user) {
    checkAndSendAlerts((int)$user['id']);
}

echo "Alertes vérifiées et envoyées avec succès à " . date('Y-m-d H:i:s') . "\n";
