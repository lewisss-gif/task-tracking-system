<?php
/**
 * Reports Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAnyRole(['Administrator', 'Project Manager']);

$database = new Database();
$db = $database->getConnection();

// Overall statistics
$statsQuery = "SELECT 
    (SELECT COUNT(*) FROM projects WHERE status != 'Cancelled') as total_projects,
    (SELECT COUNT(*) FROM tasks) as total_tasks,
    (SELECT COUNT(*) FROM tasks WHERE status = 'Completed') as completed_tasks,
    (SELECT COUNT(*) FROM tasks WHERE status != 'Completed' AND due_date < CURDATE()) as overdue_tasks,
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch();

// Task completion rate
$completionRate = $stats['total_tasks'] > 0 
    ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) 
    : 0;

// Tasks by status
$statusQuery = "SELECT status, COUNT(*) as count FROM tasks GROUP BY status";
$statusStmt = $db->prepare($statusQuery);
$statusStmt->execute();
$tasksByStatus = $statusStmt->fetchAll();

// Tasks by priority
$priorityQuery = "SELECT priority, COUNT(*) as count FROM tasks GROUP BY priority";
$priorityStmt = $db->prepare($priorityQuery);
$priorityStmt->execute();
$tasksByPriority = $priorityStmt->fetchAll();

// Top performers
$performerQuery = "SELECT u.full_name, 
                   COUNT(t.task_id) as total_tasks,
                   SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
                   FROM users u 
                   LEFT JOIN tasks t ON u.user_id = t.assigned_to 
                   WHERE u.is_active = 1 
                   GROUP BY u.user_id 
                   ORDER BY completed_tasks DESC 
                   LIMIT 5";
$performerStmt = $db->prepare($performerQuery);
$performerStmt->execute();
$topPerformers = $performerStmt->fetchAll();

// Project progress summary
$projectQuery = "SELECT p.*, 
                 COUNT(t.task_id) as total_tasks,
                 SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
                 FROM projects p 
                 LEFT JOIN tasks t ON p.project_id = t.project_id 
                 WHERE p.status != 'Cancelled' 
                 GROUP BY p.project_id 
                 ORDER BY p.created_at DESC";
$projectStmt = $db->prepare($projectQuery);
$projectStmt->execute();
$projectProgress = $projectStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-graph-up"></i> Reports & Analytics</h2>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
        
        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6>Total Projects</h6>
                        <h2><?php echo $stats['total_projects']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Completion Rate</h6>
                        <h2><?php echo $completionRate; ?>%</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h6>Total Tasks</h6>
                        <h2><?php echo $stats['total_tasks']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6>Overdue Tasks</h6>
                        <h2><?php echo $stats['overdue_tasks']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Tasks by Status Chart -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Tasks by Status</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tasks by Priority Chart -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Tasks by Priority</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top Performers -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Top Performers</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-center">Completed</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topPerformers as $performer): 
                                    $rate = $performer['total_tasks'] > 0 
                                        ? round(($performer['completed_tasks'] / $performer['total_tasks']) * 100) 
                                        : 0;
                                ?>
                                <tr>
                                    <td><?php echo escape($performer['full_name']); ?></td>
                                    <td class="text-center"><?php echo $performer['completed_tasks']; ?></td>
                                    <td class="text-center"><?php echo $performer['total_tasks']; ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $rate >= 75 ? 'bg-success' : ($rate >= 50 ? 'bg-info' : 'bg-warning'); ?>">
                                            <?php echo $rate; ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Project Progress -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Project Progress</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($projectProgress as $project): 
                            $progress = $project['total_tasks'] > 0 
                                ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) 
                                : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo escape($project['title']); ?></span>
                                <span><?php echo $progress; ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar <?php echo getProgressColor($progress); ?>" 
                                     style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?> tasks completed
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($tasksByStatus, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($tasksByStatus, 'count')); ?>,
                    backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
        
        // Priority Chart
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($tasksByPriority, 'priority')); ?>,
                datasets: [{
                    label: 'Number of Tasks',
                    data: <?php echo json_encode(array_column($tasksByPriority, 'count')); ?>,
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>
