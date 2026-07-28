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
    $isAdmin = !empty($user['is_admin'])
        || strtolower($username) === 'admin'
        || strtolower($email) === 'admin'
        || in_array(strtolower($role), ['admin', 'administrator', 'super admin'], true);

    return [
        'id' => $user['id'],
        'username' => $username,
        'email' => $email,
        'name' => $user['full_name'] ?? $user['name'] ?? $username ?? $email ?? 'User',
        'role' => $isAdmin ? 'Administrator' : ($role !== '' ? $role : 'User'),
        'is_admin' => $isAdmin,
    ];
}

function attemptLogin(string $identifier, string $password, bool $rememberMe = false): bool
{
    $user = findUserByIdentifier($identifier);

    if (!$user || !userIsActive($user) || !passwordMatches($password, getUserPasswordHash($user))) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['user'] = buildSessionUser($user);
    refreshSessionActivity();

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