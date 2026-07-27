<div class="user-management">
    <div class="section-header">
        <h2>User Management</h2>
        <div class="header-actions">
            <div class="search-box">
                <span class="material-icons">search</span>
                <input type="text" class="mui-input" id="searchUsers" placeholder="Search users...">
            </div>
            <button class="mui-btn mui-btn-contained" onclick="alert('Add User modal would open')">
                <span class="material-icons">person_add</span>
                Add User
            </button>
        </div>
    </div>

    <div class="mui-table-container">
        <table class="mui-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>#001</td>
                    <td>
                        <div class="user-cell">
                            <span class="material-icons user-avatar">account_circle</span>
                            <span>John Doe</span>
                        </div>
                    </td>
                    <td>john@example.com</td>
                    <td>Administrator</td>
                    <td><span class="status-badge active">Active</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="mui-btn mui-btn-outlined mui-btn-sm" title="Edit">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="mui-btn mui-btn-danger mui-btn-sm" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>#002</td>
                    <td>
                        <div class="user-cell">
                            <span class="material-icons user-avatar">account_circle</span>
                            <span>Jane Smith</span>
                        </div>
                    </td>
                    <td>jane@example.com</td>
                    <td>Editor</td>
                    <td><span class="status-badge active">Active</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="mui-btn mui-btn-outlined mui-btn-sm" title="Edit">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="mui-btn mui-btn-danger mui-btn-sm" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>#003</td>
                    <td>
                        <div class="user-cell">
                            <span class="material-icons user-avatar">account_circle</span>
                            <span>Bob Johnson</span>
                        </div>
                    </td>
                    <td>bob@example.com</td>
                    <td>Viewer</td>
                    <td><span class="status-badge inactive">Inactive</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="mui-btn mui-btn-outlined mui-btn-sm" title="Edit">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="mui-btn mui-btn-danger mui-btn-sm" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>#004</td>
                    <td>
                        <div class="user-cell">
                            <span class="material-icons user-avatar">account_circle</span>
                            <span>Alice Brown</span>
                        </div>
                    </td>
                    <td>alice@example.com</td>
                    <td>Editor</td>
                    <td><span class="status-badge active">Active</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="mui-btn mui-btn-outlined mui-btn-sm" title="Edit">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="mui-btn mui-btn-danger mui-btn-sm" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>#005</td>
                    <td>
                        <div class="user-cell">
                            <span class="material-icons user-avatar">account_circle</span>
                            <span>Charlie Wilson</span>
                        </div>
                    </td>
                    <td>charlie@example.com</td>
                    <td>Viewer</td>
                    <td><span class="status-badge pending">Pending</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="mui-btn mui-btn-outlined mui-btn-sm" title="Edit">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="mui-btn mui-btn-danger mui-btn-sm" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
