<?php
/**
 * Visit Slot Management for Admin
 * This module provides admin functionality to manage inspection visit slots
 */

require_once __DIR__ . '/../includes/auth.php';

// Check authentication - admin only
$auth = new Auth();
$auth->requireRole('admin');

$user = $auth->getCurrentUser();
$conn = getDBConnection();

$message = '';
$error = '';

// Handle actions
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $slot_date = sanitize($_POST['slot_date'] ?? '');
            $slot_time_start = sanitize($_POST['slot_time_start'] ?? '');
            $slot_time_end = sanitize($_POST['slot_time_end'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 5);
            
            if (!$slot_date || !$slot_time_start || !$slot_time_end) {
                $error = 'All fields are required.';
            } elseif ($slot_time_start >= $slot_time_end) {
                $error = 'End time must be after start time.';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO visit_slots (slot_date, slot_time_start, slot_time_end, capacity)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("sssi", $slot_date, $slot_time_start, $slot_time_end, $capacity);
                
                if ($stmt->execute()) {
                    $message = "Visit slot created successfully!";
                    logActivity($user['user_id'], 'visit_slot_created', 'New visit slot: ' . $slot_date . ' ' . $slot_time_start);
                } else {
                    $error = "Failed to create slot. (Duplicate time slot?)";
                }
                $stmt->close();
            }
        }
        break;
    
    case 'edit':
        $slot_id = intval($_GET['id'] ?? 0);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $capacity = intval($_POST['capacity'] ?? 5);
            $is_available = isset($_POST['is_available']) ? 1 : 0;
            
            $stmt = $conn->prepare("
                UPDATE visit_slots 
                SET capacity = ?, is_available = ?
                WHERE slot_id = ?
            ");
            $stmt->bind_param("iii", $capacity, $is_available, $slot_id);
            
            if ($stmt->execute()) {
                $message = "Visit slot updated!";
                logActivity($user['user_id'], 'visit_slot_updated', 'Updated slot ID: ' . $slot_id);
            } else {
                $error = "Failed to update slot.";
            }
            $stmt->close();
        }
        break;
    
    case 'delete':
        $slot_id = intval($_GET['id'] ?? 0);
        
        if ($slot_id) {
            // Check if slot has any bookings
            $check_stmt = $conn->prepare("SELECT booked FROM visit_slots WHERE slot_id = ?");
            $check_stmt->bind_param("i", $slot_id);
            $check_stmt->execute();
            $slot_data = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($slot_data && $slot_data['booked'] == 0) {
                $stmt = $conn->prepare("DELETE FROM visit_slots WHERE slot_id = ?");
                $stmt->bind_param("i", $slot_id);
                
                if ($stmt->execute()) {
                    $message = "Visit slot deleted!";
                    logActivity($user['user_id'], 'visit_slot_deleted', 'Deleted slot ID: ' . $slot_id);
                } else {
                    $error = "Failed to delete slot.";
                }
                $stmt->close();
            } else {
                $error = "Cannot delete slot with bookings.";
            }
        }
        break;
}

// Get all slots
$slots_stmt = $conn->prepare("
    SELECT slot_id, slot_date, slot_time_start, slot_time_end, capacity, booked,
           (capacity - booked) as available, is_available
    FROM visit_slots
    ORDER BY slot_date DESC, slot_time_start DESC
    LIMIT 100
");
$slots_stmt->execute();
$slots = $slots_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$slots_stmt->close();

// Get slot statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_slots,
        SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_slots,
        SUM(CASE WHEN slot_date >= CURDATE() THEN 1 ELSE 0 END) as future_slots,
        SUM(booked) as total_booked
    FROM visit_slots
");
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Visit Slots - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-wrapper">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="header">
                <h1 class="header-title">Manage Visit Slots</h1>
            </header>
            
            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-card-value"><?php echo $stats['total_slots'] ?? 0; ?></div>
                        <div class="stat-card-label">Total Slots</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-value text-success"><?php echo $stats['available_slots'] ?? 0; ?></div>
                        <div class="stat-card-label">Available Slots</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-value text-info"><?php echo $stats['future_slots'] ?? 0; ?></div>
                        <div class="stat-card-label">Future Slots</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-value text-warning"><?php echo $stats['total_booked'] ?? 0; ?></div>
                        <div class="stat-card-label">Total Bookings</div>
                    </div>
                </div>
                
                <!-- Create New Slot -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Create New Visit Slot</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=create">
                            <div class="row">
                                <div class="col-12 col-md-3">
                                    <div class="form-group">
                                        <label for="slot_date" class="form-label">Date *</label>
                                        <input type="date" class="form-control" id="slot_date" name="slot_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="form-group">
                                        <label for="slot_time_start" class="form-label">Start Time *</label>
                                        <input type="time" class="form-control" id="slot_time_start" name="slot_time_start" required>
                                    </div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="form-group">
                                        <label for="slot_time_end" class="form-label">End Time *</label>
                                        <input type="time" class="form-control" id="slot_time_end" name="slot_time_end" required>
                                    </div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="form-group">
                                        <label for="capacity" class="form-label">Capacity</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity" 
                                               value="5" min="1" max="100">
                                    </div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div class="form-group">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100">Create Slot</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Slots List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Visit Slots</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Capacity</th>
                                        <th>Booked</th>
                                        <th>Available</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($slots as $slot): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('M d, Y', strtotime($slot['slot_date'])); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo substr($slot['slot_time_start'], 0, 5); ?> - <?php echo substr($slot['slot_time_end'], 0, 5); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $slot['capacity']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $slot['booked']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $slot['available']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($slot['is_available']): ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Disabled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $slot['slot_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <?php if ($slot['booked'] == 0): ?>
                                                    <a href="?action=delete&id=<?php echo $slot['slot_id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Delete this slot?')">Delete</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
