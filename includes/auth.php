<?php
/**
 * Authentication Functions
 * Handles login, logout, session management, and role checks
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if user has any of the given roles
 */
function hasAnyRole($roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "login.php?error=login_required");
        exit();
    }
}

/**
 * Require specific role - redirect if not authorized
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: " . BASE_URL . "dashboard.php?error=unauthorized");
        exit();
    }
}

/**
 * Require any of the given roles
 */
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        header("Location: " . BASE_URL . "dashboard.php?error=unauthorized");
        exit();
    }
}

/**
 * Authenticate user with username and password
 */
function authenticate($username, $password) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT u.*, r.role_name 
              FROM users u 
              JOIN roles r ON u.role_id = r.role_id 
              WHERE u.username = :username AND u.is_active = 1 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    
    return false;
}

/**
 * Login user - set session variables
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Logout user - destroy session
 */
function logoutUser() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Get current logged-in user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT u.*, r.role_name 
              FROM users u 
              JOIN roles r ON u.role_id = r.role_id 
              WHERE u.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    return $stmt->fetch();
}

/**
 * Create a new user
 */
function createUser($username, $email, $password, $fullName, $roleId) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if username or email already exists
    $checkQuery = "SELECT user_id FROM users WHERE username = :username OR email = :email";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([':username' => $username, ':email' => $email]);
    
    if ($checkStmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (username, email, password_hash, full_name, role_id) 
              VALUES (:username, :email, :password_hash, :full_name, :role_id)";
    
    $stmt = $db->prepare($query);
    
    try {
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':full_name' => $fullName,
            ':role_id' => $roleId
        ]);
        
        return ['success' => true, 'user_id' => $db->lastInsertId()];
    } catch (PDOException $e) {
        error_log("User creation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create user'];
    }
}
?>
