<div class="login-card mui-card">
    <div class="login-header">
        <div class="login-icon">
            <span class="material-icons">admin_panel_settings</span>
        </div>
        <h1>User Management System</h1>
        <p class="login-subtitle">Sign in to your account</p>
    </div>
    <form action="#" method="POST" class="login-form">
        <div class="mui-input-group">
            <input type="text" class="mui-input" id="username" placeholder="Username" required>
            <label class="mui-label" for="username">Username</label>
        </div>
        <div class="mui-input-group">
            <input type="password" class="mui-input" id="password" placeholder="Password" required>
            <label class="mui-label" for="password">Password</label>
        </div>
        <div class="login-options">
            <label class="checkbox-label">
                <input type="checkbox" class="mui-checkbox" checked>
                <span class="checkbox-custom"></span>
                <span class="checkbox-text">Remember me</span>
            </label>
            <a href="#" class="forgot-link">Forgot password?</a>
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
