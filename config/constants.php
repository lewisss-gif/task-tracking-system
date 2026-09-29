<?php
/**
 * Application Constants
 */

// Application info
define('APP_NAME', 'Hub PM System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/hub-pm-system/');

// Session settings
define('SESSION_TIMEOUT', 3600); // 1 hour

// Task settings
define('TASK_STATUS_PENDING', 'Pending');
define('TASK_STATUS_IN_PROGRESS', 'In Progress');
define('TASK_STATUS_COMPLETED', 'Completed');
define('TASK_STATUS_BLOCKED', 'Blocked');

// Priority levels
define('PRIORITY_LOW', 'Low');
define('PRIORITY_MEDIUM', 'Medium');
define('PRIORITY_HIGH', 'High');
define('PRIORITY_CRITICAL', 'Critical');

// Project status
define('PROJECT_PLANNING', 'Planning');
define('PROJECT_ACTIVE', 'Active');
define('PROJECT_ON_HOLD', 'On Hold');
define('PROJECT_COMPLETED', 'Completed');
define('PROJECT_CANCELLED', 'Cancelled');

// Date format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
?>
