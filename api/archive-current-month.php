<?php
// api/archive-current-month.php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
requireAuth();
requireWritableUserContext(true);
require_once __DIR__ . '/../db.php';

date_default_timezone_set('Africa/Abidjan');

init_db();
$service = new App\Services\ApiService();
echo json_encode($service->archiveCurrentMonth(getCurrentUserId()));
?>
