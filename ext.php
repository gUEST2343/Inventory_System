<?php
echo "<h2>PostgreSQL Extension Check</h2>";

// Check extensions
echo "<h3>Extension Status:</h3>";
$extensions = ['pdo_pgsql', 'pgsql'];
foreach ($extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? '✅ LOADED' : '❌ NOT LOADED') . "<br>";
}

// Check PDO drivers
echo "<h3>PDO Drivers:</h3>";
if (class_exists('PDO')) {
    $drivers = PDO::getAvailableDrivers();
    echo "Available: " . (empty($drivers) ? 'None' : implode(', ', $drivers)) . "<br>";
}

// Check DLL
echo "<h3>PostgreSQL Client Library:</h3>";
$dllPath = 'C:\php\libpq.dll';
if (file_exists($dllPath)) {
    echo "✅ libpq.dll found at $dllPath<br>";
    $fileInfo = stat($dllPath);
    echo "Size: " . $fileInfo['size'] . " bytes<br>";
    echo "Modified: " . date('Y-m-d H:i:s', $fileInfo['mtime']) . "<br>";
} else {
    echo "❌ libpq.dll NOT found at $dllPath<br>";
    echo "This file is REQUIRED for PostgreSQL connections!<br>";
}

// System path check
echo "<h3>System PATH:</h3>";
$path = getenv('PATH');
$paths = explode(';', $path);
$phpInPath = false;
foreach ($paths as $p) {
    if (stripos($p, 'php') !== false) {
        echo "✓ PHP in PATH: $p<br>";
        $phpInPath = true;
    }
}
if (!$phpInPath) {
    echo "❌ PHP directory not in system PATH<br>";
}
?>