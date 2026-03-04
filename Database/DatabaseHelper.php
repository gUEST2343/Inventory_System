<?php

namespace App\Database;

class DatabaseHelper
{
    /**
     * Get PDO connection from config
     */
    public static function getConnection(): \PDO
    {
        $config = self::getDatabaseConfig();
        
        $dsn = self::buildDsn($config);
        
        try {
            $pdo = new \PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? []
            );
            
            // Set common attributes
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            
            return $pdo;
            
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
    
    private static function getDatabaseConfig(): array
    {
        $configFile = __DIR__ . '/../config/database.php';
        
        if (file_exists($configFile)) {
            $config = require $configFile;
            $connection = $config['connections'][$config['default']] ?? $config['connections']['mysql'];
            return $connection;
        }
        
        // Fallback to environment variables
        return [
            'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'inventory_db',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        ];
    }
    
    private static function buildDsn(array $config): string
    {
        switch ($config['driver']) {
            case 'mysql':
                return sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset']
                );
                
            case 'pgsql':
                return sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $config['host'],
                    $config['port'],
                    $config['database']
                );
                
            case 'sqlite':
                return sprintf('sqlite:%s', $config['database']);
                
            default:
                throw new \InvalidArgumentException('Unsupported database driver: ' . $config['driver']);
        }
    }
    
    /**
     * Test database connection
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getConnection();
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            
            return [
                'success' => true,
                'message' => 'Database connection successful',
                'version' => $version,
                'driver' => $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'CONNECTION_FAILED',
            ];
        }
    }
    
    /**
     * Create database if not exists
     */
    public static function createDatabaseIfNotExists(): void
    {
        $config = self::getDatabaseConfig();
        
        if ($config['driver'] !== 'mysql') {
            return; // Only works for MySQL
        }
        
        // Connect without database name
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset']
        );
        
        try {
            $pdo = new \PDO($dsn, $config['username'], $config['password']);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` 
                       CHARACTER SET {$config['charset']} 
                       COLLATE {$config['charset']}_unicode_ci");
            
            echo "Database '{$config['database']}' created or already exists.\n";
        } catch (\PDOException $e) {
            throw new \RuntimeException('Failed to create database: ' . $e->getMessage());
        }
    }
}