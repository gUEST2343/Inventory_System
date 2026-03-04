<?php

namespace App\Enums;

class ShoeType
{
    public const RUNNING = 'running';
    public const CASUAL = 'casual';
    public const FORMAL = 'formal';
    public const BOOT = 'boot';
    public const SANDAL = 'sandal';
    public const SLIPPER = 'slipper';
    
    private static array $all = [
        self::RUNNING,
        self::CASUAL,
        self::FORMAL,
        self::BOOT,
        self::SANDAL,
        self::SLIPPER,
    ];
    
    public static function getAll(): array
    {
        return self::$all;
    }
    
    public static function isValid(string $type): bool
    {
        return in_array($type, self::$all);
    }
    
    // For compatibility with from() method
    public static function from(string $value): string
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException("Invalid shoe type: $value");
        }
        return $value;
    }
}