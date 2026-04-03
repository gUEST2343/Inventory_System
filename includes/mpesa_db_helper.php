<?php

if (!function_exists('mpesaTableExists')) {
    function mpesaTableExists(PDO $pdo, string $tableName): bool
    {
        static $cache = [];
        $cacheKey = 'public.' . $tableName;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName]);

        $cache[$cacheKey] = (bool) $stmt->fetchColumn();
        return $cache[$cacheKey];
    }
}

if (!function_exists('mpesaColumnExists')) {
    function mpesaColumnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        static $cache = [];
        $cacheKey = 'public.' . $tableName . '.' . $columnName;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName, $columnName]);

        $cache[$cacheKey] = (bool) $stmt->fetchColumn();
        return $cache[$cacheKey];
    }
}

if (!function_exists('mpesaTransactionsTableExists')) {
    function mpesaTransactionsTableExists(PDO $pdo): bool
    {
        return mpesaTableExists($pdo, 'mpesa_transactions');
    }
}

if (!function_exists('mpesaParseTransactionDate')) {
    function mpesaParseTransactionDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = preg_replace('/\D+/', '', (string) $value);
        if (strlen($raw) !== 14) {
            return null;
        }

        $dateTime = DateTime::createFromFormat('YmdHis', $raw, new DateTimeZone('Africa/Nairobi'));
        if (!$dateTime) {
            return null;
        }

        return $dateTime->format('Y-m-d H:i:s');
    }
}

if (!function_exists('mpesaCallbackMetadataToMap')) {
    function mpesaCallbackMetadataToMap(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['Name'])) {
                continue;
            }

            $map[(string) $item['Name']] = $item['Value'] ?? null;
        }

        return $map;
    }
}
