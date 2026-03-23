<?php

namespace App\Database;

use PDO;
use PDOException;

class DatabaseConnection
{
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $config = self::loadConfig();
                
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;options=\'--client_encoding=%s\'',
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset'] ?? 'UTF8'
                );
                
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => $config['persistent'] ?? false,
                    ]
                );
                
                // Set schema to public
                self::$instance->exec("SET search_path TO " . ($config['schema'] ?? 'public'));
                
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        
        return self::$instance;
    }
    
    private static function loadConfig(): array
    {
        // Load from environment or config file
        $configFile = __DIR__ . '/../../config/database.php';
        
        if (file_exists($configFile)) {
            $config = require $configFile;
            $connection = $config['connections'][$config['default']] ?? $config['connections']['pgsql'];
            return $connection;
        }
        
        // Fallback to environment variables
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'database' => $_ENV['DB_DATABASE'] ?? 'inventory_db',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? 'Root',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8',
            'schema' => $_ENV['DB_SCHEMA'] ?? 'public',
        ];
    }
    
    public static function disconnect(): void
    {
        self::$instance = null;
    }
}
?>
