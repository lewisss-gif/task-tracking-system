<?php
/**
 * Notifications Page
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Mark as read
if (isset($_GET['mark_read'])) {
    $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_GET['mark_read'], ':user_id' => $user_id]);
    redirect('notifications.php');
}

if (isset($_GET['mark_all_read'])) {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    redirect('notifications.php', 'success', 'All notifications marked as read');
}

// Get all notifications
$query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bell"></i> Notifications</h2>
            <?php if (!empty($notifications)): ?>
            <a href="?mark_all_read=1" class="btn btn-outline-primary">
                <i class="bi bi-check-all"></i> Mark All as Read
            </a>
            <?php endif; ?>
        </div>
        
        <?php echo displayFlashMessages(); ?>
        
        <div class="card shadow-sm">
            <?php if (empty($notifications)): ?>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                <h5>No notifications</h5>
                <p>You're all caught up!</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notif): ?>
                <div class="list-group-item <?php echo $notif['is_read'] ? '' : 'bg-light'; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex">
                            <div class="me-3">
                                <?php 
                                $icons = [
                                    'Task Assigned' => 'person-plus text-primary',
                                    'Deadline Approaching' => 'clock text-warning',
                                    'Task Overdue' => 'exclamation-triangle text-danger',
                                    'Status Update' => 'arrow-repeat text-info',
                                    'Milestone Alert' => 'flag text-success',
                                    'Project Update' => 'folder text-primary'
                                ];
                                $icon = $icons[$notif['type']] ?? 'bell text-secondary';
                                ?>
                                <i class="bi bi-<?php echo $icon; ?> fs-4"></i>
                            </div>
                            <div>
                                <p class="mb-1"><?php echo escape($notif['message']); ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> <?php echo timeAgo($notif['created_at']); ?>
                                    <span class="badge bg-secondary ms-2"><?php echo escape($notif['type']); ?></span>
                                </small>
                            </div>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                        <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-check"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
