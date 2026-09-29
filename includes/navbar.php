<?php
$notificationCount = getUnreadNotificationCount($_SESSION['user_id']);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-kanban"></i> <?php echo APP_NAME; ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" 
                       href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <?php if (hasAnyRole(['Administrator', 'Project Manager'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'projects.php' ? 'active' : ''; ?>" 
                       href="projects.php">
                        <i class="bi bi-folder"></i> Projects
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>" 
                       href="tasks.php">
                        <i class="bi bi-list-task"></i> Tasks
                    </a>
                </li>
                
                <?php if (hasAnyRole(['Administrator', 'Project Manager'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" 
                       href="reports.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasRole('Administrator')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>" 
                       href="users.php">
                        <i class="bi bi-people"></i> Users
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link position-relative" href="notifications.php">
                        <i class="bi bi-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $notificationCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small"><?php echo escape($_SESSION['role']); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
