<?php
// Simple logger wrapper to avoid fatal errors when logging is used on pages.
// Writes to PHP error log and optionally to a rotating log file in /logs.

class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $ts = date('Y-m-d H:i:s');
        $ctx = $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $line = "[$ts] [$level] $message" . ($ctx !== '' ? " $ctx" : '');

        // Primary: PHP error log
        error_log($line);

        // Secondary: append to logs/app.log if writable
        $logDir = __DIR__ . '/../logs';
        $logFile = $logDir . '/app.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (is_writable($logDir) || (!file_exists($logFile) && is_writable(dirname($logFile)))) {
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

// Provide a noop alias in case a namespaced logger is expected elsewhere.
if (!class_exists('Logger')) {
    class_alias('Logger', 'Logger');
}
