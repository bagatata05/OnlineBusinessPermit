<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();
$requirement_id = intval($_GET['id'] ?? 0);

if (!$requirement_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$conn = getDBConnection();

// Get requirement with permit info
$stmt = $conn->prepare("
    SELECT pr.*, p.permit_id, b.owner_id, p.permit_number
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
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit();
}

// Check permissions (owner, admin, or staff can view)
if ($user['role'] !== 'admin' && $user['role'] !== 'staff' && $requirement['owner_id'] != $user['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$file_path = __DIR__ . '/../' . $requirement['file_path'];

if (!file_exists($file_path)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'File not found on server']);
    exit();
}

// Get file info
$file_info = pathinfo($file_path);
$file_extension = strtolower($file_info['extension'] ?? '');
$file_size = filesize($file_path);
$upload_date = date('Y-m-d H:i:s', filemtime($file_path));

// Determine file type and view capability
$viewable = false;
$viewer_type = 'download';
$mime_type = mime_content_type($file_path);

switch ($file_extension) {
    case 'pdf':
        $viewable = true;
        $viewer_type = 'pdf';
        break;
    case 'jpg':
    case 'jpeg':
    case 'png':
    case 'gif':
    case 'bmp':
    case 'webp':
        $viewable = true;
        $viewer_type = 'image';
        break;
    case 'txt':
    case 'log':
        $viewable = true;
        $viewer_type = 'text';
        break;
    default:
        $viewable = false;
        $viewer_type = 'download';
}

// Generate view URL (for images and PDFs that can be embedded)
$view_url = '';
if ($viewable && ($viewer_type === 'image' || $viewer_type === 'pdf')) {
    // Create a temporary viewing endpoint that serves the file inline
    $view_url = "api/view_file_content.php?id={$requirement_id}&t=" . time();
}

echo json_encode([
    'success' => true,
    'data' => [
        'requirement_id' => $requirement['requirement_id'],
        'requirement_type' => $requirement['requirement_type'],
        'file_name' => $requirement['file_name'] ?: basename($file_path),
        'file_size' => $file_size,
        'file_extension' => $file_extension,
        'mime_type' => $mime_type,
        'upload_date' => $upload_date,
        'submitted_at' => $requirement['submitted_at'],
        'verified' => $requirement['verified'],
        'verified_by' => $requirement['verified_by'],
        'viewable' => $viewable,
        'viewer_type' => $viewer_type,
        'view_url' => $view_url,
        'download_url' => "api/download_document.php?id={$requirement_id}",
        'permit_number' => $requirement['permit_number']
    ]
]);

$conn->close();
?>
