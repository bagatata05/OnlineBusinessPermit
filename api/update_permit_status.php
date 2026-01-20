<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permit.php';

// Check authentication and permissions
$auth = new Auth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();
if (!in_array($user['role'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient privileges']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$permit_id = intval($input['permit_id'] ?? 0);
$new_status = sanitize($input['status'] ?? '');
$notes = sanitize($input['notes'] ?? '');

// Validate input
if (!$permit_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Permit ID and status are required']);
    exit();
}

// Validate status
$valid_statuses = ['pending', 'under_review', 'approved', 'rejected', 'released'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Update permit status
$permit = new Permit();
$result = $permit->updateStatus($permit_id, $new_status, $user['user_id'], $notes);

echo json_encode($result);
?>
