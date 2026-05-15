<?php
// includes/logger.php

class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $file = $logDir . '/app.log';
        $date = date('Y-m-d H:i:s');
        $contextStr = $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $line = "[$date] [$level] $message" . ($contextStr ? ' ' . $contextStr : '') . PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        // also send to the PHP error log for immediate visibility
        error_log($line);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::log('WARN', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
}
