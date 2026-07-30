<?php
$roleManagementRoles = [];
$roleManagementPermissions = [];
$roleManagementError = '';
$roleManagementSearch = trim((string) ($_GET['search'] ?? ''));
$roleManagementFlash = function_exists('getRoleManagementFlash') ? getRoleManagementFlash() : null;
$roleManagementIsAdmin = function_exists('userManagementCurrentUserIsAdmin') && userManagementCurrentUserIsAdmin();
$roleManagementEditRoleId = (int) ($_GET['edit_role'] ?? 0);
$roleManagementEditRole = null;

function roleManagementPageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function roleManagementPageIcon(string $roleName, string $storedIcon = ''): string
{
    $storedIcon = trim($storedIcon);

    if ($storedIcon !== '') {
        return $storedIcon;
    }

    $normalizedRole = strtolower($roleName);

    if (str_contains($normalizedRole, 'admin')) {
        return 'admin_panel_settings';
    }

    if (str_contains($normalizedRole, 'editor')) {
        return 'edit_note';
    }

    if (str_contains($normalizedRole, 'viewer')) {
        return 'visibility';
    }

    if (str_contains($normalizedRole, 'moderator')) {
        return 'manage_accounts';
    }

    if (str_contains($normalizedRole, 'support')) {
        return 'support';
    }

    return 'security';
}

try {
    $pdo = getDatabaseConnection();

    if (function_exists('roleManagementFetchRoles')) {
        $roleManagementRoles = roleManagementFetchRoles($pdo, $roleManagementSearch);
    }

    if (function_exists('roleManagementFetchPermissions')) {
        $roleManagementPermissions = roleManagementFetchPermissions($pdo);
    }

    if ($roleManagementEditRoleId > 0 && function_exists('roleManagementFindRole')) {
        $roleManagementEditRole = roleManagementFindRole($pdo, $roleManagementEditRoleId);
    }
} catch (Throwable $exception) {
    error_log('Role management page load failed: ' . $exception->getMessage());
    $roleManagementError = 'Roles are temporarily unavailable.';
}
?>

<div class="user-roles">
    <div class="section-header">
        <h2>User Roles</h2>
        <div class="header-actions">
            <form action="index.php" method="GET" class="search-box ajax-search-form" data-target="#rolesTableBody" data-table="roles">
                <input type="hidden" name="page" value="user_roles">
                <span class="material-icons">search</span>
                <input
                    type="text"
                    class="mui-input"
                    id="searchRoles"
                    name="search"
                    placeholder="Search by role name..."
                    value="<?php echo roleManagementPageEscape($roleManagementSearch); ?>"
                >
                <button type="submit" class="mui-btn mui-btn-contained">
                    <span class="material-icons">search</span>
                    Search
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($roleManagementFlash)): ?>
        <div class="login-alert login-alert-<?php echo $roleManagementFlash['type'] === 'success' ? 'info' : 'error'; ?>" role="alert">
            <?php echo roleManagementPageEscape((string) $roleManagementFlash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($roleManagementError !== ''): ?>
        <div class="login-alert login-alert-error" role="alert">
            <?php echo roleManagementPageEscape($roleManagementError); ?>
        </div>
    <?php endif; ?>

    <?php if ($roleManagementIsAdmin): ?>
        <div class="mui-card">
            <h3><?php echo $roleManagementEditRole ? 'Edit Role' : 'Add Role'; ?></h3>
            <form action="index.php?page=user_roles" method="POST" class="user-form" data-validate="role">
                <?php echo csrfField(); ?>
                <input type="hidden" name="role_action" value="<?php echo $roleManagementEditRole ? 'update' : 'create'; ?>">
                <?php if ($roleManagementEditRole): ?>
                    <input type="hidden" name="role_id" value="<?php echo (int) $roleManagementEditRole['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="mui-input-group">
                        <input
                            type="text"
                            class="mui-input"
                            name="name"
                            placeholder="Role Name"
                            value="<?php echo roleManagementPageEscape((string) ($roleManagementEditRole['name'] ?? '')); ?>"
                            required
                        >
                        <label class="mui-label">Role Name</label>
                    </div>

                    <div class="mui-input-group">
                        <input
                            type="text"
                            class="mui-input"
                            name="description"
                            placeholder="Description"
                            value="<?php echo roleManagementPageEscape((string) ($roleManagementEditRole['description'] ?? '')); ?>"
                        >
                        <label class="mui-label">Description</label>
                    </div>

                    <div class="mui-input-group">
                        <select class="mui-input" name="status">
                            <?php $editRoleStatus = function_exists('roleManagementDisplayStatus') ? strtolower(roleManagementDisplayStatus($roleManagementEditRole ?? [])) : strtolower((string) ($roleManagementEditRole['status'] ?? 'active')); ?>
                            <option value="active" <?php echo $editRoleStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $editRoleStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <label class="mui-label">Status</label>
                    </div>

                    <div class="mui-input-group">
                        <input
                            type="text"
                            class="mui-input"
                            name="icon"
                            placeholder="Material Icon Name"
                            value="<?php echo roleManagementPageEscape((string) ($roleManagementEditRole['icon'] ?? '')); ?>"
                        >
                        <label class="mui-label">Icon</label>
                    </div>
                </div>

                <?php if (!empty($roleManagementPermissions)): ?>
                    <div class="mui-input-group">
                        <label class="mui-label" style="position: static; display: block; margin-bottom: 12px; font-size:0.85rem; color:var(--text-secondary);">Permissions</label>
                        <div class="permissions-grid">
                            <?php $selectedPermissionIds = array_map('intval', (array) ($roleManagementEditRole['permission_ids'] ?? [])); ?>
                            <?php foreach ($roleManagementPermissions as $permission): ?>
                                <?php $permissionId = (int) ($permission['id'] ?? 0); ?>
                                <label class="permission-checkbox">
                                    <input
                                        type="checkbox"
                                        name="permission_ids[]"
                                        value="<?php echo $permissionId; ?>"
                                        <?php echo in_array($permissionId, $selectedPermissionIds, true) ? 'checked' : ''; ?>
                                    >
                                    <?php echo roleManagementPageEscape((string) ($permission['name'] ?? 'Permission')); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="mui-btn mui-btn-contained">
                        <span class="material-icons"><?php echo $roleManagementEditRole ? 'save' : 'add'; ?></span>
                        <?php echo $roleManagementEditRole ? 'Update Role' : 'Add Role'; ?>
                    </button>

                    <?php if ($roleManagementEditRole): ?>
                        <a href="index.php?page=user_roles" class="mui-btn mui-btn-outlined">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="mui-table-container">
        <table class="mui-table">
            <thead>
                <tr>
                    <th>Role Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Permissions</th>
                    <th>Users Count</th>
                    <?php if ($roleManagementIsAdmin): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="rolesTableBody">
                <?php if (!empty($roleManagementRoles)): ?>
                    <?php foreach ($roleManagementRoles as $role): ?>
                        <?php
                        $roleId = (int) ($role['id'] ?? 0);
                        $roleName = (string) ($role['name'] ?? 'Role');
                        $roleDescription = (string) ($role['description'] ?? '');
                        $roleIcon = roleManagementPageIcon($roleName, (string) ($role['icon'] ?? ''));
                        $roleStatus = function_exists('roleManagementDisplayStatus') ? roleManagementDisplayStatus($role) : ucfirst(strtolower((string) ($role['status'] ?? 'active')));
                        $roleStatusClass = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $roleStatus));
                        $rolePermissions = (array) ($role['permissions'] ?? []);
                        $roleUsersCount = (int) ($role['users_count'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <div class="role-cell">
                                    <span class="material-icons role-icon"><?php echo roleManagementPageEscape($roleIcon); ?></span>
                                    <span><?php echo roleManagementPageEscape($roleName); ?></span>
                                </div>
                            </td>
                            <td><?php echo roleManagementPageEscape($roleDescription !== '' ? $roleDescription : 'No description provided'); ?></td>
                            <td>
                                <span class="status-badge <?php echo roleManagementPageEscape($roleStatusClass); ?>">
                                    <?php echo roleManagementPageEscape($roleStatus); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo !empty($rolePermissions)
                                    ? roleManagementPageEscape(implode(', ', $rolePermissions))
                                    : 'No permissions assigned'; ?>
                            </td>
                            <td><span class="role-count"><?php echo $roleUsersCount; ?></span></td>
                            <?php if ($roleManagementIsAdmin): ?>
                                <td>
                                    <div class="table-actions">
                                        <a
                                            href="index.php?page=user_roles&edit_role=<?php echo $roleId; ?>"
                                            class="mui-btn mui-btn-outlined mui-btn-sm"
                                            title="Edit"
                                        >
                                            <span class="material-icons">edit</span>
                                        </a>
                                        <form action="index.php?page=user_roles" method="POST">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="role_action" value="delete">
                                            <input type="hidden" name="role_id" value="<?php echo $roleId; ?>">
                                            <button
                                                type="submit"
                                                class="mui-btn mui-btn-danger mui-btn-sm"
                                                title="Delete"
                                                <?php echo $roleUsersCount > 0 ? 'disabled' : ''; ?>
                                                onclick="return confirm('Delete this role?');"
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
                        <td colspan="<?php echo $roleManagementIsAdmin ? '6' : '5'; ?>">
                            No roles found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>