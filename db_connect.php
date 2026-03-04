<?php
/**
 * Database Connection File
 * Connects to MySQL using PDO with secure connection settings
 * 
 * @package InventorySystem
 */

// Database configuration
$host = 'localhost';
$port = '3306';
$dbname = 'inventory_system';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

$options = [
    // Enable exceptions for error handling
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    
    // Set default fetch mode to associative array
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // Disable emulated prepared statements for security
    PDO::ATTR_EMULATE_PREPARES => false,
    
    // Set the connection character set
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Set fetch mode globally
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Handle connection errors
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display user-friendly error message (for development)
    // In production, you should log this instead of displaying
    if (getenv('APP_ENV') === 'development') {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact the administrator.");
    }
}

/**
 * Function to get database connection (for reuse)
 * 
 * @return PDO
 */
function getDBConnection() {
    global $pdo;
    return $pdo;
}
