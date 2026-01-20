<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $auth = new Auth();
    if (!$auth->checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $user = $auth->getCurrentUser();
    if ($user['role'] !== 'admin' && $user['role'] !== 'staff') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }

    $requirement_id = intval($_POST['requirement_id'] ?? 0);
    $action = sanitize($_POST['action'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if (!$requirement_id || !in_array($action, ['approve', 'decline'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
        exit();
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Failed to connect to database');
    }

    // Get requirement with permit info
    $stmt = $conn->prepare("
        SELECT pr.*, p.permit_id, p.permit_number, b.business_name, b.owner_id,
               u.first_name, u.last_name, u.contact_number, u.email
        FROM permit_requirements pr
        JOIN permits p ON pr.permit_id = p.permit_id
        JOIN businesses b ON p.business_id = b.business_id
        JOIN users u ON b.owner_id = u.user_id
        WHERE pr.requirement_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $requirement_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $requirement = $result->fetch_assoc();
    $stmt->close();

    if (!$requirement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Requirement not found']);
        exit();
    }

    // Update the requirement
    if ($action === 'approve') {
        $stmt = $conn->prepare("
            UPDATE permit_requirements 
            SET verified = TRUE, verified_by = ?, verified_at = NOW(), notes = ?
            WHERE requirement_id = ?
        ");
        $action_text = 'approved';
        $status_badge = 'Approved';
        $status_class = 'success';
        
    } else { // decline
        $stmt = $conn->prepare("
            UPDATE permit_requirements 
            SET verified = FALSE, verified_by = ?, verified_at = NOW(), notes = ?
            WHERE requirement_id = ?
        ");
        $action_text = 'declined';
        $status_badge = 'Declined';
        $status_class = 'danger';
    }
    
    if (!$stmt) {
        throw new Exception('Failed to prepare update statement: ' . $conn->error);
    }
    
    $stmt->bind_param("isi", $user['user_id'], $notes, $requirement_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update requirement: ' . $stmt->error);
    }

    // Log the action
    $log_details = "Document '{$requirement['requirement_type']}' for permit {$requirement['permit_number']} was {$action_text}";
    if (!empty($notes)) {
        $log_details .= ". Notes: " . $notes;
    }
    
    $log_stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, details, created_at) 
        VALUES (?, 'file_review', ?, NOW())
    ");
    
    if ($log_stmt) {
        $log_stmt->bind_param("is", $user['user_id'], $log_details);
        $log_stmt->execute();
        $log_stmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => "Document {$action_text} successfully",
        'data' => [
            'requirement_id' => $requirement_id,
            'action' => $action,
            'status_badge' => $status_badge,
            'status_class' => $status_class,
            'verified_by' => $user['first_name'] . ' ' . $user['last_name'],
            'verified_at' => date('M d, Y h:i A')
        ]
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log('File approval error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to process request: ' . $e->getMessage()
    ]);
}
?>
