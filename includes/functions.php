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
