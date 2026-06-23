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

$service = new App\Services\ApiService();
echo json_encode($service->checkAlerts($user_id));
?>
