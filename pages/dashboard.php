<?php
$currentUser = getAuthenticatedUser() ?? [];

function dashboardEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function dashboardTableExists(PDO $pdo, string $tableName): bool
{
    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => $tableName]);

        return (int) $statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        error_log('Dashboard table check failed: ' . $exception->getMessage());

        return false;
    }
}

function dashboardColumnExists(PDO $pdo, string $tableName, string $columnName): bool
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
        error_log('Dashboard column check failed: ' . $exception->getMessage());

        return false;
    }
}

function dashboardCountRows(PDO $pdo, string $tableName): int
{
    if (!dashboardTableExists($pdo, $tableName)) {
        return 0;
    }

    try {
        $statement = $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', str_replace('`', '``', $tableName)));

        return (int) $statement->fetchColumn();
    } catch (Throwable $exception) {
        error_log('Dashboard count failed for ' . $tableName . ': ' . $exception->getMessage());

        return 0;
    }
}

function dashboardResolveCurrentUser(PDO $pdo, array $sessionUser): array
{
    $resolvedUser = $sessionUser;
    $userId = $sessionUser['id'] ?? null;

    if (!$userId || !dashboardTableExists($pdo, 'users')) {
        return $resolvedUser;
    }

    try {
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $databaseUser = $statement->fetch();

        if (!$databaseUser) {
            return $resolvedUser;
        }

        $resolvedUser = array_merge($databaseUser, $resolvedUser);

        if (empty($resolvedUser['name'])) {
            $resolvedUser['name'] = $databaseUser['full_name']
                ?? $databaseUser['name']
                ?? $databaseUser['username']
                ?? $databaseUser['email']
                ?? 'User';
        }

        $resolvedUser['role'] = $databaseUser['role_name']
            ?? $databaseUser['role']
            ?? $databaseUser['user_role']
            ?? $databaseUser['type']
            ?? $resolvedUser['role']
            ?? '';

        if (empty($resolvedUser['role']) && !empty($databaseUser['role_id']) && dashboardTableExists($pdo, 'roles')) {
            $roleNameColumn = dashboardColumnExists($pdo, 'roles', 'name') ? 'name' : 'role';
            $roleStatement = $pdo->prepare(sprintf('SELECT `%s` FROM roles WHERE id = :id LIMIT 1', $roleNameColumn));
            $roleStatement->execute(['id' => $databaseUser['role_id']]);
            $resolvedUser['role'] = (string) ($roleStatement->fetchColumn() ?: '');
        }

        if (empty($resolvedUser['role'])) {
            $resolvedUser['role'] = 'User';
        }

        $roleLower = strtolower((string) $resolvedUser['role']);
        $resolvedUser['is_admin'] = !empty($databaseUser['is_admin'])
            || !empty($resolvedUser['is_admin'])
            || in_array($roleLower, ['admin', 'administrator', 'super admin'], true);

        return $resolvedUser;
    } catch (Throwable $exception) {
        error_log('Dashboard current user lookup failed: ' . $exception->getMessage());

        return $resolvedUser;
    }
}

function dashboardRecentUsers(PDO $pdo, int $limit = 5): array
{
    if (!dashboardTableExists($pdo, 'users')) {
        return [];
    }

    try {
        $orderBy = dashboardColumnExists($pdo, 'users', 'created_at') ? 'created_at DESC' : 'id DESC';
        $statement = $pdo->prepare("SELECT * FROM users ORDER BY {$orderBy} LIMIT :limit");
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Dashboard recent users lookup failed: ' . $exception->getMessage());

        return [];
    }
}

function dashboardUserDisplayName(array $user): string
{
    return (string) ($user['full_name'] ?? $user['name'] ?? $user['username'] ?? $user['email'] ?? 'User');
}

function dashboardUserRole(array $user): string
{
    return (string) ($user['role_name'] ?? $user['role'] ?? $user['user_role'] ?? $user['type'] ?? 'User');
}

function dashboardUserStatus(array $user): string
{
    if (array_key_exists('status', $user)) {
        return (string) $user['status'];
    }

    if (array_key_exists('is_active', $user)) {
        return $user['is_active'] ? 'Active' : 'Inactive';
    }

    if (array_key_exists('active', $user)) {
        return $user['active'] ? 'Active' : 'Inactive';
    }

    return 'Active';
}

$dashboardError = '';
$totalUsers = 0;
$totalRoles = 0;
$recentUsers = [];
$isAdmin = !empty($currentUser['is_admin']);

try {
    $pdo = getDatabaseConnection();
    $currentUser = dashboardResolveCurrentUser($pdo, $currentUser);
    $isAdmin = !empty($currentUser['is_admin']);

    if ($isAdmin) {
        $totalUsers = dashboardCountRows($pdo, 'users');
        $totalRoles = dashboardCountRows($pdo, 'roles');
    }

    $recentUsers = dashboardRecentUsers($pdo);
} catch (Throwable $exception) {
    error_log('Dashboard data load failed: ' . $exception->getMessage());
    $dashboardError = 'Dashboard data is temporarily unavailable.';
}

$userName = (string) ($currentUser['name'] ?? $currentUser['username'] ?? $currentUser['email'] ?? 'User');
$userRole = (string) ($currentUser['role'] ?? 'User');

$quickLinks = [
    [
        'label' => 'Dashboard',
        'description' => 'View account overview and system status',
        'icon' => 'dashboard',
        'url' => 'index.php?page=dashboard',
    ],
    [
        'label' => 'User Management',
        'description' => 'Manage user records and account access',
        'icon' => 'group',
        'url' => 'index.php?page=user_management',
    ],
    [
        'label' => 'User Roles',
        'description' => 'Review roles and permissions',
        'icon' => 'security',
        'url' => 'index.php?page=user_roles',
    ],
    [
        'label' => 'Profile',
        'description' => 'Update your personal account details',
        'icon' => 'person',
        'url' => 'index.php?page=profile',
    ],
];
?>

<div class="dashboard">
    <section class="dashboard-hero mui-card">
        <div>
            <p class="dashboard-eyebrow">Welcome back</p>
            <h1>Welcome, <?php echo dashboardEscape($userName); ?>!</h1>
            <p class="dashboard-subtitle">You are signed in as <strong><?php echo dashboardEscape($userRole); ?></strong>.</p>
        </div>
        <div class="dashboard-user-badge">
            <span class="material-icons">account_circle</span>
            <div>
                <span class="badge-label">Logged-in User</span>
                <strong><?php echo dashboardEscape($userName); ?></strong>
            </div>
        </div>
    </section>

    <?php if ($dashboardError !== ''): ?>
        <div class="login-alert login-alert-error" role="alert">
            <?php echo dashboardEscape($dashboardError); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-icons">person</span>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo dashboardEscape($userName); ?></div>
                <div class="stat-label">Logged-in User</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-icons">verified_user</span>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo dashboardEscape($userRole); ?></div>
                <div class="stat-label">User Role</div>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-icons">people</span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-icons">admin_panel_settings</span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($totalRoles); ?></div>
                    <div class="stat-label">Total Roles</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-grid">
        <div class="mui-card dashboard-card">
            <div class="card-header">
                <h3>Quick Links</h3>
            </div>
            <div class="quick-links-grid">
                <?php foreach ($quickLinks as $link): ?>
                    <a class="quick-link-card" href="<?php echo dashboardEscape($link['url']); ?>">
                        <span class="material-icons"><?php echo dashboardEscape($link['icon']); ?></span>
                        <div>
                            <strong><?php echo dashboardEscape($link['label']); ?></strong>
                            <p><?php echo dashboardEscape($link['description']); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mui-card dashboard-card">
            <div class="card-header">
                <h3>Recent User Activities</h3>
            </div>
            <div class="activity-list">
                <?php if (!empty($recentUsers)): ?>
                    <?php foreach ($recentUsers as $recentUser): ?>
                        <?php
                        $recentName = dashboardUserDisplayName($recentUser);
                        $recentStatus = dashboardUserStatus($recentUser);
                        $recentStatusClass = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $recentStatus));
                        ?>
                        <div class="activity-item">
                            <span class="material-icons activity-icon">person</span>
                            <div class="activity-info">
                                <p class="activity-text">
                                    <strong><?php echo dashboardEscape($recentName); ?></strong>
                                    is listed as <?php echo dashboardEscape(dashboardUserRole($recentUser)); ?>.
                                </p>
                                <span class="activity-time">
                                    Status:
                                    <span class="status-badge <?php echo dashboardEscape($recentStatusClass); ?>">
                                        <?php echo dashboardEscape($recentStatus); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-item">
                        <span class="material-icons activity-icon">info</span>
                        <div class="activity-info">
                            <p class="activity-text">No recent user activity is available.</p>
                            <span class="activity-time">Activity tracking is optional.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>