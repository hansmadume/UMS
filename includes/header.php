<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMS - User Management System</title>
    <script>
        (function () {
            try {
                var savedTheme = localStorage.getItem('ums-theme');
                var prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = savedTheme || (prefersLight ? 'light' : 'dark');

                document.documentElement.setAttribute('data-theme', theme);
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

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
    $currentPage = isset($page) && is_string($page) ? $page : 'login';
    $currentPage = preg_replace('/[^a-z0-9_]/i', '', $currentPage) ?: 'login';
    $css_file = "assets/css/{$currentPage}.css";
    if (file_exists($css_file)) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file, ENT_QUOTES, 'UTF-8') . '">';
    }
    ?>
</head>
<body>
    <?php $page = $currentPage; ?>
    <?php if ($page !== 'login'): ?>
    <?php $current_user = function_exists('getAuthenticatedUser') ? getAuthenticatedUser() : null; ?>
    <?php $notifications = function_exists('getUserNotifications') ? getUserNotifications($current_user) : []; ?>
    <?php $notification_count = count($notifications); ?>
    <?php $notification_key = hash('sha256', json_encode($notifications)); ?>
    <?php if (!empty($_SESSION['show_login_welcome'])) {
        unset($_SESSION['show_login_welcome']);
    } ?>
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
            <form class="logout-form" action="index.php?page=logout" method="post" data-confirm="Are you sure you want to log out?">
                <?php echo function_exists('csrfField') ? csrfField() : ''; ?>
                <button class="nav-item logout" type="submit">
                    <span class="material-icons">logout</span>
                    <span class="nav-text">Logout</span>
                </button>
            </form>
        </nav>
    </aside>
    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <span class="material-icons topbar-menu-icon" id="menuToggle">menu</span>
                <h3 class="topbar-title"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $page)), ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>
            <div class="topbar-right">
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Switch to light mode" aria-pressed="false" title="Toggle light/dark mode">
                    <span class="theme-toggle-track">
                        <span class="theme-toggle-thumb">
                            <span class="material-icons theme-toggle-icon">dark_mode</span>
                        </span>
                    </span>
                </button>
                <div class="topbar-clock" aria-live="polite" title="Online synced time">
                    <span class="material-icons topbar-clock-icon">schedule</span>
                    <span class="topbar-clock-text">
                        <span class="topbar-clock-time" id="sidebarClockTime">--:--:-- --</span>
                        <span class="topbar-clock-date" id="sidebarClockDate">--/--/----</span>
                    </span>
                </div>
                <?php if ($current_user): ?>
                    <span class="topbar-user">
                        <?php echo htmlspecialchars($current_user['name'] ?: $current_user['username'] ?: $current_user['email'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
                <div class="notification-menu">
                    <button class="topbar-icon notification-toggle" id="notificationToggle" type="button" aria-label="Open notifications" aria-expanded="false" aria-controls="notificationPanel" data-notification-key="<?php echo appEscape($notification_key); ?>">
                        <span class="material-icons">notifications</span>
                        <?php if ($notification_count > 0): ?>
                            <span class="notification-badge"><?php echo (int) min($notification_count, 9); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-panel" id="notificationPanel" hidden>
                        <div class="notification-panel-header">
                            <div>
                                <h4>Notifications</h4>
                                <p><?php echo $notification_count > 0 ? (int) $notification_count . ' update' . ($notification_count === 1 ? '' : 's') : 'No updates yet'; ?></p>
                            </div>
                            <span class="material-icons">campaign</span>
                        </div>
                        <div class="notification-list">
                            <?php if ($notification_count > 0): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item notification-<?php echo appEscape((string) ($notification['type'] ?? 'info')); ?>">
                                        <span class="material-icons notification-item-icon"><?php echo appEscape((string) ($notification['icon'] ?? 'notifications')); ?></span>
                                        <div class="notification-item-content">
                                            <strong><?php echo appEscape((string) ($notification['title'] ?? 'Notification')); ?></strong>
                                            <p><?php echo appEscape((string) ($notification['message'] ?? '')); ?></p>
                                            <small><?php echo appEscape((string) ($notification['time'] ?? 'Now')); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notification-empty">
                                    <span class="material-icons">notifications_none</span>
                                    <p>No notifications to show right now.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <span class="material-icons topbar-icon">account_circle</span>
            </div>
        </header>
        <!-- Page Content -->
        <main class="content">
    <?php else: ?>
        <main class="login-wrapper">
    <?php endif; ?>