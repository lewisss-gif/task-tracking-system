<?php
/**
 * User Profile Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken()) {
        redirect('profile.php', 'error', 'Invalid or expired form submission. Please try again.');
    }

    // Update profile information
    if ($_POST['action'] === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($fullName) || empty($email)) {
            redirect('profile.php', 'error', 'Full name and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('profile.php', 'error', 'Please enter a valid email address.');
        }

        // Make sure the email is not used by another account
        $checkQuery = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([':email' => $email, ':user_id' => $user_id]);

        if ($checkStmt->fetch()) {
            redirect('profile.php', 'error', 'That email address is already in use by another account.');
        }

        $query = "UPDATE users SET full_name = :full_name, email = :email WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':user_id' => $user_id
        ]);

        // Keep session data in sync
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email'] = $email;

        logActivity($user_id, 'updated_profile', 'user', $user_id, null, $fullName);
        redirect('profile.php', 'success', 'Profile updated successfully!');
    }

    // Change password
    if ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            redirect('profile.php', 'error', 'Please fill in all password fields.');
        }

        if (strlen($newPassword) < 8) {
            redirect('profile.php', 'error', 'New password must be at least 8 characters long.');
        }

        if ($newPassword !== $confirmPassword) {
            redirect('profile.php', 'error', 'New password and confirmation do not match.');
        }

        // Verify current password
        $query = "SELECT password_hash FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            redirect('profile.php', 'error', 'Your current password is incorrect.');
        }

        $query = "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':user_id' => $user_id
        ]);

        logActivity($user_id, 'changed_password', 'user', $user_id);
        redirect('profile.php', 'success', 'Password changed successfully!');
    }
}

// Get fresh user data
$user = getCurrentUser();

// The account may have been deactivated/deleted mid-session
if (!$user) {
    logoutUser();
    header("Location: " . BASE_URL . "login.php?error=login_required");
    exit();
}

// Task statistics for this user
$statsQuery = "SELECT
                 COUNT(*) as total_tasks,
                 SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                 SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                 SUM(CASE WHEN status != 'Completed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks
               FROM tasks
               WHERE assigned_to = :user_id";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute([':user_id' => $user_id]);
$stats = $statsStmt->fetch();

// Recent activity by this user
$activityQuery = "SELECT * FROM activity_logs
                  WHERE user_id = :user_id
                  ORDER BY created_at DESC
                  LIMIT 8";
$activityStmt = $db->prepare($activityQuery);
$activityStmt->execute([':user_id' => $user_id]);
$recentActivity = $activityStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-person"></i> My Profile</h2>
                <p class="text-muted">View and manage your account information</p>
            </div>
        </div>

        <?php echo displayFlashMessages(); ?>

        <div class="row g-4">
            <!-- Profile Summary -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                             style="width: 90px; height: 90px; font-size: 2.5rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <h4 class="mb-1"><?php echo escape($user['full_name']); ?></h4>
                        <p class="text-muted mb-2">@<?php echo escape($user['username']); ?></p>
                        <span class="badge bg-dark"><?php echo escape($user['role_name']); ?></span>
                        <hr>
                        <div class="text-start small">
                            <p class="mb-2"><i class="bi bi-envelope text-muted"></i> <?php echo escape($user['email']); ?></p>
                            <p class="mb-2"><i class="bi bi-calendar-plus text-muted"></i> Member since <?php echo formatDate($user['created_at']); ?></p>
                            <p class="mb-0">
                                <i class="bi bi-circle-fill <?php echo $user['is_active'] ? 'text-success' : 'text-danger'; ?>" style="font-size: 0.6rem;"></i>
                                <?php echo $user['is_active'] ? 'Active account' : 'Inactive account'; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Task Statistics -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-bar-chart"></i> My Task Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Assigned to me</span>
                            <strong><?php echo $stats['total_tasks'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Completed</span>
                            <strong class="text-success"><?php echo $stats['completed_tasks'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>In Progress</span>
                            <strong class="text-info"><?php echo $stats['in_progress_tasks'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Overdue</span>
                            <strong class="text-danger"><?php echo $stats['overdue_tasks'] ?? 0; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile & Password -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name"
                                           value="<?php echo escape($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo escape($user['username']); ?>" disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?php echo escape($user['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?php echo escape($user['role_name']); ?>" disabled>
                                <small class="text-muted">Only an administrator can change roles</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password"
                                           minlength="8" required>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password"
                                           minlength="8" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-activity"></i> My Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivity)): ?>
                            <p class="text-muted text-center py-3 mb-0">No recent activity</p>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="bi bi-<?php
                                            echo strpos($activity['action'], 'create') !== false ? 'plus-circle' :
                                                (strpos($activity['action'], 'update') !== false || strpos($activity['action'], 'change') !== false ? 'pencil' : 'activity');
                                        ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="mb-1">
                                        <?php echo escape(str_replace('_', ' ', $activity['action'])); ?>
                                        a <?php echo escape($activity['entity_type']); ?>
                                        <?php if ($activity['new_value']): ?>
                                            — <strong><?php echo escape($activity['new_value']); ?></strong>
                                        <?php endif; ?>
                                    </p>
                                    <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
