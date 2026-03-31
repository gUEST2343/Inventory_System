<?php
// check_pdo_deps.php
echo "PDO PostgreSQL Dependency Check\n";
echo "===============================\n\n";

$ext_dir = ini_get('extension_dir');
$pdo_pgsql_file = $ext_dir . DIRECTORY_SEPARATOR . 'php_pdo_pgsql.dll';

echo "pdo_pgsql.dll location: $pdo_pgsql_file\n";
echo "File exists: " . (file_exists($pdo_pgsql_file) ? "YES" : "NO") . "\n";
echo "File size: " . filesize($pdo_pgsql_file) . " bytes\n\n";

// Check if pgsql is loaded
echo "pgsql extension loaded: " . (extension_loaded('pgsql') ? "YES" : "NO") . "\n";
echo "pdo_pgsql extension loaded: " . (extension_loaded('pdo_pgsql') ? "YES" : "NO") . "\n\n";

if (!extension_loaded('pdo_pgsql') && extension_loaded('pgsql')) {
    echo "Attempting to manually load pdo_pgsql...\n";
    if (function_exists('dl')) {
        if (@dl('php_pdo_pgsql.dll')) {
            echo "✅ Manual load successful!\n";
        } else {
            echo "❌ Manual load failed\n";
            
            // Try to get Windows error
            if (function_exists('win32_last_error_message')) {
                echo "Windows error: " . win32_last_error_message() . "\n";
            }
        }
    }
}

echo "\nCheck complete\n";
?>