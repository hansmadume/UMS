<?php

require_once __DIR__ . '/../config/database.php';

function authRememberMeSeconds(): int
{
    return defined('AUTH_REMEMBER_ME_SECONDS') ? (int) constant('AUTH_REMEMBER_ME_SECONDS') : 2592000;
}

function authInactivityTimeoutSeconds(): int
{
    return defined('AUTH_INACTIVITY_TIMEOUT_SECONDS') ? (int) constant('AUTH_INACTIVITY_TIMEOUT_SECONDS') : 1800;
}

function authCookieSecure(): bool
{
    return defined('AUTH_COOKIE_SECURE') ? (bool) constant('AUTH_COOKIE_SECURE') : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

function applySecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'self'; " .
        "img-src 'self' data:; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
        "script-src 'self' 'unsafe-inline'"
    );
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (authCookieSecure()) {
        ini_set('session.cookie_secure', '1');
    }

    $remembered = !empty($_COOKIE['ums_remember_login']);
    $lifetime = $remembered ? authRememberMeSeconds() : 0;

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => authCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function redirectTo(string $page): void
{
    header('Location: index.php?page=' . urlencode($page));
    exit;
}

function appEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . appEscape(csrfToken()) . '">';
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function requireValidCsrfToken(): void
{
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new RuntimeException('Your request could not be verified. Please refresh the page and try again.');
    }
}

function authLoginMaxAttempts(): int
{
    return defined('AUTH_LOGIN_MAX_ATTEMPTS') ? max(1, (int) constant('AUTH_LOGIN_MAX_ATTEMPTS')) : 5;
}

function authLoginWindowSeconds(): int
{
    return defined('AUTH_LOGIN_WINDOW_SECONDS') ? max(60, (int) constant('AUTH_LOGIN_WINDOW_SECONDS')) : 900;
}

function authLoginLockSeconds(): int
{
    return defined('AUTH_LOGIN_LOCK_SECONDS') ? max(60, (int) constant('AUTH_LOGIN_LOCK_SECONDS')) : 900;
}

function authClientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function authLoginIdentifierHash(string $identifier): string
{
    return hash('sha256', strtolower(trim($identifier)));
}

function authEnsureLoginAttemptsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_attempts_lookup (identifier_hash, ip_address, attempted_at),
            INDEX idx_login_attempts_attempted_at (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function authPruneOldLoginAttempts(PDO $pdo): void
{
    $cutoff = date('Y-m-d H:i:s', time() - max(authLoginWindowSeconds(), authLoginLockSeconds()));
    $statement = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff');
    $statement->execute(['cutoff' => $cutoff]);
}

function authEnforceLoginThrottle(string $identifier): void
{
    try {
        $pdo = getDatabaseConnection();
        authEnsureLoginAttemptsTable($pdo);
        authPruneOldLoginAttempts($pdo);

        $cutoff = date('Y-m-d H:i:s', time() - authLoginWindowSeconds());
        $statement = $pdo->prepare(
            'SELECT COUNT(*) AS attempts, MAX(attempted_at) AS last_attempt
             FROM login_attempts
             WHERE identifier_hash = :identifier_hash
               AND ip_address = :ip_address
               AND attempted_at >= :cutoff'
        );
        $statement->execute([
            'identifier_hash' => authLoginIdentifierHash($identifier),
            'ip_address' => authClientIp(),
            'cutoff' => $cutoff,
        ]);

        $row = $statement->fetch();
        $attempts = (int) ($row['attempts'] ?? 0);
        $lastAttempt = !empty($row['last_attempt']) ? strtotime((string) $row['last_attempt']) : 0;

        if ($attempts >= authLoginMaxAttempts() && $lastAttempt > 0 && (time() - $lastAttempt) < authLoginLockSeconds()) {
            throw new RuntimeException('Too many failed login attempts. Please wait before trying again.');
        }
    } catch (RuntimeException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        error_log('Login throttle check failed: ' . $exception->getMessage());
    }
}

function authRecordFailedLogin(string $identifier): void
{
    try {
        $pdo = getDatabaseConnection();
        authEnsureLoginAttemptsTable($pdo);

        $statement = $pdo->prepare(
            'INSERT INTO login_attempts (identifier_hash, ip_address, attempted_at)
             VALUES (:identifier_hash, :ip_address, :attempted_at)'
        );
        $statement->execute([
            'identifier_hash' => authLoginIdentifierHash($identifier),
            'ip_address' => authClientIp(),
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $exception) {
        error_log('Failed login attempt recording failed: ' . $exception->getMessage());
    }
}

function authClearFailedLogins(string $identifier): void
{
    try {
        $pdo = getDatabaseConnection();
        authEnsureLoginAttemptsTable($pdo);

        $statement = $pdo->prepare(
            'DELETE FROM login_attempts
             WHERE identifier_hash = :identifier_hash
               AND ip_address = :ip_address'
        );
        $statement->execute([
            'identifier_hash' => authLoginIdentifierHash($identifier),
            'ip_address' => authClientIp(),
        ]);
    } catch (Throwable $exception) {
        error_log('Failed login attempt cleanup failed: ' . $exception->getMessage());
    }
}

function authEnsureAuditLogTable(PDO $pdo): void
{
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS audit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                user_name VARCHAR(255) NULL,
                action VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_logs_user_id (user_id),
                INDEX idx_audit_logs_action (action),
                INDEX idx_audit_logs_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $exception) {
        error_log('Audit log schema check failed: ' . $exception->getMessage());
    }
}

function recordAuditLog(string $action, ?array $user = null): void
{
    try {
        $pdo = getDatabaseConnection();
        authEnsureAuditLogTable($pdo);

        $sessionUser = $user ?? ($_SESSION['user'] ?? null);
        $userId = is_array($sessionUser) && !empty($sessionUser['id']) ? (int) $sessionUser['id'] : null;
        $userName = is_array($sessionUser)
            ? (string) ($sessionUser['name'] ?? $sessionUser['username'] ?? $sessionUser['email'] ?? 'Unknown')
            : 'Guest';

        $statement = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, user_name, action, ip_address, created_at)
             VALUES (:user_id, :user_name, :action, :ip_address, :created_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $exception) {
        error_log('Audit log write failed: ' . $exception->getMessage());
    }
}

function isAuthenticated(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function getAuthenticatedUser(): ?array
{
    if (!isAuthenticated()) {
        return null;
    }

    $username = strtolower((string) ($_SESSION['user']['username'] ?? ''));
    $email = strtolower((string) ($_SESSION['user']['email'] ?? ''));
    $role = strtolower((string) ($_SESSION['user']['role'] ?? ''));

    if (
        $username === 'admin'
        || $email === 'admin'
        || in_array($role, ['admin', 'administrator', 'super admin'], true)
    ) {
        $_SESSION['user']['is_admin'] = true;
        $_SESSION['user']['role'] = 'Administrator';
    }

    return $_SESSION['user'];
}

function refreshSessionActivity(): void
{
    $_SESSION['last_activity'] = time();
}

function persistCurrentSessionCookie(int $lifetime): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || session_id() === '') {
        return;
    }

    $params = session_get_cookie_params();

    setcookie(session_name(), session_id(), [
        'expires' => $lifetime > 0 ? time() + $lifetime : 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => authCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function logoutUser(string $reason = ''): void
{
    if (isAuthenticated()) {
        recordAuditLog('Logout');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => authCookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    setcookie('ums_remember_login', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => authCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if ($reason !== '') {
        session_start();
        $_SESSION['login_notice'] = $reason;
    }
}

function enforceInactivityTimeout(): void
{
    if (!isAuthenticated()) {
        return;
    }

    $lastActivity = $_SESSION['last_activity'] ?? time();

    if ((time() - $lastActivity) > authInactivityTimeoutSeconds()) {
        logoutUser('Your session expired due to inactivity. Please sign in again.');
        redirectTo('login');
    }

    refreshSessionActivity();
}

function authTableExists(PDO $pdo, string $tableName): bool
{
    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => $tableName]);

        return (int) $statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        error_log('Auth table check failed: ' . $exception->getMessage());

        return false;
    }
}

function authColumnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        return (int) $statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        error_log('Auth column check failed: ' . $exception->getMessage());

        return false;
    }
}

function authRoleNameColumn(PDO $pdo): ?string
{
    if (!authTableExists($pdo, 'roles')) {
        return null;
    }

    if (authColumnExists($pdo, 'roles', 'name')) {
        return 'name';
    }

    if (authColumnExists($pdo, 'roles', 'role')) {
        return 'role';
    }

    return null;
}

function findUserByIdentifier(string $identifier): ?array
{
    $pdo = getDatabaseConnection();
    $select = ['u.*'];
    $join = '';

    if (authColumnExists($pdo, 'users', 'role_id')) {
        $roleNameColumn = authRoleNameColumn($pdo);

        if ($roleNameColumn !== null) {
            $select[] = 'r.`' . str_replace('`', '``', $roleNameColumn) . '` AS role_name';
            $join = ' LEFT JOIN roles r ON r.id = u.role_id';
        }
    }

    $statement = $pdo->prepare(
        'SELECT ' . implode(', ', $select) . ' FROM users u' . $join . ' WHERE u.email = :email_identifier OR u.username = :username_identifier LIMIT 1'
    );
    $statement->execute([
        'email_identifier' => $identifier,
        'username_identifier' => $identifier,
    ]);

    $user = $statement->fetch();

    return $user ?: null;
}

function userIsActive(array $user): bool
{
    if (array_key_exists('is_active', $user)) {
        return (bool) $user['is_active'];
    }

    if (array_key_exists('active', $user)) {
        return (bool) $user['active'];
    }

    if (array_key_exists('status', $user)) {
        return strtolower((string) $user['status']) === 'active';
    }

    return true;
}

function getUserPasswordHash(array $user): string
{
    return (string) ($user['password_hash'] ?? $user['password'] ?? '');
}

function passwordMatches(string $password, string $storedPassword): bool
{
    if ($storedPassword === '') {
        return false;
    }

    if (password_get_info($storedPassword)['algo'] === 0) {
        error_log('Rejected login attempt for account with non-hashed password storage.');

        return false;
    }

    return password_verify($password, $storedPassword);
}

function buildSessionUser(array $user): array
{
    $username = (string) ($user['username'] ?? '');
    $email = (string) ($user['email'] ?? '');
    $role = (string) ($user['role_name'] ?? $user['role'] ?? $user['user_role'] ?? $user['type'] ?? '');
    $normalizedRole = strtolower($role);
    $isAdmin = !empty($user['is_admin'])
        || strtolower($username) === 'admin'
        || strtolower($email) === 'admin'
        || in_array($normalizedRole, ['admin', 'administrator', 'super admin'], true);

    return [
        'id' => $user['id'],
        'username' => $username,
        'email' => $email,
        'name' => $user['full_name'] ?? $user['name'] ?? $username ?? $email ?? 'User',
        'role' => $isAdmin ? 'Administrator' : ($role !== '' ? $role : 'Guest'),
        'is_admin' => $isAdmin,
    ];
}

function userRoleKey(?array $user = null): string
{
    $user = $user ?? getAuthenticatedUser();

    if (!$user) {
        return 'guest';
    }

    if (!empty($user['is_admin'])) {
        return 'administrator';
    }

    $role = strtolower(trim((string) ($user['role'] ?? 'guest')));

    if (in_array($role, ['admin', 'administrator', 'super admin'], true)) {
        return 'administrator';
    }

    if ($role === 'manager') {
        return 'manager';
    }

    if ($role === 'staff') {
        return 'staff';
    }

    return 'guest';
}

function userHasRole(array $allowedRoles, ?array $user = null): bool
{
    return in_array(userRoleKey($user), array_map('strtolower', $allowedRoles), true);
}

function canAccessPage(string $page): bool
{
    if ($page === 'dashboard' || $page === 'profile') {
        return isAuthenticated();
    }

    if ($page === 'user_management') {
        return userHasRole(['administrator', 'manager']);
    }

    if ($page === 'user_roles' || $page === 'audit_logs') {
        return userHasRole(['administrator']);
    }

    return true;
}

function requirePageAccess(string $page): void
{
    if (!canAccessPage($page)) {
        $_SESSION['login_notice'] = 'You are not authorized to access that page.';
        redirectTo('dashboard');
    }
}

function attemptLogin(string $identifier, string $password, bool $rememberMe = false): bool
{
    authEnforceLoginThrottle($identifier);

    $user = findUserByIdentifier($identifier);

    if (!$user || !userIsActive($user) || !passwordMatches($password, getUserPasswordHash($user))) {
        authRecordFailedLogin($identifier);
        recordAuditLog('Login Failed', ['id' => null, 'name' => $identifier]);
        return false;
    }

    authClearFailedLogins($identifier);
    session_regenerate_id(true);

    $_SESSION['user'] = buildSessionUser($user);
    refreshSessionActivity();
    recordAuditLog('Login Successful', $_SESSION['user']);

    try {
        $pdo = getDatabaseConnection();

        if (authColumnExists($pdo, 'users', 'last_login')) {
            $statement = $pdo->prepare('UPDATE users SET last_login = :last_login WHERE id = :id');
            $statement->execute([
                'last_login' => date('Y-m-d H:i:s'),
                'id' => (int) $user['id'],
            ]);
        }
    } catch (Throwable $exception) {
        error_log('Last login update failed: ' . $exception->getMessage());
    }

    if ($rememberMe) {
        $rememberLifetime = authRememberMeSeconds();
        persistCurrentSessionCookie($rememberLifetime);
        setcookie('ums_remember_login', '1', [
            'expires' => time() + $rememberLifetime,
            'path' => '/',
            'secure' => authCookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        persistCurrentSessionCookie(0);
        setcookie('ums_remember_login', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => authCookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return true;
}

function requireAuthentication(): void
{
    if (!isAuthenticated()) {
        redirectTo('login');
    }

    enforceInactivityTimeout();
}