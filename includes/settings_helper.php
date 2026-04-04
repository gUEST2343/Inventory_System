<?php
/**
 * Application settings helpers.
 */

if (!function_exists('getAppSettingDefaults')) {
    function getAppSettingDefaults(): array
    {
        return [
            'store_name' => 'StockFlow Inventory',
            'store_email' => 'admin@stockflow.com',
            'currency' => 'USD',
            'low_stock_threshold' => '5',
            'timezone' => 'Africa/Nairobi',
            'date_format' => 'Y-m-d',
            'notify_new_orders' => '1',
            'notify_low_stock' => '1',
            'notify_daily_sales_report' => '0',
            'notify_weekly_summary' => '0',
            'notify_order_status_changes' => '1',
            'notify_inventory_updates' => '1',
            'notify_user_activity' => '1',
            'last_backup_at' => '',
            'last_restored_at' => '',
        ];
    }
}

if (!function_exists('getBooleanAppSettingKeys')) {
    function getBooleanAppSettingKeys(): array
    {
        return [
            'notify_new_orders',
            'notify_low_stock',
            'notify_daily_sales_report',
            'notify_weekly_summary',
            'notify_order_status_changes',
            'notify_inventory_updates',
            'notify_user_activity',
        ];
    }
}

if (!function_exists('ensureAppSettingsTable')) {
    function ensureAppSettingsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NOT NULL DEFAULT '',
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('normalizeAppSettingValue')) {
    function normalizeAppSettingValue(string $key, $value)
    {
        $defaults = getAppSettingDefaults();
        $defaultValue = $defaults[$key] ?? '';
        $stringValue = is_scalar($value) ? trim((string) $value) : '';

        if (in_array($key, getBooleanAppSettingKeys(), true)) {
            return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        switch ($key) {
            case 'low_stock_threshold':
                $threshold = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                return $threshold === false ? $defaultValue : (string) $threshold;
            case 'currency':
                $allowedCurrencies = ['USD', 'EUR', 'GBP', 'KES'];
                return in_array($stringValue, $allowedCurrencies, true) ? $stringValue : $defaultValue;
            case 'timezone':
                $allowedTimezones = ['Africa/Nairobi', 'UTC', 'America/New_York'];
                return in_array($stringValue, $allowedTimezones, true) ? $stringValue : $defaultValue;
            case 'date_format':
                $allowedFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
                return in_array($stringValue, $allowedFormats, true) ? $stringValue : $defaultValue;
            case 'last_backup_at':
            case 'last_restored_at':
                return $stringValue;
            default:
                return $stringValue === '' ? $defaultValue : $stringValue;
        }
    }
}

if (!function_exists('getAppSettings')) {
    function getAppSettings(PDO $pdo): array
    {
        ensureAppSettingsTable($pdo);

        $settings = getAppSettingDefaults();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        foreach ($rows as $key => $value) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = normalizeAppSettingValue($key, $value);
            }
        }

        return $settings;
    }
}

if (!function_exists('saveAppSettings')) {
    function saveAppSettings(PDO $pdo, array $settings): array
    {
        ensureAppSettingsTable($pdo);

        if ($settings === []) {
            return getAppSettings($pdo);
        }

        $stmt = $pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (setting_key)
            DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, getAppSettingDefaults())) {
                continue;
            }

            $stmt->execute([$key, normalizeAppSettingValue($key, $value)]);
        }

        return getAppSettings($pdo);
    }
}

if (!function_exists('isAppSettingEnabled')) {
    function isAppSettingEnabled(array $settings, string $key): bool
    {
        return isset($settings[$key]) && normalizeAppSettingValue($key, $settings[$key]) === '1';
    }
}

if (!function_exists('createSettingsBackupPayload')) {
    function createSettingsBackupPayload(PDO $pdo): array
    {
        return [
            'type' => 'stockflow-settings-backup',
            'version' => 1,
            'generated_at' => gmdate('c'),
            'settings' => getAppSettings($pdo),
        ];
    }
}

if (!function_exists('restoreSettingsBackupPayload')) {
    function restoreSettingsBackupPayload(PDO $pdo, array $payload): array
    {
        if (($payload['type'] ?? '') !== 'stockflow-settings-backup') {
            throw new InvalidArgumentException('Invalid backup file selected.');
        }

        if (!isset($payload['settings']) || !is_array($payload['settings'])) {
            throw new InvalidArgumentException('Backup file does not contain any settings data.');
        }

        $allowedKeys = array_keys(getAppSettingDefaults());
        $restorable = [];

        foreach ($payload['settings'] as $key => $value) {
            if (in_array($key, $allowedKeys, true) && !in_array($key, ['last_backup_at', 'last_restored_at'], true)) {
                $restorable[$key] = $value;
            }
        }

        $restorable['last_restored_at'] = date('c');

        return saveAppSettings($pdo, $restorable);
    }
}

