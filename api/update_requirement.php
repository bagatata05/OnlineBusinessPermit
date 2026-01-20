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

$requirement_id = intval($input['requirement_id'] ?? 0);
$is_submitted = isset($input['is_submitted']) ? (bool)$input['is_submitted'] : false;
$is_verified = isset($input['is_verified']) ? (bool)$input['is_verified'] : false;
$notes = sanitize($input['notes'] ?? '');

// Validate input
if (!$requirement_id) {
    echo json_encode(['success' => false, 'message' => 'Requirement ID is required']);
    exit();
}

// Update requirement
$permit = new Permit();
$result = $permit->updateRequirement($requirement_id, $is_submitted, $is_verified, $user['user_id'], $notes);

echo json_encode($result);
?>
