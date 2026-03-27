<?php
/**
 * Application Constants
 * Global constants for the Inventory System
 */

// Database Tables
define('TABLE_USERS', 'users');
define('TABLE_CATEGORIES', 'categories');
define('TABLE_PRODUCTS', 'products');
define('TABLE_STOCK_LOGS', 'stock_logs');
define('TABLE_ORDERS', 'orders');
define('TABLE_ORDER_ITEMS', 'order_items');
define('TABLE_PAYMENTS', 'payments');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_STAFF', 'staff');
define('ROLE_CUSTOMER', 'customer');

// User Roles Array
define('USER_ROLES', [
    ROLE_ADMIN => 'Administrator',
    ROLE_MANAGER => 'Manager',
    ROLE_STAFF => 'Staff',
    ROLE_CUSTOMER => 'Customer'
]);

// Stock Actions
define('STOCK_ACTION_ADD', 'add');
define('STOCK_ACTION_REMOVE', 'remove');
define('STOCK_ACTION_ADJUST', 'adjust');
define('STOCK_ACTION_SALE', 'sale');
define('STOCK_ACTION_RETURN', 'return');
define('STOCK_ACTION_TRANSFER', 'transfer');

// Stock Status
define('STATUS_IN_STOCK', 'in_stock');
define('STATUS_LOW_STOCK', 'low_stock');
define('STATUS_OUT_OF_STOCK', 'out_of_stock');
define('STATUS_OVERSTOCKED', 'overstocked');

// Order Status
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_CANCELLED', 'cancelled');
define('ORDER_STATUS_REFUNDED', 'refunded');

// Payment Status
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_COMPLETED', 'completed');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_REFUNDED', 'refunded');

// Payment Methods
define('PAYMENT_METHOD_MPESA', 'mpesa');
define('PAYMENT_METHOD_CARD', 'card');
define('PAYMENT_METHOD_CASH', 'cash');
define('PAYMENT_METHOD_BANK', 'bank');

// M-Pesa Constants
define('MPESA_ENVIRONMENT', 'sandbox'); // sandbox or production
define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE') ?: '174379');
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: 'your_consumer_key');
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: 'your_consumer_secret');
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: 'your_passkey');
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: 'http://localhost/api/mpesa/callback.php');

// Application Settings
define('APP_NAME', getenv('APP_NAME') ?: 'Inventory System');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Africa/Nairobi');
define('APP_LOCALE', 'en_US');

// Pagination
define('ITEMS_PER_PAGE', 25);
define('ITEMS_PER_PAGE_OPTIONS', [10, 25, 50, 100]);

// Session Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('REMEMBER_ME_DAYS', 30);

// Security
define('HASH_ALGORITHM', 'bcrypt');
define('MIN_PASSWORD_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds

// File Upload
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Date/Time Formats
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M d, Y');
define('DISPLAY_DATETIME_FORMAT', 'M d, Y H:i');

// Currency
define('DEFAULT_CURRENCY', 'KES');
define('CURRENCY_SYMBOL', 'KSh ');
define('DECIMAL_PLACES', 2);

// Error Codes
define('ERROR_NONE', 0);
define('ERROR_GENERAL', 1);
define('ERROR_NOT_FOUND', 2);
define('ERROR_UNAUTHORIZED', 3);
define('ERROR_FORBIDDEN', 4);
define('ERROR_VALIDATION', 5);
define('ERROR_DATABASE', 6);
define('ERROR_PAYMENT', 7);

// Status Codes
define('STATUS_ACTIVE', 1);
define('STATUS_INACTIVE', 0);
define('STATUS_DELETED', -1);

// Email Settings
define('MAIL_ENABLED', false);
define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.mailtrap.io');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 2525);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@inventorysystem.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Inventory System');

// API Settings
define('API_ENABLED', true);
define('API_VERSION', 'v1');
define('API_PREFIX', '/api');

// Logging
define('LOG_ENABLED', true);
define('LOG_PATH', __DIR__ . '/../logs/');
define('LOG_LEVEL', 'error'); // debug, info, warning, error

// Cache
define('CACHE_ENABLED', false);
define('CACHE_PATH', __DIR__ . '/../cache/');
define('CACHE_LIFETIME', 3600); // 1 hour

// CSRF Token
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);
?>
