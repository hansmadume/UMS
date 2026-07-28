<?php
// UMS - User Management System
// Main entry point / router

// Include configuration and authentication helpers before any output
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/users.php';

startSecureSession();

// Simple routing based on request
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// Allowed pages
$allowed_pages = ['login', 'dashboard', 'profile', 'user_management', 'user_roles', 'logout'];

if (!in_array($page, $allowed_pages, true)) {
    $page = 'login';
}

$protected_pages = ['dashboard', 'profile', 'user_management', 'user_roles'];
$login_error = '';
$login_notice = '';

if ($page === 'logout') {
    logoutUser();
    redirectTo('login');
}

if (!empty($_SESSION['login_notice'])) {
    $login_notice = $_SESSION['login_notice'];
    unset($_SESSION['login_notice']);
}

if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if ($identifier === '' || $password === '') {
        $login_error = 'Please enter your email address or username and password.';
    } else {
        try {
            if (attemptLogin($identifier, $password, $remember_me)) {
                redirectTo('dashboard');
            }

            $login_error = 'Invalid email/username or password.';
        } catch (Throwable $exception) {
            error_log('Login error: ' . $exception->getMessage());
            $login_error = 'Unable to validate your login at this time. Please try again later.';
        }
    }
}

if ($page === 'login' && isAuthenticated()) {
    redirectTo('dashboard');
}

if (in_array($page, $protected_pages, true)) {
    requireAuthentication();
}

handleProfileManagementRequest();
handleUserManagementRequest();
handleRoleManagementRequest();

// Include header
require_once 'includes/header.php';

if (in_array($page, $allowed_pages, true) && $page !== 'logout') {
    require_once "pages/{$page}.php";
} else {
    require_once 'pages/login.php';
}

// Include footer
require_once 'includes/footer.php';