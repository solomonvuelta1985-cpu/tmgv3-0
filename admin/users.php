<?php
/**
 * User Management Page
 * Admins can create, edit, delete, and manage user accounts
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication and check session timeout
require_admin();
check_session_timeout();

$page_title = "User Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-users.css">

    <!-- Application Configuration - MUST be loaded before other JS files -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../public/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
                <div class="page-header">
                    <h2><?php echo $page_title; ?></h2>
                    <button class="btn btn-primary" onclick="showCreateForm()">
                        <i class="fas fa-plus"></i> Create New User
                    </button>
                </div>

                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                        <?php
                        echo $_SESSION['flash_message'];
                        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Create/Edit User Form (Initially Hidden) -->
                <div id="userFormCard" class="card" style="display: none;">
                    <div class="card-header">
                        <h5 id="formTitle">Create New User</h5>
                    </div>
                    <div class="card-body">
                        <form id="userForm">
                            <input type="hidden" id="user_id" name="user_id">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username"
                                           pattern="[a-zA-Z0-9_\-]{3,20}" required
                                           title="3-20 characters: letters, numbers, underscore, dash only">
                                    <small class="text-muted">3-20 characters (letters, numbers, _, -)</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password <span id="passwordRequired">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password"
                                           minlength="8">
                                    <small class="text-muted">Min 8 characters, must include letters and numbers</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin">Admin - Full Access</option>
                                        <option value="enforcer">Enforcer - Creates Citations</option>
                                        <option value="cashier">Cashier - Processes Payments</option>
                                        <option value="lto_staff">LTO Staff - Read-Only LTO Search</option>
                                        <option value="user">User - View Only</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3" id="statusField" style="display: none;">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save User
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelForm()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card">
                    <div class="card-header">
                        <h5>Search & Filters</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="searchInput"
                                       placeholder="Search username, name, or email...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="roleFilter">
                                    <option value="">All Roles</option>
                                    <option value="admin">Admin</option>
                                    <option value="enforcer">Enforcer</option>
                                    <option value="cashier">Cashier</option>
                                    <option value="lto_staff">LTO Staff</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" onclick="loadUsers()">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User List Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>User Accounts</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="userTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <i class="fas fa-spinner fa-spin"></i> Loading users...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="resetPasswordForm">
                    <div class="modal-body">
                        <input type="hidden" id="reset_user_id" name="user_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <p>Reset password for: <strong id="reset_username"></strong></p>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password *</label>
                            <input type="password" class="form-control" id="new_password"
                                   name="new_password" minlength="8" required>
                            <small class="text-muted">Min 8 characters, must include letters and numbers</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/tmg/assets/js/user-management.js"></script>
</body>
</html>
