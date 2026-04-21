<?php
// api/archive-current-month.php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
requireAuth();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../telegram_bot.php';

date_default_timezone_set('Africa/Abidjan');

init_db();
$user_id = getCurrentUserId();

global $__nikolaii;
if (!isset($__nikolaii)) {
    $__nikolaii = new Nikolaii();
}

$archiveResult = archiveCurrentCycle($user_id);
if ($archiveResult['success']) {
    $__nikolaii->sendMessage(buildArchiveSummaryMessage($archiveResult));
    echo json_encode([
        'success' => true,
        'message' => $archiveResult['message'],
        'cycle' => $archiveResult['cycle'],
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'status' => $archiveResult['status'] ?? 'error',
    'message' => $archiveResult['message'] ?? 'Échec de l’archivage.',
    'cycle' => $archiveResult['cycle'] ?? null,
]);
?>
