<?php
$users = [];
$roles = [];
$userManagementError = '';
$userManagementSearch = trim((string) ($_GET['search'] ?? ''));
$userManagementRoleFilter = trim((string) ($_GET['role_filter'] ?? ''));
$userManagementStatusFilter = trim((string) ($_GET['status_filter'] ?? ''));
$userManagementEditUserId = (int) ($_GET['edit_user'] ?? 0);
$userManagementEditUser = null;
$userManagementFlash = function_exists('getUserManagementFlash') ? getUserManagementFlash() : null;
$userManagementIsAdmin = function_exists('userManagementCurrentUserIsAdmin') && userManagementCurrentUserIsAdmin();
$userManagementCanManage = $userManagementIsAdmin || (function_exists('userHasRole') && userHasRole(['manager']));
$currentUser = function_exists('getAuthenticatedUser') ? getAuthenticatedUser() : null;

function userManagementPageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = getDatabaseConnection();

    if (function_exists('userManagementFetchUsers')) {
        $users = userManagementFetchUsers($pdo, $userManagementSearch, $userManagementRoleFilter, $userManagementStatusFilter);
    }

    if (function_exists('userManagementFetchRoles')) {
        $roles = userManagementFetchRoles($pdo);
    }

    if ($userManagementEditUserId > 0 && function_exists('userManagementFindUser')) {
        $userManagementEditUser = userManagementFindUser($pdo, $userManagementEditUserId);
    }
} catch (Throwable $exception) {
    error_log('User management page load failed: ' . $exception->getMessage());
    $userManagementError = 'Users are temporarily unavailable.';
}

$formUser = $userManagementEditUser ?? [];
$formUserName = !empty($formUser) && function_exists('userManagementDisplayName') ? userManagementDisplayName($formUser) : '';
$formUsername = !empty($formUser) && function_exists('userManagementDisplayUsername') ? userManagementDisplayUsername($formUser) : (string) ($formUser['username'] ?? '');
$formEmail = (string) ($formUser['email'] ?? '');
$formRole = !empty($formUser) && function_exists('userManagementDisplayRole') ? userManagementDisplayRole($formUser) : 'User';
$formRoleId = (int) ($formUser['role_id'] ?? 0);
$formStatus = !empty($formUser) && function_exists('userManagementDisplayStatus') ? strtolower(userManagementDisplayStatus($formUser)) : 'active';
$formContact = !empty($formUser) && function_exists('userManagementDisplayContact') ? userManagementDisplayContact($formUser) : '';
$formAddress = !empty($formUser) && function_exists('userManagementDisplayAddress') ? userManagementDisplayAddress($formUser) : '';
$isEditingSelf = !empty($currentUser['id']) && !empty($formUser['id']) && (int) $currentUser['id'] === (int) $formUser['id'];
?>

<div class="user-management">
    <div class="section-header">
        <h2>User Management</h2>
        <div class="header-actions">
            <form action="index.php" method="GET" class="search-box ajax-search-form" data-target="#usersTableBody">
                <input type="hidden" name="page" value="user_management">
                <span class="material-icons">search</span>
                <input
                    type="text"
                    class="mui-input"
                    id="searchUsers"
                    name="search"
                    placeholder="Search by name, username, or email..."
                    value="<?php echo userManagementPageEscape($userManagementSearch); ?>"
                >
                <select class="mui-input" name="role_filter">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option
                            value="<?php echo (int) $role['id']; ?>"
                            <?php echo $userManagementRoleFilter === (string) ((int) $role['id']) ? 'selected' : ''; ?>
                        >
                            <?php echo userManagementPageEscape((string) $role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="mui-input" name="status_filter">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $userManagementStatusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $userManagementStatusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="mui-btn mui-btn-contained">
                    <span class="material-icons">filter_list</span>
                    Filter
                </button>
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

    <?php if ($userManagementCanManage): ?>
        <div class="mui-card">
            <h3><?php echo $userManagementEditUser ? 'Edit User' : 'Add User'; ?></h3>
            <form action="index.php?page=user_management" method="POST" class="user-form" data-validate="user">
                <?php echo csrfField(); ?>
                <input type="hidden" name="user_action" value="<?php echo $userManagementEditUser ? 'update' : 'create'; ?>">
                <?php if ($userManagementEditUser): ?>
                    <input type="hidden" name="user_id" value="<?php echo (int) $userManagementEditUser['id']; ?>">
                <?php endif; ?>

                <div class="mui-input-group">
                    <input type="text" class="mui-input" name="name" placeholder="Full Name" value="<?php echo userManagementPageEscape($formUserName); ?>" required>
                    <label class="mui-label">Full Name</label>
                </div>

                <div class="mui-input-group">
                    <input type="text" class="mui-input" name="username" placeholder="Username" value="<?php echo userManagementPageEscape($formUsername); ?>" required>
                    <label class="mui-label">Username</label>
                </div>

                <div class="mui-input-group">
                    <input type="email" class="mui-input" name="email" placeholder="Email" value="<?php echo userManagementPageEscape($formEmail); ?>" required>
                    <label class="mui-label">Email</label>
                </div>

                <div class="mui-input-group">
                    <input type="text" class="mui-input" name="contact_number" placeholder="Contact Number" value="<?php echo userManagementPageEscape($formContact); ?>">
                    <label class="mui-label">Contact Number</label>
                </div>

                <div class="mui-input-group">
                    <input type="text" class="mui-input" name="address" placeholder="Address" value="<?php echo userManagementPageEscape($formAddress); ?>">
                    <label class="mui-label">Address</label>
                </div>

                <?php if (!empty($roles)): ?>
                    <div class="mui-input-group">
                        <select class="mui-input" name="role_id" <?php echo $isEditingSelf ? 'disabled' : ''; ?>>
                            <?php foreach ($roles as $role): ?>
                                <option
                                    value="<?php echo (int) $role['id']; ?>"
                                    <?php echo $formRoleId === (int) $role['id'] ? 'selected' : ''; ?>
                                >
                                    <?php echo userManagementPageEscape((string) $role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="mui-label">Role<?php echo $isEditingSelf ? ' - cannot edit your own role' : ''; ?></label>
                    </div>
                <?php else: ?>
                    <div class="mui-input-group">
                        <input
                            type="text"
                            class="mui-input"
                            name="role"
                            placeholder="Role"
                            value="<?php echo userManagementPageEscape($formRole); ?>"
                            <?php echo $isEditingSelf ? 'readonly' : ''; ?>
                        >
                        <label class="mui-label">Role<?php echo $isEditingSelf ? ' - cannot edit your own role' : ''; ?></label>
                    </div>
                <?php endif; ?>

                <div class="mui-input-group">
                    <select class="mui-input" name="status" <?php echo $isEditingSelf ? 'disabled' : ''; ?>>
                        <option value="active" <?php echo $formStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $formStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <label class="mui-label">Status<?php echo $isEditingSelf ? ' - cannot activate/deactivate your own account' : ''; ?></label>
                </div>

                <div class="mui-input-group">
                    <input
                        type="password"
                        class="mui-input"
                        name="password"
                        placeholder="<?php echo $userManagementEditUser ? 'New Password (leave blank to keep current)' : 'Password'; ?>"
                        autocomplete="new-password"
                        <?php echo $userManagementEditUser ? '' : 'required'; ?>
                    >
                    <label class="mui-label">Password</label>
                </div>

                <div class="mui-input-group">
                    <input
                        type="password"
                        class="mui-input"
                        name="confirm_password"
                        placeholder="Confirm Password"
                        autocomplete="new-password"
                        <?php echo $userManagementEditUser ? '' : 'required'; ?>
                    >
                    <label class="mui-label">Confirm Password</label>
                </div>

                <button type="submit" class="mui-btn mui-btn-contained">
                    <span class="material-icons"><?php echo $userManagementEditUser ? 'save' : 'person_add'; ?></span>
                    <?php echo $userManagementEditUser ? 'Update User' : 'Add User'; ?>
                </button>

                <?php if ($userManagementEditUser): ?>
                    <a href="index.php?page=user_management" class="mui-btn mui-btn-outlined">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>

    <div class="mui-table-container">
        <table class="mui-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Last Login</th>
                    <?php if ($userManagementCanManage): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userId = (int) ($user['id'] ?? 0);
                        $displayName = function_exists('userManagementDisplayName') ? userManagementDisplayName($user) : (string) ($user['name'] ?? 'User');
                        $displayUsername = function_exists('userManagementDisplayUsername') ? userManagementDisplayUsername($user) : (string) ($user['username'] ?? '');
                        $displayRole = function_exists('userManagementDisplayRole') ? userManagementDisplayRole($user) : (string) ($user['role'] ?? 'User');
                        $displayStatus = function_exists('userManagementDisplayStatus') ? userManagementDisplayStatus($user) : 'Active';
                        $displayCreated = function_exists('userManagementDisplayDate') ? userManagementDisplayDate((string) ($user['created_at'] ?? '')) : (string) ($user['created_at'] ?? 'N/A');
                        $displayLastLogin = function_exists('userManagementDisplayDate') ? userManagementDisplayDate((string) ($user['last_login'] ?? '')) : (string) ($user['last_login'] ?? 'N/A');
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
                            <td><?php echo userManagementPageEscape($displayUsername); ?></td>
                            <td><?php echo userManagementPageEscape((string) ($user['email'] ?? '')); ?></td>
                            <td><?php echo userManagementPageEscape($displayRole); ?></td>
                            <td>
                                <span class="status-badge <?php echo userManagementPageEscape($statusClass); ?>">
                                    <?php echo userManagementPageEscape($displayStatus); ?>
                                </span>
                            </td>
                            <td><?php echo userManagementPageEscape($displayCreated); ?></td>
                            <td><?php echo userManagementPageEscape($displayLastLogin); ?></td>
                            <?php if ($userManagementCanManage): ?>
                                <td>
                                    <div class="table-actions">
                                        <a
                                            href="index.php?page=user_management&edit_user=<?php echo $userId; ?>"
                                            class="mui-btn mui-btn-outlined mui-btn-sm"
                                            title="Edit"
                                        >
                                            <span class="material-icons">edit</span>
                                        </a>
                                        <form action="index.php?page=user_management" method="POST">
                                            <?php echo csrfField(); ?>
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
                        <td colspan="<?php echo $userManagementCanManage ? '9' : '8'; ?>">
                            No users found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>