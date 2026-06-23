<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
requireAuth();
require_once __DIR__ . '/../db.php';

init_db();
$service = new App\Services\ApiService();
echo json_encode($service->budgetVsSpent(getCurrentUserId()));
?>
