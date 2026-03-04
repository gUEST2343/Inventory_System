<?php

namespace App\Enums;

class ClothingType
{
    public const SHIRT = 'shirt';
    public const PANTS = 'pants';
    public const JACKET = 'jacket';
    public const DRESS = 'dress';
    public const SKIRT = 'skirt';
    public const SWEATER = 'sweater';
    
    private static array $all = [
        self::SHIRT,
        self::PANTS,
        self::JACKET,
        self::DRESS,
        self::SKIRT,
        self::SWEATER,
    ];
    
    public static function getAll(): array
    {
        return self::$all;
    }
    
    public static function isValid(string $type): bool
    {
        return in_array($type, self::$all);
    }
}