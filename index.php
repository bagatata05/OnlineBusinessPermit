<?php
require_once 'config.php';

// Simple routing
$page = isset($_GET['page']) ? $_GET['page'] : 'login';
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

// Authentication check for protected pages
$protected_pages = ['dashboard', 'profile', 'applications', 'renewals', 'payments', 'admin'];
if (in_array($page, $protected_pages) && !isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit();
}

// Include the appropriate page
switch ($page) {
    case 'login':
        include 'pages/login.php';
        break;
    case 'register':
        include 'pages/register.php';
        break;
    case 'dashboard':
        include 'pages/dashboard.php';
        break;
    case 'business-registration':
        include 'pages/business-registration.php';
        break;
    case 'permit-application':
        include 'pages/permit-application.php';
        break;
    case 'renewals':
        include 'pages/renewals.php';
        break;
    case 'payments':
        include 'pages/payments.php';
        break;
    case 'tracking':
        include 'pages/tracking.php';
        break;
    case 'admin':
        include 'pages/admin.php';
        break;
    case 'profile':
        include 'pages/profile.php';
        break;
    default:
        include 'pages/404.php';
        break;
}
?>
