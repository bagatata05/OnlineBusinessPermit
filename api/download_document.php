<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkAuth()) {
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

$user = $auth->getCurrentUser();
$requirement_id = intval($_GET['id'] ?? 0);

if (!$requirement_id) {
    header('HTTP/1.0 400 Bad Request');
    exit();
}

$conn = getDBConnection();

// Get requirement with permit info
$stmt = $conn->prepare("
    SELECT pr.*, p.permit_id, b.owner_id
    FROM permit_requirements pr
    JOIN permits p ON pr.permit_id = p.permit_id
    JOIN businesses b ON p.business_id = b.business_id
    WHERE pr.requirement_id = ?
");
$stmt->bind_param("i", $requirement_id);
$stmt->execute();
$requirement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$requirement || !$requirement['file_path']) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

// Check permissions (owner, admin, or staff can download)
if ($user['role'] !== 'admin' && $user['role'] !== 'staff' && $requirement['owner_id'] != $user['user_id']) {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

$file_path = __DIR__ . '/../' . $requirement['file_path'];

if (!file_exists($file_path)) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

// Set headers for file download
$file_name = $requirement['file_name'] ?: basename($file_path);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . htmlspecialchars($file_name) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($file_path);
exit();
?>

