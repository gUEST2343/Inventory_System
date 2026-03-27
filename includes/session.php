<?php
/**
 * Session Management
 * Handles session initialization, security, and management
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters before starting session
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

/**
 * Initialize session with security checks
 */
function initSession() {
    // Check for session hijacking
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_agent'])) {
        if ($_SESSION['user_agent'] !== getUserAgent()) {
            // Possible session hijacking - destroy session
            destroySession();
            return false;
        }
    }
    
    // Check for session fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['user_agent'] = getUserAgent();
        $_SESSION['ip_address'] = getUserIP();
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $timeout = SESSION_TIMEOUT;
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > $timeout) {
            destroySession();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Create session for logged in user
 */
function createUserSession($user) {
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    
    // Clear any existing session data
    $_SESSION = [];
    
    // Set user data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['user_agent'] = getUserAgent();
    $_SESSION['ip_address'] = getUserIP();
    $_SESSION['initiated'] = true;
    
    // Generate session token
    $_SESSION['session_token'] = generateRandomString(32);
    
    return true;
}

/**
 * Destroy session
 */
function destroySession() {
    // Clear session data
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Start new session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true && 
           isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? 'guest';
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return getCurrentUserRole() === $role;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is manager or admin
 */
function isManager() {
    $role = getCurrentUserRole();
    return $role === 'admin' || $role === 'manager';
}

/**
 * Get session data
 */
function getSessionData($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

/**
 * Set session data
 */
function setSessionData($key, $value) {
    $_SESSION[$key] = $value;
}

/**
 * Flash session data (stored temporarily)
 */
function flash($key, $value = null) {
    if ($value === null) {
        // Get and clear flash data
        if (isset($_SESSION['flash'][$key])) {
            $value = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $value;
        }
        return null;
    }
    
    // Set flash data
    $_SESSION['flash'][$key] = $value;
    return true;
}

/**
 * Get all flash data
 */
function getAllFlashData() {
    $flashData = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashData;
}

/**
 * Validate session token
 */
function validateSessionToken($token) {
    return isset($_SESSION['session_token']) && 
           hash_equals($_SESSION['session_token'], $token);
}

/**
 * Get session info
 */
function getSessionInfo() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'ip_address' => $_SESSION['ip_address'] ?? null,
        'session_token' => $_SESSION['session_token'] ?? null
    ];
}

/**
 * Extend session
 */
function extendSession() {
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Get session remaining time
 */
function getSessionRemainingTime() {
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    return max(0, SESSION_TIMEOUT - $elapsed);
}

/**
 * Set session message (for displaying to user)
 */
function setMessage($type, $message) {
    $_SESSION['messages'][$type] = $message;
}

/**
 * Get and clear session messages
 */
function getMessages() {
    $messages = $_SESSION['messages'] ?? [];
    unset($_SESSION['messages']);
    return $messages;
}

/**
 * Check if user IP changed (security)
 */
function checkIPChange() {
    if (!isset($_SESSION['ip_address'])) {
        return true;
    }
    
    return $_SESSION['ip_address'] === getUserIP();
}

// Initialize session on include
initSession();
