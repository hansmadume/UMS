<?php
$profileUser = null;
$profileError = '';
$profileFlash = function_exists('getProfileManagementFlash') ? getProfileManagementFlash() : null;

function profilePageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = getDatabaseConnection();

    if (function_exists('profileManagementFetchCurrentUser')) {
        $profileUser = profileManagementFetchCurrentUser($pdo);
    }
} catch (Throwable $exception) {
    error_log('Profile page load failed: ' . $exception->getMessage());
    $profileError = 'Profile is temporarily unavailable.';
}

if ($profileUser === null && $profileError === '') {
    $sessionUser = function_exists('getAuthenticatedUser') ? getAuthenticatedUser() : null;

    if ($sessionUser) {
        $profileUser = $sessionUser;
    }
}

$profileName = $profileUser ? userManagementDisplayName($profileUser) : 'User';
$profileUsername = $profileUser ? userManagementDisplayUsername($profileUser) : '';
$profileEmail = (string) ($profileUser['email'] ?? '');
$profileContact = $profileUser ? userManagementDisplayContact($profileUser) : '';
$profileAddress = $profileUser ? userManagementDisplayAddress($profileUser) : '';
$profilePhoto = $profileUser ? userManagementDisplayProfilePhoto($profileUser) : '';
$profileRole = $profileUser ? userManagementDisplayRole($profileUser) : 'User';
$profileStatus = $profileUser ? userManagementDisplayStatus($profileUser) : 'Active';
$profileCreated = userManagementDisplayDate((string) ($profileUser['created_at'] ?? ''));
$profileLastLogin = userManagementDisplayDate((string) ($profileUser['last_login'] ?? ''));
$statusClass = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $profileStatus));
?>

<div class="profile-page">
    <?php if (!empty($profileFlash)): ?>
        <div class="login-alert login-alert-<?php echo $profileFlash['type'] === 'success' ? 'info' : 'error'; ?>" role="alert">
            <?php echo profilePageEscape((string) $profileFlash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($profileError !== ''): ?>
        <div class="login-alert login-alert-error" role="alert">
            <?php echo profilePageEscape($profileError); ?>
        </div>
    <?php endif; ?>

    <div class="mui-card profile-header-card">
        <div class="profile-avatar">
            <?php if ($profilePhoto !== ''): ?>
                <img src="<?php echo profilePageEscape($profilePhoto); ?>" alt="Profile Photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
            <?php else: ?>
                <span class="material-icons">person</span>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h2><?php echo profilePageEscape($profileName); ?></h2>
            <p class="profile-email"><?php echo profilePageEscape($profileEmail); ?></p>
            <span class="status-badge <?php echo profilePageEscape($statusClass); ?>">
                <?php echo profilePageEscape($profileRole); ?> / <?php echo profilePageEscape($profileStatus); ?>
            </span>
        </div>
        <div class="profile-meta">
            <div class="meta-item">
                <span class="material-icons">calendar_today</span>
                <span>Joined: <?php echo profilePageEscape($profileCreated); ?></span>
            </div>
            <div class="meta-item">
                <span class="material-icons">login</span>
                <span>Last Login: <?php echo profilePageEscape($profileLastLogin); ?></span>
            </div>
        </div>
    </div>

    <div class="mui-card profile-form-card">
        <h3 class="form-title">Profile Details</h3>

        <div class="profile-form">
            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileName); ?>" readonly>
                    <label class="mui-label">Full Name</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileUsername); ?>" readonly>
                    <label class="mui-label">Username</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileEmail); ?>" readonly>
                    <label class="mui-label">Email Address</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileContact); ?>" readonly>
                    <label class="mui-label">Contact Number</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileAddress); ?>" readonly>
                    <label class="mui-label">Address</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileRole); ?>" readonly>
                    <label class="mui-label">User Role</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileStatus); ?>" readonly>
                    <label class="mui-label">Account Status</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileCreated); ?>" readonly>
                    <label class="mui-label">Date Created</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileLastLogin); ?>" readonly>
                    <label class="mui-label">Last Login</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profilePhoto !== '' ? $profilePhoto : 'No profile photo uploaded'); ?>" readonly>
                    <label class="mui-label">Profile Photo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="mui-card profile-form-card">
        <h3 class="form-title">Edit Profile</h3>
        <form action="index.php?page=profile" method="POST" enctype="multipart/form-data" class="profile-form">
            <div class="form-row">
                <div class="mui-input-group">
                    <input
                        type="text"
                        class="mui-input"
                        id="name"
                        name="name"
                        placeholder="Full Name"
                        value="<?php echo profilePageEscape($profileName); ?>"
                        required
                    >
                    <label class="mui-label" for="name">Full Name</label>
                </div>
                <div class="mui-input-group">
                    <input
                        type="text"
                        class="mui-input"
                        id="contact_number"
                        name="contact_number"
                        placeholder="Contact Number"
                        value="<?php echo profilePageEscape($profileContact); ?>"
                    >
                    <label class="mui-label" for="contact_number">Contact Number</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input
                        type="text"
                        class="mui-input"
                        id="address"
                        name="address"
                        placeholder="Address"
                        value="<?php echo profilePageEscape($profileAddress); ?>"
                    >
                    <label class="mui-label" for="address">Address</label>
                </div>
                <div class="mui-input-group">
                    <input type="file" class="mui-input" id="profile_photo" name="profile_photo" accept="image/*">
                    <label class="mui-label" for="profile_photo">Profile Photo</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input type="password" class="mui-input" id="password" name="password" placeholder="New Password" autocomplete="new-password">
                    <label class="mui-label" for="password">New Password</label>
                </div>
                <div class="mui-input-group">
                    <input type="password" class="mui-input" id="confirm_password" name="confirm_password" placeholder="Confirm Password" autocomplete="new-password">
                    <label class="mui-label" for="confirm_password">Confirm Password</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileUsername); ?>" readonly>
                    <label class="mui-label">Username - cannot be edited here</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileEmail); ?>" readonly>
                    <label class="mui-label">Email - cannot be edited here</label>
                </div>
            </div>

            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileRole); ?>" readonly>
                    <label class="mui-label">Role - users cannot edit their own role</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" value="<?php echo profilePageEscape($profileStatus); ?>" readonly>
                    <label class="mui-label">Status - users cannot activate/deactivate their own account</label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="mui-btn mui-btn-contained">
                    <span class="material-icons">save</span>
                    Save Changes
                </button>
                <button type="reset" class="mui-btn mui-btn-outlined">
                    <span class="material-icons">close</span>
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>