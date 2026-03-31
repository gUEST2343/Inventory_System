<?php
/**
 * Database Connection Test Script
 * Run this to check if your database connection is working
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

// Check if PDO PostgreSQL extension is loaded
echo "<h3>1. Checking PDO PostgreSQL Extension</h3>";
if (extension_loaded('pdo_pgsql')) {
    echo "<p style='color: green;'>✓ PDO PostgreSQL extension is loaded</p>";
} else {
    echo "<p style='color: red;'>✗ PDO PostgreSQL extension is NOT loaded</p>";
    echo "<p>Solution: Enable pdo_pgsql extension in php.ini</p>";
}

// Check PostgreSQL service
echo "<h3>2. Checking PostgreSQL Service</h3>";
$connection = @fsockopen('localhost', 5432, $errno, $errstr, 5);
if ($connection) {
    echo "<p style='color: green;'>✓ PostgreSQL is running on port 5432</p>";
    fclose($connection);
} else {
    echo "<p style='color: red;'>✗ Cannot connect to PostgreSQL on port 5432</p>";
    echo "<p>Solution: Start PostgreSQL service</p>";
}

// Try to connect to database
echo "<h3>3. Testing Database Connection</h3>";
$host = 'localhost';
$port = '5432';
$dbname = 'Inventory_DB';
$username = 'postgres';
$password = 'Root';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ Successfully connected to database: $dbname</p>";
    
    // Test query
    echo "<h3>4. Testing Query</h3>";
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "<p>PostgreSQL Version: $version</p>";
    
    // Check if users table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'users'");
    if ($stmt->fetchColumn() > 0) {
        echo "<p style='color: green;'>✓ Users table exists</p>";
        
        // Check if there's at least one user
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        echo "<p>Number of users: $userCount</p>";
        
        if ($userCount > 0) {
            // Show existing users (passwords hidden)
            $stmt = $pdo->query("SELECT id, username, email, role FROM users LIMIT 5");
            echo "<h4>Existing Users:</h4>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
            while ($user = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . $user['id'] . "</td>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . $user['role'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠ No users found. You need to create an admin user.</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Users table does not exist</p>";
        echo "<p>Solution: Run the SQL setup files to create the database tables.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    
    echo "<h3>Possible Solutions:</h3>";
    echo "<ul>";
    echo "<li>Make sure PostgreSQL is running</li>";
    echo "<li>Check if database 'Inventory_DB' exists</li>";
    echo "<li>Verify username and password</li>";
    echo "<li>Create database: <code>CREATE DATABASE \"Inventory_DB\";</code></li>";
    echo "<li>Run setup.php to create database and tables</li>";
    echo "</ul>";
    
    echo "<h3>Quick Fix:</h3>";
    echo "<p>Go to <a href='setup.php'>setup.php</a> to create the database and tables.</p>";
}

echo "<h3>Quick Fix Commands:</h3>";
echo "<pre>";
echo "# Start PostgreSQL (Windows)\n";
echo "net start postgresql-x64-16\n\n";
echo "# Or using XAMPP\n";
echo "# Start Apache and PostgreSQL from XAMPP Control Panel\n\n";
echo "# Create database (run in pgAdmin or psql)\n";
echo "CREATE DATABASE \"Inventory_DB\";\n\n";
echo "# Run setup.php instead:\n";
echo "http://localhost/inventory-system/setup.php\n";
echo "</pre>";
?>
