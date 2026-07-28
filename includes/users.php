<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

function userManagementQuoteColumn(string $columnName): string
{
    return '`' . str_replace('`', '``', $columnName) . '`';
}

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

function userManagementEnsureUserSchema(PDO $pdo): void
{
    if (!userManagementTableExists($pdo, 'users')) {
        return;
    }

    $columns = [
        'contact_number' => 'VARCHAR(50) NULL',
        'address' => 'TEXT NULL',
        'profile_photo' => 'VARCHAR(255) NULL',
        'last_login' => 'DATETIME NULL',
        'deleted_at' => 'DATETIME NULL',
    ];

    if (!userManagementColumnExists($pdo, 'users', 'status')
        && !userManagementColumnExists($pdo, 'users', 'is_active')
        && !userManagementColumnExists($pdo, 'users', 'active')
    ) {
        $columns['status'] = "VARCHAR(20) NOT NULL DEFAULT 'active'";
    }

    if (!userManagementColumnExists($pdo, 'users', 'created_at')) {
        $columns['created_at'] = 'DATETIME NULL';
    }

    if (!userManagementColumnExists($pdo, 'users', 'updated_at')) {
        $columns['updated_at'] = 'DATETIME NULL';
    }

    foreach ($columns as $columnName => $definition) {
        if (!userManagementColumnExists($pdo, 'users', $columnName)) {
            try {
                $pdo->exec('ALTER TABLE users ADD COLUMN ' . userManagementQuoteColumn($columnName) . ' ' . $definition);
            } catch (Throwable $exception) {
                error_log('User schema update failed for ' . $columnName . ': ' . $exception->getMessage());
            }
        }
    }

    if (userManagementTableExists($pdo, 'roles') && !userManagementColumnExists($pdo, 'users', 'role_id')) {
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN role_id INT UNSIGNED NULL');
        } catch (Throwable $exception) {
            error_log('User schema update failed for role_id: ' . $exception->getMessage());
        }
    }

    userManagementEnsureAdminRoleAssignment($pdo);
}

function userManagementEnsureAdminRoleAssignment(PDO $pdo): void
{
    if (
        !userManagementTableExists($pdo, 'users')
        || !userManagementTableExists($pdo, 'roles')
        || !userManagementColumnExists($pdo, 'users', 'role_id')
        || !function_exists('roleManagementNameColumn')
    ) {
        return;
    }

    $roleNameColumn = roleManagementNameColumn($pdo);

    if ($roleNameColumn === null) {
        return;
    }

    try {
        $roleStatement = $pdo->prepare(
            "SELECT id FROM roles WHERE LOWER(" . userManagementQuoteColumn($roleNameColumn) . ") IN ('administrator', 'admin', 'super admin') ORDER BY id ASC LIMIT 1"
        );
        $roleStatement->execute();
        $administratorRoleId = (int) $roleStatement->fetchColumn();

        if ($administratorRoleId <= 0) {
            return;
        }

        $whereParts = [];

        if (userManagementColumnExists($pdo, 'users', 'username')) {
            $whereParts[] = "LOWER(username) = 'admin'";
        }

        if (userManagementColumnExists($pdo, 'users', 'email')) {
            $whereParts[] = "LOWER(email) IN ('admin', 'admin@example.com')";
        }

        if (userManagementColumnExists($pdo, 'users', 'full_name')) {
            $whereParts[] = "LOWER(full_name) = 'system administrator'";
        } elseif (userManagementColumnExists($pdo, 'users', 'name')) {
            $whereParts[] = "LOWER(name) = 'system administrator'";
        }

        if (empty($whereParts)) {
            return;
        }

        $pdo->exec(
            'UPDATE users SET role_id = ' . $administratorRoleId . ' WHERE (' . implode(' OR ', $whereParts) . ') AND (role_id IS NULL OR role_id = 0)'
        );
    } catch (Throwable $exception) {
        error_log('Admin role assignment failed: ' . $exception->getMessage());
    }
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

function userManagementRedirect(array $params = []): void
{
    $query = array_merge(['page' => 'user_management'], $params);

    header('Location: index.php?' . http_build_query($query));
    exit;
}

function profileManagementFlash(string $type, string $message): void
{
    $_SESSION['profile_management_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getProfileManagementFlash(): ?array
{
    if (empty($_SESSION['profile_management_flash']) || !is_array($_SESSION['profile_management_flash'])) {
        return null;
    }

    $flash = $_SESSION['profile_management_flash'];
    unset($_SESSION['profile_management_flash']);

    return $flash;
}

function profileManagementRedirect(): void
{
    redirectTo('profile');
}

function userManagementCurrentUserIsAdmin(): bool
{
    $currentUser = getAuthenticatedUser();

    if (empty($currentUser['id'])) {
        return false;
    }

    $username = strtolower((string) ($currentUser['username'] ?? ''));
    $email = strtolower((string) ($currentUser['email'] ?? ''));
    $sessionRole = strtolower((string) ($currentUser['role'] ?? ''));

    if (!empty($currentUser['is_admin'])
        || $username === 'admin'
        || $email === 'admin'
        || in_array($sessionRole, ['admin', 'administrator', 'super admin'], true)
    ) {
        $_SESSION['user']['is_admin'] = true;
        $_SESSION['user']['role'] = 'Administrator';

        return true;
    }

    try {
        $pdo = getDatabaseConnection();

        if (!userManagementTableExists($pdo, 'users')) {
            return false;
        }

        $databaseUser = userManagementFindUser($pdo, (int) $currentUser['id']);

        if (!$databaseUser) {
            return false;
        }

        $role = strtolower(userManagementDisplayRole($databaseUser));
        $isAdmin = !empty($databaseUser['is_admin']) || in_array($role, ['admin', 'administrator', 'super admin'], true);

        if ($isAdmin) {
            $_SESSION['user']['is_admin'] = true;
            $_SESSION['user']['role'] = 'Administrator';
        }

        return $isAdmin;
    } catch (Throwable $exception) {
        error_log('User management admin check failed: ' . $exception->getMessage());

        return false;
    }
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
    $allowedStatuses = ['active', 'inactive'];

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

function userManagementDisplayUsername(array $user): string
{
    return (string) ($user['username'] ?? '');
}

function userManagementDisplayRole(array $user): string
{
    $username = strtolower((string) ($user['username'] ?? ''));
    $email = strtolower((string) ($user['email'] ?? ''));
    $role = (string) (
        $user['role_name']
        ?? $user['role']
        ?? $user['user_role']
        ?? $user['type']
        ?? ''
    );

    if (
        !empty($user['is_admin'])
        || $username === 'admin'
        || $email === 'admin'
        || in_array(strtolower($role), ['admin', 'administrator', 'super admin'], true)
    ) {
        return 'Administrator';
    }

    return $role !== '' ? $role : 'User';
}

function userManagementDisplayContact(array $user): string
{
    return (string) (
        $user['contact_number']
        ?? $user['contact']
        ?? $user['phone']
        ?? $user['phone_number']
        ?? ''
    );
}

function userManagementDisplayAddress(array $user): string
{
    return (string) ($user['address'] ?? '');
}

function userManagementDisplayProfilePhoto(array $user): string
{
    return (string) (
        $user['profile_photo']
        ?? $user['avatar']
        ?? $user['photo']
        ?? ''
    );
}

function userManagementDisplayDate(?string $value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('M d, Y h:i A', $timestamp) : (string) $value;
}

function userManagementFetchRoles(PDO $pdo): array
{
    if (!userManagementTableExists($pdo, 'roles')) {
        return [];
    }

    try {
        $nameColumn = roleManagementNameColumn($pdo);

        if ($nameColumn === null) {
            return [];
        }

        $select = ['id', userManagementQuoteColumn($nameColumn) . ' AS name'];

        if (userManagementColumnExists($pdo, 'roles', 'description')) {
            $select[] = '`description`';
        }

        $statement = $pdo->query('SELECT ' . implode(', ', $select) . ' FROM roles ORDER BY ' . userManagementQuoteColumn($nameColumn) . ' ASC');

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('User management role fetch failed: ' . $exception->getMessage());

        return [];
    }
}

function userManagementUserSelectSql(PDO $pdo): array
{
    $select = ['u.*'];
    $join = '';

    if (userManagementColumnExists($pdo, 'users', 'role_id') && userManagementTableExists($pdo, 'roles')) {
        $roleNameColumn = roleManagementNameColumn($pdo);

        if ($roleNameColumn !== null) {
            $select[] = 'r.' . userManagementQuoteColumn($roleNameColumn) . ' AS role_name';
            $join = ' LEFT JOIN roles r ON r.id = u.role_id';
        }
    }

    return [$select, $join];
}

function userManagementFetchUsers(PDO $pdo, string $search = '', string $roleFilter = '', string $statusFilter = ''): array
{
    if (!userManagementTableExists($pdo, 'users')) {
        return [];
    }

    userManagementEnsureUserSchema($pdo);

    try {
        [$select, $join] = userManagementUserSelectSql($pdo);
        $whereParts = [];
        $params = [];

        if (userManagementColumnExists($pdo, 'users', 'deleted_at')) {
            $whereParts[] = 'u.deleted_at IS NULL';
        }

        if ($search !== '') {
            $searchColumns = userManagementAvailableColumns($pdo, 'users', [
                'full_name',
                'name',
                'username',
                'email',
            ]);

            if (!empty($searchColumns)) {
                $conditions = [];

                foreach ($searchColumns as $index => $columnName) {
                    $paramName = ':search_' . $index;
                    $conditions[] = 'u.' . userManagementQuoteColumn($columnName) . ' LIKE ' . $paramName;
                    $params[$paramName] = '%' . $search . '%';
                }

                $whereParts[] = '(' . implode(' OR ', $conditions) . ')';
            }
        }

        if ($roleFilter !== '') {
            if (ctype_digit($roleFilter) && userManagementColumnExists($pdo, 'users', 'role_id')) {
                $whereParts[] = 'u.role_id = :role_id';
                $params['role_id'] = (int) $roleFilter;
            } else {
                foreach (['role', 'user_role', 'type'] as $columnName) {
                    if (userManagementColumnExists($pdo, 'users', $columnName)) {
                        $whereParts[] = 'LOWER(u.' . userManagementQuoteColumn($columnName) . ') = LOWER(:role_name)';
                        $params['role_name'] = $roleFilter;
                        break;
                    }
                }
            }
        }

        if ($statusFilter !== '') {
            $normalizedStatus = userManagementStatusFromInput($statusFilter);

            if (userManagementColumnExists($pdo, 'users', 'status')) {
                $whereParts[] = 'LOWER(u.status) = :status';
                $params['status'] = $normalizedStatus;
            } elseif (userManagementColumnExists($pdo, 'users', 'is_active')) {
                $whereParts[] = 'u.is_active = :is_active';
                $params['is_active'] = $normalizedStatus === 'active' ? 1 : 0;
            } elseif (userManagementColumnExists($pdo, 'users', 'active')) {
                $whereParts[] = 'u.active = :active';
                $params['active'] = $normalizedStatus === 'active' ? 1 : 0;
            }
        }

        $where = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';
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

    userManagementEnsureUserSchema($pdo);

    try {
        [$select, $join] = userManagementUserSelectSql($pdo);
        $where = ' WHERE u.id = :id';

        if (userManagementColumnExists($pdo, 'users', 'deleted_at')) {
            $where .= ' AND u.deleted_at IS NULL';
        }

        $statement = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM users u' . $join . $where . ' LIMIT 1');
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

    if (userManagementColumnExists($pdo, 'users', 'deleted_at')) {
        $sql .= ' AND deleted_at IS NULL';
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

    if (userManagementColumnExists($pdo, 'users', 'deleted_at')) {
        $sql .= ' AND deleted_at IS NULL';
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function userManagementBuildWritableData(PDO $pdo, array $input, bool $isCreate, bool $includeAdminFields = true): array
{
    userManagementEnsureUserSchema($pdo);

    $data = [];
    $name = trim((string) ($input['name'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $role = trim((string) ($input['role'] ?? 'User'));
    $roleId = (int) ($input['role_id'] ?? 0);
    $status = userManagementStatusFromInput((string) ($input['status'] ?? 'active'));
    $contact = trim((string) ($input['contact_number'] ?? $input['contact'] ?? $input['phone'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));

    if (userManagementColumnExists($pdo, 'users', 'full_name')) {
        $data['full_name'] = $name;
    } elseif (userManagementColumnExists($pdo, 'users', 'name')) {
        $data['name'] = $name;
    }

    if ($includeAdminFields) {
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
    }

    if (userManagementColumnExists($pdo, 'users', 'contact_number')) {
        $data['contact_number'] = $contact;
    } elseif (userManagementColumnExists($pdo, 'users', 'contact')) {
        $data['contact'] = $contact;
    } elseif (userManagementColumnExists($pdo, 'users', 'phone')) {
        $data['phone'] = $contact;
    } elseif (userManagementColumnExists($pdo, 'users', 'phone_number')) {
        $data['phone_number'] = $contact;
    }

    if (userManagementColumnExists($pdo, 'users', 'address')) {
        $data['address'] = $address;
    }

    if (!empty($input['profile_photo_path'])) {
        if (userManagementColumnExists($pdo, 'users', 'profile_photo')) {
            $data['profile_photo'] = (string) $input['profile_photo_path'];
        } elseif (userManagementColumnExists($pdo, 'users', 'avatar')) {
            $data['avatar'] = (string) $input['profile_photo_path'];
        } elseif (userManagementColumnExists($pdo, 'users', 'photo')) {
            $data['photo'] = (string) $input['profile_photo_path'];
        }
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

function userManagementValidateInput(PDO $pdo, array $input, bool $isCreate, ?int $userId = null, bool $includeAdminFields = true): array
{
    $errors = [];
    $name = trim((string) ($input['name'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');
    $contact = trim((string) ($input['contact_number'] ?? $input['contact'] ?? $input['phone'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));

    if ($name === '') {
        $errors[] = 'Full Name is required.';
    }

    if ($includeAdminFields) {
        if ($username === '') {
            $errors[] = 'Username is required.';
        }

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if ($email !== '' && userManagementEmailExists($pdo, $email, $userId)) {
            $errors[] = 'Email is already in use.';
        }

        if ($username !== '' && userManagementUsernameExists($pdo, $username, $userId)) {
            $errors[] = 'Username is already in use.';
        }
    }

    if ($isCreate && $password === '') {
        $errors[] = 'Password is required.';
    }

    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== '' && $password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (strlen($contact) > 50) {
        $errors[] = 'Contact Number must be 50 characters or fewer.';
    }

    if (strlen($address) > 1000) {
        $errors[] = 'Address must be 1000 characters or fewer.';
    }

    return $errors;
}

function userManagementInsertUser(PDO $pdo, array $data): void
{
    if (empty($data)) {
        throw new RuntimeException('No user data was provided.');
    }

    $columns = array_keys($data);
    $columnSql = implode(', ', array_map('userManagementQuoteColumn', $columns));
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
        $sets[] = userManagementQuoteColumn($columnName) . ' = :' . $columnName;
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

    if (userManagementColumnExists($pdo, 'users', 'deleted_at')) {
        $data = [
            'deleted_at' => date('Y-m-d H:i:s'),
            'id' => $userId,
        ];
        $sets = 'deleted_at = :deleted_at';

        if (userManagementColumnExists($pdo, 'users', 'status')) {
            $data['status'] = 'inactive';
            $sets .= ', status = :status';
        }

        if (userManagementColumnExists($pdo, 'users', 'is_active')) {
            $data['is_active'] = 0;
            $sets .= ', is_active = :is_active';
        } elseif (userManagementColumnExists($pdo, 'users', 'active')) {
            $data['active'] = 0;
            $sets .= ', active = :active';
        }

        $statement = $pdo->prepare('UPDATE users SET ' . $sets . ' WHERE id = :id');
        $statement->execute($data);

        return;
    }

    $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);
}

function profileManagementHandleUpload(int $userId): string
{
    if (empty($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
        return '';
    }

    $file = $_FILES['profile_photo'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Profile photo upload failed.');
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Profile photo must be 2MB or smaller.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $mimeType = is_file($tmpName) ? mime_content_type($tmpName) : '';
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Profile photo must be a JPG, PNG, GIF, or WEBP image.');
    }

    $uploadDirectory = __DIR__ . '/../assets/media/profile_photos';

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create profile photo upload directory.');
    }

    $relativePath = 'assets/media/profile_photos/user_' . $userId . '_' . time() . '.' . $allowedTypes[$mimeType];
    $absolutePath = __DIR__ . '/../' . $relativePath;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException('Unable to save profile photo.');
    }

    return $relativePath;
}

function profileManagementFetchCurrentUser(PDO $pdo): ?array
{
    $currentUser = getAuthenticatedUser();

    if (empty($currentUser['id'])) {
        return null;
    }

    return userManagementFindUser($pdo, (int) $currentUser['id']);
}

function handleProfileManagementRequest(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_GET['page'] ?? '') !== 'profile') {
        return;
    }

    try {
        $pdo = getDatabaseConnection();
        $currentUser = getAuthenticatedUser();

        if (empty($currentUser['id'])) {
            throw new RuntimeException('You must be logged in to update your profile.');
        }

        $userId = (int) $currentUser['id'];
        $existingUser = userManagementFindUser($pdo, $userId);

        if ($existingUser === null) {
            throw new RuntimeException('Your account could not be found.');
        }

        $_POST['username'] = (string) ($existingUser['username'] ?? '');
        $_POST['email'] = (string) ($existingUser['email'] ?? '');
        $_POST['role'] = userManagementDisplayRole($existingUser);
        $_POST['status'] = strtolower(userManagementDisplayStatus($existingUser));

        $uploadedPhoto = profileManagementHandleUpload($userId);

        if ($uploadedPhoto !== '') {
            $_POST['profile_photo_path'] = $uploadedPhoto;
        }

        $errors = userManagementValidateInput($pdo, $_POST, false, $userId, false);

        if (!empty($errors)) {
            profileManagementFlash('error', implode(' ', $errors));
            profileManagementRedirect();
        }

        $data = userManagementBuildWritableData($pdo, $_POST, false, false);
        userManagementUpdateUser($pdo, $userId, $data);

        $updatedUser = userManagementFindUser($pdo, $userId);

        if ($updatedUser !== null) {
            $_SESSION['user']['name'] = userManagementDisplayName($updatedUser);
            $_SESSION['user']['role'] = userManagementDisplayRole($updatedUser);
        }

        profileManagementFlash('success', 'Profile updated successfully.');
        profileManagementRedirect();
    } catch (Throwable $exception) {
        error_log('Profile management request failed: ' . $exception->getMessage());
        profileManagementFlash('error', $exception->getMessage());
        profileManagementRedirect();
    }
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

        userManagementEnsureUserSchema($pdo);
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
            $existingUser = $userId > 0 ? userManagementFindUser($pdo, $userId) : null;

            if ($userId <= 0 || $existingUser === null) {
                throw new RuntimeException('The selected user could not be found.');
            }

            $currentUser = getAuthenticatedUser();
            $isEditingSelf = !empty($currentUser['id']) && (int) $currentUser['id'] === $userId;

            if ($isEditingSelf) {
                $_POST['role_id'] = (string) ($existingUser['role_id'] ?? 0);
                $_POST['role'] = userManagementDisplayRole($existingUser);
                $_POST['status'] = strtolower(userManagementDisplayStatus($existingUser));
            }

            $errors = userManagementValidateInput($pdo, $_POST, false, $userId);

            if (!empty($errors)) {
                userManagementFlash('error', implode(' ', $errors));
                userManagementRedirect(['edit_user' => $userId]);
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

function roleManagementFlash(string $type, string $message): void
{
    $_SESSION['role_management_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getRoleManagementFlash(): ?array
{
    if (empty($_SESSION['role_management_flash']) || !is_array($_SESSION['role_management_flash'])) {
        return null;
    }

    $flash = $_SESSION['role_management_flash'];
    unset($_SESSION['role_management_flash']);

    return $flash;
}

function roleManagementRedirect(array $params = []): void
{
    $query = array_merge(['page' => 'user_roles'], $params);

    header('Location: index.php?' . http_build_query($query));
    exit;
}

function requireRoleManagementAdmin(): void
{
    if (!userManagementCurrentUserIsAdmin()) {
        roleManagementFlash('error', 'Only administrators can manage roles.');
        redirectTo('dashboard');
    }
}

function roleManagementEnsureRolesTable(PDO $pdo): void
{
    if (!userManagementTableExists($pdo, 'roles')) {
        $pdo->exec(
            'CREATE TABLE roles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'active\',
                icon VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    $schemaColumns = [
        'description' => 'VARCHAR(255) NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
    ];

    foreach ($schemaColumns as $columnName => $definition) {
        if (!userManagementColumnExists($pdo, 'roles', $columnName)) {
            try {
                $pdo->exec('ALTER TABLE roles ADD COLUMN ' . userManagementQuoteColumn($columnName) . ' ' . $definition);
            } catch (Throwable $exception) {
                error_log('Role schema update failed for ' . $columnName . ': ' . $exception->getMessage());
            }
        }
    }

    roleManagementSeedDefaultRoles($pdo);
    roleManagementEnsurePermissionsSchema($pdo);
}

function roleManagementSeedDefaultRoles(PDO $pdo): void
{
    $nameColumn = roleManagementNameColumn($pdo);

    if ($nameColumn === null) {
        return;
    }

    $defaultRoles = [
        'Administrator' => 'Full system access and administration permissions.',
        'Manager' => 'Can manage assigned staff and operational records.',
        'Staff' => 'Standard staff user access.',
        'Guest' => 'Limited read-only or guest access.',
    ];

    foreach ($defaultRoles as $roleName => $description) {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM roles WHERE LOWER(' . userManagementQuoteColumn($nameColumn) . ') = LOWER(:name)'
            );
            $statement->execute(['name' => $roleName]);

            if ((int) $statement->fetchColumn() > 0) {
                continue;
            }

            $data = [
                $nameColumn => $roleName,
            ];

            if (userManagementColumnExists($pdo, 'roles', 'description')) {
                $data['description'] = $description;
            }

            if (userManagementColumnExists($pdo, 'roles', 'status')) {
                $data['status'] = 'active';
            }

            if (userManagementColumnExists($pdo, 'roles', 'created_at')) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }

            $columns = array_keys($data);
            $columnSql = implode(', ', array_map('userManagementQuoteColumn', $columns));
            $paramSql = implode(', ', array_map(static fn ($column) => ':' . $column, $columns));

            $insert = $pdo->prepare('INSERT INTO roles (' . $columnSql . ') VALUES (' . $paramSql . ')');
            $insert->execute($data);
        } catch (Throwable $exception) {
            error_log('Default role seed failed for ' . $roleName . ': ' . $exception->getMessage());
        }
    }
}

function roleManagementDefaultPermissions(): array
{
    return [
        'view_users' => 'View Users',
        'create_users' => 'Create Users',
        'edit_users' => 'Edit Users',
        'delete_users' => 'Delete Users',
        'view_roles' => 'View Roles',
        'manage_roles' => 'Manage Roles',
        'manage_settings' => 'Manage Settings',
    ];
}

function roleManagementEnsurePermissionsSchema(PDO $pdo): void
{
    if (!userManagementTableExists($pdo, 'permissions')) {
        $pdo->exec(
            'CREATE TABLE permissions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    if (!userManagementTableExists($pdo, 'role_permissions')) {
        $pdo->exec(
            'CREATE TABLE role_permissions (
                role_id INT UNSIGNED NOT NULL,
                permission_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (role_id, permission_id),
                INDEX idx_role_permissions_permission_id (permission_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    roleManagementSeedDefaultPermissions($pdo);
}

function roleManagementSeedDefaultPermissions(PDO $pdo): void
{
    foreach (roleManagementDefaultPermissions() as $slug => $name) {
        try {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug = :slug OR name = :name');
            $statement->execute([
                'slug' => $slug,
                'name' => $name,
            ]);

            if ((int) $statement->fetchColumn() > 0) {
                continue;
            }

            $insert = $pdo->prepare('INSERT INTO permissions (name, slug, description) VALUES (:name, :slug, :description)');
            $insert->execute([
                'name' => $name,
                'slug' => $slug,
                'description' => $name . ' permission.',
            ]);
        } catch (Throwable $exception) {
            error_log('Default permission seed failed for ' . $name . ': ' . $exception->getMessage());
        }
    }
}

function roleManagementFetchPermissions(PDO $pdo): array
{
    roleManagementEnsurePermissionsSchema($pdo);

    try {
        $statement = $pdo->query('SELECT id, name, slug, description FROM permissions ORDER BY name ASC');

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Permission fetch failed: ' . $exception->getMessage());

        return [];
    }
}

function roleManagementFetchPermissionIdsForRole(PDO $pdo, int $roleId): array
{
    roleManagementEnsurePermissionsSchema($pdo);

    if ($roleId <= 0) {
        return [];
    }

    try {
        $statement = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id ORDER BY permission_id ASC');
        $statement->execute(['role_id' => $roleId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $exception) {
        error_log('Role permission ID fetch failed: ' . $exception->getMessage());

        return [];
    }
}

function roleManagementFetchPermissionNamesForRole(PDO $pdo, int $roleId): array
{
    roleManagementEnsurePermissionsSchema($pdo);

    if ($roleId <= 0) {
        return [];
    }

    try {
        $statement = $pdo->prepare(
            'SELECT p.name
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id
             ORDER BY p.name ASC'
        );
        $statement->execute(['role_id' => $roleId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $exception) {
        error_log('Role permission name fetch failed: ' . $exception->getMessage());

        return [];
    }
}

function roleManagementPermissionIdsFromInput(PDO $pdo, array $input): array
{
    roleManagementEnsurePermissionsSchema($pdo);

    $permissionIds = $input['permission_ids'] ?? [];

    if (!is_array($permissionIds)) {
        return [];
    }

    $permissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn ($id) => $id > 0)));

    if (empty($permissionIds)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($permissionIds), '?'));
    $statement = $pdo->prepare('SELECT id FROM permissions WHERE id IN (' . $placeholders . ')');
    $statement->execute($permissionIds);

    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function roleManagementSyncRolePermissions(PDO $pdo, int $roleId, array $permissionIds): void
{
    roleManagementEnsurePermissionsSchema($pdo);

    if ($roleId <= 0) {
        throw new RuntimeException('The selected role could not be found.');
    }

    $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')->execute(['role_id' => $roleId]);

    if (empty($permissionIds)) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');

    foreach ($permissionIds as $permissionId) {
        $insert->execute([
            'role_id' => $roleId,
            'permission_id' => (int) $permissionId,
        ]);
    }
}

function roleManagementNameColumn(PDO $pdo): ?string
{
    if (!userManagementTableExists($pdo, 'roles')) {
        return null;
    }

    if (userManagementColumnExists($pdo, 'roles', 'name')) {
        return 'name';
    }

    if (userManagementColumnExists($pdo, 'roles', 'role')) {
        return 'role';
    }

    return null;
}

function roleManagementFetchRoles(PDO $pdo): array
{
    roleManagementEnsureRolesTable($pdo);

    $nameColumn = roleManagementNameColumn($pdo);

    if ($nameColumn === null) {
        return [];
    }

    try {
        $select = [
            'id',
            userManagementQuoteColumn($nameColumn) . ' AS name',
        ];

        if (userManagementColumnExists($pdo, 'roles', 'description')) {
            $select[] = '`description`';
        } else {
            $select[] = "'' AS description";
        }

        if (userManagementColumnExists($pdo, 'roles', 'status')) {
            $select[] = '`status`';
        } else {
            $select[] = "'active' AS status";
        }

        if (userManagementColumnExists($pdo, 'roles', 'icon')) {
            $select[] = '`icon`';
        } else {
            $select[] = "'' AS icon";
        }

        $statement = $pdo->query(
            'SELECT ' . implode(', ', $select) . ' FROM roles ORDER BY ' . userManagementQuoteColumn($nameColumn) . ' ASC'
        );

        $roles = $statement->fetchAll();

        foreach ($roles as &$role) {
            $roleId = (int) ($role['id'] ?? 0);
            $role['users_count'] = roleManagementCountUsers($pdo, $roleId, (string) ($role['name'] ?? ''));
            $role['permission_ids'] = roleManagementFetchPermissionIdsForRole($pdo, $roleId);
            $role['permissions'] = roleManagementFetchPermissionNamesForRole($pdo, $roleId);
        }

        unset($role);

        return $roles;
    } catch (Throwable $exception) {
        error_log('Role management role fetch failed: ' . $exception->getMessage());

        return [];
    }
}

function roleManagementFindRole(PDO $pdo, int $roleId): ?array
{
    roleManagementEnsureRolesTable($pdo);

    $nameColumn = roleManagementNameColumn($pdo);

    if ($roleId <= 0 || $nameColumn === null) {
        return null;
    }

    $select = [
        'id',
        userManagementQuoteColumn($nameColumn) . ' AS name',
    ];

    if (userManagementColumnExists($pdo, 'roles', 'description')) {
        $select[] = '`description`';
    } else {
        $select[] = "'' AS description";
    }

    if (userManagementColumnExists($pdo, 'roles', 'status')) {
        $select[] = '`status`';
    } else {
        $select[] = "'active' AS status";
    }

    if (userManagementColumnExists($pdo, 'roles', 'icon')) {
        $select[] = '`icon`';
    } else {
        $select[] = "'' AS icon";
    }

    $statement = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM roles WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $roleId]);
    $role = $statement->fetch();

    if (!$role) {
        return null;
    }

    $role['users_count'] = roleManagementCountUsers($pdo, (int) $role['id'], (string) $role['name']);
    $role['permission_ids'] = roleManagementFetchPermissionIdsForRole($pdo, (int) $role['id']);
    $role['permissions'] = roleManagementFetchPermissionNamesForRole($pdo, (int) $role['id']);

    return $role;
}

function roleManagementDisplayStatus(array $role): string
{
    if (array_key_exists('status', $role) && (string) $role['status'] !== '') {
        return ucfirst(strtolower((string) $role['status']));
    }

    return 'Active';
}

function roleManagementStatusFromInput(string $status): string
{
    $normalized = strtolower(trim($status));

    return in_array($normalized, ['active', 'inactive'], true) ? $normalized : 'active';
}

function roleManagementCountUsers(PDO $pdo, int $roleId, string $roleName): int
{
    if (!userManagementTableExists($pdo, 'users')) {
        return 0;
    }

    try {
        if ($roleId > 0 && userManagementColumnExists($pdo, 'users', 'role_id')) {
            $sql = 'SELECT COUNT(*) FROM users WHERE role_id = :role_id';

            if (userManagementColumnExists($pdo, 'users', 'deleted_at')) {
                $sql .= ' AND deleted_at IS NULL';
            }

            $statement = $pdo->prepare($sql);
            $statement->execute(['role_id' => $roleId]);

            return (int) $statement->fetchColumn();
        }

        foreach (['role', 'user_role', 'type'] as $columnName) {
            if (userManagementColumnExists($pdo, 'users', $columnName)) {
                $sql = 'SELECT COUNT(*) FROM users WHERE LOWER(' . userManagementQuoteColumn($columnName) . ') = LOWER(:role_name)';

                if (userManagementColumnExists($pdo, 'users', 'deleted_at')) {
                    $sql .= ' AND deleted_at IS NULL';
                }

                $statement = $pdo->prepare($sql);
                $statement->execute(['role_name' => $roleName]);

                return (int) $statement->fetchColumn();
            }
        }
    } catch (Throwable $exception) {
        error_log('Role management user count failed: ' . $exception->getMessage());
    }

    return 0;
}

function roleManagementRoleNameExists(PDO $pdo, string $name, ?int $excludeRoleId = null): bool
{
    roleManagementEnsureRolesTable($pdo);

    $nameColumn = roleManagementNameColumn($pdo);

    if ($nameColumn === null) {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM roles WHERE LOWER(' . userManagementQuoteColumn($nameColumn) . ') = LOWER(:name)';
    $params = ['name' => $name];

    if ($excludeRoleId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $excludeRoleId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function roleManagementValidateInput(PDO $pdo, array $input, ?int $roleId = null): array
{
    $errors = [];
    $name = trim((string) ($input['name'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $status = roleManagementStatusFromInput((string) ($input['status'] ?? 'active'));
    $icon = trim((string) ($input['icon'] ?? ''));

    if ($name === '') {
        $errors[] = 'Role name is required.';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Role name must be 100 characters or fewer.';
    } elseif (roleManagementRoleNameExists($pdo, $name, $roleId)) {
        $errors[] = 'A role with this name already exists.';
    }

    if (strlen($description) > 255) {
        $errors[] = 'Description must be 255 characters or fewer.';
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Status must be Active or Inactive.';
    }

    if (strlen($icon) > 64) {
        $errors[] = 'Icon name must be 64 characters or fewer.';
    }

    return $errors;
}

function roleManagementBuildWritableData(PDO $pdo, array $input, bool $isCreate): array
{
    roleManagementEnsureRolesTable($pdo);

    $nameColumn = roleManagementNameColumn($pdo);

    if ($nameColumn === null) {
        throw new RuntimeException('The roles table needs a name or role column.');
    }

    $data = [
        $nameColumn => trim((string) ($input['name'] ?? '')),
    ];

    if (userManagementColumnExists($pdo, 'roles', 'description')) {
        $data['description'] = trim((string) ($input['description'] ?? ''));
    }

    if (userManagementColumnExists($pdo, 'roles', 'status')) {
        $data['status'] = roleManagementStatusFromInput((string) ($input['status'] ?? 'active'));
    }

    if (userManagementColumnExists($pdo, 'roles', 'icon')) {
        $data['icon'] = trim((string) ($input['icon'] ?? ''));
    }

    if ($isCreate && userManagementColumnExists($pdo, 'roles', 'created_at')) {
        $data['created_at'] = date('Y-m-d H:i:s');
    }

    if (userManagementColumnExists($pdo, 'roles', 'updated_at')) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    return $data;
}

function roleManagementInsertRole(PDO $pdo, array $data): int
{
    if (empty($data)) {
        throw new RuntimeException('No role data was provided.');
    }

    $columns = array_keys($data);
    $columnSql = implode(', ', array_map('userManagementQuoteColumn', $columns));
    $paramSql = implode(', ', array_map(static fn ($column) => ':' . $column, $columns));

    $statement = $pdo->prepare('INSERT INTO roles (' . $columnSql . ') VALUES (' . $paramSql . ')');
    $statement->execute($data);

    return (int) $pdo->lastInsertId();
}

function roleManagementUpdateRole(PDO $pdo, int $roleId, array $data): void
{
    if (empty($data)) {
        throw new RuntimeException('No role data was provided.');
    }

    $sets = [];

    foreach (array_keys($data) as $columnName) {
        $sets[] = userManagementQuoteColumn($columnName) . ' = :' . $columnName;
    }

    $data['id'] = $roleId;

    $statement = $pdo->prepare('UPDATE roles SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $statement->execute($data);
}

function roleManagementDeleteRole(PDO $pdo, int $roleId): void
{
    $role = roleManagementFindRole($pdo, $roleId);

    if ($role === null) {
        throw new RuntimeException('The selected role could not be found.');
    }

    if ((int) ($role['users_count'] ?? 0) > 0) {
        throw new RuntimeException('Roles assigned to users cannot be deleted.');
    }

    $statement = $pdo->prepare('DELETE FROM roles WHERE id = :id');
    $statement->execute(['id' => $roleId]);
}

function handleRoleManagementRequest(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_GET['page'] ?? '') !== 'user_roles') {
        return;
    }

    requireRoleManagementAdmin();

    try {
        $pdo = getDatabaseConnection();
        roleManagementEnsureRolesTable($pdo);
        $action = (string) ($_POST['role_action'] ?? '');

        if ($action === 'create') {
            $errors = roleManagementValidateInput($pdo, $_POST);

            if (!empty($errors)) {
                roleManagementFlash('error', implode(' ', $errors));
                roleManagementRedirect();
            }

            $data = roleManagementBuildWritableData($pdo, $_POST, true);
            $roleId = roleManagementInsertRole($pdo, $data);
            roleManagementSyncRolePermissions($pdo, $roleId, roleManagementPermissionIdsFromInput($pdo, $_POST));
            roleManagementFlash('success', 'Role created successfully.');
            roleManagementRedirect();
        }

        if ($action === 'update') {
            $roleId = (int) ($_POST['role_id'] ?? 0);

            if ($roleId <= 0 || roleManagementFindRole($pdo, $roleId) === null) {
                throw new RuntimeException('The selected role could not be found.');
            }

            $errors = roleManagementValidateInput($pdo, $_POST, $roleId);

            if (!empty($errors)) {
                roleManagementFlash('error', implode(' ', $errors));
                roleManagementRedirect(['edit_role' => $roleId]);
            }

            $data = roleManagementBuildWritableData($pdo, $_POST, false);
            roleManagementUpdateRole($pdo, $roleId, $data);
            roleManagementSyncRolePermissions($pdo, $roleId, roleManagementPermissionIdsFromInput($pdo, $_POST));
            roleManagementFlash('success', 'Role updated successfully.');
            roleManagementRedirect();
        }

        if ($action === 'delete') {
            $roleId = (int) ($_POST['role_id'] ?? 0);

            if ($roleId <= 0) {
                throw new RuntimeException('The selected role could not be found.');
            }

            roleManagementDeleteRole($pdo, $roleId);
            roleManagementFlash('success', 'Role deleted successfully.');
            roleManagementRedirect();
        }

        roleManagementFlash('error', 'Invalid role management action.');
        roleManagementRedirect();
    } catch (Throwable $exception) {
        error_log('Role management request failed: ' . $exception->getMessage());
        roleManagementFlash('error', $exception->getMessage());
        roleManagementRedirect();
    }
}