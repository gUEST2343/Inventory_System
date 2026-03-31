<?php
// Simple test to check if dashboard is loading
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test file loading...<br>";

session_start();

echo "Session started<br>";

include 'db_connect.php';

echo "DB connected<br>";

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "DB Connection Failed: PDO connection object is not available.";
} else {
    echo "DB Connected successfully<br>";

    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Users count: " . $count;
}
?>
