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
    return isAuthenticated() ? $_SESSION['user'] : null;
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

function findUserByIdentifier(string $identifier): ?array
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'SELECT * FROM users WHERE email = :email_identifier OR username = :username_identifier LIMIT 1'
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
    $role = $user['role_name'] ?? $user['role'] ?? $user['user_role'] ?? $user['type'] ?? '';

    return [
        'id' => $user['id'],
        'username' => $user['username'] ?? '',
        'email' => $user['email'] ?? '',
        'name' => $user['full_name'] ?? $user['name'] ?? $user['username'] ?? $user['email'] ?? 'User',
        'role' => $role !== '' ? $role : 'User',
        'is_admin' => !empty($user['is_admin']) || in_array(strtolower((string) $role), ['admin', 'administrator', 'super admin'], true),
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