<?php
require_once '../db.php';

header('Content-Type: application/json');

// Ensure DB is initialized
init_db();

// Get default user
$user_id = ensure_default_user();

// Insert a test alert
$alert_id = insertAlert($user_id, 'test', 'This is a test alert to verify the red dot badge functionality.');

if ($alert_id) {
    echo json_encode(['success' => true, 'message' => 'Test alert created successfully', 'alert_id' => $alert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create test alert']);
}
?>
