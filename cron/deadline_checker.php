<?php
/**
 * Automated Deadline Checker
 * Run this script daily via cron job or Task Scheduler
 * 
 * Linux/Mac: 0 8 * * * php /path/to/cron/deadline_checker.php
 * Windows: Use Task Scheduler to run daily at 8:00 AM
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

echo "[" . date('Y-m-d H:i:s') . "] Starting deadline check...\n";

// ============================================================
// 1. Find tasks due within 24 hours (tomorrow)
// ============================================================
$query = "SELECT t.task_id, t.title, t.assigned_to, t.due_date, 
          u.email, u.full_name 
          FROM tasks t 
          JOIN users u ON t.assigned_to = u.user_id 
          WHERE t.status != 'Completed' 
          AND t.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";

$stmt = $db->prepare($query);
$stmt->execute();
$upcomingTasks = $stmt->fetchAll();

echo "Found " . count($upcomingTasks) . " tasks due tomorrow\n";

foreach ($upcomingTasks as $task) {
    // Create in-app notification
    createNotification(
        $task['assigned_to'],
        "Reminder: Task '{$task['title']}' is due tomorrow!",
        'Deadline Approaching',
        $task['task_id'],
        'task'
    );
    
    // Send email (uncomment in production with proper mail config)
    // $subject = "Task Deadline Reminder: " . $task['title'];
    // $message = "Dear {$task['full_name']},\n\n";
    // $message .= "This is a reminder that your task '{$task['title']}' is due on {$task['due_date']}.\n\n";
    // $message .= "Please ensure it is completed on time.\n\nBest regards,\nHub PM System";
    // mail($task['email'], $subject, $message, "From: noreply@hubpm.local");
    
    echo "  - Notified {$task['full_name']} about task: {$task['title']}\n";
}

// ============================================================
// 2. Find overdue tasks and mark them
// ============================================================
$overdueQuery = "SELECT t.task_id, t.title, t.assigned_to, t.due_date, 
                 t.status, u.full_name, p.manager_id 
                 FROM tasks t 
                 JOIN users u ON t.assigned_to = u.user_id 
                 JOIN projects p ON t.project_id = p.project_id 
                 WHERE t.status NOT IN ('Completed', 'Blocked') 
                 AND t.due_date < CURDATE()";

$overdueStmt = $db->prepare($overdueQuery);
$overdueStmt->execute();
$overdueTasks = $overdueStmt->fetchAll();

echo "Found " . count($overdueTasks) . " overdue tasks\n";

foreach ($overdueTasks as $task) {
    // Update task status to Blocked
    $updateQuery = "UPDATE tasks SET status = 'Blocked' WHERE task_id = :task_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':task_id' => $task['task_id']]);
    
    // Notify assigned user
    createNotification(
        $task['assigned_to'],
        "Task '{$task['title']}' is OVERDUE! Please update immediately.",
        'Task Overdue',
        $task['task_id'],
        'task'
    );
    
    // Notify project manager
    createNotification(
        $task['manager_id'],
        "Task '{$task['title']}' assigned to {$task['full_name']} is overdue!",
        'Task Overdue',
        $task['task_id'],
        'task'
    );
    
    echo "  - Marked overdue: {$task['title']} (assigned to {$task['full_name']})\n";
}

// ============================================================
// 3. Check milestones approaching
// ============================================================
$milestoneQuery = "SELECT m.milestone_id, m.title, m.due_date, 
                   p.title as project_title, p.manager_id 
                   FROM milestones m 
                   JOIN projects p ON m.project_id = p.project_id 
                   WHERE m.status != 'Completed' 
                   AND m.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";

$milestoneStmt = $db->prepare($milestoneQuery);
$milestoneStmt->execute();
$approachingMilestones = $milestoneStmt->fetchAll();

echo "Found " . count($approachingMilestones) . " approaching milestones\n";

foreach ($approachingMilestones as $milestone) {
    createNotification(
        $milestone['manager_id'],
        "Milestone '{$milestone['title']}' for project '{$milestone['project_title']}' is due on {$milestone['due_date']}!",
        'Milestone Alert',
        $milestone['milestone_id'],
        'milestone'
    );
    
    echo "  - Notified manager about milestone: {$milestone['title']}\n";
}

// ============================================================
// 4. Update milestone statuses
// ============================================================
$updateMilestonesQuery = "UPDATE milestones 
                          SET status = 'Overdue' 
                          WHERE status != 'Completed' 
                          AND due_date < CURDATE()";
$updateMilestonesStmt = $db->prepare($updateMilestonesQuery);
$updateMilestonesStmt->execute();

echo "Updated overdue milestone statuses\n";

echo "[" . date('Y-m-d H:i:s') . "] Deadline check completed.\n";
?>
