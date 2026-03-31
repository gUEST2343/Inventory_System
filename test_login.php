<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing db_connect.php</h2>";

require_once 'db_connect.php';

if ($db_connection_failed) {
    echo "<p style='color:red'>❌ Connection Failed: " . htmlspecialchars($db_error_message) . "</p>";
} else {
    echo "<p style='color:green'>✓ Connection Successful!</p>";
    echo "<p>PDO Object: </p>";
    var_dump($pdo);
}
?>
