<?php
/**
 * Logout Handler
 */
require_once 'includes/auth.php';

logoutUser();

header("Location: login.php?logged_out=1");
exit();
?>
