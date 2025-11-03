<?php
// =============================
// Script d'envoi automatique des alertes via Telegram
// À exécuter toutes les heures via le Planificateur de tâches Windows
// =============================

// Définir le fuseau horaire d'Abidjan (GMT+0)
date_default_timezone_set('Africa/Abidjan');

// Initialisation base de données et utilisateur par défaut
require_once __DIR__ . '/db.php';
init_db();
$user_id = ensure_default_user();

// =============================
// Notification et Telegram Bot
// =============================
require_once __DIR__ . '/telegram_bot.php';
global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

// Exécuter les vérifications et envoi des alertes

// Clear sent alerts to allow re-sending
setMeta('telegram_sent_alerts', json_encode([]));

checkAndSendAlerts();

echo "Alertes vérifiées et envoyées avec succès à " . date('Y-m-d H:i:s') . "\n";
?>
