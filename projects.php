<?php
/**
 * Projects Management Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle project creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // All POST actions require a valid CSRF token
    if (!verifyCsrfToken()) {
        redirect('projects.php', 'error', 'Invalid or expired form submission. Please try again.');
    }

    if ($_POST['action'] === 'create' && hasAnyRole(['Administrator', 'Project Manager'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? '';
        $managerId = (int)($_POST['manager_id'] ?? 0);

        if (empty($title) || empty($startDate) || empty($endDate) || !$managerId) {
            redirect('projects.php', 'error', 'Please fill in all required fields.');
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            redirect('projects.php', 'error', 'End date cannot be before the start date.');
        }

        $validStatuses = [PROJECT_PLANNING, PROJECT_ACTIVE, PROJECT_ON_HOLD, PROJECT_COMPLETED, PROJECT_CANCELLED];
        if (!in_array($status, $validStatuses, true)) {
            redirect('projects.php', 'error', 'Invalid status selected.');
        }

        // Verify the manager exists
        $managerCheck = $db->prepare("SELECT user_id FROM users WHERE user_id = :manager_id AND is_active = 1");
        $managerCheck->execute([':manager_id' => $managerId]);
        if (!$managerCheck->fetch()) {
            redirect('projects.php', 'error', 'Invalid manager selected.');
        }

        try {
            $query = "INSERT INTO projects (title, description, start_date, end_date, manager_id, status)
                      VALUES (:title, :description, :start_date, :end_date, :manager_id, :status)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':manager_id' => $managerId,
                ':status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("Project creation error: " . $e->getMessage());
            redirect('projects.php', 'error', 'Failed to create project. Please try again.');
        }

        $projectId = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'created_project', 'project', $projectId, null, $title);

        redirect('projects.php', 'success', 'Project created successfully!');
    }

    if ($_POST['action'] === 'delete' && hasAnyRole(['Administrator', 'Project Manager'])) {
        $projectId = (int)($_POST['project_id'] ?? 0);

        $query = "DELETE FROM projects WHERE project_id = :project_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':project_id' => $projectId]);

        logActivity($_SESSION['user_id'], 'deleted_project', 'project', $projectId);
        redirect('projects.php', 'success', 'Project deleted successfully!');
    }
}

// Get all projects
$query = "SELECT p.*, u.full_name as manager_name 
          FROM projects p 
          JOIN users u ON p.manager_id = u.user_id 
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$projects = $stmt->fetchAll();

// Get possible managers for the dropdown (Administrators and Project Managers)
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
    <title>Projects - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-folder"></i> Projects</h2>
            <?php if (hasAnyRole(['Administrator', 'Project Manager'])): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                <i class="bi bi-plus-circle"></i> New Project
            </button>
            <?php endif; ?>
        </div>
        
        <?php echo displayFlashMessages(); ?>
        
        <div class="row g-4">
            <?php if (empty($projects)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> No projects found. Create your first project!
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($projects as $project): 
                    $progress = calculateProjectProgress($project['project_id']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?php echo escape($project['title']); ?></h5>
                                <?php echo getStatusBadge($project['status']); ?>
                            </div>
                            <p class="card-text text-muted small">
                                <?php echo escape(substr($project['description'] ?? '', 0, 100)) . (strlen($project['description'] ?? '') > 100 ? '...' : ''); ?>
                            </p>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $progress; ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo getProgressColor($progress); ?>" 
                                         style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="small text-muted">
                                <div><i class="bi bi-person"></i> <?php echo escape($project['manager_name']); ?></div>
                                <div><i class="bi bi-calendar"></i> <?php echo formatDate($project['start_date']); ?> - <?php echo formatDate($project['end_date']); ?></div>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="btn-group w-100">
                                <a href="project_detail.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <?php if (hasAnyRole(['Administrator', 'Project Manager'])): ?>
                                <a href="edit_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $project['project_id']; ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Project Modal -->
    <div class="modal fade" id="createProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Project Title *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date *</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Project Manager *</label>
                            <select class="form-select" name="manager_id" required>
                                <?php foreach ($managers as $manager): ?>
                                <option value="<?php echo $manager['user_id']; ?>"
                                        <?php echo $manager['user_id'] == $_SESSION['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($manager['full_name']); ?> (<?php echo escape($manager['role_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="Planning">Planning</option>
                                <option value="Active" selected>Active</option>
                                <option value="On Hold">On Hold</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="project_id" id="deleteProjectId">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(projectId) {
            if (confirm('Are you sure you want to delete this project? All tasks and milestones will also be deleted.')) {
                document.getElementById('deleteProjectId').value = projectId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
