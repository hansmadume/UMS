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

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $remembered = !empty($_COOKIE['ums_remember_login']);
    $lifetime = $remembered ? authRememberMeSeconds() : 0;

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
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

    setcookie(
        session_name(),
        session_id(),
        $lifetime > 0 ? time() + $lifetime : 0,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

function logoutUser(string $reason = ''): void
{
    if (isAuthenticated()) {
        recordAuditLog('Logout');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }

    setcookie('ums_remember_login', '', time() - 3600, '/');

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

    if (password_get_info($storedPassword)['algo'] !== 0) {
        return password_verify($password, $storedPassword);
    }

    return hash_equals($storedPassword, $password);
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
    $user = findUserByIdentifier($identifier);

    if (!$user || !userIsActive($user) || !passwordMatches($password, getUserPasswordHash($user))) {
        recordAuditLog('Login Failed', ['id' => null, 'name' => $identifier]);
        return false;
    }

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
        setcookie('ums_remember_login', '1', time() + $rememberLifetime, '/', '', false, true);
    } else {
        persistCurrentSessionCookie(0);
        setcookie('ums_remember_login', '', time() - 3600, '/');
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