<?php
/**
 * Project Detail Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$canManage = hasAnyRole(['Administrator', 'Project Manager']);

$project_id = (int)($_GET['id'] ?? 0);

// Fetch the project
$query = "SELECT p.*, u.full_name as manager_name
          FROM projects p
          JOIN users u ON p.manager_id = u.user_id
          WHERE p.project_id = :project_id";
$stmt = $db->prepare($query);
$stmt->execute([':project_id' => $project_id]);
$project = $stmt->fetch();

if (!$project) {
    redirect('projects.php', 'error', 'Project not found.');
}

// Handle form submissions (managers/admins only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $canManage) {
    if (!verifyCsrfToken()) {
        redirect('project_detail.php?id=' . $project_id, 'error', 'Invalid or expired form submission. Please try again.');
    }

    // Add a milestone
    if ($_POST['action'] === 'add_milestone') {
        $title = trim($_POST['title'] ?? '');
        $dueDate = $_POST['due_date'] ?? '';

        if (empty($title) || empty($dueDate)) {
            redirect('project_detail.php?id=' . $project_id, 'error', 'Milestone title and due date are required.');
        }

        $query = "INSERT INTO milestones (project_id, title, description, due_date)
                  VALUES (:project_id, :title, :description, :due_date)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':project_id' => $project_id,
            ':title' => $title,
            ':description' => trim($_POST['description'] ?? ''),
            ':due_date' => $dueDate
        ]);

        logActivity($user_id, 'created_milestone', 'project', $project_id, null, $title);
        redirect('project_detail.php?id=' . $project_id, 'success', 'Milestone added successfully!');
    }

    // Update milestone status
    if ($_POST['action'] === 'update_milestone') {
        $milestoneId = (int)($_POST['milestone_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $validStatuses = ['Pending', 'In Progress', 'Completed', 'Overdue'];

        if (in_array($status, $validStatuses, true)) {
            $query = "UPDATE milestones SET status = :status
                      WHERE milestone_id = :milestone_id AND project_id = :project_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':status' => $status,
                ':milestone_id' => $milestoneId,
                ':project_id' => $project_id
            ]);

            logActivity($user_id, 'updated_milestone', 'milestone', $milestoneId, null, $status);
            redirect('project_detail.php?id=' . $project_id, 'success', 'Milestone updated!');
        }
    }
}

// Milestones
$milestonesQuery = "SELECT * FROM milestones WHERE project_id = :project_id ORDER BY due_date ASC";
$milestonesStmt = $db->prepare($milestonesQuery);
$milestonesStmt->execute([':project_id' => $project_id]);
$milestones = $milestonesStmt->fetchAll();

// Tasks
$tasksQuery = "SELECT t.*, u.full_name as assignee_name, m.title as milestone_title
               FROM tasks t
               LEFT JOIN users u ON t.assigned_to = u.user_id
               LEFT JOIN milestones m ON t.milestone_id = m.milestone_id
               WHERE t.project_id = :project_id
               ORDER BY t.due_date ASC";
$tasksStmt = $db->prepare($tasksQuery);
$tasksStmt->execute([':project_id' => $project_id]);
$tasks = $tasksStmt->fetchAll();

$progress = calculateProjectProgress($project_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($project['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-folder"></i> <?php echo escape($project['title']); ?></h2>
                <p class="text-muted mb-0">
                    Managed by <?php echo escape($project['manager_name']); ?> &middot;
                    <?php echo formatDate($project['start_date']); ?> &ndash; <?php echo formatDate($project['end_date']); ?>
                    &middot; <?php echo getStatusBadge($project['status']); ?>
                </p>
            </div>
            <div>
                <?php if ($canManage): ?>
                <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <?php endif; ?>
                <a href="projects.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php echo displayFlashMessages(); ?>

        <div class="row g-4">
            <!-- Overview -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase small">Description</h6>
                        <p><?php echo nl2br(escape($project['description'] ?? '')) ?: '<span class="text-muted">No description provided.</span>'; ?></p>

                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Overall Progress</span>
                            <span><?php echo $progress; ?>%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar <?php echo getProgressColor($progress); ?>"
                                 style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Milestones -->
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-flag"></i> Milestones</h5>
                        <?php if ($canManage): ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
                            <i class="bi bi-plus"></i> Add
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($milestones)): ?>
                            <p class="text-muted text-center py-4 mb-0">No milestones yet</p>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($milestones as $milestone):
                                $mOverdue = strtotime($milestone['due_date']) < strtotime('today') && $milestone['status'] !== 'Completed';
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo escape($milestone['title']); ?></h6>
                                        <small class="<?php echo $mOverdue ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                            <i class="bi bi-calendar"></i> <?php echo formatDate($milestone['due_date']); ?>
                                        </small>
                                    </div>
                                    <?php if ($canManage): ?>
                                    <form method="POST" class="d-inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="update_milestone">
                                        <input type="hidden" name="milestone_id" value="<?php echo $milestone['milestone_id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: auto;"
                                                onchange="this.form.submit()">
                                            <?php foreach (['Pending', 'In Progress', 'Completed', 'Overdue'] as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo $milestone['status'] === $status ? 'selected' : ''; ?>>
                                                <?php echo $status; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <?php else: ?>
                                    <?php echo getStatusBadge($milestone['status']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tasks -->
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-list-task"></i> Tasks (<?php echo count($tasks); ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Task</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No tasks in this project yet</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($tasks as $task):
                                        $tOverdue = $task['due_date'] && strtotime($task['due_date']) < strtotime('today') && $task['status'] !== 'Completed';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo escape($task['title']); ?></strong>
                                            <?php if ($task['milestone_title']): ?>
                                            <br><small class="text-muted"><i class="bi bi-flag"></i> <?php echo escape($task['milestone_title']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo escape($task['assignee_name'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo getPriorityBadge($task['priority']); ?></td>
                                        <td>
                                            <?php echo getStatusBadge($task['status']); ?>
                                            <br><small><?php echo getAssignmentBadge($task['assignment_status'] ?? ASSIGNMENT_PENDING); ?></small>
                                        </td>
                                        <td class="<?php echo $tOverdue ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo formatDate($task['due_date']); ?>
                                        </td>
                                        <td>
                                            <a href="task_detail.php?id=<?php echo $task['task_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Milestone Modal -->
    <?php if ($canManage): ?>
    <div class="modal fade" id="addMilestoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="add_milestone">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-flag"></i> Add Milestone</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date *</label>
                            <input type="date" class="form-control" name="due_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Milestone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
