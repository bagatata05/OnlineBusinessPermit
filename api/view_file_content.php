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

// Check permissions (owner, admin, or staff can view)
if ($user['role'] !== 'admin' && $user['role'] !== 'staff' && $requirement['owner_id'] != $user['user_id']) {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

$file_path = __DIR__ . '/../' . $requirement['file_path'];

if (!file_exists($file_path)) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

// Get file info
$file_info = pathinfo($file_path);
$file_extension = strtolower($file_info['extension'] ?? '');
$file_name = $requirement['file_name'] ?: basename($file_path);

// Set appropriate content type for inline viewing
$mime_type = mime_content_type($file_path);

switch ($file_extension) {
    case 'pdf':
        header('Content-Type: application/pdf');
        break;
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    case 'png':
        header('Content-Type: image/png');
        break;
    case 'gif':
        header('Content-Type: image/gif');
        break;
    case 'bmp':
        header('Content-Type: image/bmp');
        break;
    case 'webp':
        header('Content-Type: image/webp');
        break;
    default:
        header('Content-Type: ' . $mime_type);
}

// Set headers for inline viewing (not download)
header('Content-Disposition: inline; filename="' . htmlspecialchars($file_name) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: max-age=3600, must-revalidate');
header('Pragma: public');

// Prevent hotlinking by checking referrer
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    $allowed_domains = [$_SERVER['HTTP_HOST'], 'localhost'];
    if (!in_array($referer['host'], $allowed_domains)) {
        header('HTTP/1.0 403 Forbidden');
        exit();
    }
}

readfile($file_path);
exit();
?>
