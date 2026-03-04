<?php

namespace App\Enums;

class Color
{
    // Basic Colors - THIS SYNTAX IS CORRECT FOR PHP 7.1+
    public const RED = 'red';
    public const BLUE = 'blue'; 
    public const GREEN = 'green';
    public const BLACK = 'black';
    public const WHITE = 'white';
    public const YELLOW = 'yellow';
    public const PURPLE = 'purple';
    public const ORANGE = 'orange';
    public const PINK = 'pink';
    public const BROWN = 'brown';
    public const GRAY = 'gray';
    public const NAVY = 'navy';
    
    // Store all colors
    private static $all = [
        self::RED, self::BLUE, self::GREEN, self::BLACK, self::WHITE,
        self::YELLOW, self::PURPLE, self::ORANGE, self::PINK, self::BROWN,
        self::GRAY, self::NAVY,
    ];
    
    // Store display names
    private static $displayNames = [
        self::RED => 'Red',
        self::BLUE => 'Blue',
        self::GREEN => 'Green',
        self::BLACK => 'Black',
        self::WHITE => 'White',
        self::YELLOW => 'Yellow',
        self::PURPLE => 'Purple',
        self::ORANGE => 'Orange',
        self::PINK => 'Pink',
        self::BROWN => 'Brown',
        self::GRAY => 'Gray',
        self::NAVY => 'Navy',
    ];
    
    // Store hex codes
    private static $hexCodes = [
        self::RED => '#FF0000',
        self::BLUE => '#0000FF',
        self::GREEN => '#008000',
        self::BLACK => '#000000',
        self::WHITE => '#FFFFFF',
        self::YELLOW => '#FFFF00',
        self::PURPLE => '#800080',
        self::ORANGE => '#FFA500',
        self::PINK => '#FFC0CB',
        self::BROWN => '#A52A2A',
        self::GRAY => '#808080',
        self::NAVY => '#000080',
    ];
    
    /**
     * Get all valid colors
     */
    public static function getAll()
    {
        return self::$all;
    }
    
    /**
     * Check if a color is valid
     */
    public static function isValid($color)
    {
        return in_array($color, self::$all);
    }
    
    /**
     * Get display name for a color
     */
    public static function getDisplayName($color)
    {
        return isset(self::$displayNames[$color]) ? self::$displayNames[$color] : ucfirst($color);
    }
    
    /**
     * Get hex code for a color
     */
    public static function getHexCode($color)
    {
        return isset(self::$hexCodes[$color]) ? self::$hexCodes[$color] : '#CCCCCC';
    }
    
    /**
     * Emulate tryFrom() method
     */
    public static function tryFrom($value)
    {
        return self::isValid($value) ? $value : null;
    }
    
    /**
     * Emulate from() method
     */
    public static function from($value)
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException("Invalid color: $value");
        }
        return $value;
    }
}