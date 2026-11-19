<?php
/**
 * Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Sanitize output to prevent XSS
 */
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

/**
 * Get time ago string
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'Just now';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $classes = [
        'Pending' => 'bg-warning text-dark',
        'In Progress' => 'bg-info text-dark',
        'Completed' => 'bg-success',
        'Blocked' => 'bg-danger',
        'Planning' => 'bg-secondary',
        'Active' => 'bg-primary',
        'On Hold' => 'bg-warning text-dark',
        'Cancelled' => 'bg-dark',
        'Overdue' => 'bg-danger'
    ];
    
    $class = $classes[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $class . '">' . escape($status) . '</span>';
}

/**
 * Get priority badge HTML
 */
function getPriorityBadge($priority) {
    $classes = [
        'Low' => 'bg-success',
        'Medium' => 'bg-info text-dark',
        'High' => 'bg-warning text-dark',
        'Critical' => 'bg-danger'
    ];
    
    $class = $classes[$priority] ?? 'bg-secondary';
    return '<span class="badge ' . $class . '">' . escape($priority) . '</span>';
}

/**
 * Get assignment response badge HTML
 */
function getAssignmentBadge($status) {
    $labels = [
        ASSIGNMENT_PENDING => 'Awaiting Response',
        ASSIGNMENT_ACCEPTED => 'Accepted',
        ASSIGNMENT_REJECTED => 'Rejected'
    ];
    $classes = [
        ASSIGNMENT_PENDING => 'bg-secondary',
        ASSIGNMENT_ACCEPTED => 'bg-success',
        ASSIGNMENT_REJECTED => 'bg-danger'
    ];

    $label = $labels[$status] ?? 'Awaiting Response';
    $class = $classes[$status] ?? 'bg-secondary';

    return '<span class="badge ' . $class . '">' . escape($label) . '</span>';
}

/**
 * Get a user's current workload based on their active tasks
 */
function getUserWorkload($userId) {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT
                COUNT(*) AS active_tasks,
                COALESCE(SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END), 0) AS in_progress,
                COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status = 'Blocked' THEN 1 ELSE 0 END), 0) AS blocked,
                COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END), 0) AS overdue
              FROM tasks
              WHERE assigned_to = :user_id
              AND status != 'Completed'
              AND assignment_status != 'Rejected'";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $workload = $stmt->fetch() ?: [];

    return [
        'active_tasks' => (int)($workload['active_tasks'] ?? 0),
        'in_progress' => (int)($workload['in_progress'] ?? 0),
        'pending' => (int)($workload['pending'] ?? 0),
        'blocked' => (int)($workload['blocked'] ?? 0),
        'overdue' => (int)($workload['overdue'] ?? 0)
    ];
}

/**
 * Describe a workload level for badges and warnings
 */
function getWorkloadMeta($activeTasks) {
    $activeTasks = (int)$activeTasks;

    if ($activeTasks >= WORKLOAD_HEAVY_THRESHOLD) {
        return [
            'label' => 'Heavy',
            'class' => 'danger',
            'message' => 'Your workload is heavy. Accepting more work may affect your existing deadlines.'
        ];
    }

    if ($activeTasks >= WORKLOAD_MODERATE_THRESHOLD) {
        return [
            'label' => 'Balanced',
            'class' => 'warning',
            'message' => 'Your workload is balanced. Consider your capacity before accepting more work.'
        ];
    }

    return [
        'label' => 'Light',
        'class' => 'success',
        'message' => 'You have capacity to take on more work.'
    ];
}

/**
 * Get progress percentage color
 */
function getProgressColor($percentage) {
    if ($percentage >= 75) return 'bg-success';
    if ($percentage >= 50) return 'bg-info';
    if ($percentage >= 25) return 'bg-warning';
    return 'bg-danger';
}

/**
 * Calculate project progress
 */
function calculateProjectProgress($projectId) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
              FROM tasks 
              WHERE project_id = :project_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $projectId);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['total_tasks'] == 0) return 0;
    
    return round(($result['completed_tasks'] / $result['total_tasks']) * 100);
}

/**
 * Get user's full name by ID
 */
function getUserName($userId) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT full_name FROM users WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch();
    
    return $user ? $user['full_name'] : 'Unknown User';
}

/**
 * Get all users by role
 */
function getUsersByRole($roleName = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($roleName) {
        $query = "SELECT u.* FROM users u 
                  JOIN roles r ON u.role_id = r.role_id 
                  WHERE r.role_name = :role_name AND u.is_active = 1 
                  ORDER BY u.full_name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':role_name', $roleName);
    } else {
        $query = "SELECT u.* FROM users u 
                  WHERE u.is_active = 1 
                  ORDER BY u.full_name";
        $stmt = $db->prepare($query);
    }
    
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Create notification
 */
function createNotification($userId, $message, $type, $referenceId = null, $referenceType = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO notifications (user_id, message, type, reference_id, reference_type) 
              VALUES (:user_id, :message, :type, :reference_id, :reference_type)";
    
    $stmt = $db->prepare($query);
    
    return $stmt->execute([
        ':user_id' => $userId,
        ':message' => $message,
        ':type' => $type,
        ':reference_id' => $referenceId,
        ':reference_type' => $referenceType
    ]);
}

/**
 * Log activity
 */
function logActivity($userId, $action, $entityType, $entityId, $oldValue = null, $newValue = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, old_value, new_value) 
              VALUES (:user_id, :action, :entity_type, :entity_id, :old_value, :new_value)";
    
    $stmt = $db->prepare($query);
    
    return $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':old_value' => $oldValue,
        ':new_value' => $newValue
    ]);
}

/**
 * Record a user's response to a task assignment.
 *
 * Accepting moves the task to In Progress. Flagging as pending or rejecting
 * returns it to the pending queue while keeping it assigned so a manager can
 * review or reassign it.
 *
 * @return array{success: bool, message: string}
 */
function respondToTaskAssignment($taskId, $userId, $response, $reason = '') {
    $validResponses = [ASSIGNMENT_PENDING, ASSIGNMENT_ACCEPTED, ASSIGNMENT_REJECTED];

    if (!is_string($response) || !in_array($response, $validResponses, true)) {
        return ['success' => false, 'message' => 'Invalid assignment response.'];
    }

    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT t.task_id, t.title, t.status, t.assigned_to, t.created_by,
                     t.assignment_status, p.manager_id
              FROM tasks t
              JOIN projects p ON t.project_id = p.project_id
              WHERE t.task_id = :task_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':task_id' => (int)$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        return ['success' => false, 'message' => 'Task not found.'];
    }

    if ((int)$task['assigned_to'] !== (int)$userId) {
        return ['success' => false, 'message' => 'Only the assigned user can respond to this task.'];
    }

    if ($task['status'] === TASK_STATUS_COMPLETED) {
        return ['success' => false, 'message' => 'Completed tasks cannot be accepted, deferred, or rejected.'];
    }

    // Accepted work starts immediately; pending/rejected work stays queued.
    $newStatus = $response === ASSIGNMENT_ACCEPTED
        ? TASK_STATUS_IN_PROGRESS
        : TASK_STATUS_PENDING;

    $reason = $response === ASSIGNMENT_REJECTED && is_string($reason) ? trim($reason) : null;
    if ($reason !== null && $reason !== '') {
        $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 500) : substr($reason, 0, 500);
    } else {
        $reason = null;
    }

    $updateQuery = "UPDATE tasks
                    SET assignment_status = :assignment_status,
                        assignment_responded_at = NOW(),
                        rejection_reason = :rejection_reason,
                        status = :status,
                        completed_at = NULL
                    WHERE task_id = :task_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        ':assignment_status' => $response,
        ':rejection_reason' => $reason,
        ':status' => $newStatus,
        ':task_id' => (int)$taskId
    ]);

    // Let the project manager and task creator know about the response.
    $recipients = [];
    foreach ([$task['manager_id'], $task['created_by']] as $recipientId) {
        if ($recipientId && (int)$recipientId !== (int)$userId) {
            $recipients[(int)$recipientId] = (int)$recipientId;
        }
    }

    $actorName = $_SESSION['full_name'] ?? 'A team member';
    $messages = [
        ASSIGNMENT_ACCEPTED => "{$actorName} accepted task '{$task['title']}' and started work on it.",
        ASSIGNMENT_PENDING => "{$actorName} flagged task '{$task['title']}' as pending due to their current workload.",
        ASSIGNMENT_REJECTED => "{$actorName} rejected task '{$task['title']}'" . ($reason ? ": {$reason}" : '.')
    ];

    foreach ($recipients as $recipientId) {
        createNotification(
            $recipientId,
            $messages[$response],
            'Status Update',
            (int)$taskId,
            'task'
        );
    }

    $actions = [
        ASSIGNMENT_ACCEPTED => 'accepted_task',
        ASSIGNMENT_PENDING => 'flagged_task_pending',
        ASSIGNMENT_REJECTED => 'rejected_task'
    ];
    logActivity($userId, $actions[$response], 'task', (int)$taskId, $task['assignment_status'], $response);

    $successMessages = [
        ASSIGNMENT_ACCEPTED => 'Task accepted — it has been moved to In Progress.',
        ASSIGNMENT_PENDING => 'Task flagged as pending. Your manager has been notified.',
        ASSIGNMENT_REJECTED => 'Task rejected. It stays assigned to you so your manager can review or reassign it.'
    ];

    return ['success' => true, 'message' => $successMessages[$response]];
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($userId) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as count FROM notifications 
              WHERE user_id = :user_id AND is_read = 0";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    return $stmt->fetch()['count'];
}

/**
 * Redirect with message
 */
function redirect($url, $type = null, $message = null) {
    if ($type && $message) {
        $_SESSION['flash_' . $type] = $message;
    }
    header("Location: " . $url);
    exit();
}

/**
 * Get or create the CSRF token for this session
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field for forms
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Verify a submitted CSRF token (defaults to POSTed token)
 */
function verifyCsrfToken($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Display flash messages
 */
function displayFlashMessages() {
    $types = ['success', 'error', 'warning', 'info'];
    $html = '';
    
    foreach ($types as $type) {
        if (isset($_SESSION['flash_' . $type])) {
            $alertClass = $type === 'error' ? 'danger' : $type;
            $html .= '<div class="alert alert-' . $alertClass . ' alert-dismissible fade show" role="alert">';
            $html .= escape($_SESSION['flash_' . $type]);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
            unset($_SESSION['flash_' . $type]);
        }
    }
    
    return $html;
}
?>
