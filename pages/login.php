<div class="login-card mui-card">
    <div class="login-header">
        <div class="login-icon">
            <span class="material-icons">admin_panel_settings</span>
        </div>
        <h1>User Management System</h1>
        <p class="login-subtitle">Sign in to your account</p>
    </div>

    <?php if (!empty($login_notice)): ?>
        <div class="login-alert login-alert-info" role="alert">
            <?php echo htmlspecialchars($login_notice, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($login_error)): ?>
        <div class="login-alert login-alert-error" role="alert">
            <?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form action="index.php?page=login" method="POST" class="login-form" novalidate>
        <?php echo csrfField(); ?>
        <div class="mui-input-group">
            <input
                type="text"
                class="mui-input"
                id="identifier"
                name="identifier"
                placeholder="Email Address or Username"
                value="<?php echo htmlspecialchars($_POST['identifier'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="username"
                required
            >
            <label class="mui-label" for="identifier">Email Address or Username</label>
        </div>
        <div class="mui-input-group">
            <input
                type="password"
                class="mui-input"
                id="password"
                name="password"
                placeholder="Password"
                autocomplete="current-password"
                required
            >
            <label class="mui-label" for="password">Password</label>
        </div>
        <div class="login-options">
            <label class="checkbox-label">
                <input type="checkbox" class="mui-checkbox" name="remember_me" value="1" <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                <span class="checkbox-custom"></span>
                <span class="checkbox-text">Remember me</span>
            </label>
            <a href="#" class="forgot-link" aria-controls="forgot-password-message">Forgot password?</a>
        </div>
        <div id="forgot-password-message" class="login-alert login-alert-info forgot-password-message" role="alert" tabindex="-1" hidden>
            <strong>Please contact your system administrator to reset your password.</strong><br>
            For security reasons, self-service password reset is not available on this system.
        </div>
        <button type="submit" class="mui-btn mui-btn-contained login-btn">
            <span class="material-icons">login</span>
            Sign In
        </button>
    </form>
    <div class="login-footer">
        <p>&copy; <?php echo date('Y'); ?> UMS. All rights reserved.</p>
    </div>
</div>