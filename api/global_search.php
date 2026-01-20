<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
$auth = new Auth();
if (!$auth->checkAuth()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$searchTerm = sanitize($_GET['q'] ?? '');
if (strlen($searchTerm) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search term too short']);
    exit();
}

$conn = getDBConnection();
$results = [];

// Search permits (admin/staff can see all, applicants only see their own)
if ($user['role'] === 'admin' || $user['role'] === 'staff') {
    // Admin/Staff: Search all permits
    $stmt = $conn->prepare("
        SELECT p.permit_id, p.permit_number, p.permit_status, p.application_date,
               b.business_name, u.first_name, u.last_name,
               'permit' as type
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        JOIN users u ON b.owner_id = u.user_id
        WHERE p.permit_number LIKE ? OR b.business_name LIKE ?
        ORDER BY p.application_date DESC
        LIMIT 10
    ");
    $searchPattern = "%{$searchTerm}%";
    $stmt->bind_param("ss", $searchPattern, $searchPattern);
    $stmt->execute();
    $permitResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($permitResults as $result) {
        $results[] = [
            'id' => $result['permit_id'],
            'title' => $result['permit_number'],
            'subtitle' => $result['business_name'] . ' • ' . ucfirst($result['permit_status']),
            'type' => 'permit',
            'url' => "index.php?page=tracking&id=" . $result['permit_id']
        ];
    }
    $stmt->close();
    
} else {
    // Applicant: Search only their permits
    $stmt = $conn->prepare("
        SELECT p.permit_id, p.permit_number, p.permit_status, p.application_date,
               b.business_name,
               'permit' as type
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        WHERE b.owner_id = ? AND (p.permit_number LIKE ? OR b.business_name LIKE ?)
        ORDER BY p.application_date DESC
        LIMIT 10
    ");
    $searchPattern = "%{$searchTerm}%";
    $stmt->bind_param("iss", $user['user_id'], $searchPattern, $searchPattern);
    $stmt->execute();
    $permitResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($permitResults as $result) {
        $results[] = [
            'id' => $result['permit_id'],
            'title' => $result['permit_number'],
            'subtitle' => $result['business_name'] . ' • ' . ucfirst($result['permit_status']),
            'type' => 'permit',
            'url' => "index.php?page=tracking&id=" . $result['permit_id']
        ];
    }
    $stmt->close();
}

// Search businesses (admin/staff only)
if ($user['role'] === 'admin' || $user['role'] === 'staff') {
    $stmt = $conn->prepare("
        SELECT b.business_id, b.business_name, b.business_type,
               u.first_name, u.last_name, u.email,
               'business' as type
        FROM businesses b
        JOIN users u ON b.owner_id = u.user_id
        WHERE b.business_name LIKE ? OR b.business_type LIKE ?
        ORDER BY b.business_name
        LIMIT 5
    ");
    $searchPattern = "%{$searchTerm}%";
    $stmt->bind_param("ss", $searchPattern, $searchPattern);
    $stmt->execute();
    $businessResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($businessResults as $result) {
        $results[] = [
            'id' => $result['business_id'],
            'title' => $result['business_name'],
            'subtitle' => $result['business_type'] . ' • ' . $result['first_name'] . ' ' . $result['last_name'],
            'type' => 'business',
            'url' => "index.php?page=business-registration&action=view&id=" . $result['business_id']
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'total' => count($results)
]);

$conn->close();
?>
