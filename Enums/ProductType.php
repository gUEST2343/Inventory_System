<?php

namespace App\Enums;

class ProductType
{
    public const SHOE = 'shoe';
    public const CLOTHING = 'clothing';
    
    private static array $all = [
        self::SHOE,
        self::CLOTHING,
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