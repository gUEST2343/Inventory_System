<?php
/**
 * Database Connection File
 */

$pdo = $pdo ?? null;
$db_connection_failed = false;
$db_connection_error = '';

try {
    if (!$pdo instanceof PDO) {

        // Render provides DATABASE_URL automatically
        $databaseUrl = getenv('DATABASE_URL');

        if ($databaseUrl) {
            $parsed = parse_url($databaseUrl);
            $host   = $parsed['host'];
            $port   = $parsed['port'] ?? 5432;
            $dbname = ltrim($parsed['path'], '/');
            $user   = $parsed['user'];
            $pass   = $parsed['pass'];

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

        } else {
            // Fall back to individual env vars
            $host     = getenv('DB_HOST')     ?: getenv('PGHOST')     ?: 'localhost';
            $port     = getenv('DB_PORT')     ?: getenv('PGPORT')     ?: '5432';
            $dbname   = getenv('DB_NAME')     ?: getenv('PGDATABASE') ?: getenv('DB_DATABASE') ?: 'Inventory_DB';
            $user     = getenv('DB_USER')     ?: getenv('PGUSER')     ?: getenv('DB_USERNAME') ?: 'postgres';
            $password = getenv('DB_PASSWORD') ?: getenv('PGPASSWORD');
            if ($password === false || $password === null) {
                // Local development fallback for PostgreSQL on localhost
                $password = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                    ? 'Root'
                    : '';
            }

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
    }

    $pdo->exec("SET TIME ZONE 'UTC'");
    $pdo->exec("SET search_path TO public");

    // Schema check for suppliers table
    try {
        $tableStmt = $pdo->prepare("
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'suppliers'
        ");
        $tableStmt->execute();

        if ($tableStmt->fetch()) {
            $columnStmt = $pdo->prepare("
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'public'
                AND table_name = 'suppliers'
                AND column_name = 'is_active'
            ");
            $columnStmt->execute();

            if (!$columnStmt->fetch()) {
                $pdo->exec("ALTER TABLE suppliers ADD COLUMN is_active BOOLEAN DEFAULT true");
            }
        }
    } catch (PDOException $e) {
        error_log("Schema check failed: " . $e->getMessage());
    }

} catch (PDOException $e) {
    $pdo = null;
    $db_connection_failed = true;
    // Surface a helpful error message for debugging while still logging the full exception.
    $db_connection_error = 'Database connection failed: ' . $e->getMessage();
    error_log("PostgreSQL Connection Error: " . $e->getMessage());
}

function getDBConnection() {
    global $pdo;
    return $pdo;
}

function isDBConnected() {
    global $pdo;
    return $pdo instanceof PDO;
}
