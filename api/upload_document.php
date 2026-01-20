<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->checkAuth()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$requirement_id = intval($_POST['requirement_id'] ?? 0);
$permit_id = intval($_POST['permit_id'] ?? 0);

if (!$requirement_id || !$permit_id) {
    echo json_encode(['success' => false, 'message' => 'Missing requirement or permit ID']);
    exit();
}

// Verify user has permission to upload for this permit
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT p.permit_id, b.owner_id 
    FROM permits p
    JOIN businesses b ON p.business_id = b.business_id
    WHERE p.permit_id = ?
");
$stmt->bind_param("i", $permit_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Permit not found']);
    exit();
}

// Check if user is the owner or admin/staff
if ($user['role'] !== 'admin' && $user['role'] !== 'staff' && $result['owner_id'] != $user['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['document'];
$allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$max_size = MAX_FILE_SIZE; // 5MB

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, PNG, and GIF are allowed.']);
    exit();
}

// Validate file size
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds maximum allowed size (5MB)']);
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/permit_' . $permit_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$file_name = 'req_' . $requirement_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$file_path = $upload_dir . $file_name;
$relative_path = 'uploads/permit_' . $permit_id . '/' . $file_name;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

// Update requirement in database
$stmt = $conn->prepare("
    UPDATE permit_requirements 
    SET file_name = ?, 
        file_path = ?, 
        is_submitted = TRUE, 
        submitted_at = NOW(),
        verified = FALSE,
        verified_by = NULL,
        verified_at = NULL,
        notes = NULL
    WHERE requirement_id = ? AND permit_id = ?
");
$stmt->bind_param("ssii", $file['name'], $relative_path, $requirement_id, $permit_id);

if ($stmt->execute()) {
    logActivity($user['user_id'], 'document_upload', "Document uploaded for requirement ID: {$requirement_id}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Document uploaded successfully',
        'file_name' => $file['name'],
        'file_path' => $relative_path
    ]);
} else {
    // Delete file if database update fails
    unlink($file_path);
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
}

$stmt->close();
$conn->close();
?>

