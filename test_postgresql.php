<?php
/**
 * test_postgresql.php - Run this to debug connection issues
 */
echo "<h2>PostgreSQL Connection Test</h2>";

// Check if PostgreSQL extensions are loaded
echo "<h3>1. Checking PHP Extensions:</h3>";
$extensions = get_loaded_extensions();
$pdo_pgsql = extension_loaded('pdo_pgsql');
$pgsql = extension_loaded('pgsql');

echo "pdo_pgsql extension: " . ($pdo_pgsql ? '✅ Loaded' : '❌ NOT LOADED') . "<br>";
echo "pgsql extension: " . ($pgsql ? '✅ Loaded' : '❌ NOT LOADED') . "<br>";

if (!$pdo_pgsql) {
    echo "<p style='color: red'>To enable PostgreSQL in XAMPP:</p>";
    echo "<ul>";
    echo "<li>Open C:\\xampp\\php\\php.ini</li>";
    echo "<li>Uncomment: extension=php_pdo_pgsql.dll</li>";
    echo "<li>Uncomment: extension=php_pgsql.dll</li>";
    echo "<li>Restart Apache</li>";
    echo "</ul>";
}

// Test connection
echo "<h3>2. Testing Database Connection:</h3>";
try {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'Inventory_DB';
    $user = 'postgres';
    $password = 'Root';
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password);
    
    echo "✅ Successfully connected to PostgreSQL!<br>";
    echo "Database: " . $dbname . "<br>";
    echo "Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
    
    // Test query
    $result = $pdo->query("SELECT current_database(), current_user, version()");
    $row = $result->fetch();
    echo "Current Database: " . $row['current_database'] . "<br>";
    echo "Current User: " . $row['current_user'] . "<br>";
    
    // Check tables
    echo "<h3>3. Checking Tables:</h3>";
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    echo "<ul>";
    while ($table = $tables->fetch()) {
        echo "<li>" . $table['table_name'] . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "<br>";
    
    // Provide troubleshooting steps
    echo "<h3>Troubleshooting Steps:</h3>";
    echo "<ul>";
    echo "<li>Check if PostgreSQL is running in XAMPP (Click on 'PostgreSQL' Start)</li>";
    echo "<li>Verify port 5432 is not blocked: netstat -an | findstr 5432</li>";
    echo "<li>Check if database 'Inventory_DB' exists: CREATE DATABASE \"Inventory_DB\";</li>";
    echo "<li>Verify credentials: username 'postgres', password 'Root'</li>";
    echo "<li>Check PostgreSQL logs in C:\\xampp\\postgresql\\data\\pg_log</li>";
    echo "</ul>";
}
?>

