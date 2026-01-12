<?php
/**
 * Session Management
 * Secure session handling with additional security measures
 */

// Start session with custom settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    session_name(SESSION_NAME);
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Session created more than 30 minutes ago
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 * 
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * 
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    if (!isset($_SESSION['user_data'])) {
        // Fetch user data from database
        $sql = "SELECT u.*, GROUP_CONCAT(r.role_name) as roles 
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE u.id = ?
                GROUP BY u.id";
        
        $user = getRow($sql, [getCurrentUserId()]);
        
        if ($user) {
            $_SESSION['user_data'] = $user;
        }
    }
    
    return $_SESSION['user_data'] ?? null;
}

/**
 * Check if user has specific role
 * 
 * @param string $role Role name to check
 * @return bool
 */
function hasRole($role) {
    $user = getCurrentUser();
    
    if (!$user || !isset($user['roles'])) {
        return false;
    }
    
    $roles = explode(',', $user['roles']);
    return in_array($role, $roles);
}

/**
 * Check if user has any of the specified roles
 * 
 * @param array $roles Array of role names
 * @return bool
 */
function hasAnyRole($roles) {
    foreach ($roles as $role) {
        if (hasRole($role)) {
            return true;
        }
    }
    return false;
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Require specific role - show error if user doesn't have it
 * 
 * @param string|array $roles Role name(s) required
 */
function requireRole($roles) {
    requireLogin();
    
    $roles = is_array($roles) ? $roles : [$roles];
    
    if (!hasAnyRole($roles)) {
        http_response_code(403);
        die("Access denied. You don't have permission to access this page.");
    }
}

/**
 * Set flash message
 * 
 * @param string $type Type of message (success, error, warning, info)
 * @param string $message Message content
 */
function setFlash($type, $message) {
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash messages
 * 
 * @return array
 */
function getFlash() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Update last activity time
 */
function updateLastActivity() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
        
        // Update last_login in database
        query("UPDATE users SET last_login = NOW() WHERE id = ?", [getCurrentUserId()]);
    }
}

// Update last activity on each request
updateLastActivity();
