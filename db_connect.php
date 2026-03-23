<?php
/**
 * Database Connection File
 * Connects to PostgreSQL using PDO with secure connection settings
 *
 * @package InventorySystem
 */

$pdo = $pdo ?? null;
$db_connection_failed = false;
$db_connection_error = '';

try {
    if (!$pdo instanceof PDO) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'Inventory_DB');
        $user = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'postgres');
        $passwordFromEnv = getenv('DB_PASSWORD');
        $passwordCandidates = $passwordFromEnv !== false ? [$passwordFromEnv] : ['Root', ''];
        $lastConnectionError = null;

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        foreach ($passwordCandidates as $password) {
            try {
                $pdo = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $lastConnectionError = null;
                break;
            } catch (PDOException $candidateError) {
                $lastConnectionError = $candidateError;
                $pdo = null;
            }
        }

        if (!$pdo instanceof PDO && $lastConnectionError instanceof PDOException) {
            throw $lastConnectionError;
        }
    }

    $pdo->exec("SET TIME ZONE 'UTC'");
    $pdo->exec("SET search_path TO public");

    try {
        $tableStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = 'suppliers'
        ");
        $tableStmt->execute();

        if ($tableStmt->fetch()) {
            $columnStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
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
        error_log("Schema check failed for suppliers.is_active: " . $e->getMessage());
    }
} catch (PDOException $e) {
    $pdo = null;
    $db_connection_failed = true;
    $db_connection_error = 'Database connection failed. Confirm PostgreSQL is running and your credentials are correct.';
    error_log("PostgreSQL Connection Error: " . $e->getMessage());
}

/**
 * Function to get database connection (for reuse)
 *
 * @return PDO|null
 */
function getDBConnection()
{
    global $pdo;
    return $pdo;
}

/**
 * Function to check if database is connected
 *
 * @return bool
 */
function isDBConnected()
{
    global $pdo;
    return $pdo instanceof PDO;
}

