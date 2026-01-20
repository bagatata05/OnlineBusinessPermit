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
$conn = getDBConnection();

$stats = [];

// Get statistics based on user role
if ($user['role'] === 'admin') {
    // Admin stats - all permits
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN permit_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN permit_status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN permit_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN permit_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN permit_status = 'released' THEN 1 ELSE 0 END) as released,
            SUM(total_fee) as total_revenue
        FROM permits
    ");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get monthly trends
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(application_date, '%Y-%m') as month,
            COUNT(*) as applications,
            SUM(CASE WHEN permit_status = 'approved' THEN 1 ELSE 0 END) as approved
        FROM permits 
        WHERE application_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(application_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $stats['monthly_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($user['role'] === 'staff') {
    // Staff stats - all permits but limited view
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN permit_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN permit_status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN permit_status = 'approved' THEN 1 ELSE 0 END) as approved
        FROM permits
    ");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
} else {
    // Applicant stats - only their permits
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN permit_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN permit_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN permit_status = 'released' THEN 1 ELSE 0 END) as released
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        WHERE b.owner_id = ?
    ");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
}

// Get recent activity (last 24 hours)
$stmt = $conn->prepare("
    SELECT COUNT(*) as recent_activity
    FROM audit_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute();
$stats['recent_activity'] = $stmt->get_result()->fetch_assoc()['recent_activity'];

$conn->close();

echo json_encode(['success' => true, 'stats' => $stats]);
?>
