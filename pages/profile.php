<div class="profile-page">
    <!-- Profile Header Card -->
    <div class="mui-card profile-header-card">
        <div class="profile-avatar">
            <span class="material-icons">person</span>
        </div>
        <div class="profile-info">
            <h2>John Doe</h2>
            <p class="profile-email">john.doe@example.com</p>
            <span class="status-badge active">Administrator</span>
        </div>
        <div class="profile-meta">
            <div class="meta-item">
                <span class="material-icons">calendar_today</span>
                <span>Joined: January 2024</span>
            </div>
            <div class="meta-item">
                <span class="material-icons">location_on</span>
                <span>New York, USA</span>
            </div>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="mui-card profile-form-card">
        <h3 class="form-title">Edit Profile</h3>
        <form action="#" method="POST" class="profile-form">
            <div class="form-row">
                <div class="mui-input-group">
                    <input type="text" class="mui-input" id="first_name" placeholder="First Name" value="John" required>
                    <label class="mui-label" for="first_name">First Name</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" id="last_name" placeholder="Last Name" value="Doe" required>
                    <label class="mui-label" for="last_name">Last Name</label>
                </div>
            </div>
            <div class="form-row">
                <div class="mui-input-group">
                    <input type="email" class="mui-input" id="email" placeholder="Email" value="john.doe@example.com" required>
                    <label class="mui-label" for="email">Email</label>
                </div>
                <div class="mui-input-group">
                    <input type="text" class="mui-input" id="phone" placeholder="Phone" value="+1 (555) 123-4567">
                    <label class="mui-label" for="phone">Phone</label>
                </div>
            </div>
            <div class="form-row">
                <div class="mui-select-group">
                    <select class="mui-select" id="role" required>
                        <option value="admin" selected>Administrator</option>
                        <option value="editor">Editor</option>
                        <option value="viewer">Viewer</option>
                    </select>
                    <label class="mui-label" for="role">Role</label>
                </div>
                <div class="mui-select-group">
                    <select class="mui-select" id="status" required>
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                    </select>
                    <label class="mui-label" for="status">Status</label>
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
