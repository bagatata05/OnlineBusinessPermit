<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permit.php';

// Check authentication
$auth = new Auth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();

// Get permit ID from GET request
$permit_id = intval($_GET['permit_id'] ?? 0);
if (!$permit_id) {
    echo json_encode(['success' => false, 'message' => 'Permit ID is required']);
    exit();
}

// Get permit details
$permit = new Permit();
$permit_details = $permit->getPermitById($permit_id, $user['user_id'], $user['role']);

if (!$permit_details) {
    echo json_encode(['success' => false, 'message' => 'Permit not found or access denied']);
    exit();
}

echo json_encode(['success' => true, 'permit' => $permit_details]);
?>
