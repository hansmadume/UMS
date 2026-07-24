<?php
// UMS - User Management System
// Main entry point / router

// Start session
session_start();

// Include configuration
require_once 'config/database.php';

// Include header
require_once 'includes/header.php';

// Simple routing based on request
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// Allowed pages
$allowed_pages = ['login', 'dashboard', 'profile', 'user_management', 'user_roles'];

if (in_array($page, $allowed_pages)) {
    require_once "pages/{$page}.php";
} else {
    require_once 'pages/login.php';
}

// Include footer
require_once 'includes/footer.php';