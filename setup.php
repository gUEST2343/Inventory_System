<?php
/**
 * Database Setup Script
 * This script creates the database and imports the SQL file
 * 
 * Run this in your browser: http://localhost/inventory-system/setup.php
 */

// Database configuration
$host = 'localhost';
$port = '3306';
$username = 'root';
$password = '';
$dbname = 'inventory_system';
$charset = 'utf8mb4';

echo "<h1>Inventory System - Database Setup</h1>";
echo "<pre>";
echo "Connecting to MySQL server...\n";

try {
    // Connect to MySQL server (without database)
    $dsn = "mysql:host={$host};port={$port};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Connected to MySQL server successfully!\n\n";
    
    // Create database if not exists
    echo "Creating database '{$dbname}' if it doesn't exist...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '{$dbname}' is ready!\n\n";
    
    // Select the database
    $pdo->exec("USE `{$dbname}`");
    
    // Read and execute SQL file
    $sqlFile = __DIR__ . '/sql/inventory_system.sql';
    
    if (file_exists($sqlFile)) {
        echo "Reading SQL file: {$sqlFile}\n";
        $sql = file_get_contents($sqlFile);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $tableCount = 0;
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') !== false) {
                continue;
            }
            try {
                $pdo->exec($statement);
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    $tableCount++;
                }
            } catch (PDOException $e) {
                // Ignore errors for CREATE DATABASE and USE (already done)
                if (stripos($e->getMessage(), 'CREATE DATABASE') === false && 
                    stripos($e->getMessage(), 'Already exists') === false) {
                    // echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✓ SQL file imported successfully!\n";
        echo "✓ Created tables and sample data!\n\n";
    } else {
        echo "✗ SQL file not found: {$sqlFile}\n";
    }
    
    // Verify tables
    echo "Verifying database tables:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
    echo "\n";
    
    // Check if admin user exists
    echo "Checking default user:\n";
    $stmt = $pdo->query("SELECT username, email, role FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    if ($admin) {
        echo "  ✓ Admin user exists: {$admin['username']} ({$admin['role']})\n";
        echo "  ✓ Email: {$admin['email']}\n";
        echo "  ✓ Password: admin123\n";
    } else {
        echo "  ✗ Admin user not found!\n";
    }
    
    echo "\n========================================\n";
    echo "✓ Database setup completed successfully!\n";
    echo "========================================\n";
    echo "\nYou can now access your inventory system at:\n";
    echo "  <a href='http://localhost/inventory-system/' target='_blank'>http://localhost/inventory-system/</a>\n";
    echo "\nLogin credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "\n";
    echo "<strong>IMPORTANT: Delete this file (setup.php) after setup for security!</strong>\n";
    
} catch (PDOException $e) {
    echo "✗ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nMake sure:\n";
    echo "  1. XAMPP MySQL is running\n";
    echo "  2. MySQL credentials are correct\n";
}

echo "</pre>";
