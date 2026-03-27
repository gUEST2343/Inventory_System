<?php

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo "Database connection is not available.\n";
    exit(1);
}

$driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$migrationFile = match ($driver) {
    'mysql' => __DIR__ . '/../sql/payment_sync_mysql.sql',
    default => __DIR__ . '/../sql/payment_sync_postgresql.sql',
};

if (!is_file($migrationFile)) {
    http_response_code(500);
    echo "Migration file not found: {$migrationFile}\n";
    exit(1);
}

$sql = file_get_contents($migrationFile);
if ($sql === false) {
    http_response_code(500);
    echo "Unable to read migration file.\n";
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "Payment sync migration applied successfully using {$driver}.\n";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
