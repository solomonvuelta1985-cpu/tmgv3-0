/**
 * User Management JavaScript
 * Handles CRUD operations for user management
 */

let resetPasswordModal;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal
    resetPasswordModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));

    // Load users on page load
    loadUsers();

    // User form submit
    document.getElementById('userForm').addEventListener('submit', handleUserSubmit);

    // Reset password form submit
    document.getElementById('resetPasswordForm').addEventListener('submit', handlePasswordReset);

    // Enter key in search triggers filter
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            loadUsers();
        }
    });
});

/**
 * Load users from API with optional filters
 */
function loadUsers() {
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;

    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (role) params.append('role', role);
    if (status) params.append('status', status);

    fetch(buildApiUrl(`api/user_list.php?${params}`))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderUserTable(data.users);
            } else {
                showAlert('Error loading users: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error loading users: ' + error.message, 'danger');
        });
}

/**
 * Render user table
 */
function renderUserTable(users) {
    const tbody = document.getElementById('userTableBody');

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No users found</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${escapeHtml(user.username)}</td>
            <td>${escapeHtml(user.full_name)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td><span class="badge role-badge bg-${getRoleBadgeColor(user.role)}">${user.role.toUpperCase()}</span></td>
            <td><span class="badge status-badge bg-${getStatusBadgeColor(user.status)}">${user.status}</span></td>
            <td>${user.last_login ? formatDateTime(user.last_login) : 'Never'}</td>
            <td>${formatDate(user.created_at)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="editUser(${user.user_id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="showResetPassword(${user.user_id}, '${escapeHtml(user.username)}')" title="Reset Password">
                        <i class="fas fa-key"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.user_id}, '${escapeHtml(user.username)}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Show create user form
 */
function showCreateForm() {
    document.getElementById('formTitle').textContent = 'Create New User';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('username').readOnly = false;
    document.getElementById('password').required = true;
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('statusField').style.display = 'none';
    document.getElementById('userFormCard').style.display = 'block';
    document.getElementById('username').focus();
}

/**
 * Edit user
 */
function editUser(userId) {
    fetch(buildApiUrl(`api/user_list.php?user_id=${userId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users.length > 0) {
                const user = data.users[0];

                document.getElementById('formTitle').textContent = 'Edit User';
                document.getElementById('user_id').value = user.user_id;
                document.getElementById('username').value = user.username;
                document.getElementById('username').readOnly = true; // Can't change username
                document.getElementById('password').value = '';
                document.getElementById('password').required = false;
                document.getElementById('passwordRequired').style.display = 'none';
                document.getElementById('full_name').value = user.full_name;
                document.getElementById('email').value = user.email;
                document.getElementById('role').value = user.role;
                document.getElementById('status').value = user.status;
                document.getElementById('statusField').style.display = 'block';
                document.getElementById('userFormCard').style.display = 'block';

                document.getElementById('full_name').focus();
            }
        })
        .catch(error => {
            showAlert('Error loading user: ' + error.message, 'danger');
        });
}

/**
 * Cancel form
 */
function cancelForm() {
    document.getElementById('userFormCard').style.display = 'none';
    document.getElementById('userForm').reset();
}

/**
 * Handle user form submission
 */
function handleUserSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    fetch(buildApiUrl('api/user_save.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            cancelForm();
            loadUsers();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error saving user: ' + error.message, 'danger');
    });
}

/**
 * Delete user with confirmation
 */
function deleteUser(userId, username) {
    if (!confirm(`Are you sure you want to delete user "${username}"?\n\nThis action cannot be undone.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    fetch(buildApiUrl('api/user_delete.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadUsers();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error deleting user: ' + error.message, 'danger');
    });
}

/**
 * Show reset password modal
 */
function showResetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('new_password').value = '';
    resetPasswordModal.show();
}

/**
 * Handle password reset
 */
function handlePasswordReset(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    fetch(buildApiUrl('api/user_reset_password.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            resetPasswordModal.hide();
            document.getElementById('resetPasswordForm').reset();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error resetting password: ' + error.message, 'danger');
    });
}

/**
 * Utility functions
 */
function getRoleBadgeColor(role) {
    const colors = {
        'admin': 'danger',
        'enforcer': 'primary',
        'cashier': 'success',
        'user': 'secondary'
    };
    return colors[role] || 'secondary';
}

function getStatusBadgeColor(status) {
    const colors = {
        'active': 'success',
        'inactive': 'secondary',
        'suspended': 'danger'
    };
    return colors[status] || 'secondary';
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
