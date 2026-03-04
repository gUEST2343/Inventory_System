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
                    'mysql:host=%s;dbname=%s;charset=%s',
                    $config['host'],
                    $config['database'],
                    $config['charset']
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
                
                // Set timezone
                self::$instance->exec("SET time_zone = '" . $config['timezone'] . "'");
                
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
            return require $configFile;
        }
        
        // Fallback to environment variables
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_DATABASE'] ?? 'inventory_db',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'timezone' => $_ENV['DB_TIMEZONE'] ?? '+00:00',
        ];
    }
    
    public static function disconnect(): void
    {
        self::$instance = null;
    }
}