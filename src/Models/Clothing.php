<?php

namespace App\Models;

use App\Enums\SizeCategory;
use App\Enums\ClothingType;
use App\Exceptions\ValidationException;

class Clothing extends AbstractProduct
{
    private string $clothingType;  // Changed from ClothingType to string
    private string $size;          // Changed from SizeCategory to string
    private string $fabric;
    private ?string $fit;
    private ?string $careInstructions;
    
    public function __construct(
        string $sku,
        string $name,
        string $brand,
        float $price,
        string $clothingType,      // Changed from ClothingType to string
        string $size,              // Changed from SizeCategory to string
        string $fabric,
        ?string $fit = null,
        ?string $careInstructions = null
    ) {
        $this->setSku($sku);
        $this->name = $name;
        $this->brand = $brand;
        $this->setPrice($price);
        $this->setClothingType($clothingType);  // Use setter with validation
        $this->setSize($size);                  // Use setter with validation
        $this->setFabric($fabric);
        $this->fit = $fit;
        $this->careInstructions = $careInstructions;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }
    
    // Implement abstract methods from AbstractProduct
    public function getType(): string
    {
        return 'clothing';
    }
    
    public function validate(): bool
    {
        $errors = [];
        
        if (empty($this->fabric)) {
            $errors[] = 'Fabric type is required';
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Clothing validation failed: ' . implode(', ', $errors));
        }
        
        return true;
    }
    
    // Clothing type validation
    public function setClothingType(string $clothingType): void
    {
        if (!ClothingType::isValid($clothingType)) {
            $validTypes = ClothingType::getAll();
            throw new ValidationException(
                sprintf('Invalid clothing type: %s. Allowed: %s', 
                    $clothingType, 
                    implode(', ', $validTypes))
            );
        }
        $this->clothingType = $clothingType;
    }
    
    public function getClothingType(): string
    {
        return $this->clothingType;
    }
    
    // Size validation - only clothing sizes allowed
    public function setSize(string $size): void
    {
        $allowedSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        if (!in_array($size, $allowedSizes)) {
            throw new ValidationException(
                sprintf('Invalid clothing size: %s. Must be XS-XXXL', $size)
            );
        }
        $this->size = $size;
    }
    
    public function getSize(): string
    {
        return $this->size;
    }
    
    // Check if size is clothing size (utility method)
    public function isClothingSize(): bool
    {
        $allowedSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        return in_array($this->size, $allowedSizes);
    }
    
    // Fabric validation with business rules
    public function setFabric(string $fabric): void
    {
        $fabric = ucwords(strtolower(trim($fabric)));
        
        $fabricRules = [
            'Cotton' => ['shirt', 'pants', 'dress'],
            'Polyester' => ['jacket', 'sweater'],
            'Wool' => ['sweater', 'jacket'],
            'Silk' => ['shirt', 'dress'],
            'Denim' => ['pants', 'jacket'],
            'Linen' => ['shirt', 'pants'],
        ];
        
        if (!array_key_exists($fabric, $fabricRules)) {
            throw new ValidationException(
                sprintf('Invalid fabric: %s. Allowed: %s',
                    $fabric,
                    implode(', ', array_keys($fabricRules)))
            );
        }
        
        // Check fabric compatibility with clothing type
        if (!in_array($this->clothingType, $fabricRules[$fabric])) {
            throw new ValidationException(
                sprintf('%s fabric is not suitable for %s items',
                    $fabric, $this->clothingType)
            );
        }
        
        $this->fabric = $fabric;
    }
    
    public function getFabric(): string
    {
        return $this->fabric;
    }
    
    // Getters and setters for optional properties
    public function getFit(): ?string
    {
        return $this->fit;
    }
    
    public function setFit(?string $fit): void
    {
        $this->fit = $fit;
    }
    
    public function getCareInstructions(): ?string
    {
        return $this->careInstructions;
    }
    
    public function setCareInstructions(?string $careInstructions): void
    {
        $this->careInstructions = $careInstructions;
    }
    
    // Size chart for different clothing types
    public function getSizeChart(): array
    {
        $charts = [
            'shirt' => [
                'XS' => ['chest' => '86-91 cm', 'length' => '66 cm'],
                'S' => ['chest' => '91-97 cm', 'length' => '68 cm'],
                'M' => ['chest' => '97-102 cm', 'length' => '71 cm'],
                'L' => ['chest' => '102-107 cm', 'length' => '74 cm'],
                'XL' => ['chest' => '107-112 cm', 'length' => '76 cm'],
            ],
            'pants' => [
                'XS' => ['waist' => '71-76 cm', 'inseam' => '81 cm'],
                'S' => ['waist' => '76-81 cm', 'inseam' => '84 cm'],
                'M' => ['waist' => '81-86 cm', 'inseam' => '87 cm'],
                'L' => ['waist' => '86-91 cm', 'inseam' => '89 cm'],
                'XL' => ['waist' => '91-97 cm', 'inseam' => '91 cm'],
            ],
            'jacket' => [
                'XS' => ['chest' => '86-91 cm', 'sleeve' => '63 cm'],
                'S' => ['chest' => '91-97 cm', 'sleeve' => '64 cm'],
                'M' => ['chest' => '97-102 cm', 'sleeve' => '65 cm'],
                'L' => ['chest' => '102-107 cm', 'sleeve' => '66 cm'],
                'XL' => ['chest' => '107-112 cm', 'sleeve' => '67 cm'],
            ],
            'dress' => [
                'XS' => ['bust' => '81-86 cm', 'waist' => '66-71 cm', 'length' => '86 cm'],
                'S' => ['bust' => '86-91 cm', 'waist' => '71-76 cm', 'length' => '89 cm'],
                'M' => ['bust' => '91-97 cm', 'waist' => '76-81 cm', 'length' => '92 cm'],
                'L' => ['bust' => '97-102 cm', 'waist' => '81-86 cm', 'length' => '95 cm'],
                'XL' => ['bust' => '102-107 cm', 'waist' => '86-91 cm', 'length' => '98 cm'],
            ],
        ];
        
        return $charts[$this->clothingType] ?? [];
    }
    
    // Business logic methods
    public function calculateWashingCost(): float
    {
        $fabricCosts = [
            'Cotton' => 5.00,
            'Silk' => 12.50,
            'Wool' => 10.00,
            'Denim' => 7.50,
            'Polyester' => 4.00,
            'Linen' => 6.00,
        ];
        
        return $fabricCosts[$this->fabric] ?? 5.00;
    }
    
    public function isDryCleanOnly(): bool
    {
        $dryCleanFabrics = ['Silk', 'Wool', 'Cashmere'];
        return in_array($this->fabric, $dryCleanFabrics);
    }
    
    public function getShelfLife(): string
    {
        $shelfLife = [
            'Cotton' => '5-7 years',
            'Polyester' => '8-10 years',
            'Silk' => '3-5 years',
            'Wool' => '7-10 years',
            'Denim' => '10-15 years',
            'Linen' => '6-8 years',
        ];
        
        return $shelfLife[$this->fabric] ?? '5-7 years';
    }
    
    // For compatibility with old code
    public function getSizeValue(): string
    {
        return $this->size;
    }
    
    public function getClothingTypeValue(): string
    {
        return $this->clothingType;
    }
}