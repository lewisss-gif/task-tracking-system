<?php
/**
 * Edit Project Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAnyRole(['Administrator', 'Project Manager']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$project_id = (int)($_GET['id'] ?? 0);

// Fetch the project
$query = "SELECT * FROM projects WHERE project_id = :project_id";
$stmt = $db->prepare($query);
$stmt->execute([':project_id' => $project_id]);
$project = $stmt->fetch();

if (!$project) {
    redirect('projects.php', 'error', 'Project not found.');
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verifyCsrfToken()) {
        redirect('edit_project.php?id=' . $project_id, 'error', 'Invalid or expired form submission. Please try again.');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $managerId = (int)($_POST['manager_id'] ?? 0);

    if (empty($title) || empty($startDate) || empty($endDate) || !$managerId) {
        redirect('edit_project.php?id=' . $project_id, 'error', 'Please fill in all required fields.');
    }

    if (strtotime($endDate) < strtotime($startDate)) {
        redirect('edit_project.php?id=' . $project_id, 'error', 'End date cannot be before the start date.');
    }

    $validStatuses = ['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled'];
    if (!in_array($status, $validStatuses, true)) {
        redirect('edit_project.php?id=' . $project_id, 'error', 'Invalid status selected.');
    }

    // Verify the selected manager exists
    $managerCheck = $db->prepare("SELECT user_id FROM users WHERE user_id = :manager_id AND is_active = 1");
    $managerCheck->execute([':manager_id' => $managerId]);
    if (!$managerCheck->fetch()) {
        redirect('edit_project.php?id=' . $project_id, 'error', 'Invalid manager selected.');
    }

    $query = "UPDATE projects
              SET title = :title, description = :description, start_date = :start_date,
                  end_date = :end_date, status = :status, manager_id = :manager_id
              WHERE project_id = :project_id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':status' => $status,
        ':manager_id' => $managerId,
        ':project_id' => $project_id
    ]);

    logActivity($user_id, 'updated_project', 'project', $project_id, $project['title'], $title);
    redirect('project_detail.php?id=' . $project_id, 'success', 'Project updated successfully!');
}

// Get possible managers (Administrators and Project Managers)
$managersQuery = "SELECT u.user_id, u.full_name, r.role_name
                  FROM users u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE r.role_name IN ('Administrator', 'Project Manager') AND u.is_active = 1
                  ORDER BY u.full_name";
$managersStmt = $db->prepare($managersQuery);
$managersStmt->execute();
$managers = $managersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil"></i> Edit Project</h2>
            <a href="project_detail.php?id=<?php echo $project_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Project
            </a>
        </div>

        <?php echo displayFlashMessages(); ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="update">

                    <div class="mb-3">
                        <label class="form-label">Project Title *</label>
                        <input type="text" class="form-control" name="title"
                               value="<?php echo escape($project['title']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4"><?php echo escape($project['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date"
                                   value="<?php echo escape($project['start_date']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date"
                                   value="<?php echo escape($project['end_date']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manager *</label>
                            <select class="form-select" name="manager_id" required>
                                <?php foreach ($managers as $manager): ?>
                                <option value="<?php echo $manager['user_id']; ?>"
                                        <?php echo $project['manager_id'] == $manager['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($manager['full_name']); ?> (<?php echo escape($manager['role_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <?php foreach (['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled'] as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $project['status'] === $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
