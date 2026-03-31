<?php
/**
 * Simple test to check if index.php works
 */
echo "<h1>Testing index.php</h1>";

// Check PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";

// Check if session works
session_start();
echo "<p>Session ID: " . session_id() . "</p>";

// Simulate logged in user
$_SESSION['logged_in'] = true;
$_SESSION['full_name'] = 'Admin User';
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;

echo "<p>Session set: logged_in = true</p>";

// Try to include db_connect
echo "<p>Including db_connect.php...</p>";
require_once 'db_connect.php';

if (isset($pdo) && $pdo !== null) {
    echo "<p style='color:green'>Database connected!</p>";
    
    // Test a simple query
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM products");
        $result = $stmt->fetch();
        echo "<p>Products count: " . $result['cnt'] . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>Query error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>Database NOT connected!</p>";
}

// Check if module files exist
$modules = ['dashboard', 'products', 'inventory', 'orders', 'customers', 'suppliers', 'reports', 'settings'];
echo "<h3>Module Files:</h3><ul>";
foreach ($modules as $mod) {
    $file = "modules/$mod.php";
    if (file_exists($file)) {
        echo "<li style='color:green'>$mod.php exists</li>";
    } else {
        echo "<li style='color:red'>$mod.php MISSING</li>";
    }
}
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<p>To test the full dashboard:</p>";
echo "<ol>";
echo "<li>Make sure you're logged in (visit login.php first)</li>";
echo "<li>Then visit index.php?page=dashboard</li>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Check Apache/PHP error logs</li>";
echo "</ol>";
?>

