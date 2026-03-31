<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Login Functionality</h2>";

// Start session
session_start();

// Include database connection
require_once 'db_connect.php';

echo "<p>Database connection status: " . ($db_connection_failed ? "FAILED" : "OK") . "</p>";
echo "<p>PDO: " . ($pdo !== null ? "Connected" : "NULL") . "</p>";

if (!$db_connection_failed && $pdo !== null) {
    echo "<h3>Testing Login Query</h3>";
    
    // Test the login query
    $username = 'admin';
    $password = 'admin123';
    
    try {
        // Use proper PostgreSQL boolean comparison
        $stmt = $pdo->prepare("SELECT id, username, password, email, full_name, role FROM users WHERE username = :username AND is_active = :is_active");
        $stmt->execute(['username' => $username, 'is_active' => true]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<p style='color:green'>✓ User found: " . htmlspecialchars($user['username']) . "</p>";
            echo "<p>Password hash exists: " . (isset($user['password']) ? "Yes" : "No") . "</p>";
            echo "<p>Hash: " . htmlspecialchars($user['password']) . "</p>";
            
            // Check if password matches
            if (password_verify($password, $user['password'])) {
                echo "<p style='color:green'>✓ Password verified!</p>";
            } else {
                echo "<p style='color:orange'>⚠ Password does NOT match. Updating password hash...</p>";
                
                // Generate new hash and update
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = :password WHERE username = :username");
                $update->execute(['password' => $new_hash, 'username' => $username]);
                
                echo "<p style='color:green'>✓ Password hash updated!</p>";
                echo "<p>New hash: " . htmlspecialchars($new_hash) . "</p>";
                
                // Verify again
                $stmt = $pdo->prepare("SELECT password FROM users WHERE username = :username");
                $stmt->execute(['username' => $username]);
                $updated_user = $stmt->fetch();
                
                if (password_verify($password, $updated_user['password'])) {
                    echo "<p style='color:green'>✓ Password now verified successfully!</p>";
                }
            }
        } else {
            echo "<p style='color:red'>✗ User not found</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>✗ Query Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ Database connection failed: " . htmlspecialchars($db_error_message) . "</p>";
}
?>
