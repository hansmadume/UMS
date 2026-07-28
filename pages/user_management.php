<?php
$users = [];
$roles = [];
$userManagementError = '';
$userManagementSearch = trim((string) ($_GET['search'] ?? ''));
$userManagementFlash = function_exists('getUserManagementFlash') ? getUserManagementFlash() : null;
$userManagementIsAdmin = function_exists('userManagementCurrentUserIsAdmin') && userManagementCurrentUserIsAdmin();

function userManagementPageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = getDatabaseConnection();

    if (function_exists('userManagementFetchUsers')) {
        $users = userManagementFetchUsers($pdo, $userManagementSearch);
    }

    if (function_exists('userManagementFetchRoles')) {
        $roles = userManagementFetchRoles($pdo);
    }
} catch (Throwable $exception) {
    error_log('User management page load failed: ' . $exception->getMessage());
    $userManagementError = 'Users are temporarily unavailable.';
}
?>

<div class="user-management">
    <div class="section-header">
        <h2>User Management</h2>
        <div class="header-actions">
            <form action="index.php" method="GET" class="search-box">
                <input type="hidden" name="page" value="user_management">
                <span class="material-icons">search</span>
                <input
                    type="text"
                    class="mui-input"
                    id="searchUsers"
                    name="search"
                    placeholder="Search users..."
                    value="<?php echo userManagementPageEscape($userManagementSearch); ?>"
                >
            </form>
        </div>
    </div>

    <?php if (!empty($userManagementFlash)): ?>
        <div class="login-alert login-alert-<?php echo $userManagementFlash['type'] === 'success' ? 'info' : 'error'; ?>" role="alert">
            <?php echo userManagementPageEscape((string) $userManagementFlash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($userManagementError !== ''): ?>
        <div class="login-alert login-alert-error" role="alert">
            <?php echo userManagementPageEscape($userManagementError); ?>
        </div>
    <?php endif; ?>

    <?php if ($userManagementIsAdmin): ?>
        <div class="mui-card">
            <h3>Add User</h3>
            <form action="index.php?page=user_management" method="POST" class="user-form">
                <input type="hidden" name="user_action" value="create">

                <div class="mui-input-group">
                    <input type="text" class="mui-input" name="name" placeholder="Full Name" required>
                    <label class="mui-label">Full Name</label>
                </div>

                <div class="mui-input-group">
                    <input type="text" class="mui-input" name="username" placeholder="Username" required>
                    <label class="mui-label">Username</label>
                </div>

                <div class="mui-input-group">
                    <input type="email" class="mui-input" name="email" placeholder="Email" required>
                    <label class="mui-label">Email</label>
                </div>

                <?php if (!empty($roles)): ?>
                    <div class="mui-input-group">
                        <select class="mui-input" name="role_id">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo (int) $role['id']; ?>">
                                    <?php echo userManagementPageEscape((string) $role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="mui-label">Role</label>
                    </div>
                <?php else: ?>
                    <div class="mui-input-group">
                        <input type="text" class="mui-input" name="role" placeholder="Role" value="User">
                        <label class="mui-label">Role</label>
                    </div>
                <?php endif; ?>

                <div class="mui-input-group">
                    <select class="mui-input" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                    </select>
                    <label class="mui-label">Status</label>
                </div>

                <div class="mui-input-group">
                    <input type="password" class="mui-input" name="password" placeholder="Password" autocomplete="new-password" required>
                    <label class="mui-label">Password</label>
                </div>

                <button type="submit" class="mui-btn mui-btn-contained">
                    <span class="material-icons">person_add</span>
                    Add User
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div class="mui-table-container">
        <table class="mui-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <?php if ($userManagementIsAdmin): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userId = (int) ($user['id'] ?? 0);
                        $displayName = function_exists('userManagementDisplayName') ? userManagementDisplayName($user) : (string) ($user['name'] ?? 'User');
                        $displayRole = function_exists('userManagementDisplayRole') ? userManagementDisplayRole($user) : (string) ($user['role'] ?? 'User');
                        $displayStatus = function_exists('userManagementDisplayStatus') ? userManagementDisplayStatus($user) : 'Active';
                        $statusClass = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $displayStatus));
                        ?>
                        <tr>
                            <td>#<?php echo str_pad((string) $userId, 3, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div class="user-cell">
                                    <span class="material-icons user-avatar">account_circle</span>
                                    <span><?php echo userManagementPageEscape($displayName); ?></span>
                                </div>
                            </td>
                            <td><?php echo userManagementPageEscape((string) ($user['email'] ?? '')); ?></td>
                            <td><?php echo userManagementPageEscape($displayRole); ?></td>
                            <td>
                                <span class="status-badge <?php echo userManagementPageEscape($statusClass); ?>">
                                    <?php echo userManagementPageEscape($displayStatus); ?>
                                </span>
                            </td>
                            <?php if ($userManagementIsAdmin): ?>
                                <td>
                                    <div class="table-actions">
                                        <form action="index.php?page=user_management" method="POST">
                                            <input type="hidden" name="user_action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                            <button
                                                type="submit"
                                                class="mui-btn mui-btn-danger mui-btn-sm"
                                                title="Delete"
                                                onclick="return confirm('Delete this user?');"
                                            >
                                                <span class="material-icons">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $userManagementIsAdmin ? '6' : '5'; ?>">
                            No users found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>