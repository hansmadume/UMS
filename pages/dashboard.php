<div class="dashboard">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-icons">people</span>
            </div>
            <div class="stat-info">
                <div class="stat-value">1,245</div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-icons">verified_user</span>
            </div>
            <div class="stat-info">
                <div class="stat-value">1,180</div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-icons">admin_panel_settings</span>
            </div>
            <div class="stat-info">
                <div class="stat-value">8</div>
                <div class="stat-label">Roles</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-icons">person_add</span>
            </div>
            <div class="stat-info">
                <div class="stat-value">23</div>
                <div class="stat-label">New Today</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Recent Users -->
        <div class="mui-card dashboard-card">
            <div class="card-header">
                <h3>Recent Users</h3>
                <a href="index.php?page=user_management" class="mui-btn mui-btn-outlined mui-btn-sm">
                    <span class="material-icons">visibility</span>
                    View All
                </a>
            </div>
            <div class="mui-table-container">
                <table class="mui-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>John Doe</td>
                            <td>john@example.com</td>
                            <td>Admin</td>
                            <td><span class="status-badge active">Active</span></td>
                        </tr>
                        <tr>
                            <td>Jane Smith</td>
                            <td>jane@example.com</td>
                            <td>Editor</td>
                            <td><span class="status-badge active">Active</span></td>
                        </tr>
                        <tr>
                            <td>Bob Johnson</td>
                            <td>bob@example.com</td>
                            <td>Viewer</td>
                            <td><span class="status-badge inactive">Inactive</span></td>
                        </tr>
                        <tr>
                            <td>Alice Brown</td>
                            <td>alice@example.com</td>
                            <td>Editor</td>
                            <td><span class="status-badge active">Active</span></td>
                        </tr>
                        <tr>
                            <td>Charlie Wilson</td>
                            <td>charlie@example.com</td>
                            <td>Viewer</td>
                            <td><span class="status-badge pending">Pending</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="mui-card dashboard-card">
            <div class="card-header">
                <h3>Recent Activity</h3>
            </div>
            <div class="activity-list">
                <div class="activity-item">
                    <span class="material-icons activity-icon">person_add</span>
                    <div class="activity-info">
                        <p class="activity-text">New user <strong>John Doe</strong> registered</p>
                        <span class="activity-time">2 minutes ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <span class="material-icons activity-icon">edit</span>
                    <div class="activity-info">
                        <p class="activity-text">Role <strong>Editor</strong> was updated</p>
                        <span class="activity-time">15 minutes ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <span class="material-icons activity-icon">delete</span>
                    <div class="activity-info">
                        <p class="activity-text">User <strong>Bob Johnson</strong> was deactivated</p>
                        <span class="activity-time">1 hour ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <span class="material-icons activity-icon">security</span>
                    <div class="activity-info">
                        <p class="activity-text">Role <strong>Moderator</strong> was created</p>
                        <span class="activity-time">3 hours ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <span class="material-icons activity-icon">settings</span>
                    <div class="activity-info">
                        <p class="activity-text">System settings were updated</p>
                        <span class="activity-time">5 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
