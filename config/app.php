<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => isset($_ENV['APP_NAME']) ? $_ENV['APP_NAME'] : 'Inventory Management System',
    
    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'env' => isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'production',
    
    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => isset($_ENV['APP_DEBUG']) ? filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) : false,
    
    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    'url' => isset($_ENV['APP_URL']) ? $_ENV['APP_URL'] : 'http://localhost',
    
    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => isset($_ENV['APP_TIMEZONE']) ? $_ENV['APP_TIMEZONE'] : 'UTC',
    
    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    */
    'locale' => isset($_ENV['APP_LOCALE']) ? $_ENV['APP_LOCALE'] : 'en',
    'fallback_locale' => isset($_ENV['APP_FALLBACK_LOCALE']) ? $_ENV['APP_FALLBACK_LOCALE'] : 'en',
    
    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */
    'key' => isset($_ENV['APP_KEY']) ? $_ENV['APP_KEY'] : null,
    'cipher' => 'AES-256-CBC',
    
    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        // Core providers
        'App\Providers\DatabaseServiceProvider',
        'App\Providers\ValidationServiceProvider',
        'App\Providers\RepositoryServiceProvider',
        'App\Providers\ServiceServiceProvider',
        
        // Third-party providers
        // Add any third-party providers here
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'DB' => 'App\Database\DatabaseManager',
        'Validator' => 'App\Services\ValidationService',
        'Audit' => 'App\Services\AuditService',
        'Inventory' => 'App\Services\InventoryService',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Application Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'global' => [
            'App\Middleware\CorsMiddleware',
            'App\Middleware\JsonMiddleware',
            'App\Middleware\LoggingMiddleware',
        ],
        
        'api' => [
            'App\Middleware\AuthMiddleware',
            'App\Middleware\ValidationMiddleware',
            'App\Middleware\AuditMiddleware',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    */
    'table_prefix' => isset($_ENV['DB_TABLE_PREFIX']) ? $_ENV['DB_TABLE_PREFIX'] : '',
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => isset($_ENV['RATE_LIMITING_ENABLED']) ? 
            filter_var($_ENV['RATE_LIMITING_ENABLED'], FILTER_VALIDATE_BOOLEAN) : true,
        'max_requests' => isset($_ENV['RATE_LIMIT_MAX_REQUESTS']) ? 
            (int)$_ENV['RATE_LIMIT_MAX_REQUESTS'] : 100,
        'window_minutes' => isset($_ENV['RATE_LIMIT_WINDOW_MINUTES']) ? 
            (int)$_ENV['RATE_LIMIT_WINDOW_MINUTES'] : 1,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allowed_origins' => isset($_ENV['CORS_ALLOWED_ORIGINS']) ? 
            explode(',', $_ENV['CORS_ALLOWED_ORIGINS']) : ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => isset($_ENV['LOG_CHANNEL']) ? $_ENV['LOG_CHANNEL'] : 'daily',
        'level' => isset($_ENV['LOG_LEVEL']) ? $_ENV['LOG_LEVEL'] : 'error',
        'path' => isset($_ENV['LOG_PATH']) ? $_ENV['LOG_PATH'] : __DIR__ . '/../storage/logs/app.log',
        'max_files' => isset($_ENV['LOG_MAX_FILES']) ? (int)$_ENV['LOG_MAX_FILES'] : 30,
        
        // Custom log channels
        'channels' => [
            'audit' => [
                'path' => __DIR__ . '/../storage/logs/audit.log',
                'level' => 'info',
            ],
            'stock' => [
                'path' => __DIR__ . '/../storage/logs/stock.log',
                'level' => 'info',
            ],
            'error' => [
                'path' => __DIR__ . '/../storage/logs/error.log',
                'level' => 'error',
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'driver' => isset($_ENV['CACHE_DRIVER']) ? $_ENV['CACHE_DRIVER'] : 'file',
        'path' => isset($_ENV['CACHE_PATH']) ? $_ENV['CACHE_PATH'] : __DIR__ . '/../storage/cache',
        'ttl' => isset($_ENV['CACHE_TTL']) ? (int)$_ENV['CACHE_TTL'] : 3600, // 1 hour
        
        // Redis configuration (if using redis)
        'redis' => [
            'host' => isset($_ENV['REDIS_HOST']) ? $_ENV['REDIS_HOST'] : '127.0.0.1',
            'port' => isset($_ENV['REDIS_PORT']) ? (int)$_ENV['REDIS_PORT'] : 6379,
            'password' => isset($_ENV['REDIS_PASSWORD']) ? $_ENV['REDIS_PASSWORD'] : null,
            'database' => isset($_ENV['REDIS_DATABASE']) ? (int)$_ENV['REDIS_DATABASE'] : 0,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'driver' => isset($_ENV['SESSION_DRIVER']) ? $_ENV['SESSION_DRIVER'] : 'file',
        'lifetime' => isset($_ENV['SESSION_LIFETIME']) ? (int)$_ENV['SESSION_LIFETIME'] : 120,
        'path' => isset($_ENV['SESSION_PATH']) ? $_ENV['SESSION_PATH'] : __DIR__ . '/../storage/sessions',
        'cookie' => isset($_ENV['SESSION_COOKIE']) ? $_ENV['SESSION_COOKIE'] : 'inventory_session',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'strict_mode' => isset($_ENV['VALIDATION_STRICT_MODE']) ? 
            filter_var($_ENV['VALIDATION_STRICT_MODE'], FILTER_VALIDATE_BOOLEAN) : true,
        'custom_messages' => [
            'required' => 'The :attribute field is required.',
            'numeric' => 'The :attribute must be a number.',
            'min' => 'The :attribute must be at least :min.',
            'max' => 'The :attribute may not be greater than :max.',
            'regex' => 'The :attribute format is invalid.',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Inventory Configuration
    |--------------------------------------------------------------------------
    */
    'inventory' => [
        'max_quantity' => isset($_ENV['INVENTORY_MAX_QUANTITY']) ? 
            (int)$_ENV['INVENTORY_MAX_QUANTITY'] : 10000,
        'max_adjustment' => isset($_ENV['INVENTORY_MAX_ADJUSTMENT']) ? 
            (int)$_ENV['INVENTORY_MAX_ADJUSTMENT'] : 1000,
        'safety_stock_default' => isset($_ENV['SAFETY_STOCK_DEFAULT']) ? 
            (int)$_ENV['SAFETY_STOCK_DEFAULT'] : 10,
        'low_stock_threshold' => isset($_ENV['LOW_STOCK_THRESHOLD']) ? 
            (int)$_ENV['LOW_STOCK_THRESHOLD'] : 20,
        
        // Barcode configuration
        'barcode' => [
            'min_length' => 12,
            'max_length' => 14,
            'validate_check_digit' => true,
            'allowed_types' => ['EAN-13', 'UPC-A', 'UPC-E'],
        ],
        
        // Audit configuration
        'audit' => [
            'keep_days' => isset($_ENV['AUDIT_KEEP_DAYS']) ? 
                (int)$_ENV['AUDIT_KEEP_DAYS'] : 365, // Keep audit logs for 1 year
            'log_all_changes' => isset($_ENV['AUDIT_LOG_ALL_CHANGES']) ? 
                filter_var($_ENV['AUDIT_LOG_ALL_CHANGES'], FILTER_VALIDATE_BOOLEAN) : true,
            'suspicious_activity_threshold' => [
                'adjustments_per_hour' => 50,
                'quantity_per_hour' => 1000,
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'driver' => isset($_ENV['MAIL_DRIVER']) ? $_ENV['MAIL_DRIVER'] : 'smtp',
        'host' => isset($_ENV['MAIL_HOST']) ? $_ENV['MAIL_HOST'] : 'smtp.mailtrap.io',
        'port' => isset($_ENV['MAIL_PORT']) ? (int)$_ENV['MAIL_PORT'] : 2525,
        'username' => isset($_ENV['MAIL_USERNAME']) ? $_ENV['MAIL_USERNAME'] : '',
        'password' => isset($_ENV['MAIL_PASSWORD']) ? $_ENV['MAIL_PASSWORD'] : '',
        'encryption' => isset($_ENV['MAIL_ENCRYPTION']) ? $_ENV['MAIL_ENCRYPTION'] : 'tls',
        'from' => [
            'address' => isset($_ENV['MAIL_FROM_ADDRESS']) ? $_ENV['MAIL_FROM_ADDRESS'] : 'noreply@inventory.com',
            'name' => isset($_ENV['MAIL_FROM_NAME']) ? $_ENV['MAIL_FROM_NAME'] : 'Inventory System',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'version' => 'v1',
        'prefix' => 'api',
        'rate_limit' => 60, // requests per minute
        'auth' => [
            'type' => 'jwt', // jwt, token, basic
            'token_expiry' => 3600, // 1 hour in seconds
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Path Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'storage' => __DIR__ . '/../storage',
        'logs' => __DIR__ . '/../storage/logs',
        'cache' => __DIR__ . '/../storage/cache',
        'sessions' => __DIR__ . '/../storage/sessions',
        'uploads' => __DIR__ . '/../storage/uploads',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'password_hashing' => [
            'algorithm' => PASSWORD_BCRYPT,
            'cost' => 12,
        ],
        'csrf_protection' => true,
        'xss_protection' => true,
        'hsts_enabled' => true,
    ],
];