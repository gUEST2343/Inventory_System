<?php
/**
 * Admin User Seeder
 * Creates a default admin user if none exists
 */

// Include database connection
require_once __DIR__ . '/../db_connect.php';

echo "<h2>Creating Admin User</h2>";

if ($pdo === null) {
    echo "<p style='color: red;'>Error: Database connection not available. Please check your database settings.</p>";
    exit;
}

try {
    // Check if users table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'users'");
    if ($stmt->fetchColumn() == 0) {
        echo "<p style='color: red;'>Error: users table does not exist. Please run the SQL setup files first.</p>";
        exit;
    }
    
    // Check if admin user exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount > 0) {
        echo "<p style='color: orange;'>Admin user already exists. No new user created.</p>";
    } else {
        // Create default admin user
        $username = 'admin';
        $email = 'admin@inventory.com';
        $fullName = 'System Administrator';
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, full_name, password, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'admin', 1, NOW(), NOW())
        ");
        
        $stmt->execute([$username, $email, $fullName, $password]);
        
        echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
        echo "<p>Username: <strong>admin</strong></p>";
        echo "<p>Password: <strong>admin123</strong></p>";
    }
    
    // Also create a manager and staff user for testing
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'manager'");
    if ($stmt->fetchColumn() == 0) {
        $username = 'manager';
        $email = 'manager@inventory.com';
        $fullName = 'Store Manager';
        $password = password_hash('manager123', PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, full_name, password, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'manager', 1, NOW(), NOW())
        ");
        $stmt->execute([$username, $email, $fullName, $password]);
        echo "<p style='color: green;'>✓ Manager user created!</p>";
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'staff'");
    if ($stmt->fetchColumn() == 0) {
        $username = 'staff';
        $email = 'staff@inventory.com';
        $fullName = 'Store Staff';
        $password = password_hash('staff123', PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, full_name, password, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'staff', 1, NOW(), NOW())
        ");
        $stmt->execute([$username, $email, $fullName, $password]);
        echo "<p style='color: green;'>✓ Staff user created!</p>";
    }
    
    // Show all users
    echo "<h3>All Users:</h3>";
    $stmt = $pdo->query("SELECT id, username, email, full_name, role, is_active FROM users ORDER BY id");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
    while ($user = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php'>Go to Login</a></p>";
