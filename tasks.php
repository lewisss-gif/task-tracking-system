<?php
/**
 * Tasks Management Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create' && hasAnyRole(['Administrator', 'Project Manager'])) {
        $query = "INSERT INTO tasks (project_id, milestone_id, title, description, priority, assigned_to, created_by, due_date) 
                  VALUES (:project_id, :milestone_id, :title, :description, :priority, :assigned_to, :created_by, :due_date)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':project_id' => $_POST['project_id'],
            ':milestone_id' => !empty($_POST['milestone_id']) ? $_POST['milestone_id'] : null,
            ':title' => $_POST['title'],
            ':description' => $_POST['description'],
            ':priority' => $_POST['priority'],
            ':assigned_to' => !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
            ':created_by' => $user_id,
            ':due_date' => $_POST['due_date']
        ]);
        
        $taskId = $db->lastInsertId();
        
        // Send notification to assigned user
        if (!empty($_POST['assigned_to'])) {
            createNotification(
                $_POST['assigned_to'],
                "You have been assigned a new task: " . $_POST['title'],
                'Task Assigned',
                $taskId,
                'task'
            );
        }
        
        logActivity($user_id, 'created_task', 'task', $taskId, null, $_POST['title']);
        redirect('tasks.php', 'success', 'Task created successfully!');
    }
    
    if ($_POST['action'] === 'update_status') {
        // Verify user owns the task or is admin/manager
        $checkQuery = "SELECT assigned_to, created_by FROM tasks WHERE task_id = :task_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([':task_id' => $_POST['task_id']]);
        $task = $checkStmt->fetch();
        
        if ($task && ($task['assigned_to'] == $user_id || hasAnyRole(['Administrator', 'Project Manager']))) {
            $oldStatus = $_POST['old_status'] ?? '';
            
            $query = "UPDATE tasks SET status = :status, completed_at = :completed_at WHERE task_id = :task_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':status' => $_POST['status'],
                ':completed_at' => $_POST['status'] === 'Completed' ? date('Y-m-d H:i:s') : null,
                ':task_id' => $_POST['task_id']
            ]);
            
            logActivity($user_id, 'updated_status', 'task', $_POST['task_id'], $oldStatus, $_POST['status']);
            redirect('tasks.php', 'success', 'Task status updated!');
        }
    }
}

// Build query based on role
if ($role === 'Team Member') {
    $query = "SELECT t.*, p.title as project_title, u.full_name as assignee_name,
              m.title as milestone_title
              FROM tasks t 
              JOIN projects p ON t.project_id = p.project_id 
              LEFT JOIN users u ON t.assigned_to = u.user_id 
              LEFT JOIN milestones m ON t.milestone_id = m.milestone_id 
              WHERE t.assigned_to = :user_id 
              ORDER BY 
                CASE t.status 
                    WHEN 'Blocked' THEN 1 
                    WHEN 'In Progress' THEN 2 
                    WHEN 'Pending' THEN 3 
                    WHEN 'Completed' THEN 4 
                END,
                t.due_date ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
} else {
    $query = "SELECT t.*, p.title as project_title, u.full_name as assignee_name,
              m.title as milestone_title
              FROM tasks t 
              JOIN projects p ON t.project_id = p.project_id 
              LEFT JOIN users u ON t.assigned_to = u.user_id 
              LEFT JOIN milestones m ON t.milestone_id = m.milestone_id 
              ORDER BY 
                CASE t.status 
                    WHEN 'Blocked' THEN 1 
                    WHEN 'In Progress' THEN 2 
                    WHEN 'Pending' THEN 3 
                    WHEN 'Completed' THEN 4 
                END,
                t.due_date ASC";
    $stmt = $db->prepare($query);
}

$stmt->execute();
$tasks = $stmt->fetchAll();

// Get projects and users for dropdowns
$projectsQuery = "SELECT project_id, title FROM projects WHERE status != 'Cancelled' ORDER BY title";
$projectsStmt = $db->prepare($projectsQuery);
$projectsStmt->execute();
$projects = $projectsStmt->fetchAll();

$users = getUsersByRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-list-task"></i> <?php echo $role === 'Team Member' ? 'My Tasks' : 'All Tasks'; ?></h2>
            <?php if (hasAnyRole(['Administrator', 'Project Manager'])): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="bi bi-plus-circle"></i> New Task
            </button>
            <?php endif; ?>
        </div>
        
        <?php echo displayFlashMessages(); ?>
        
        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search tasks...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Blocked">Blocked</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="priorityFilter">
                            <option value="">All Priorities</option>
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                            <i class="bi bi-arrow-clockwise"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tasks Table -->
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tasksTable">
                    <thead class="table-light">
                        <tr>
                            <th>Task</th>
                            <th>Project</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No tasks found
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): 
                                $isOverdue = $task['due_date'] && strtotime($task['due_date']) < time() && $task['status'] !== 'Completed';
                            ?>
                            <tr class="task-row" 
                                data-status="<?php echo $task['status']; ?>"
                                data-priority="<?php echo $task['priority']; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo escape($task['title']); ?></strong>
                                        <?php if ($task['milestone_title']): ?>
                                        <br><small class="text-muted"><i class="bi bi-flag"></i> <?php echo escape($task['milestone_title']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo escape($task['project_title']); ?></td>
                                <td><?php echo escape($task['assignee_name'] ?? 'Unassigned'); ?></td>
                                <td><?php echo getPriorityBadge($task['priority']); ?></td>
                                <td>
                                    <?php if ($task['assigned_to'] == $user_id || hasAnyRole(['Administrator', 'Project Manager'])): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                        <input type="hidden" name="old_status" value="<?php echo $task['status']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: auto; display: inline-block;" 
                                                onchange="this.form.submit()">
                                            <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Blocked" <?php echo $task['status'] === 'Blocked' ? 'selected' : ''; ?>>Blocked</option>
                                        </select>
                                    </form>
                                    <?php else: ?>
                                    <?php echo getStatusBadge($task['status']); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo formatDate($task['due_date']); ?>
                                    <?php if ($isOverdue): ?>
                                    <br><small><i class="bi bi-exclamation-triangle"></i> Overdue</small>
                                    <?php endif; ?>
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
    
    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Task Title *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project *</label>
                                <select class="form-select" name="project_id" id="projectSelect" required>
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['project_id']; ?>">
                                        <?php echo escape($project['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Milestone</label>
                                <select class="form-select" name="milestone_id" id="milestoneSelect">
                                    <option value="">Select Milestone (Optional)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assign To</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['user_id']; ?>">
                                        <?php echo escape($u['full_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date *</label>
                                <input type="date" class="form-control" name="due_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        function filterTasks() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const priority = document.getElementById('priorityFilter').value;
            
            document.querySelectorAll('.task-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowStatus = row.dataset.status;
                const rowPriority = row.dataset.priority;
                
                const matchesSearch = !search || text.includes(search);
                const matchesStatus = !status || rowStatus === status;
                const matchesPriority = !priority || rowPriority === priority;
                
                row.style.display = (matchesSearch && matchesStatus && matchesPriority) ? '' : 'none';
            });
        }
        
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('priorityFilter').value = '';
            filterTasks();
        }
        
        document.getElementById('searchInput').addEventListener('keyup', filterTasks);
        document.getElementById('statusFilter').addEventListener('change', filterTasks);
        document.getElementById('priorityFilter').addEventListener('change', filterTasks);
        
        // Load milestones when project is selected
        document.getElementById('projectSelect')?.addEventListener('change', function() {
            const projectId = this.value;
            const milestoneSelect = document.getElementById('milestoneSelect');
            
            milestoneSelect.innerHTML = '<option value="">Select Milestone (Optional)</option>';
            
            if (projectId) {
                fetch('api/get_milestones.php?project_id=' + projectId)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(milestone => {
                            const option = document.createElement('option');
                            option.value = milestone.milestone_id;
                            option.textContent = milestone.title;
                            milestoneSelect.appendChild(option);
                        });
                    });
            }
        });
    </script>
</body>
</html>
