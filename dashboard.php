<?php
/**
 * Dashboard - Main page after login
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get statistics based on role
if ($role === 'Administrator' || $role === 'Project Manager') {
    // Total projects
    $projectQuery = "SELECT COUNT(*) as total FROM projects WHERE status != 'Cancelled'";
    $projectStmt = $db->prepare($projectQuery);
    $projectStmt->execute();
    $totalProjects = $projectStmt->fetch()['total'];
    
    // Total tasks
    $taskQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
                  FROM tasks";
    $taskStmt = $db->prepare($taskQuery);
    $taskStmt->execute();
    $taskStats = $taskStmt->fetch();
    
    // Overdue tasks
    $overdueQuery = "SELECT COUNT(*) as total FROM tasks 
                     WHERE status != 'Completed' AND due_date < CURDATE()";
    $overdueStmt = $db->prepare($overdueQuery);
    $overdueStmt->execute();
    $overdueTasks = $overdueStmt->fetch()['total'];
    
    // Team members
    $memberQuery = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
    $memberStmt = $db->prepare($memberQuery);
    $memberStmt->execute();
    $totalMembers = $memberStmt->fetch()['total'];
    
    // Get all projects with progress
    $projectsQuery = "SELECT p.*, u.full_name as manager_name 
                      FROM projects p 
                      JOIN users u ON p.manager_id = u.user_id 
                      WHERE p.status != 'Cancelled' 
                      ORDER BY p.created_at DESC 
                      LIMIT 5";
    $projectsStmt = $db->prepare($projectsQuery);
    $projectsStmt->execute();
    $recentProjects = $projectsStmt->fetchAll();
    
} else {
    // Team member statistics
    $taskQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
                  FROM tasks WHERE assigned_to = :user_id";
    $taskStmt = $db->prepare($taskQuery);
    $taskStmt->bindParam(':user_id', $user_id);
    $taskStmt->execute();
    $taskStats = $taskStmt->fetch();
    
    // My overdue tasks
    $overdueQuery = "SELECT COUNT(*) as total FROM tasks 
                     WHERE assigned_to = :user_id AND status != 'Completed' AND due_date < CURDATE()";
    $overdueStmt = $db->prepare($overdueQuery);
    $overdueStmt->bindParam(':user_id', $user_id);
    $overdueStmt->execute();
    $overdueTasks = $overdueStmt->fetch()['total'];
    
    $recentProjects = [];
    $totalProjects = 0;
    $totalMembers = 0;
}

// Get upcoming deadlines
$deadlineQuery = "SELECT t.*, p.title as project_title, u.full_name as assignee_name 
                  FROM tasks t 
                  JOIN projects p ON t.project_id = p.project_id 
                  LEFT JOIN users u ON t.assigned_to = u.user_id 
                  WHERE t.status != 'Completed' 
                  AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";

if ($role === 'Team Member') {
    $deadlineQuery .= " AND t.assigned_to = :user_id";
}

$deadlineQuery .= " ORDER BY t.due_date ASC LIMIT 5";

$deadlineStmt = $db->prepare($deadlineQuery);
if ($role === 'Team Member') {
    $deadlineStmt->bindParam(':user_id', $user_id);
}
$deadlineStmt->execute();
$upcomingDeadlines = $deadlineStmt->fetchAll();

// Get recent activity
$activityQuery = "SELECT al.*, u.full_name 
                  FROM activity_logs al 
                  JOIN users u ON al.user_id = u.user_id 
                  ORDER BY al.created_at DESC 
                  LIMIT 8";
$activityStmt = $db->prepare($activityQuery);
$activityStmt->execute();
$recentActivity = $activityStmt->fetchAll();

// Get unread notifications
$notifQuery = "SELECT * FROM notifications 
               WHERE user_id = :user_id AND is_read = 0 
               ORDER BY created_at DESC LIMIT 5";
$notifStmt = $db->prepare($notifQuery);
$notifStmt->bindParam(':user_id', $user_id);
$notifStmt->execute();
$unreadNotifications = $notifStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
                <p class="text-muted">Welcome back, <?php echo escape($_SESSION['full_name']); ?>!</p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <?php if ($role === 'Administrator' || $role === 'Project Manager'): ?>
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase mb-1">Active Projects</h6>
                                <h2 class="mb-0"><?php echo $totalProjects; ?></h2>
                            </div>
                            <i class="bi bi-folder fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase mb-1">Tasks Completed</h6>
                                <h2 class="mb-0"><?php echo $taskStats['completed'] ?? 0; ?>/<?php echo $taskStats['total'] ?? 0; ?></h2>
                            </div>
                            <i class="bi bi-check-circle fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase mb-1">Overdue Tasks</h6>
                                <h2 class="mb-0"><?php echo $overdueTasks; ?></h2>
                            </div>
                            <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($role === 'Administrator' || $role === 'Project Manager'): ?>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase mb-1">Team Members</h6>
                                <h2 class="mb-0"><?php echo $totalMembers; ?></h2>
                            </div>
                            <i class="bi bi-people fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="row g-4">
            <!-- Recent Projects -->
            <?php if (!empty($recentProjects)): ?>
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-folder"></i> Recent Projects</h5>
                        <a href="projects.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentProjects as $project): 
                            $progress = calculateProjectProgress($project['project_id']);
                        ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1"><?php echo escape($project['title']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i> <?php echo escape($project['manager_name']); ?> | 
                                        <i class="bi bi-calendar"></i> <?php echo formatDate($project['end_date']); ?>
                                    </small>
                                </div>
                                <?php echo getStatusBadge($project['status']); ?>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?php echo getProgressColor($progress); ?>" 
                                     style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $progress; ?>% complete</small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Deadlines & Notifications -->
            <div class="col-lg-4">
                <!-- Notifications -->
                <?php if (!empty($unreadNotifications)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-bell"></i> Notifications</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($unreadNotifications as $notif): ?>
                        <div class="p-3 border-bottom">
                            <p class="mb-1 small"><?php echo escape($notif['message']); ?></p>
                            <small class="text-muted"><?php echo timeAgo($notif['created_at']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer text-center">
                        <a href="notifications.php" class="btn btn-sm btn-link">View All</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Upcoming Deadlines -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-calendar-event"></i> Upcoming Deadlines</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($upcomingDeadlines)): ?>
                            <p class="text-muted text-center py-3 mb-0">No upcoming deadlines</p>
                        <?php else: ?>
                            <?php foreach ($upcomingDeadlines as $task): ?>
                            <div class="p-3 border-bottom">
                                <h6 class="mb-1 small"><?php echo escape($task['title']); ?></h6>
                                <small class="text-muted d-block">
                                    <i class="bi bi-folder"></i> <?php echo escape($task['project_title']); ?>
                                </small>
                                <small class="text-danger">
                                    <i class="bi bi-clock"></i> Due: <?php echo formatDate($task['due_date']); ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-activity"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivity)): ?>
                            <p class="text-muted text-center py-3 mb-0">No recent activity</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($recentActivity as $activity): ?>
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <div class="activity-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-<?php 
                                                echo strpos($activity['action'], 'create') !== false ? 'plus-circle' : 
                                                    (strpos($activity['action'], 'update') !== false ? 'pencil' : 'trash'); 
                                            ?>"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="mb-1">
                                            <strong><?php echo escape($activity['full_name']); ?></strong>
                                            <?php echo escape(str_replace('_', ' ', $activity['action'])); ?>
                                            a <?php echo escape($activity['entity_type']); ?>
                                        </p>
                                        <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
