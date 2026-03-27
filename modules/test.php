<?php
/**
 * modules/test.php - Diagnostic module to identify issues
 */
if (!isset($pdo) || basename($_SERVER['PHP_SELF']) == 'test.php') {
    header('Location: ../admin.php');
    exit;
}

// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<div class='container-fluid'>";
echo "<h2><i class='fas fa-stethoscope'></i> Module Diagnostic</h2>";

// Check 1: PHP Version and Settings
echo "<div class='card mb-3'>";
echo "<div class='card-header bg-info text-white'><h5 class='mb-0'><i class='fas fa-info-circle'></i> PHP Information</h5></div>";
echo "<div class='card-body'>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>Display Errors:</strong> " . (ini_get('display_errors') ? 'ON' : 'OFF') . "<br>";
echo "<strong>Error Reporting:</strong> " . error_reporting() . "<br>";
echo "</div>";

// Check 2: Database Connection
echo "<div class='card mb-3'>";
echo "<div class='card-header bg-primary text-white'><h5 class='mb-0'><i class='fas fa-database'></i> Database Connection</h5></div>";
echo "<div class='card-body'>";
if (isset($pdo) && $pdo !== null) {
    echo "<span class='text-success'><i class='fas fa-check-circle'></i> PDO Object: Available</span><br>";
    try {
        $stmt = $pdo->query("SELECT current_database()");
        $dbname = $stmt->fetchColumn();
        echo "<span class='text-success'><i class='fas fa-check-circle'></i> Connected to Database: " . $dbname . "</span><br>";
        
        // List all tables
        $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        echo "<strong>Tables in database:</strong><br>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . $table . "</li>";
        }
        echo "</ul>";
    } catch (PDOException $e) {
        echo "<span class='text-danger'><i class='fas fa-times-circle'></i> Database Error: " . $e->getMessage() . "</span><br>";
    }
} else {
    echo "<span class='text-danger'><i class='fas fa-times-circle'></i> PDO Object: NOT Available</span><br>";
}
echo "</div>";

// Check 3: Module File Status
echo "<div class='card mb-3'>";
echo "<div class='card-header bg-secondary text-white'><h5 class='mb-0'><i class='fas fa-folder'></i> Module Files</h5></div>";
echo "<div class='card-body'>";
$modules = ['dashboard', 'products', 'inventory', 'orders', 'customers', 'suppliers', 'reports', 'settings'];
foreach ($modules as $module) {
    $file = __DIR__ . "/$module.php";
    if (file_exists($file)) {
        $size = filesize($file);
        $content = file_get_contents($file);
        $hasContent = strlen(trim($content)) > 0;
        if ($hasContent && $size > 100) {
            echo "<span class='text-success'><i class='fas fa-check-circle'></i> $module.php: OK ($size bytes)</span><br>";
        } else {
            echo "<span class='text-warning'><i class='fas fa-exclamation-triangle'></i> $module.php: Empty or too small ($size bytes)</span><br>";
        }
    } else {
        echo "<span class='text-danger'><i class='fas fa-times-circle'></i> $module.php: MISSING</span><br>";
    }
}
echo "</div>";

// Check 4: Session Data
echo "<div class='card mb-3'>";
echo "<div class='card-header bg-dark text-white'><h5 class='mb-0'><i class='fas fa-user-circle'></i> Session Data</h5></div>";
echo "<div class='card-body'>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Started:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "<br>";
echo "<strong>User Logged In:</strong> " . (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] ? 'Yes' : 'No') . "<br>";
if (isset($_SESSION['user_id'])) {
    echo "<strong>User ID:</strong> " . $_SESSION['user_id'] . "<br>";
}
if (isset($_SESSION['user_role'])) {
    echo "<strong>User Role:</strong> " . $_SESSION['user_role'] . "<br>";
}
echo "</div>";

// Check 5: Test Query
echo "<div class='card mb-3'>";
echo "<div class='card-header bg-success text-white'><h5 class='mb-0'><i class='fas fa-search'></i> Database Queries Test</h5></div>";
echo "<div class='card-body'>";

$tests = [
    'products' => "SELECT COUNT(*) FROM products",
    'categories' => "SELECT COUNT(*) FROM categories",
    'suppliers' => "SELECT COUNT(*) FROM suppliers",
    'orders' => "SELECT COUNT(*) FROM orders",
    'users' => "SELECT COUNT(*) FROM users"
];

foreach ($tests as $name => $query) {
    try {
        $stmt = $pdo->query($query);
        $count = $stmt->fetchColumn();
        echo "<span class='text-success'><i class='fas fa-check-circle'></i> $name table: $count records</span><br>";
    } catch (PDOException $e) {
        echo "<span class='text-danger'><i class='fas fa-times-circle'></i> $name table: " . $e->getMessage() . "</span><br>";
    }
}

echo "</div>";

echo "</div>";

echo "</div>";
