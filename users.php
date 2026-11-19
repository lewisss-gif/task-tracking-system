<?php
/**
 * User Management Page (Administrator only)
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('Administrator');

$database = new Database();
$db = $database->getConnection();
$current_user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken()) {
        redirect('users.php', 'error', 'Invalid or expired form submission. Please try again.');
    }

    // Create a new user
    if ($_POST['action'] === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);

        if (empty($username) || empty($email) || empty($fullName) || empty($password) || !$roleId) {
            redirect('users.php', 'error', 'Please fill in all required fields.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('users.php', 'error', 'Please enter a valid email address.');
        }

        if (strlen($password) < 8) {
            redirect('users.php', 'error', 'Password must be at least 8 characters long.');
        }

        // Validate role exists
        $roleCheck = $db->prepare("SELECT role_id FROM roles WHERE role_id = :role_id");
        $roleCheck->execute([':role_id' => $roleId]);
        if (!$roleCheck->fetch()) {
            redirect('users.php', 'error', 'Invalid role selected.');
        }

        $result = createUser($username, $email, $password, $fullName, $roleId);

        if ($result['success']) {
            logActivity($current_user_id, 'created_user', 'user', $result['user_id'], null, $username);
            redirect('users.php', 'success', 'User created successfully!');
        } else {
            redirect('users.php', 'error', $result['message']);
        }
    }

    // Activate / deactivate a user
    if ($_POST['action'] === 'toggle_status') {
        $targetId = (int)($_POST['user_id'] ?? 0);

        if ($targetId === $current_user_id) {
            redirect('users.php', 'error', 'You cannot deactivate your own account.');
        }

        $query = "UPDATE users SET is_active = NOT is_active WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $targetId]);

        logActivity($current_user_id, 'toggled_user_status', 'user', $targetId);
        redirect('users.php', 'success', 'User status updated.');
    }

    // Change a user's role
    if ($_POST['action'] === 'change_role') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($targetId === $current_user_id) {
            redirect('users.php', 'error', 'You cannot change your own role.');
        }

        $roleCheck = $db->prepare("SELECT role_id FROM roles WHERE role_id = :role_id");
        $roleCheck->execute([':role_id' => $roleId]);
        if (!$roleCheck->fetch()) {
            redirect('users.php', 'error', 'Invalid role selected.');
        }

        $query = "UPDATE users SET role_id = :role_id WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':role_id' => $roleId, ':user_id' => $targetId]);

        logActivity($current_user_id, 'changed_user_role', 'user', $targetId, null, (string)$roleId);
        redirect('users.php', 'success', 'User role updated.');
    }

    // Reset a user's password
    if ($_POST['action'] === 'reset_password') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if (strlen($newPassword) < 8) {
            redirect('users.php', 'error', 'Password must be at least 8 characters long.');
        }

        $query = "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':user_id' => $targetId
        ]);

        logActivity($current_user_id, 'reset_user_password', 'user', $targetId);
        redirect('users.php', 'success', 'Password reset successfully.');
    }
}

// Get all users with their roles
$query = "SELECT u.*, r.role_name
          FROM users u
          JOIN roles r ON u.role_id = r.role_id
          ORDER BY u.created_at ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll();

// Get roles for dropdowns
$rolesStmt = $db->prepare("SELECT * FROM roles ORDER BY role_id");
$rolesStmt->execute();
$roles = $rolesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people"></i> User Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="bi bi-person-plus"></i> New User
            </button>
        </div>

        <?php echo displayFlashMessages(); ?>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo escape($user['full_name']); ?></strong>
                                <br><small class="text-muted">@<?php echo escape($user['username']); ?></small>
                            </td>
                            <td><?php echo escape($user['email']); ?></td>
                            <td>
                                <?php if ($user['user_id'] == $current_user_id): ?>
                                    <span class="badge bg-dark"><?php echo escape($user['role_name']); ?></span>
                                <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <select name="role_id" class="form-select form-select-sm" style="width: auto;"
                                            onchange="this.form.submit()">
                                        <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>"
                                                <?php echo $user['role_id'] == $role['role_id'] ? 'selected' : ''; ?>>
                                            <?php echo escape($role['role_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" title="Reset Password"
                                            data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                            data-user-id="<?php echo $user['user_id']; ?>"
                                            data-user-name="<?php echo escape($user['full_name']); ?>">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <?php if ($user['user_id'] != $current_user_id): ?>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                                title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="bi bi-<?php echo $user['is_active'] ? 'person-x' : 'person-check'; ?>"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus"></i> Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" minlength="8" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['role_id']; ?>">
                                    <?php echo escape($role['role_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-key"></i> Reset Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reset password for <strong id="resetUserName"></strong>:</p>
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" class="form-control" name="new_password" minlength="8" required>
                            <small class="text-muted">Minimum 8 characters</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate the reset password modal
        document.getElementById('resetPasswordModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('resetUserId').value = button.getAttribute('data-user-id');
            document.getElementById('resetUserName').textContent = button.getAttribute('data-user-name');
        });
    </script>
</body>
</html>
