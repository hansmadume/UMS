<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

function userManagementTableExists(PDO $pdo, string $tableName): bool
{
    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => $tableName]);

        return (int) $statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        error_log('User management table check failed: ' . $exception->getMessage());

        return false;
    }
}

function userManagementColumnExists(PDO $pdo, string $tableName, string $columnName): bool
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
        error_log('User management column check failed: ' . $exception->getMessage());

        return false;
    }
}

function userManagementAvailableColumns(PDO $pdo, string $tableName, array $candidateColumns): array
{
    $columns = [];

    foreach ($candidateColumns as $columnName) {
        if (userManagementColumnExists($pdo, $tableName, $columnName)) {
            $columns[] = $columnName;
        }
    }

    return $columns;
}

function userManagementFlash(string $type, string $message): void
{
    $_SESSION['user_management_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getUserManagementFlash(): ?array
{
    if (empty($_SESSION['user_management_flash']) || !is_array($_SESSION['user_management_flash'])) {
        return null;
    }

    $flash = $_SESSION['user_management_flash'];
    unset($_SESSION['user_management_flash']);

    return $flash;
}

function userManagementRedirect(): void
{
    redirectTo('user_management');
}

function userManagementCurrentUserIsAdmin(): bool
{
    $currentUser = getAuthenticatedUser();

    return !empty($currentUser['is_admin']);
}

function requireUserManagementAdmin(): void
{
    if (!userManagementCurrentUserIsAdmin()) {
        userManagementFlash('error', 'Only administrators can manage users.');
        redirectTo('dashboard');
    }
}

function userManagementStatusFromInput(string $status): string
{
    $normalized = strtolower(trim($status));
    $allowedStatuses = ['active', 'inactive', 'pending'];

    return in_array($normalized, $allowedStatuses, true) ? $normalized : 'active';
}

function userManagementDisplayStatus(array $user): string
{
    if (array_key_exists('status', $user) && (string) $user['status'] !== '') {
        return ucfirst(strtolower((string) $user['status']));
    }

    if (array_key_exists('is_active', $user)) {
        return (int) $user['is_active'] === 1 ? 'Active' : 'Inactive';
    }

    if (array_key_exists('active', $user)) {
        return (int) $user['active'] === 1 ? 'Active' : 'Inactive';
    }

    return 'Active';
}

function userManagementDisplayName(array $user): string
{
    return (string) (
        $user['full_name']
        ?? $user['name']
        ?? $user['username']
        ?? $user['email']
        ?? 'User'
    );
}

function userManagementDisplayRole(array $user): string
{
    return (string) (
        $user['role_name']
        ?? $user['role']
        ?? $user['user_role']
        ?? $user['type']
        ?? 'User'
    );
}

function userManagementFetchRoles(PDO $pdo): array
{
    if (!userManagementTableExists($pdo, 'roles')) {
        return [];
    }

    try {
        $nameColumn = userManagementColumnExists($pdo, 'roles', 'name') ? 'name' : null;

        if ($nameColumn === null && userManagementColumnExists($pdo, 'roles', 'role')) {
            $nameColumn = 'role';
        }

        if ($nameColumn === null) {
            return [];
        }

        $select = ['id', sprintf('`%s` AS name', str_replace('`', '``', $nameColumn))];

        if (userManagementColumnExists($pdo, 'roles', 'description')) {
            $select[] = '`description`';
        }

        $statement = $pdo->query('SELECT ' . implode(', ', $select) . ' FROM roles ORDER BY `' . str_replace('`', '``', $nameColumn) . '` ASC');

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('User management role fetch failed: ' . $exception->getMessage());

        return [];
    }
}

function userManagementFetchUsers(PDO $pdo, string $search = ''): array
{
    if (!userManagementTableExists($pdo, 'users')) {
        return [];
    }

    try {
        $select = ['u.*'];
        $join = '';

        if (userManagementColumnExists($pdo, 'users', 'role_id') && userManagementTableExists($pdo, 'roles')) {
            $roleNameColumn = userManagementColumnExists($pdo, 'roles', 'name') ? 'name' : null;

            if ($roleNameColumn === null && userManagementColumnExists($pdo, 'roles', 'role')) {
                $roleNameColumn = 'role';
            }

            if ($roleNameColumn !== null) {
                $select[] = 'r.`' . str_replace('`', '``', $roleNameColumn) . '` AS role_name';
                $join = ' LEFT JOIN roles r ON r.id = u.role_id';
            }
        }

        $searchColumns = userManagementAvailableColumns($pdo, 'users', [
            'full_name',
            'name',
            'username',
            'email',
            'role',
            'user_role',
            'type',
            'status',
        ]);

        $where = '';
        $params = [];

        if ($search !== '' && !empty($searchColumns)) {
            $conditions = [];

            foreach ($searchColumns as $index => $columnName) {
                $paramName = ':search_' . $index;
                $conditions[] = 'u.`' . str_replace('`', '``', $columnName) . '` LIKE ' . $paramName;
                $params[$paramName] = '%' . $search . '%';
            }

            $where = ' WHERE ' . implode(' OR ', $conditions);
        }

        $orderBy = userManagementColumnExists($pdo, 'users', 'id') ? 'u.id DESC' : '1';
        $statement = $pdo->prepare(
            'SELECT ' . implode(', ', $select) . ' FROM users u' . $join . $where . ' ORDER BY ' . $orderBy
        );
        $statement->execute($params);

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('User management user fetch failed: ' . $exception->getMessage());

        return [];
    }
}

function userManagementFindUser(PDO $pdo, int $userId): ?array
{
    if (!userManagementTableExists($pdo, 'users')) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        return $user ?: null;
    } catch (Throwable $exception) {
        error_log('User management user lookup failed: ' . $exception->getMessage());

        return null;
    }
}

function userManagementEmailExists(PDO $pdo, string $email, ?int $excludeUserId = null): bool
{
    if (!userManagementTableExists($pdo, 'users') || !userManagementColumnExists($pdo, 'users', 'email')) {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
    $params = ['email' => $email];

    if ($excludeUserId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $excludeUserId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function userManagementUsernameExists(PDO $pdo, string $username, ?int $excludeUserId = null): bool
{
    if (!userManagementTableExists($pdo, 'users') || !userManagementColumnExists($pdo, 'users', 'username')) {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
    $params = ['username' => $username];

    if ($excludeUserId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $excludeUserId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function userManagementBuildWritableData(PDO $pdo, array $input, bool $isCreate): array
{
    $data = [];

    $name = trim((string) ($input['name'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $role = trim((string) ($input['role'] ?? 'User'));
    $roleId = (int) ($input['role_id'] ?? 0);
    $status = userManagementStatusFromInput((string) ($input['status'] ?? 'active'));

    if (userManagementColumnExists($pdo, 'users', 'full_name')) {
        $data['full_name'] = $name;
    } elseif (userManagementColumnExists($pdo, 'users', 'name')) {
        $data['name'] = $name;
    }

    if (userManagementColumnExists($pdo, 'users', 'username')) {
        $data['username'] = $username !== '' ? $username : $email;
    }

    if (userManagementColumnExists($pdo, 'users', 'email')) {
        $data['email'] = $email;
    }

    if (userManagementColumnExists($pdo, 'users', 'role_id') && $roleId > 0) {
        $data['role_id'] = $roleId;
    } elseif (userManagementColumnExists($pdo, 'users', 'role')) {
        $data['role'] = $role;
    } elseif (userManagementColumnExists($pdo, 'users', 'user_role')) {
        $data['user_role'] = $role;
    } elseif (userManagementColumnExists($pdo, 'users', 'type')) {
        $data['type'] = $role;
    }

    if (userManagementColumnExists($pdo, 'users', 'status')) {
        $data['status'] = $status;
    }

    if (userManagementColumnExists($pdo, 'users', 'is_active')) {
        $data['is_active'] = $status === 'active' ? 1 : 0;
    } elseif (userManagementColumnExists($pdo, 'users', 'active')) {
        $data['active'] = $status === 'active' ? 1 : 0;
    }

    $password = (string) ($input['password'] ?? '');

    if ($password !== '') {
        $passwordColumn = userManagementColumnExists($pdo, 'users', 'password_hash') ? 'password_hash' : null;

        if ($passwordColumn === null && userManagementColumnExists($pdo, 'users', 'password')) {
            $passwordColumn = 'password';
        }

        if ($passwordColumn !== null) {
            $data[$passwordColumn] = password_hash($password, PASSWORD_DEFAULT);
        }
    } elseif ($isCreate) {
        throw new InvalidArgumentException('Password is required when creating a user.');
    }

    if ($isCreate && userManagementColumnExists($pdo, 'users', 'created_at')) {
        $data['created_at'] = date('Y-m-d H:i:s');
    }

    if (userManagementColumnExists($pdo, 'users', 'updated_at')) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    return $data;
}

function userManagementValidateInput(PDO $pdo, array $input, bool $isCreate, ?int $userId = null): array
{
    $errors = [];

    $name = trim((string) ($input['name'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if ($isCreate && $password === '') {
        $errors[] = 'Password is required.';
    }

    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($email !== '' && userManagementEmailExists($pdo, $email, $userId)) {
        $errors[] = 'Email is already in use.';
    }

    if ($username !== '' && userManagementUsernameExists($pdo, $username, $userId)) {
        $errors[] = 'Username is already in use.';
    }

    return $errors;
}

function userManagementInsertUser(PDO $pdo, array $data): void
{
    if (empty($data)) {
        throw new RuntimeException('No user data was provided.');
    }

    $columns = array_keys($data);
    $columnSql = implode(', ', array_map(static fn ($column) => '`' . str_replace('`', '``', $column) . '`', $columns));
    $paramSql = implode(', ', array_map(static fn ($column) => ':' . $column, $columns));

    $statement = $pdo->prepare('INSERT INTO users (' . $columnSql . ') VALUES (' . $paramSql . ')');
    $statement->execute($data);
}

function userManagementUpdateUser(PDO $pdo, int $userId, array $data): void
{
    if (empty($data)) {
        throw new RuntimeException('No user data was provided.');
    }

    $sets = [];

    foreach (array_keys($data) as $columnName) {
        $sets[] = '`' . str_replace('`', '``', $columnName) . '` = :' . $columnName;
    }

    $data['id'] = $userId;

    $statement = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $statement->execute($data);
}

function userManagementDeleteUser(PDO $pdo, int $userId): void
{
    $currentUser = getAuthenticatedUser();

    if (!empty($currentUser['id']) && (int) $currentUser['id'] === $userId) {
        throw new RuntimeException('You cannot delete your own account while signed in.');
    }

    $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);
}

function handleUserManagementRequest(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_GET['page'] ?? '') !== 'user_management') {
        return;
    }

    requireUserManagementAdmin();

    try {
        $pdo = getDatabaseConnection();

        if (!userManagementTableExists($pdo, 'users')) {
            throw new RuntimeException('The users table does not exist.');
        }

        $action = (string) ($_POST['user_action'] ?? '');

        if ($action === 'create') {
            $errors = userManagementValidateInput($pdo, $_POST, true);

            if (!empty($errors)) {
                userManagementFlash('error', implode(' ', $errors));
                userManagementRedirect();
            }

            $data = userManagementBuildWritableData($pdo, $_POST, true);
            userManagementInsertUser($pdo, $data);
            userManagementFlash('success', 'User created successfully.');
            userManagementRedirect();
        }

        if ($action === 'update') {
            $userId = (int) ($_POST['user_id'] ?? 0);

            if ($userId <= 0 || userManagementFindUser($pdo, $userId) === null) {
                throw new RuntimeException('The selected user could not be found.');
            }

            $errors = userManagementValidateInput($pdo, $_POST, false, $userId);

            if (!empty($errors)) {
                userManagementFlash('error', implode(' ', $errors));
                userManagementRedirect();
            }

            $data = userManagementBuildWritableData($pdo, $_POST, false);
            userManagementUpdateUser($pdo, $userId, $data);
            userManagementFlash('success', 'User updated successfully.');
            userManagementRedirect();
        }

        if ($action === 'delete') {
            $userId = (int) ($_POST['user_id'] ?? 0);

            if ($userId <= 0 || userManagementFindUser($pdo, $userId) === null) {
                throw new RuntimeException('The selected user could not be found.');
            }

            userManagementDeleteUser($pdo, $userId);
            userManagementFlash('success', 'User deleted successfully.');
            userManagementRedirect();
        }

        userManagementFlash('error', 'Invalid user management action.');
        userManagementRedirect();
    } catch (Throwable $exception) {
        error_log('User management request failed: ' . $exception->getMessage());
        userManagementFlash('error', $exception->getMessage());
        userManagementRedirect();
    }
}