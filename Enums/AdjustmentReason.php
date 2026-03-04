<?php

namespace App\Enums;

class AdjustmentReason
{
    // Define constants
    public const INITIAL_COUNT = 'initial_count';
    public const RECEIVED = 'received';
    public const SOLD = 'sold';
    public const RETURNED = 'returned';
    public const DAMAGED = 'damaged';
    public const LOST = 'lost';
    public const FOUND = 'found';
    public const AUDIT = 'audit';
    public const TRANSFER = 'transfer';
    public const TRANSFER_OUT = 'transfer_out';
    public const TRANSFER_IN = 'transfer_in';
    public const RESTOCK = 'restock';
    public const SAMPLE = 'sample';
    
    // Store all valid reasons
    private static array $all = [
        self::INITIAL_COUNT,
        self::RECEIVED,
        self::SOLD,
        self::RETURNED,
        self::DAMAGED,
        self::LOST,
        self::FOUND,
        self::AUDIT,
        self::TRANSFER,
        self::TRANSFER_OUT,
        self::TRANSFER_IN,
        self::RESTOCK,
        self::SAMPLE,
    ];
    
    // Store descriptions
    private static array $descriptions = [
        self::INITIAL_COUNT => 'Initial stock count',
        self::RECEIVED => 'Received from supplier',
        self::SOLD => 'Sold to customer',
        self::RETURNED => 'Customer return',
        self::DAMAGED => 'Damaged/Defective item',
        self::LOST => 'Lost or theft',
        self::FOUND => 'Found inventory',
        self::AUDIT => 'Audit correction',
        self::TRANSFER => 'Store transfer',
        self::SAMPLE => 'Sample or display item',
    ];
    
    /**
     * Get all valid adjustment reasons
     */
    public static function getAll(): array
    {
        return self::$all;
    }
    
    /**
     * Check if a reason is valid
     */
    public static function isValid(string $reason): bool
    {
        return in_array($reason, self::$all);
    }
    
    /**
     * Get description for a reason
     */
    public static function getDescription(string $reason): string
    {
        return self::$descriptions[$reason] ?? 'Unknown reason';
    }
    
    /**
     * Get all reasons with descriptions
     */
    public static function getAllWithDescriptions(): array
    {
        $result = [];
        foreach (self::$all as $reason) {
            $result[$reason] = self::$descriptions[$reason];
        }
        return $result;
    }
    
    /**
     * ADD THIS: Emulate PHP 8.1+ tryFrom() method
     * Returns the value if valid, null otherwise
     */
    public static function tryFrom(string $value): ?string
    {
        return self::isValid($value) ? $value : null;
    }
    
    /**
     * ADD THIS: Emulate PHP 8.1+ from() method
     * Returns the value if valid, throws exception otherwise
     */
    public static function from(string $value): string
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid adjustment reason: %s. Valid: %s', 
                    $value, 
                    implode(', ', self::$all))
            );
        }
        return $value;
    }
    
    /**
     * ADD THIS: Emulate PHP 8.1+ cases() method
     */
    public static function cases(): array
    {
        return self::$all;
    }
    
    /**
     * Get business rules for a reason
     */
    public static function getBusinessRules(string $reason): array
    {
        $rules = [
            self::INITIAL_COUNT => [
                'can_increase' => true,
                'can_decrease' => false,
                'requires_approval' => false,
                'affects_profit' => false,
            ],
            self::RECEIVED => [
                'can_increase' => true,
                'can_decrease' => false,
                'requires_approval' => false,
                'affects_profit' => false,
            ],
            self::SOLD => [
                'can_increase' => false,
                'can_decrease' => true,
                'requires_approval' => false,
                'affects_profit' => true,
            ],
            self::RETURNED => [
                'can_increase' => true,
                'can_decrease' => false,
                'requires_approval' => true,
                'affects_profit' => false,
            ],
            self::DAMAGED => [
                'can_increase' => false,
                'can_decrease' => true,
                'requires_approval' => true,
                'affects_profit' => true,
            ],
            self::LOST => [
                'can_increase' => false,
                'can_decrease' => true,
                'requires_approval' => true,
                'affects_profit' => true,
            ],
            self::FOUND => [
                'can_increase' => true,
                'can_decrease' => false,
                'requires_approval' => true,
                'affects_profit' => false,
            ],
            self::AUDIT => [
                'can_increase' => true,
                'can_decrease' => true,
                'requires_approval' => false,
                'affects_profit' => false,
            ],
            self::TRANSFER => [
                'can_increase' => true,
                'can_decrease' => true,
                'requires_approval' => true,
                'affects_profit' => false,
            ],
            self::TRANSFER_OUT => [
                'can_increase' => false,
                'can_decrease' => true,
                'requires_approval' => true,
                'affects_profit' => false,
            ],
            self::TRANSFER_IN => [
                'can_increase' => true,
                'can_decrease' => false,
                'requires_approval' => true,
                'affects_profit' => false,
            ],
            self::RESTOCK => [
                'can_increase' => true,
                'can_decrease' => false,
                'requires_approval' => false,
                'affects_profit' => false,
            ],
            self::SAMPLE => [
                'can_increase' => false,
                'can_decrease' => true,
                'requires_approval' => true,
                'affects_profit' => false,
            ],
        ];

        return $rules[$reason] ?? [
            'can_increase' => false,
            'can_decrease' => false,
            'requires_approval' => true,
            'affects_profit' => false,
        ];
    }
    
    /**
     * Check if a reason allows increasing stock
     */
    public static function canIncreaseStock(string $reason): bool
    {
        $rules = self::getBusinessRules($reason);
        return $rules['can_increase'];
    }
    
    /**
     * Check if a reason allows decreasing stock
     */
    public static function canDecreaseStock(string $reason): bool
    {
        $rules = self::getBusinessRules($reason);
        return $rules['can_decrease'];
    }
    
    /**
     * Check if a reason requires approval
     */
    public static function requiresApproval(string $reason): bool
    {
        $rules = self::getBusinessRules($reason);
        return $rules['requires_approval'];
    }
    
    /**
     * Check if a reason affects profit/loss
     */
    public static function affectsProfit(string $reason): bool
    {
        $rules = self::getBusinessRules($reason);
        return $rules['affects_profit'];
    }
}