<?php
/**
 * Database and authentication configuration for UMS.
 *
 * Expected users table columns:
 * - id
 * - username
 * - email
 * - password_hash (preferred) or password
 * - status/is_active/active (optional)
 * - full_name/name (optional)
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ums');
define('DB_USER', 'root');
define('DB_PASS', 'mysql');
define('DB_CHARSET', 'utf8mb4');

/**
 * Inactive authenticated users are logged out after this many seconds.
 * Change this value to configure the inactivity timeout.
 */
define('AUTH_INACTIVITY_TIMEOUT_SECONDS', 1800);

/**
 * Session cookie lifetime used when the login form "Remember me" option is checked.
 */
define('AUTH_REMEMBER_ME_SECONDS', 60 * 60 * 24 * 30);

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}