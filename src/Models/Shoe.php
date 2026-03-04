<?php

namespace App\Models;

use App\Enums\SizeCategory;
use App\Enums\ShoeType;
use App\Traits\SizeConversion;
use App\Exceptions\ValidationException;

class Shoe extends AbstractProduct
{
    use SizeConversion;
    
    private ShoeType $type;
    private SizeCategory $size;
    private string $material;
    private string $soleType;
    private string $closureType;
    
    public function __construct(
        string $sku,
        string $name,
        string $brand,
        float $price,
        ShoeType $type,
        SizeCategory $size,
        string $material
    ) {
        $this->setSku($sku);
        $this->name = $name;
        $this->brand = $brand;
        $this->setPrice($price);
        $this->type = $type;
        $this->setSize($size);
        $this->setMaterial($material);
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }
    
    // Strict size validation - only shoe sizes allowed
    public function setSize(SizeCategory $size): void
    {
        if (!$size->isShoeSize()) {
            throw new ValidationException(
                sprintf('Invalid shoe size: %s. Must be numeric EU size (35-48)', $size->value)
            );
        }
        $this->size = $size;
    }
    
    public function setMaterial(string $material): void
    {
        $allowedMaterials = ['Leather', 'Suede', 'Canvas', 'Mesh', 'Rubber', 'Synthetic'];
        if (!in_array($material, $allowedMaterials)) {
            throw new ValidationException(
                sprintf('Invalid material: %s. Allowed: %s', 
                    $material, 
                    implode(', ', $allowedMaterials))
            );
        }
        $this->material = $material;
    }
    
    // Get all available sizes for this shoe type
    public static function getAvailableSizes(ShoeType $type): array
    {
        return match($type) {
            ShoeType::RUNNING, ShoeType::CASUAL => [
                SizeCategory::SIZE_38, SizeCategory::SIZE_39, SizeCategory::SIZE_40,
                SizeCategory::SIZE_41, SizeCategory::SIZE_42, SizeCategory::SIZE_43,
                SizeCategory::SIZE_44, SizeCategory::SIZE_45
            ],
            ShoeType::FORMAL => [
                SizeCategory::SIZE_39, SizeCategory::SIZE_40, SizeCategory::SIZE_41,
                SizeCategory::SIZE_42, SizeCategory::SIZE_43, SizeCategory::SIZE_44
            ],
            default => SizeCategory::cases(), // All sizes
        };
    }
    
    // Convert sizes between systems
    public function getUsSize(): float
    {
        return $this->euToUs((int)$this->size->value);
    }
    
    public function getUkSize(): float
    {
        return $this->euToUk((int)$this->size->value);
    }
    
    public function getType(): string
    {
        return 'shoe';
    }
    
    public function validate(): bool
    {
        // Comprehensive validation
        $errors = [];
        
        if (strlen($this->name) < 2 || strlen($this->name) > 100) {
            $errors[] = 'Name must be 2-100 characters';
        }
        
        if (!preg_match('/^[A-Z][a-zA-Z\s]{1,49}$/', $this->brand)) {
            $errors[] = 'Brand must start with uppercase letter, 2-50 characters';
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Shoe validation failed: ' . implode(', ', $errors));
        }
        
        return true;
    }
}