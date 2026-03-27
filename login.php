<?php
/**
 * Login Page
 * Handles user authentication with secure session management
 * 
 * @package InventorySystem
 */

// Start session at the beginning
session_start();

// Include database connection
require_once __DIR__ . '/db_connect.php';

/**
 * Escape output for safe HTML rendering.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Check if database connection failed
$db_error = '';
if (isset($db_connection_failed) && $db_connection_failed) {
    $db_error = $db_connection_error ?: 'Database connection is currently unavailable. Please try again later.';
}

// Check if user is already logged in, redirect to the correct area
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $redirect = in_array($_SESSION['role'] ?? '', ['admin', 'manager'], true)
        ? 'admin.php'
        : 'customer_dashboard.php';
    header('Location: ' . $redirect);
    exit;
}

// Initialize error message
$error = '';
$success = $_SESSION['flash_success'] ?? '';
$submittedLogin = '';
unset($_SESSION['flash_success']);

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get and sanitize input
    $login = trim($_POST['login'] ?? ($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $submittedLogin = $login;

    // Validate input
    if (empty($login) || empty($password)) {
        $error = 'Please enter both your email or username and password.';
    } elseif (!($pdo instanceof PDO)) {
        // Database connection is not available
        $error = $db_connection_error ?: 'Database connection is unavailable. Please try again later.';
    } else {
        try {
            // Prepare SQL statement to prevent SQL injection
            $stmt = $pdo->prepare("
                SELECT id, username, password, email, full_name, role
                FROM users
                WHERE (LOWER(username) = LOWER(:login) OR LOWER(email) = LOWER(:email))
                  AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute([
                'login' => $login,
                'email' => $login,
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if ($user && password_verify($password, $user['password'])) {
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Store user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                // Update last login time (optional)
                $updateStmt = $pdo->prepare('UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $updateStmt->execute(['id' => $user['id']]);
                
                // Redirect based on user role
                $user_role = $user['role'];
                if ($user_role === 'admin' || $user_role === 'manager') {
                    // Admin/Manager goes to the admin dashboard
                    header('Location: admin.php');
                } else {
                    // Regular users go to customer dashboard
                    header('Location: customer_dashboard.php');
                }
                exit;
                
            } else {
                // Invalid credentials
                $error = 'Invalid email, username, or password.';
                
                // Log failed login attempt (optional)
                error_log("Failed login attempt for login: " . $login);
            }
            
        } catch (PDOException $e) {
            // Log database error
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inventory System Login">
    <title>Login - Inventory System</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
                <h1>Inventory System</h1>
                <p>Please login to continue</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($db_error)): ?>
                <div class="error-message">
                    <?php echo e($db_error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="error-message" style="background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.28); color: #d3f6e4;">
                    <?php echo e($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="login">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Email or Username
                    </label>
                    <input 
                        type="text" 
                        id="login" 
                        name="login" 
                        placeholder="Enter your email or username"
                        required
                        autocomplete="username"
                        value="<?php echo e($submittedLogin); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="login-btn">
                    <span>Login</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>
            
            <div class="login-footer">
                <p><a href="register.php" style="color: inherit;">Create an account</a> if you are new here.</p>
                <p>&copy; <?php echo date('Y'); ?> Inventory System. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
