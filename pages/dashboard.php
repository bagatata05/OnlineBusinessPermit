<?php
require_once __DIR__ . '/../includes/auth.php';

// Check authentication
$auth = new Auth();
if (!$auth->checkAuth()) {
    header('Location: index.php?page=login');
    exit();
}

$user = $auth->getCurrentUser();
$conn = getDBConnection();

// Get dashboard statistics based on user role
$stats = [];
$applications = [];
$recentActivity = [];

if ($user['role'] === 'admin') {
    // Admin stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN permit_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN permit_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN permit_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN permit_status = 'released' THEN 1 ELSE 0 END) as released
        FROM permits
    ");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get recent applications
    $stmt = $conn->prepare("
        SELECT p.*, b.business_name, u.first_name, u.last_name 
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        JOIN users u ON b.owner_id = u.user_id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get recent activity
    $stmt = $conn->prepare("
        SELECT al.*, u.first_name, u.last_name 
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($user['role'] === 'staff') {
    // Staff stats
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
    
    // Get pending applications
    $stmt = $conn->prepare("
        SELECT p.*, b.business_name, u.first_name, u.last_name 
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        JOIN users u ON b.owner_id = u.user_id
        WHERE p.permit_status IN ('pending', 'under_review')
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} else {
    // Applicant stats
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
    
    // Get user's applications
    $stmt = $conn->prepare("
        SELECT p.*, b.business_name 
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        WHERE b.owner_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="header">
                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                    <span>☰</span>
                </button>
                <h1 class="header-title">Dashboard</h1>
                <div class="header-actions">
                    <div class="header-user">
                        <div class="header-user-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                    </div>
                </div>
            </header>
            
            <div class="content">
                <!-- Welcome Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="mb-2">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h2>
                        <p class="text-secondary mb-0">
                            <?php 
                            switch($user['role']) {
                                case 'admin':
                                    echo 'System Administrator';
                                    break;
                                case 'staff':
                                    echo 'Staff Member';
                                    break;
                                default:
                                    echo 'Business Applicant';
                                    break;
                            }
                            ?>
                        </p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-icon primary">📊</div>
                        <div class="stat-card-value text-primary"><?php echo number_format($stats['total_applications'] ?? 0); ?></div>
                        <div class="stat-card-label">Total Applications</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon warning">⏳</div>
                        <div class="stat-card-value text-warning"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                        <div class="stat-card-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon success">✅</div>
                        <div class="stat-card-value text-success"><?php echo number_format($stats['approved'] ?? 0); ?></div>
                        <div class="stat-card-label">Approved</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon info">📋</div>
                        <div class="stat-card-value text-info"><?php echo number_format($stats['released'] ?? 0); ?></div>
                        <div class="stat-card-label">Released</div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Applications -->
                    <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php echo $user['role'] === 'admin' ? 'Recent Applications' : 'Your Applications'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($applications)): ?>
                            <p class="text-gray text-center">No applications found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Permit #</th>
                                            <th>Business</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <?php if ($user['role'] !== 'applicant'): ?>
                                                <th>Applicant</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): ?>
                                            <tr>
                                                <td>
                                                    <a href="index.php?page=tracking&id=<?php echo $app['permit_id']; ?>" 
                                                       class="text-primary">
                                                        <?php echo htmlspecialchars($app['permit_number']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($app['business_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo ucfirst($app['permit_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch($app['permit_status']) {
                                                        case 'pending':
                                                            $statusClass = 'badge-warning';
                                                            break;
                                                        case 'under_review':
                                                            $statusClass = 'badge-info';
                                                            break;
                                                        case 'approved':
                                                            $statusClass = 'badge-success';
                                                            break;
                                                        case 'rejected':
                                                            $statusClass = 'badge-danger';
                                                            break;
                                                        case 'released':
                                                            $statusClass = 'badge-primary';
                                                            break;
                                                        default:
                                                            $statusClass = 'badge-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $app['permit_status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($app['application_date']); ?></td>
                                                <?php if ($user['role'] !== 'applicant'): ?>
                                                    <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

                    <!-- Quick Actions / Recent Activity -->
                    <div class="col-12 col-lg-4">
                <?php if ($user['role'] === 'applicant'): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="index.php?page=business-registration" class="btn btn-primary">
                                    Register New Business
                                </a>
                                <a href="index.php?page=permit-application" class="btn btn-success">
                                    Apply for Permit
                                </a>
                                <a href="index.php?page=renewals" class="btn btn-warning">
                                    Renew Permit
                                </a>
                                <a href="index.php?page=tracking" class="btn btn-info">
                                    Track Application
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($user['role'] === 'admin' && !empty($recentActivity)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-log">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-gray">
                                            <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                        </small>
                                        <div class="mt-1">
                                            <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                            <?php if ($activity['first_name']): ?>
                                                by <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($activity['details']): ?>
                                            <small class="text-gray"><?php echo htmlspecialchars($activity['details']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Add click handlers for interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Handle status badge clicks for filtering
            const statusBadges = document.querySelectorAll('.badge');
            statusBadges.forEach(badge => {
                badge.style.cursor = 'pointer';
                badge.addEventListener('click', function() {
                    const status = this.textContent.trim();
                    // Could implement filtering here
                    console.log('Filter by status:', status);
                });
            });
        });
    </script>
</body>
</html>
