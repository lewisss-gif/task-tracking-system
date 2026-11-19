<?php
/**
 * Task Detail Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$task_id = (int)($_GET['id'] ?? 0);

// Fetch the task
$query = "SELECT t.*, p.title as project_title, p.manager_id,
          m.title as milestone_title,
          assignee.full_name as assignee_name,
          creator.full_name as creator_name
          FROM tasks t
          JOIN projects p ON t.project_id = p.project_id
          LEFT JOIN milestones m ON t.milestone_id = m.milestone_id
          LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
          JOIN users creator ON t.created_by = creator.user_id
          WHERE t.task_id = :task_id";
$stmt = $db->prepare($query);
$stmt->execute([':task_id' => $task_id]);
$task = $stmt->fetch();

if (!$task) {
    redirect('tasks.php', 'error', 'Task not found.');
}

// Team Members may only view their own tasks
if ($role === 'Team Member' && $task['assigned_to'] != $user_id && $task['created_by'] != $user_id) {
    redirect('tasks.php', 'error', 'You are not authorized to view this task.');
}

$canManage = hasAnyRole(['Administrator', 'Project Manager']);
$canUpdate = $canManage || $task['assigned_to'] == $user_id;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken()) {
        redirect('task_detail.php?id=' . $task_id, 'error', 'Invalid or expired form submission. Please try again.');
    }

    // Accept, flag as pending, or reject an assignment
    if ($_POST['action'] === 'respond') {
        $response = $_POST['response'] ?? '';
        $reason = $_POST['reason'] ?? '';

        $result = respondToTaskAssignment($task_id, $user_id, $response, $reason);
        redirect('task_detail.php?id=' . $task_id, $result['success'] ? 'success' : 'error', $result['message']);
    }

    // Update status
    if ($_POST['action'] === 'update_status' && $canUpdate) {
        $newStatus = $_POST['status'] ?? '';
        $validStatuses = ['Pending', 'In Progress', 'Completed', 'Blocked'];

        if (in_array($newStatus, $validStatuses, true)) {
            $query = "UPDATE tasks SET status = :status, completed_at = :completed_at WHERE task_id = :task_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':status' => $newStatus,
                ':completed_at' => $newStatus === 'Completed' ? date('Y-m-d H:i:s') : null,
                ':task_id' => $task_id
            ]);

            // Notify the assignee when someone else changes the status
            if ($task['assigned_to'] && $task['assigned_to'] != $user_id) {
                createNotification(
                    $task['assigned_to'],
                    "Status of task '{$task['title']}' changed to {$newStatus}",
                    'Status Update',
                    $task_id,
                    'task'
                );
            }

            logActivity($user_id, 'updated_status', 'task', $task_id, $task['status'], $newStatus);
            redirect('task_detail.php?id=' . $task_id, 'success', 'Task status updated!');
        }
    }

    // Reassign a task (managers/admins only)
    if ($_POST['action'] === 'reassign' && $canManage) {
        $newAssignee = isset($_POST['assigned_to']) && is_numeric($_POST['assigned_to'])
            ? (int)$_POST['assigned_to']
            : null;

        if ($task['status'] === TASK_STATUS_COMPLETED) {
            redirect('task_detail.php?id=' . $task_id, 'error', 'Completed tasks cannot be reassigned.');
        }

        if ($newAssignee) {
            $userCheck = $db->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND is_active = 1");
            $userCheck->execute([':user_id' => $newAssignee]);
            if (!$userCheck->fetch()) {
                redirect('task_detail.php?id=' . $task_id, 'error', 'Invalid assignee selected.');
            }
        }

        $oldAssignee = $task['assigned_to'];

        $query = "UPDATE tasks
                  SET assigned_to = :assigned_to,
                      assignment_status = :assignment_status,
                      assignment_responded_at = NULL,
                      rejection_reason = NULL,
                      status = :status,
                      completed_at = NULL
                  WHERE task_id = :task_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':assigned_to' => $newAssignee,
            ':assignment_status' => ASSIGNMENT_PENDING,
            ':status' => TASK_STATUS_PENDING,
            ':task_id' => $task_id
        ]);

        if ($newAssignee && $newAssignee != $user_id) {
            createNotification(
                $newAssignee,
                "You have been assigned a task: {$task['title']}",
                'Task Assigned',
                $task_id,
                'task'
            );
        }

        logActivity($user_id, 'reassigned_task', 'task', $task_id, $oldAssignee, $newAssignee);
        redirect('task_detail.php?id=' . $task_id, 'success', 'Task reassigned. The new assignee must respond to the assignment.');
    }

    // Add comment
    if ($_POST['action'] === 'add_comment') {
        $content = trim($_POST['content'] ?? '');

        if (empty($content)) {
            redirect('task_detail.php?id=' . $task_id, 'error', 'Comment cannot be empty.');
        }

        $query = "INSERT INTO comments (task_id, user_id, content) VALUES (:task_id, :user_id, :content)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':task_id' => $task_id,
            ':user_id' => $user_id,
            ':content' => $content
        ]);

        // Notify the assignee about the new comment
        if ($task['assigned_to'] && $task['assigned_to'] != $user_id) {
            createNotification(
                $task['assigned_to'],
                "New comment on task '{$task['title']}'",
                'Status Update',
                $task_id,
                'task'
            );
        }

        logActivity($user_id, 'added_comment', 'task', $task_id);
        redirect('task_detail.php?id=' . $task_id, 'success', 'Comment added!');
    }
}

// Get comments
$commentsQuery = "SELECT c.*, u.full_name
                  FROM comments c
                  JOIN users u ON c.user_id = u.user_id
                  WHERE c.task_id = :task_id
                  ORDER BY c.created_at ASC";
$commentsStmt = $db->prepare($commentsQuery);
$commentsStmt->execute([':task_id' => $task_id]);
$comments = $commentsStmt->fetchAll();

$isOverdue = $task['due_date'] && strtotime($task['due_date']) < strtotime('today') && $task['status'] !== 'Completed';
$assignmentStatus = $task['assignment_status'] ?? ASSIGNMENT_PENDING;
$isAssignee = $task['assigned_to'] == $user_id;
$workload = getUserWorkload($user_id);
$workloadMeta = getWorkloadMeta($workload['active_tasks']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-list-task"></i> Task Details</h2>
            <a href="tasks.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Tasks
            </a>
        </div>

        <?php echo displayFlashMessages(); ?>

        <div class="row g-4">
            <!-- Task Information -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo escape($task['title']); ?></h5>
                        <div>
                            <?php echo getPriorityBadge($task['priority']); ?>
                            <?php echo getStatusBadge($task['status']); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase small">Description</h6>
                        <p><?php echo nl2br(escape($task['description'] ?? '')) ?: '<span class="text-muted">No description provided.</span>'; ?></p>

                        <?php if ($isOverdue): ?>
                        <div class="alert alert-danger py-2">
                            <i class="bi bi-exclamation-triangle"></i> This task is overdue!
                        </div>
                        <?php endif; ?>

                        <?php if ($assignmentStatus === ASSIGNMENT_REJECTED): ?>
                        <div class="alert alert-warning py-2">
                            <i class="bi bi-x-circle"></i>
                            <strong>Assignment rejected.</strong>
                            <?php if (!empty($task['rejection_reason'])): ?>
                            <br><span class="small">Reason: <?php echo escape($task['rejection_reason']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <hr>

                        <div class="row small">
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-folder text-muted"></i>
                                <strong>Project:</strong>
                                <a href="project_detail.php?id=<?php echo $task['project_id']; ?>">
                                    <?php echo escape($task['project_title']); ?>
                                </a>
                            </div>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-flag text-muted"></i>
                                <strong>Milestone:</strong>
                                <?php echo escape($task['milestone_title'] ?? 'None'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-person text-muted"></i>
                                <strong>Assigned to:</strong>
                                <?php echo escape($task['assignee_name'] ?? 'Unassigned'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-person-check text-muted"></i>
                                <strong>Assignment:</strong>
                                <?php echo getAssignmentBadge($assignmentStatus); ?>
                                <?php if ($task['assignment_responded_at']): ?>
                                <br><small class="text-muted">Responded <?php echo timeAgo($task['assignment_responded_at']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-person-plus text-muted"></i>
                                <strong>Created by:</strong>
                                <?php echo escape($task['creator_name']); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-calendar-event text-muted"></i>
                                <strong>Due date:</strong>
                                <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo formatDate($task['due_date']); ?>
                                </span>
                            </div>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-clock text-muted"></i>
                                <strong>Created:</strong>
                                <?php echo formatDateTime($task['created_at']); ?>
                            </div>
                            <?php if ($task['completed_at']): ?>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-check-circle text-success"></i>
                                <strong>Completed:</strong>
                                <?php echo formatDateTime($task['completed_at']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Comments -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Comments (<?php echo count($comments); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted text-center py-3">No comments yet. Be the first to comment!</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="bg-light rounded p-2">
                                        <strong class="small"><?php echo escape($comment['full_name']); ?></strong>
                                        <p class="mb-0 small"><?php echo nl2br(escape($comment['content'])); ?></p>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($comment['created_at']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <form method="POST" class="mt-3">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="add_comment">
                            <div class="mb-2">
                                <textarea class="form-control" name="content" rows="2"
                                          placeholder="Write a comment..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-send"></i> Post Comment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Assignment response & status update -->
            <div class="col-lg-4">
                <?php if ($isAssignee && $task['status'] !== TASK_STATUS_COMPLETED): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-person-check"></i> Your Response</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small text-muted">Current response</span>
                            <?php echo getAssignmentBadge($assignmentStatus); ?>
                        </div>

                        <div class="alert alert-<?php echo $workloadMeta['class']; ?> py-2 small mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong>Your workload</strong>
                                <span class="badge text-bg-<?php echo $workloadMeta['class']; ?>"><?php echo $workloadMeta['label']; ?></span>
                            </div>
                            <?php echo $workload['active_tasks']; ?> active &middot;
                            <?php echo $workload['in_progress']; ?> in progress &middot;
                            <?php echo $workload['pending']; ?> pending &middot;
                            <?php echo $workload['overdue']; ?> overdue
                            <br><?php echo $workloadMeta['message']; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <?php if ($assignmentStatus !== ASSIGNMENT_ACCEPTED): ?>
                            <form method="POST" class="accept-task-form">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="respond">
                                <input type="hidden" name="response" value="<?php echo ASSIGNMENT_ACCEPTED; ?>">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check-lg"></i> Accept Task
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($assignmentStatus !== ASSIGNMENT_PENDING): ?>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="respond">
                                <input type="hidden" name="response" value="<?php echo ASSIGNMENT_PENDING; ?>">
                                <button type="submit" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-clock"></i> Flag as Pending
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($assignmentStatus !== ASSIGNMENT_REJECTED): ?>
                            <button type="button" class="btn btn-outline-danger w-100"
                                    data-bs-toggle="modal" data-bs-target="#rejectTaskModal">
                                <i class="bi bi-x-lg"></i> Reject Task
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($canUpdate): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-arrow-repeat"></i> Update Status</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="update_status">
                            <select name="status" class="form-select mb-3">
                                <?php foreach (['Pending', 'In Progress', 'Completed', 'Blocked'] as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $task['status'] === $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle"></i> Update Status
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($canManage && $task['status'] !== TASK_STATUS_COMPLETED): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-person-gear"></i> Reassign Task</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="reassign">
                            <select name="assigned_to" class="form-select mb-3">
                                <option value="">Unassigned</option>
                                <?php foreach (getUsersByRole() as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>" <?php echo $task['assigned_to'] == $u['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($u['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-arrow-repeat"></i> Reassign
                            </button>
                            <small class="text-muted d-block mt-2">
                                Reassigning resets the assignment response to Pending and returns the task to the queue.
                            </small>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reject Task Modal -->
    <div class="modal fade" id="rejectTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="respond">
                    <input type="hidden" name="response" value="<?php echo ASSIGNMENT_REJECTED; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-x-circle"></i> Reject Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            You are about to reject <strong><?php echo escape($task['title']); ?></strong>.
                            The task will stay assigned to you and your project manager will be notified so it can be reviewed or reassigned.
                        </p>
                        <label class="form-label">Reason (optional)</label>
                        <textarea class="form-control" name="reason" rows="3" maxlength="500"
                                  placeholder="e.g. I do not have capacity for this task right now"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentWorkload = <?php echo (int)$workload['active_tasks']; ?>;
        const heavyWorkload = currentWorkload >= <?php echo (int)WORKLOAD_HEAVY_THRESHOLD; ?>;

        document.querySelectorAll('.accept-task-form').forEach(form => {
            form.addEventListener('submit', function(event) {
                if (heavyWorkload && !confirm('You already have ' + currentWorkload + ' active tasks. Accepting this task will add to your workload. Continue?')) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
