<?php

namespace App\Enums;

/**
 * Helper class for working with enums
 * Provides utility methods to validate and retrieve enum cases
 */
class EnumHelper
{
    /**
     * Check if a value is valid for a given enum class
     * 
     * @param string $enumClass The fully qualified enum class name
     * @param mixed $value The value to check
     * @return bool True if valid, false otherwise
     */
    public static function isValid(string $enumClass, mixed $value): bool
    {
        // If the enum class has an isValid method, use it
        if (method_exists($enumClass, 'isValid')) {
            return $enumClass::isValid($value);
        }
        
        // For PHP 8.1+ backed enums, try to use tryFrom
        if (method_exists($enumClass, 'tryFrom')) {
            return $enumClass::tryFrom($value) !== null;
        }
        
        // For PHP 8.1+ enums, check if cases exist
        if (method_exists($enumClass, 'cases')) {
            $cases = $enumClass::cases();
            foreach ($cases as $case) {
                if ($case->value === $value || $case->name === $value) {
                    return true;
                }
            }
            return false;
        }
        
        // Fallback: check if getAll method exists
        if (method_exists($enumClass, 'getAll')) {
            $allValues = $enumClass::getAll();
            return in_array($value, $allValues, true);
        }
        
        throw new \InvalidArgumentException(
            "Enum class $enumClass does not have a supported validation method"
        );
    }
    
    /**
     * Get all valid cases/values for an enum
     * 
     * @param string $enumClass The fully qualified enum class name
     * @return array Array of valid enum values
     */
    public static function getCases(string $enumClass): array
    {
        // If the enum class has a cases method (PHP 8.1+), use it
        if (method_exists($enumClass, 'cases')) {
            $cases = $enumClass::cases();
            // Return just the values for backed enums, or names for unit enums
            return array_map(function($case) {
                return isset($case->value) ? $case->value : $case->name;
            }, $cases);
        }
        
        // For class-based enums, try getAll method
        if (method_exists($enumClass, 'getAll')) {
            return $enumClass::getAll();
        }
        
        throw new \InvalidArgumentException(
            "Enum class $enumClass does not have a supported method to get cases"
        );
    }
    
    /**
     * Get a value from an enum by name or value
     * Similar to PHP 8.1+ from() method
     * 
     * @param string $enumClass The fully qualified enum class name
     * @param mixed $value The value to convert
     * @return mixed The enum value
     * @throws \InvalidArgumentException If value is not valid
     */
    public static function from(string $enumClass, mixed $value): mixed
    {
        // If the enum class has a from method, use it
        if (method_exists($enumClass, 'from')) {
            return $enumClass::from($value);
        }
        
        // For PHP 8.1+ backed enums, try from() or tryFrom()
        if (method_exists($enumClass, 'tryFrom')) {
            $result = $enumClass::tryFrom($value);
            if ($result === null) {
                throw new \InvalidArgumentException(
                    "Invalid value '$value' for enum $enumClass"
                );
            }
            return $result;
        }
        
        // For PHP 8.1+ unit enums
        if (method_exists($enumClass, 'cases')) {
            $cases = $enumClass::cases();
            foreach ($cases as $case) {
                if ($case->value === $value || $case->name === $value) {
                    return $case;
                }
            }
            throw new \InvalidArgumentException(
                "Invalid value '$value' for enum $enumClass"
            );
        }
        
        // Fallback: check getAll and return value if valid
        if (method_exists($enumClass, 'getAll') && method_exists($enumClass, 'isValid')) {
            if ($enumClass::isValid($value)) {
                return $value;
            }
            throw new \InvalidArgumentException(
                "Invalid value '$value' for enum $enumClass"
            );
        }
        
        throw new \InvalidArgumentException(
            "Enum class $enumClass does not support the from() method"
        );
    }
    
    /**
     * Try to get a value from an enum, returning null if invalid
     * Similar to PHP 8.1+ tryFrom() method
     * 
     * @param string $enumClass The fully qualified enum class name
     * @param mixed $value The value to convert
     * @return mixed The enum value or null if invalid
     */
    public static function tryFrom(string $enumClass, mixed $value): mixed
    {
        // If the enum class has a tryFrom method, use it
        if (method_exists($enumClass, 'tryFrom')) {
            return $enumClass::tryFrom($value);
        }
        
        // For PHP 8.1+ backed enums, try from() 
        if (method_exists($enumClass, 'from')) {
            try {
                return $enumClass::from($value);
            } catch (\InvalidArgumentException $e) {
                return null;
            }
        }
        
        // For PHP 8.1+ unit enums
        if (method_exists($enumClass, 'cases')) {
            $cases = $enumClass::cases();
            foreach ($cases as $case) {
                if ($case->value === $value || $case->name === $value) {
                    return $case;
                }
            }
            return null;
        }
        
        // Fallback: check isValid and getAll
        if (method_exists($enumClass, 'isValid') && method_exists($enumClass, 'getAll')) {
            if ($enumClass::isValid($value)) {
                return $value;
            }
            return null;
        }
        
        return null;
    }
    
    /**
     * Get description for an enum value
     * 
     * @param string $enumClass The fully qualified enum class name
     * @param mixed $value The enum value
     * @return string The description or the value as string
     */
    public static function getDescription(string $enumClass, mixed $value): string
    {
        // If the enum class has a getDescription method, use it
        if (method_exists($enumClass, 'getDescription')) {
            return $enumClass::getDescription($value);
        }
        
        return (string)$value;
    }
    
    /**
     * Get all enum values with their descriptions
     * 
     * @param string $enumClass The fully qualified enum class name
     * @return array Associative array of value => description
     */
    public static function getAllWithDescriptions(string $enumClass): array
    {
        // If the enum class has a getAllWithDescriptions method, use it
        if (method_exists($enumClass, 'getAllWithDescriptions')) {
            return $enumClass::getAllWithDescriptions();
        }
        
        // Fallback: get cases and build descriptions
        $cases = self::getCases($enumClass);
        $descriptions = [];
        
        foreach ($cases as $case) {
            $descriptions[$case] = self::getDescription($enumClass, $case);
        }
        
        return $descriptions;
    }
}
