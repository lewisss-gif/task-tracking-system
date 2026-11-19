<?php
/**
 * Settings Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

// Handle settings save (preferences are stored in the session)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken()) {
        redirect('settings.php', 'error', 'Invalid or expired form submission. Please try again.');
    }

    if ($_POST['action'] === 'save_preferences') {
        $_SESSION['pref_tasks_per_page'] = max(5, min(100, (int)($_POST['tasks_per_page'] ?? 20)));
        $_SESSION['pref_default_task_filter'] = $_POST['default_task_filter'] ?? '';
        $_SESSION['pref_compact_view'] = isset($_POST['compact_view']) ? 1 : 0;

        logActivity($_SESSION['user_id'], 'updated_settings', 'user', $_SESSION['user_id']);
        redirect('settings.php', 'success', 'Settings saved successfully!');
    }
}

$tasksPerPage = $_SESSION['pref_tasks_per_page'] ?? 20;
$defaultTaskFilter = $_SESSION['pref_default_task_filter'] ?? '';
$compactView = $_SESSION['pref_compact_view'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-gear"></i> Settings</h2>
                <p class="text-muted">Manage your preferences and view system information</p>
            </div>
        </div>

        <?php echo displayFlashMessages(); ?>

        <div class="row g-4">
            <!-- Preferences -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-sliders"></i> Preferences</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="save_preferences">

                            <div class="mb-3">
                                <label class="form-label">Tasks per page</label>
                                <select class="form-select" name="tasks_per_page">
                                    <?php foreach ([10, 20, 50, 100] as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo $tasksPerPage === $option ? 'selected' : ''; ?>>
                                        <?php echo $option; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Default task filter</label>
                                <select class="form-select" name="default_task_filter">
                                    <option value="" <?php echo $defaultTaskFilter === '' ? 'selected' : ''; ?>>All Statuses</option>
                                    <?php foreach (['Pending', 'In Progress', 'Completed', 'Blocked'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $defaultTaskFilter === $status ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="compactView" name="compact_view"
                                       <?php echo $compactView ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="compactView">Compact table view</label>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Save Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account shortcuts -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-person-gear"></i> Account</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Manage your personal details and password from your profile page.</p>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-person"></i> Go to Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th style="width: 40%;">Application</th>
                                    <td><?php echo APP_NAME; ?></td>
                                </tr>
                                <tr>
                                    <th>Version</th>
                                    <td><?php echo APP_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <th>PHP Version</th>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <th>Database</th>
                                    <td>
                                        <?php
                                        try {
                                            $database = new Database();
                                            $db = $database->getConnection();
                                            echo '<span class="badge bg-success">Connected</span> ' . escape($db->query('SELECT DATABASE()')->fetchColumn());
                                        } catch (Exception $e) {
                                            echo '<span class="badge bg-danger">Connection failed</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Session Timeout</th>
                                    <td><?php echo round(SESSION_TIMEOUT / 60); ?> minutes</td>
                                </tr>
                                <tr>
                                    <th>Logged in as</th>
                                    <td><?php echo escape($_SESSION['full_name']); ?> (<?php echo escape($_SESSION['role']); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Login Time</th>
                                    <td><?php echo isset($_SESSION['login_time']) ? formatDateTime(date('Y-m-d H:i:s', $_SESSION['login_time'])) : 'N/A'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
