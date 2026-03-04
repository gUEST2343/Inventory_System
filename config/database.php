<?php

/**
 * Get environment variable with fallback
 */
function getEnvVar($key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    
    if ($value === false || $value === null) {
        return $default;
    }
    
    // Convert string booleans
    if (strtolower($value) === 'true') {
        return true;
    }
    if (strtolower($value) === 'false') {
        return false;
    }
    
    // Convert string null
    if (strtolower($value) === 'null') {
        return null;
    }
    
    return $value;
}

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */
    'default' => getEnvVar('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => getEnvVar('DB_HOST', '127.0.0.1'),
            'port' => getEnvVar('DB_PORT', '3306'),
            'database' => getEnvVar('DB_DATABASE', 'inventory_db'),
            'username' => getEnvVar('DB_USERNAME', 'root'),
            'password' => getEnvVar('DB_PASSWORD', ''),
            'unix_socket' => getEnvVar('DB_SOCKET', ''),
            'charset' => getEnvVar('DB_CHARSET', 'utf8mb4'),
            'collation' => getEnvVar('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => getEnvVar('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => (bool) getEnvVar('DB_STRICT_MODE', true),
            'engine' => getEnvVar('DB_ENGINE', 'InnoDB'),
            'timezone' => getEnvVar('DB_TIMEZONE', '+00:00'),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                PDO::MYSQL_ATTR_SSL_CA => getEnvVar('DB_SSL_CA'),
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => (bool) getEnvVar('DB_SSL_VERIFY', false),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => getEnvVar('DB_HOST', '127.0.0.1'),
            'port' => getEnvVar('DB_PORT', '5432'),
            'database' => getEnvVar('DB_DATABASE', 'inventory_db'),
            'username' => getEnvVar('DB_USERNAME', 'postgres'),
            'password' => getEnvVar('DB_PASSWORD', ''),
            'charset' => getEnvVar('DB_CHARSET', 'utf8'),
            'prefix' => getEnvVar('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'schema' => getEnvVar('DB_SCHEMA', 'public'),
            'sslmode' => getEnvVar('DB_SSLMODE', 'prefer'),
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => getEnvVar('DB_DATABASE', __DIR__ . '/../database/database.sqlite'),
            'prefix' => getEnvVar('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'foreign_key_constraints' => (bool) getEnvVar('DB_FOREIGN_KEYS', true),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => getEnvVar('DB_HOST', 'localhost'),
            'port' => getEnvVar('DB_PORT', '1433'),
            'database' => getEnvVar('DB_DATABASE', 'inventory_db'),
            'username' => getEnvVar('DB_USERNAME', 'sa'),
            'password' => getEnvVar('DB_PASSWORD', ''),
            'charset' => getEnvVar('DB_CHARSET', 'utf8'),
            'prefix' => getEnvVar('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'encrypt' => getEnvVar('DB_ENCRYPT', 'no'),
            'trust_server_certificate' => (bool) getEnvVar('DB_TRUST_SERVER_CERTIFICATE', false),
        ],

        'testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'client' => getEnvVar('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => getEnvVar('REDIS_CLUSTER', 'redis'),
            'prefix' => getEnvVar('REDIS_PREFIX', 'inventory_database_'),
        ],
        'default' => [
            'host' => getEnvVar('REDIS_HOST', '127.0.0.1'),
            'password' => getEnvVar('REDIS_PASSWORD'),
            'port' => getEnvVar('REDIS_PORT', 6379),
            'database' => (int) getEnvVar('REDIS_DB', 0),
        ],
        'cache' => [
            'host' => getEnvVar('REDIS_HOST', '127.0.0.1'),
            'password' => getEnvVar('REDIS_PASSWORD'),
            'port' => getEnvVar('REDIS_PORT', 6379),
            'database' => (int) getEnvVar('REDIS_CACHE_DB', 1),
        ],
        'session' => [
            'host' => getEnvVar('REDIS_HOST', '127.0.0.1'),
            'password' => getEnvVar('REDIS_PASSWORD'),
            'port' => getEnvVar('REDIS_PORT', 6379),
            'database' => (int) getEnvVar('REDIS_SESSION_DB', 2),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Backup Configuration
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'enabled' => (bool) getEnvVar('DB_BACKUP_ENABLED', false),
        'path' => __DIR__ . '/../storage/backups/database',
        'retention_days' => (int) getEnvVar('DB_BACKUP_RETENTION_DAYS', 30),
        'compress' => (bool) getEnvVar('DB_BACKUP_COMPRESS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => (bool) getEnvVar('DB_LOGGING_ENABLED', false),
        'slow_query_threshold' => (int) getEnvVar('DB_SLOW_QUERY_THRESHOLD', 1000),
        'log_file' => __DIR__ . '/../storage/logs/database.log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Pooling
    |--------------------------------------------------------------------------
    */
    'pool' => [
        'enabled' => (bool) getEnvVar('DB_POOL_ENABLED', false),
        'min_connections' => (int) getEnvVar('DB_POOL_MIN', 1),
        'max_connections' => (int) getEnvVar('DB_POOL_MAX', 10),
        'max_idle_time' => (int) getEnvVar('DB_POOL_IDLE_TIME', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Replication Configuration
    |--------------------------------------------------------------------------
    */
    'replication' => [
        'enabled' => (bool) getEnvVar('DB_REPLICATION_ENABLED', false),
        'write' => [
            'host' => getEnvVar('DB_WRITE_HOST', getEnvVar('DB_HOST', '127.0.0.1')),
            'port' => getEnvVar('DB_WRITE_PORT', getEnvVar('DB_PORT', 3306)),
        ],
        'read' => [
            [
                'host' => getEnvVar('DB_READ_HOST', getEnvVar('DB_HOST', '127.0.0.1')),
                'port' => getEnvVar('DB_READ_PORT', getEnvVar('DB_PORT', 3306)),
            ],
        ],
        'sticky' => (bool) getEnvVar('DB_STICKY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Health Check
    |--------------------------------------------------------------------------
    */
    'health' => [
        'enabled' => (bool) getEnvVar('DB_HEALTH_CHECK_ENABLED', true),
        'check_interval' => (int) getEnvVar('DB_HEALTH_CHECK_INTERVAL', 60),
        'timeout' => (int) getEnvVar('DB_HEALTH_CHECK_TIMEOUT', 5),
    ],
];