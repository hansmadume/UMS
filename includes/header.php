<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMS - User Management System</title>
    
    <!-- Google Fonts - Roboto (MUI Standard) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Global Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Page-Specific Styles -->
    <?php
    $page = isset($_GET['page']) ? $_GET['page'] : 'login';
    $css_file = "assets/css/{$page}.css";
    if (file_exists($css_file)) {
        echo '<link rel="stylesheet" href="' . $css_file . '">';
    }
    ?>
</head>
<body>
    <?php if ($page !== 'login'): ?>
    <?php $current_user = function_exists('getAuthenticatedUser') ? getAuthenticatedUser() : null; ?>
    <?php $can_manage_users = function_exists('userHasRole') && userHasRole(['administrator', 'manager'], $current_user); ?>
    <?php $can_manage_roles = function_exists('userHasRole') && userHasRole(['administrator'], $current_user); ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <span class="material-icons sidebar-logo">admin_panel_settings</span>
            <h2>UMS</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php?page=dashboard" class="nav-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                <span class="material-icons">dashboard</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <?php if ($can_manage_users): ?>
                <a href="index.php?page=user_management" class="nav-item <?php echo $page === 'user_management' ? 'active' : ''; ?>">
                    <span class="material-icons">group</span>
                    <span class="nav-text">Users</span>
                </a>
            <?php endif; ?>
            <?php if ($can_manage_roles): ?>
                <a href="index.php?page=user_roles" class="nav-item <?php echo $page === 'user_roles' ? 'active' : ''; ?>">
                    <span class="material-icons">security</span>
                    <span class="nav-text">Roles</span>
                </a>
                <a href="index.php?page=audit_logs" class="nav-item <?php echo $page === 'audit_logs' ? 'active' : ''; ?>">
                    <span class="material-icons">history</span>
                    <span class="nav-text">Audit Logs</span>
                </a>
            <?php endif; ?>
            <a href="index.php?page=profile" class="nav-item <?php echo $page === 'profile' ? 'active' : ''; ?>">
                <span class="material-icons">person</span>
                <span class="nav-text">Profile</span>
            </a>
            <div class="nav-spacer"></div>
            <a href="index.php?page=logout" class="nav-item logout">
                <span class="material-icons">logout</span>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </aside>
    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <span class="material-icons topbar-menu-icon" id="menuToggle">menu</span>
                <h3 class="topbar-title"><?php echo ucfirst(str_replace('_', ' ', $page)); ?></h3>
            </div>
            <div class="topbar-right">
                <?php if ($current_user): ?>
                    <span class="topbar-user">
                        <?php echo htmlspecialchars($current_user['name'] ?: $current_user['username'] ?: $current_user['email'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
                <span class="material-icons topbar-icon">notifications</span>
                <span class="material-icons topbar-icon">account_circle</span>
            </div>
        </header>
        <!-- Page Content -->
        <main class="content">
    <?php else: ?>
        <main class="login-wrapper">
    <?php endif; ?>

