<?php
// Sidebar Navigation Component
if (!isset($user)) {
    require_once __DIR__ . '/auth.php';
    $auth = new Auth();
    $user = $auth->getCurrentUser();
}

$current_page = $_GET['page'] ?? 'dashboard';
$user_initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
?>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php?page=dashboard" class="sidebar-logo">
            <div class="sidebar-logo-icon">📋</div>
            <span class="sidebar-logo-text">Business Permit</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <span>☰</span>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Main</div>
            <div class="sidebar-nav-item">
                <a href="index.php?page=dashboard" class="sidebar-nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <span class="sidebar-nav-icon">📊</span>
                    <span class="sidebar-nav-text">Dashboard</span>
                </a>
            </div>
            
            <?php if ($user['role'] === 'applicant'): ?>
                <div class="sidebar-nav-item">
                    <a href="index.php?page=business-registration" class="sidebar-nav-link <?php echo $current_page === 'business-registration' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">🏢</span>
                        <span class="sidebar-nav-text">Register Business</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="index.php?page=permit-application" class="sidebar-nav-link <?php echo $current_page === 'permit-application' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">📝</span>
                        <span class="sidebar-nav-text">Apply for Permit</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="index.php?page=renewals" class="sidebar-nav-link <?php echo $current_page === 'renewals' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">🔄</span>
                        <span class="sidebar-nav-text">Renewals</span>
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (in_array($user['role'], ['admin', 'staff'])): ?>
                <div class="sidebar-nav-item">
                    <a href="index.php?page=admin" class="sidebar-nav-link <?php echo $current_page === 'admin' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">⚙️</span>
                        <span class="sidebar-nav-text">Admin Panel</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Tools</div>
            <?php if ($user['role'] === 'applicant'): ?>
                <div class="sidebar-nav-item">
                    <a href="index.php?page=tracking" class="sidebar-nav-link <?php echo $current_page === 'tracking' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">🔍</span>
                        <span class="sidebar-nav-text">Track Application</span>
                    </a>
                </div>
            <?php endif; ?>
            <div class="sidebar-nav-item">
                <a href="index.php?page=profile" class="sidebar-nav-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                    <span class="sidebar-nav-icon">👤</span>
                    <span class="sidebar-nav-text">Profile</span>
                </a>
            </div>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?php echo $user_initials; ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="sidebar-user-role"><?php echo ucfirst($user['role']); ?></div>
            </div>
            <div class="sidebar-user-menu">
                <button class="sidebar-user-menu-btn" aria-label="User menu">⋯</button>
            </div>
        </div>
        <div class="sidebar-nav-item mt-2">
            <a href="api/logout.php" class="sidebar-nav-link text-danger">
                <span class="sidebar-nav-icon">🚪</span>
                <span class="sidebar-nav-text">Logout</span>
            </a>
        </div>
    </div>
</aside>

