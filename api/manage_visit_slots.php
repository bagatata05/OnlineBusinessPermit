<?php
require_once __DIR__ . '/../includes/auth.php';

// Check authentication - admin only
$auth = new Auth();
if (!$auth->checkAuth() || $auth->getCurrentUser()['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

$conn = getDBConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'get_slots':
        // Get available visit slots
        $start_date = $_GET['start_date'] ?? date('Y-m-d');
        $end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
        
        $stmt = $conn->prepare("
            SELECT slot_id, slot_date, slot_time_start, slot_time_end, 
                   capacity, booked, (capacity - booked) as available, is_available
            FROM visit_slots
            WHERE slot_date BETWEEN ? AND ? AND is_available = TRUE
            ORDER BY slot_date, slot_time_start
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode(['success' => true, 'slots' => $slots]);
        break;
    
    case 'create_slot':
        // Create new visit slot (admin)
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required']);
            exit();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $slot_date = sanitize($input['slot_date'] ?? '');
        $slot_time_start = sanitize($input['slot_time_start'] ?? '');
        $slot_time_end = sanitize($input['slot_time_end'] ?? '');
        $capacity = intval($input['capacity'] ?? 5);
        
        if (!$slot_date || !$slot_time_start || !$slot_time_end) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        $stmt = $conn->prepare("
            INSERT INTO visit_slots (slot_date, slot_time_start, slot_time_end, capacity)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("sssi", $slot_date, $slot_time_start, $slot_time_end, $capacity);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'slot_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create slot']);
        }
        $stmt->close();
        break;
    
    case 'book_slot':
        // Book a visit slot for a permit
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required']);
            exit();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $permit_id = intval($input['permit_id'] ?? 0);
        $slot_id = intval($input['slot_id'] ?? 0);
        
        if (!$permit_id || !$slot_id) {
            echo json_encode(['success' => false, 'message' => 'Missing permit or slot ID']);
            exit();
        }
        
        // Check slot availability
        $slot_stmt = $conn->prepare("
            SELECT capacity, booked FROM visit_slots WHERE slot_id = ?
        ");
        $slot_stmt->bind_param("i", $slot_id);
        $slot_stmt->execute();
        $slot = $slot_stmt->get_result()->fetch_assoc();
        $slot_stmt->close();
        
        if (!$slot || $slot['booked'] >= $slot['capacity']) {
            echo json_encode(['success' => false, 'message' => 'Slot is full']);
            exit();
        }
        
        // Update permit with scheduled visit date
        $permit_stmt = $conn->prepare("
            SELECT slot_date FROM visit_slots WHERE slot_id = ?
        ");
        $permit_stmt->bind_param("i", $slot_id);
        $permit_stmt->execute();
        $slot_info = $permit_stmt->get_result()->fetch_assoc();
        $permit_stmt->close();
        
        $update_stmt = $conn->prepare("
            UPDATE permits SET scheduled_visit_date = ?, slot_id = ? WHERE permit_id = ?
        ");
        $update_stmt->bind_param("sii", $slot_info['slot_date'], $slot_id, $permit_id);
        
        if ($update_stmt->execute()) {
            // Increment booked count
            $book_stmt = $conn->prepare("
                UPDATE visit_slots SET booked = booked + 1 WHERE slot_id = ?
            ");
            $book_stmt->bind_param("i", $slot_id);
            $book_stmt->execute();
            $book_stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Visit slot booked']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to book slot']);
        }
        $update_stmt->close();
        break;
    
    case 'get_permit_slots':
        // Get available slots for a specific permit
        $permit_id = intval($_GET['permit_id'] ?? 0);
        
        if (!$permit_id) {
            echo json_encode(['success' => false, 'message' => 'Permit ID required']);
            exit();
        }
        
        // Get permit's current slot if any
        $permit_stmt = $conn->prepare("
            SELECT scheduled_visit_date, slot_id FROM permits WHERE permit_id = ?
        ");
        $permit_stmt->bind_param("i", $permit_id);
        $permit_stmt->execute();
        $permit_data = $permit_stmt->get_result()->fetch_assoc();
        $permit_stmt->close();
        
        // Get available slots
        $slots_stmt = $conn->prepare("
            SELECT slot_id, slot_date, slot_time_start, slot_time_end, capacity, booked
            FROM visit_slots
            WHERE slot_date >= CURDATE() AND is_available = TRUE AND (capacity - booked) > 0
            ORDER BY slot_date, slot_time_start
            LIMIT 20
        ");
        $slots_stmt->execute();
        $slots = $slots_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $slots_stmt->close();
        
        echo json_encode([
            'success' => true,
            'current_slot' => $permit_data,
            'available_slots' => $slots
        ]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}

$conn->close();
?>
