<?php
/**
 * Get environment variable with fallback
 */
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        // Handle string booleans and null
        $lowerValue = strtolower($value);
        if ($lowerValue === 'true') {
            return true;
        }
        if ($lowerValue === 'false') {
            return false;
        }
        if ($lowerValue === 'null') {
            return null;
        }

        return $value;
    }
}

/**
 * Get the path to the resources directory
 */
if (!function_exists('resource_path')) {
    function resource_path($path = '')
    {
        $basePath = dirname(__DIR__) . '/resources';
        return $path ? $basePath . '/' . trim($path, '/') : $basePath;
    }
}

/**
 * Parse comma-separated string to array
 */
if (!function_exists('parseEnvArray')) {
    function parseEnvArray($value, $default = [])
    {
        if ($value === null || $value === false || $value === '') {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return $default;
        }

        $values = array_map('trim', explode(',', $value));
        $values = array_values(array_filter($values, static fn($v) => $v !== ''));

        return $values ?: $default;
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | General Validation Settings
    |--------------------------------------------------------------------------
    */
    'general' => [
        'strict_mode' => filter_var(env('VALIDATION_STRICT_MODE', true), FILTER_VALIDATE_BOOLEAN),
        'stop_on_first_failure' => filter_var(env('VALIDATION_STOP_ON_FIRST_FAILURE', false), FILTER_VALIDATE_BOOLEAN),
        'escape_validation_messages' => filter_var(env('VALIDATION_ESCAPE_MESSAGES', true), FILTER_VALIDATE_BOOLEAN),
        'trim_strings' => filter_var(env('VALIDATION_TRIM_STRINGS', true), FILTER_VALIDATE_BOOLEAN),
        'convert_empty_strings_to_null' => filter_var(env('VALIDATION_CONVERT_EMPTY_TO_NULL', true), FILTER_VALIDATE_BOOLEAN),
        'skip_validation_on_errors' => filter_var(env('SKIP_VALIDATION_ON_ERRORS', false), FILTER_VALIDATE_BOOLEAN),
        'validate_unrecognized_fields' => filter_var(env('VALIDATE_UNRECOGNIZED_FIELDS', true), FILTER_VALIDATE_BOOLEAN),
        'max_nesting_level' => (int) env('VALIDATION_MAX_NESTING_LEVEL', 10),
        'parallel_validation' => filter_var(env('PARALLEL_VALIDATION', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Validation Messages
    |--------------------------------------------------------------------------
    */
    'messages' => [
        // Required
        'required' => 'The :attribute field is required.',
        'required_if' => 'The :attribute field is required when :other is :value.',
        'required_unless' => 'The :attribute field is required unless :other is in :values.',
        'required_with' => 'The :attribute field is required when :values is present.',
        'required_with_all' => 'The :attribute field is required when :values are present.',
        'required_without' => 'The :attribute field is required when :values is not present.',
        'required_without_all' => 'The :attribute field is required when none of :values are present.',
        'filled' => 'The :attribute field must have a value.',
        'present' => 'The :attribute field must be present.',

        // Type
        'string' => 'The :attribute must be a string.',
        'numeric' => 'The :attribute must be a number.',
        'integer' => 'The :attribute must be an integer.',
        'float' => 'The :attribute must be a float.',
        'boolean' => 'The :attribute field must be true or false.',
        'array' => 'The :attribute must be an array.',
        'object' => 'The :attribute must be an object.',
        'file' => 'The :attribute must be a file.',
        'image' => 'The :attribute must be an image.',

        // Size
        'min' => [
            'numeric' => 'The :attribute must be at least :min.',
            'string' => 'The :attribute must be at least :min characters.',
            'array' => 'The :attribute must have at least :min items.',
            'file' => 'The :attribute must be at least :min kilobytes.',
        ],
        'max' => [
            'numeric' => 'The :attribute may not be greater than :max.',
            'string' => 'The :attribute may not be greater than :max characters.',
            'array' => 'The :attribute may not have more than :max items.',
            'file' => 'The :attribute may not be greater than :max kilobytes.',
        ],
        'between' => [
            'numeric' => 'The :attribute must be between :min and :max.',
            'string' => 'The :attribute must be between :min and :max characters.',
            'array' => 'The :attribute must have between :min and :max items.',
            'file' => 'The :attribute must be between :min and :max kilobytes.',
        ],
        'size' => [
            'numeric' => 'The :attribute must be :size.',
            'string' => 'The :attribute must be :size characters.',
            'array' => 'The :attribute must contain :size items.',
            'file' => 'The :attribute must be :size kilobytes.',
        ],

        // Format
        'email' => 'The :attribute must be a valid email address.',
        'url' => 'The :attribute must be a valid URL.',
        'ip' => 'The :attribute must be a valid IP address.',
        'ipv4' => 'The :attribute must be a valid IPv4 address.',
        'ipv6' => 'The :attribute must be a valid IPv6 address.',
        'mac_address' => 'The :attribute must be a valid MAC address.',
        'regex' => 'The :attribute format is invalid.',
        'date' => 'The :attribute is not a valid date.',
        'date_format' => 'The :attribute does not match the format :format.',
        'date_equals' => 'The :attribute must be a date equal to :date.',
        'before' => 'The :attribute must be a date before :date.',
        'before_or_equal' => 'The :attribute must be a date before or equal to :date.',
        'after' => 'The :attribute must be a date after :date.',
        'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
        'timezone' => 'The :attribute must be a valid timezone.',

        // Database
        'unique' => 'The :attribute has already been taken.',
        'exists' => 'The selected :attribute is invalid.',
        'in' => 'The selected :attribute is invalid.',
        'not_in' => 'The selected :attribute is invalid.',
        'distinct' => 'The :attribute has a duplicate value.',

        // Confirmation
        'confirmed' => 'The :attribute confirmation does not match.',
        'same' => 'The :attribute and :other must match.',
        'different' => 'The :attribute and :other must be different.',

        // Digits
        'digits' => 'The :attribute must be :digits digits.',
        'digits_between' => 'The :attribute must be between :min and :max digits.',

        // JSON
        'json' => 'The :attribute must be a valid JSON string.',

        // File
        'mimes' => 'The :attribute must be a file of type: :values.',
        'mimetypes' => 'The :attribute must be a file of type: :values.',
        'dimensions' => 'The :attribute has invalid image dimensions.',

        // Other
        'accepted' => 'The :attribute must be accepted.',
        'active_url' => 'The :attribute is not a valid URL.',
        'alpha' => 'The :attribute may only contain letters.',
        'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
        'alpha_num' => 'The :attribute may only contain letters and numbers.',
        'gt' => [
            'numeric' => 'The :attribute must be greater than :value.',
            'string' => 'The :attribute must be greater than :value characters.',
            'array' => 'The :attribute must have more than :value items.',
        ],
        'gte' => [
            'numeric' => 'The :attribute must be greater than or equal :value.',
            'string' => 'The :attribute must be greater than or equal :value characters.',
            'array' => 'The :attribute must have :value items or more.',
        ],
        'lt' => [
            'numeric' => 'The :attribute must be less than :value.',
            'string' => 'The :attribute must be less than :value characters.',
            'array' => 'The :attribute must have less than :value items.',
        ],
        'lte' => [
            'numeric' => 'The :attribute must be less than or equal :value.',
            'string' => 'The :attribute must be less than or equal :value characters.',
            'array' => 'The :attribute must not have more than :value items.',
        ],
        'nullable' => 'The :attribute may be null.',
        'prohibited' => 'The :attribute field is prohibited.',
        'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
        'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
        'prohibits' => 'The :attribute field prohibits :other from being present.',
        'sometimes' => 'The :attribute field is required when present.',
        'uuid' => 'The :attribute must be a valid UUID.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Rule Validations
    |--------------------------------------------------------------------------
    */
    'business_rules' => [
        'inventory' => [
            'quantity' => [
                'min' => (int) env('INVENTORY_MIN_QUANTITY', 0),
                'max' => (int) env('INVENTORY_MAX_QUANTITY', 100000),
                'allow_negative' => filter_var(env('INVENTORY_ALLOW_NEGATIVE', false), FILTER_VALIDATE_BOOLEAN),
                'allow_decimal' => filter_var(env('INVENTORY_ALLOW_DECIMAL', false), FILTER_VALIDATE_BOOLEAN),
                'decimal_places' => (int) env('INVENTORY_DECIMAL_PLACES', 2),
                'increment' => (float) env('INVENTORY_INCREMENT', 1),
            ],
            'price' => [
                'min' => (float) env('INVENTORY_MIN_PRICE', 0),
                'max' => (float) env('INVENTORY_MAX_PRICE', 1000000),
                'decimals' => (int) env('INVENTORY_PRICE_DECIMALS', 2),
                'allow_negative' => filter_var(env('INVENTORY_ALLOW_NEGATIVE_PRICE', false), FILTER_VALIDATE_BOOLEAN),
                'currency' => env('INVENTORY_CURRENCY', 'USD'),
                'precision' => (float) env('INVENTORY_PRICE_PRECISION', 0.01),
            ],
            'adjustment' => [
                'min' => (int) env('INVENTORY_MIN_ADJUSTMENT', -1000),
                'max' => (int) env('INVENTORY_MAX_ADJUSTMENT', 1000),
                'require_reason' => filter_var(env('ADJUSTMENT_REQUIRE_REASON', true), FILTER_VALIDATE_BOOLEAN),
                'require_notes_for' => ['damaged', 'lost', 'audit', 'expired', 'theft', 'quality'],
                'approval_threshold' => [
                    'quantity' => (int) env('ADJUSTMENT_APPROVAL_QUANTITY', 100),
                    'value' => (float) env('ADJUSTMENT_APPROVAL_VALUE', 10000),
                    'percentage' => (int) env('ADJUSTMENT_APPROVAL_PERCENTAGE', 20),
                ],
            ],
            'safety_stock' => [
                'min' => (int) env('MIN_SAFETY_STOCK', 0),
                'max' => (int) env('MAX_SAFETY_STOCK', 1000),
                'default' => (int) env('DEFAULT_SAFETY_STOCK', 10),
            ],
            'reorder_point' => [
                'min' => (int) env('MIN_REORDER_POINT', 0),
                'max' => (int) env('MAX_REORDER_POINT', 10000),
                'calculation_method' => env('REORDER_CALCULATION_METHOD', 'fixed'),
            ],
        ],

        'product' => [
            'require_barcode' => filter_var(env('PRODUCT_REQUIRE_BARCODE', false), FILTER_VALIDATE_BOOLEAN),
            'require_sku' => filter_var(env('PRODUCT_REQUIRE_SKU', true), FILTER_VALIDATE_BOOLEAN),
            'unique_barcode' => filter_var(env('PRODUCT_UNIQUE_BARCODE', true), FILTER_VALIDATE_BOOLEAN),
            'unique_sku' => filter_var(env('PRODUCT_UNIQUE_SKU', true), FILTER_VALIDATE_BOOLEAN),
            'require_category' => filter_var(env('PRODUCT_REQUIRE_CATEGORY', true), FILTER_VALIDATE_BOOLEAN),
            'require_brand' => filter_var(env('PRODUCT_REQUIRE_BRAND', true), FILTER_VALIDATE_BOOLEAN),
            'require_price' => filter_var(env('PRODUCT_REQUIRE_PRICE', true), FILTER_VALIDATE_BOOLEAN),
            'require_weight' => filter_var(env('PRODUCT_REQUIRE_WEIGHT', false), FILTER_VALIDATE_BOOLEAN),
            'require_dimensions' => filter_var(env('PRODUCT_REQUIRE_DIMENSIONS', false), FILTER_VALIDATE_BOOLEAN),
            'max_variants' => (int) env('PRODUCT_MAX_VARIANTS', 50),
            'max_attributes' => (int) env('PRODUCT_MAX_ATTRIBUTES', 20),
        ],

        'stock_movement' => [
            'require_reason' => filter_var(env('STOCK_REQUIRE_REASON', true), FILTER_VALIDATE_BOOLEAN),
            'batch_tracking' => [
                'required_for' => ['pharmaceutical', 'food', 'cosmetic', 'chemical'],
                'expiry_required' => filter_var(env('EXPIRY_TRACKING_REQUIRED', true), FILTER_VALIDATE_BOOLEAN),
                'serial_required' => filter_var(env('SERIAL_TRACKING_REQUIRED', false), FILTER_VALIDATE_BOOLEAN),
                'lot_required' => filter_var(env('LOT_TRACKING_REQUIRED', true), FILTER_VALIDATE_BOOLEAN),
            ],
            'suspicious_activity' => [
                'max_adjustments_per_hour' => (int) env('MAX_ADJUSTMENTS_PER_HOUR', 50),
                'max_quantity_per_hour' => (int) env('MAX_QUANTITY_PER_HOUR', 1000),
                'max_value_per_hour' => (float) env('MAX_VALUE_PER_HOUR', 50000),
                'alert_on_consecutive_negatives' => (int) env('ALERT_CONSECUTIVE_NEGATIVES', 3),
            ],
        ],

        'supplier' => [
            'min_rating' => (float) env('SUPPLIER_MIN_RATING', 1),
            'max_rating' => (float) env('SUPPLIER_MAX_RATING', 5),
            'lead_time' => [
                'min' => (int) env('SUPPLIER_MIN_LEAD_TIME', 1),
                'max' => (int) env('SUPPLIER_MAX_LEAD_TIME', 365),
                'default' => (int) env('SUPPLIER_DEFAULT_LEAD_TIME', 7),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Rules
    |--------------------------------------------------------------------------
    */
    'custom_rules' => [
        'barcode' => [
            'patterns' => [
                'EAN-13' => '/^[0-9]{13}$/',
                'UPC-A' => '/^[0-9]{12}$/',
                'UPC-E' => '/^[0-9]{6,8}$/',
                'EAN-8' => '/^[0-9]{8}$/',
                'CODE-128' => '/^[\x00-\x7F]{1,}$/',
                'CODE-39' => '/^[A-Z0-9\-\.\s\$\/\+\%]{1,}$/',
            ],
            'validate_check_digit' => filter_var(env('BARCODE_VALIDATE_CHECK_DIGIT', true), FILTER_VALIDATE_BOOLEAN),
            'allowed_types' => parseEnvArray(env('BARCODE_ALLOWED_TYPES'), ['EAN-13', 'UPC-A', 'UPC-E', 'EAN-8', 'CODE-128', 'CODE-39']),
            'message' => 'Invalid barcode format. Must be a valid :type barcode.',
        ],

        'sku' => [
            'pattern' => '/^[A-Z0-9][A-Z0-9\-_]{5,49}$/',
            'unique' => filter_var(env('SKU_UNIQUE_REQUIRED', true), FILTER_VALIDATE_BOOLEAN),
            'auto_generate' => filter_var(env('SKU_AUTO_GENERATE', false), FILTER_VALIDATE_BOOLEAN),
            'prefix' => env('SKU_PREFIX', 'INV'),
            'separator' => env('SKU_SEPARATOR', '-'),
            'min_length' => (int) env('SKU_MIN_LENGTH', 6),
            'max_length' => (int) env('SKU_MAX_LENGTH', 50),
            'message' => 'SKU must be 6-50 characters, start with letter/number, and contain only uppercase letters, numbers, hyphens, and underscores.',
        ],

        'color' => [
            'allowed' => [
                'red', 'blue', 'green', 'black', 'white', 'yellow', 'purple', 'orange',
                'pink', 'brown', 'gray', 'navy', 'cyan', 'magenta', 'silver', 'gold',
                'beige', 'maroon', 'olive', 'teal', 'indigo', 'violet', 'turquoise',
            ],
            'allow_custom' => filter_var(env('ALLOW_CUSTOM_COLORS', false), FILTER_VALIDATE_BOOLEAN),
            'hex_pattern' => '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'rgb_pattern' => '/^rgb\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}\s*\)$/',
            'rgba_pattern' => '/^rgba\(\s*(?:\d{1,3}\s*,\s*){3}(?:0|1|0?\.\d+)\s*\)$/',
            'message' => 'Invalid color. Must be a predefined color name or valid hex/rgb/rgba format.',
        ],

        'size' => [
            'types' => [
                'clothing' => ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL', '5XL'],
                'shoe_eu' => range(35, 48, 1),
                'shoe_us' => range(5, 15, 0.5),
                'shoe_uk' => range(3, 13, 0.5),
                'numeric' => ['One Size', 'Custom', 'N/A'],
            ],
            'allow_custom' => filter_var(env('ALLOW_CUSTOM_SIZES', false), FILTER_VALIDATE_BOOLEAN),
            'custom_max_length' => (int) env('CUSTOM_SIZE_MAX_LENGTH', 20),
            'message' => 'Invalid size. Must be a valid size for the selected type or a custom size.',
        ],

        'date' => [
            'format' => env('DATE_FORMAT', 'Y-m-d'),
            'datetime_format' => env('DATETIME_FORMAT', 'Y-m-d H:i:s'),
            'min_date' => env('MIN_DATE', '2000-01-01'),
            'max_date' => env('MAX_DATE', '2100-12-31'),
            'business_days_only' => filter_var(env('BUSINESS_DAYS_ONLY', false), FILTER_VALIDATE_BOOLEAN),
            'exclude_weekends' => [6, 0],
            'holidays' => parseEnvArray(env('HOLIDAYS'), []),
            'message' => 'Date must be in :format format between :min_date and :max_date.',
        ],

        'email' => [
            'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'check_mx' => filter_var(env('EMAIL_CHECK_MX', false), FILTER_VALIDATE_BOOLEAN),
            'check_dns' => filter_var(env('EMAIL_CHECK_DNS', false), FILTER_VALIDATE_BOOLEAN),
            'disposable_domains' => parseEnvArray(env('DISPOSABLE_EMAIL_DOMAINS'), [
                'temp-mail.org', 'guerrillamail.com', 'mailinator.com', '10minutemail.com',
                'yopmail.com', 'throwawaymail.com', 'fakeinbox.com', 'trashmail.com',
            ]),
            'block_disposable' => filter_var(env('BLOCK_DISPOSABLE_EMAILS', true), FILTER_VALIDATE_BOOLEAN),
            'message' => 'Invalid email address.',
        ],

        'phone' => [
            'pattern' => '/^\+?[1-9]\d{1,14}$/',
            'e164_pattern' => '/^\+[1-9]\d{1,14}$/',
            'country_code' => env('DEFAULT_COUNTRY_CODE', 'US'),
            'allow_national_format' => filter_var(env('ALLOW_NATIONAL_PHONE_FORMAT', true), FILTER_VALIDATE_BOOLEAN),
            'min_length' => (int) env('PHONE_MIN_LENGTH', 10),
            'max_length' => (int) env('PHONE_MAX_LENGTH', 15),
            'message' => 'Phone number must be a valid international number.',
        ],

        'file' => [
            'max_size_kb' => (int) env('MAX_FILE_SIZE_KB', 2048),
            'max_size_mb' => (int) env('MAX_FILE_SIZE_MB', 2),
            'allowed_mimes' => [
                'image' => parseEnvArray(env('ALLOWED_IMAGE_MIMES'), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff']),
                'document' => parseEnvArray(env('ALLOWED_DOCUMENT_MIMES'), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx']),
                'archive' => parseEnvArray(env('ALLOWED_ARCHIVE_MIMES'), ['zip', 'rar', '7z', 'tar', 'gz']),
                'audio' => parseEnvArray(env('ALLOWED_AUDIO_MIMES'), ['mp3', 'wav', 'ogg', 'm4a']),
                'video' => parseEnvArray(env('ALLOWED_VIDEO_MIMES'), ['mp4', 'avi', 'mov', 'wmv', 'flv']),
            ],
            'max_dimensions' => [
                'width' => (int) env('MAX_IMAGE_WIDTH', 1920),
                'height' => (int) env('MAX_IMAGE_HEIGHT', 1080),
            ],
            'min_dimensions' => [
                'width' => (int) env('MIN_IMAGE_WIDTH', 100),
                'height' => (int) env('MIN_IMAGE_HEIGHT', 100),
            ],
            'virus_scan' => filter_var(env('FILE_VIRUS_SCAN', false), FILTER_VALIDATE_BOOLEAN),
            'message' => 'Invalid file. Must be a valid file type and within size limits.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Length Constraints
    |--------------------------------------------------------------------------
    */
    'lengths' => [
        'product' => [
            'name' => ['min' => 2, 'max' => 200],
            'sku' => ['min' => 6, 'max' => 50],
            'brand' => ['min' => 2, 'max' => 100],
            'model' => ['min' => 2, 'max' => 100],
            'description' => ['min' => 0, 'max' => 5000],
            'short_description' => ['min' => 0, 'max' => 500],
            'manufacturer' => ['min' => 2, 'max' => 100],
            'category' => ['min' => 2, 'max' => 100],
            'tags' => ['min' => 2, 'max' => 500],
        ],
        'inventory' => [
            'barcode' => ['min' => 8, 'max' => 50],
            'location' => ['min' => 2, 'max' => 100],
            'bin' => ['min' => 1, 'max' => 20],
            'rack' => ['min' => 1, 'max' => 10],
            'shelf' => ['min' => 1, 'max' => 10],
            'lot_number' => ['min' => 2, 'max' => 50],
            'serial_number' => ['min' => 2, 'max' => 100],
            'warehouse' => ['min' => 2, 'max' => 100],
            'zone' => ['min' => 2, 'max' => 50],
        ],
        'supplier' => [
            'code' => ['min' => 2, 'max' => 50],
            'name' => ['min' => 2, 'max' => 200],
            'contact_person' => ['min' => 2, 'max' => 100],
            'company' => ['min' => 2, 'max' => 200],
            'tax_id' => ['min' => 2, 'max' => 30],
            'vat_number' => ['min' => 2, 'max' => 30],
        ],
        'customer' => [
            'name' => ['min' => 2, 'max' => 100],
            'company' => ['min' => 2, 'max' => 200],
            'tax_id' => ['min' => 2, 'max' => 30],
        ],
        'user' => [
            'username' => ['min' => 3, 'max' => 50],
            'password' => ['min' => 8, 'max' => 100],
            'email' => ['min' => 5, 'max' => 100],
            'first_name' => ['min' => 2, 'max' => 50],
            'last_name' => ['min' => 2, 'max' => 50],
            'display_name' => ['min' => 2, 'max' => 100],
        ],
        'address' => [
            'street' => ['min' => 5, 'max' => 200],
            'city' => ['min' => 2, 'max' => 100],
            'state' => ['min' => 2, 'max' => 50],
            'postal_code' => ['min' => 3, 'max' => 20],
            'country' => ['min' => 2, 'max' => 50],
            'address_line1' => ['min' => 5, 'max' => 200],
            'address_line2' => ['min' => 0, 'max' => 200],
        ],
        'general' => [
            'notes' => ['min' => 0, 'max' => 1000],
            'comments' => ['min' => 0, 'max' => 500],
            'reason' => ['min' => 5, 'max' => 200],
            'reference' => ['min' => 2, 'max' => 50],
            'description' => ['min' => 0, 'max' => 500],
            'remarks' => ['min' => 0, 'max' => 1000],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validator Classes
    |--------------------------------------------------------------------------
    */
    'validators' => [
        // Using relative paths instead of namespace references
        'barcode' => 'App\Services\Validators\BarcodeValidator',
        'sku' => 'App\Services\Validators\SkuValidator',
        'price' => 'App\Services\Validators\PriceValidator',
        'stock' => 'App\Services\Validators\StockValidator',
        'date' => 'App\Services\Validators\DateValidator',
        'email' => 'App\Services\Validators\EmailValidator',
        'phone' => 'App\Services\Validators\PhoneValidator',
        'file' => 'App\Services\Validators\FileValidator',
        'inventory' => 'App\Services\Validators\InventoryValidator',
        'quantity' => 'App\Services\Validators\QuantityValidator',
        'location' => 'App\Services\Validators\LocationValidator',
        'supplier' => 'App\Services\Validators\SupplierValidator',
        'customer' => 'App\Services\Validators\CustomerValidator',
        'user' => 'App\Services\Validators\UserValidator',
        'address' => 'App\Services\Validators\AddressValidator',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Error Handling
    |--------------------------------------------------------------------------
    */
    'error_handling' => [
        'format' => env('VALIDATION_ERROR_FORMAT', 'array'),
        'include_field_names' => filter_var(env('INCLUDE_FIELD_NAMES_IN_ERROR', true), FILTER_VALIDATE_BOOLEAN),
        'include_original_values' => filter_var(env('INCLUDE_ORIGINAL_VALUES', false), FILTER_VALIDATE_BOOLEAN),
        'log_validation_errors' => filter_var(env('LOG_VALIDATION_ERRORS', false), FILTER_VALIDATE_BOOLEAN),
        'log_level' => env('VALIDATION_LOG_LEVEL', 'debug'),
        'suppress_sensitive_fields' => parseEnvArray(env('SENSITIVE_FIELDS'), [
            'password', 'password_confirmation', 'credit_card', 'token', 'secret',
            'api_key', 'private_key', 'secret_key', 'auth_token', 'session_token',
            'ssn', 'social_security', 'passport', 'driver_license',
        ]),
        'sanitize_error_messages' => filter_var(env('SANITIZE_ERROR_MESSAGES', true), FILTER_VALIDATE_BOOLEAN),
        'max_errors_per_field' => (int) env('MAX_ERRORS_PER_FIELD', 5),
        'max_total_errors' => (int) env('MAX_TOTAL_ERRORS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Response Configuration
    |--------------------------------------------------------------------------
    */
    'response' => [
        'formats' => [
            'json' => [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ':errors',
                'timestamp' => ':timestamp',
                'request_id' => ':request_id',
                'validation_rules' => ':rules',
                'status_code' => 422,
            ],
            'array' => [
                'status' => 'error',
                'code' => 422,
                'message' => 'Validation Error',
                'details' => ':errors',
                'timestamp' => ':timestamp',
            ],
            'html' => [
                'wrapper' => '<div class="validation-errors alert alert-danger">:errors</div>',
                'error' => '<p class="error text-danger">:message</p>',
                'list' => '<ul class="error-list list-unstyled">:errors</ul>',
                'item' => '<li>:message</li>',
            ],
            'xml' => [
                'root' => '<?xml version="1.0" encoding="UTF-8"?><response></response>',
                'error' => '<error field=":field">:message</error>',
            ],
        ],
        'default_format' => env('VALIDATION_RESPONSE_FORMAT', 'json'),
        'status_code' => 422,
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Validation-Error' => 'true',
            'X-Validation-Errors-Count' => ':count',
        ],
        'include_http_status' => filter_var(env('INCLUDE_HTTP_STATUS_IN_RESPONSE', true), FILTER_VALIDATE_BOOLEAN),
        'pretty_print' => filter_var(env('VALIDATION_PRETTY_PRINT', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Language and Localization
    |--------------------------------------------------------------------------
    */
    'language' => [
        'default' => env('VALIDATION_LANGUAGE', 'en'),
        'fallback' => 'en',
        'available' => parseEnvArray(env('AVAILABLE_LANGUAGES'), ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ar', 'ko', 'hi']),
        'path' => resource_path('lang/validation'),
        'custom_messages_path' => resource_path('lang/custom'),
        'auto_detect' => filter_var(env('VALIDATION_AUTO_DETECT_LANGUAGE', true), FILTER_VALIDATE_BOOLEAN),
        'use_accept_language_header' => filter_var(env('USE_ACCEPT_LANGUAGE_HEADER', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_validated_rules' => filter_var(env('CACHE_VALIDATION_RULES', true), FILTER_VALIDATE_BOOLEAN),
        'cache_ttl' => (int) env('VALIDATION_CACHE_TTL', 3600),
        'cache_driver' => env('VALIDATION_CACHE_DRIVER', 'file'),
        'skip_on_empty' => filter_var(env('SKIP_VALIDATION_ON_EMPTY', false), FILTER_VALIDATE_BOOLEAN),
        'parallel_validation' => filter_var(env('PARALLEL_VALIDATION', false), FILTER_VALIDATE_BOOLEAN),
        'max_validation_depth' => (int) env('MAX_VALIDATION_DEPTH', 10),
        'batch_size' => (int) env('VALIDATION_BATCH_SIZE', 100),
        'memory_limit_mb' => (int) env('VALIDATION_MEMORY_LIMIT_MB', 128),
        'timeout_seconds' => (int) env('VALIDATION_TIMEOUT_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting for Validation Requests
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => filter_var(env('VALIDATION_RATE_LIMITING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'max_requests' => (int) env('VALIDATION_MAX_REQUESTS', 100),
        'window_minutes' => (int) env('VALIDATION_WINDOW_MINUTES', 1),
        'throttle_by_ip' => filter_var(env('VALIDATION_THROTTLE_BY_IP', true), FILTER_VALIDATE_BOOLEAN),
        'throttle_by_user' => filter_var(env('VALIDATION_THROTTLE_BY_USER', false), FILTER_VALIDATE_BOOLEAN),
        'cache_driver' => env('VALIDATION_RATE_LIMIT_CACHE_DRIVER', 'redis'),
        'reset_strategy' => env('VALIDATION_RESET_STRATEGY', 'fixed_window'),
        'exempt_ips' => parseEnvArray(env('VALIDATION_EXEMPT_IPS', '127.0.0.1')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Testing & Debugging
    |--------------------------------------------------------------------------
    */
    'debug' => [
        'enabled' => filter_var(env('VALIDATION_DEBUG_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'log_validation_process' => filter_var(env('LOG_VALIDATION_PROCESS', false), FILTER_VALIDATE_BOOLEAN),
        'show_validation_rules' => filter_var(env('SHOW_VALIDATION_RULES', false), FILTER_VALIDATE_BOOLEAN),
        'include_backtrace' => filter_var(env('INCLUDE_VALIDATION_BACKTRACE', false), FILTER_VALIDATE_BOOLEAN),
        'performance_metrics' => filter_var(env('VALIDATION_PERFORMANCE_METRICS', false), FILTER_VALIDATE_BOOLEAN),
        'test_mode' => filter_var(env('VALIDATION_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
