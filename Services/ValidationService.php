---  
```php
<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Exceptions\InvalidBarcodeException;
use App\Models\AbstractProduct;
use App\Models\Shoe;
use App\Models\Clothing;
use App\Enums\AdjustmentReason;
use App\Enums\ShoeSize;
use App\Enums\ClothingSize;

class ValidationService
{
    private array $validationRules = [];
    private array $validationErrors = [];
    
    public function __construct()
    {
        $this->initializeValidationRules();
    }
    
    private function initializeValidationRules(): void
    {
        $this->validationRules = [
            'barcode' => [
                'pattern' => '/^[0-9]{12,14}$/',
                'message' => 'Barcode must be 12-14 numeric digits (EAN/UPC format)',
                'check_digit' => true,
            ],
            'sku' => [
                'pattern' => '/^[A-Z0-9]{8,12}$/',
                'message' => 'SKU must be 8-12 uppercase alphanumeric characters',
            ],
            'quantity' => [
                'min' => 0,
                'max' => 10000,
                'message' => 'Quantity must be between 0 and 10,000',
            ],
            'price' => [
                'min' => 0,
                'max' => 10000,
                'decimals' => 2,
                'message' => 'Price must be between 0 and 10,000 with 2 decimal places',
            ],
            'adjustment' => [
                'min' => -1000,
                'max' => 1000,
                'message' => 'Adjustment must be between -1,000 and 1,000',
            ],
        ];
    }
    
    public function validateBarcode(string $barcode): bool
    {
        if (!preg_match($this->validationRules['barcode']['pattern'], $barcode)) {
            throw new InvalidBarcodeException(
                $this->validationRules['barcode']['message'],
                $barcode
            );
        }
        
        if (strlen($barcode) === 13 && !$this->validateEAN13CheckDigit($barcode)) {
            throw new InvalidBarcodeException('Invalid EAN-13 check digit', $barcode);
        }
        
        if (strlen($barcode) === 12 && !$this->validateUPCA($barcode)) {
            throw new InvalidBarcodeException('Invalid UPC-A barcode format', $barcode);
        }
        
        return true;
    }
    
    private function validateEAN13CheckDigit(string $barcode): bool
    {
        $checkDigit = (int)substr($barcode, -1);
        $sum = 0;
        
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$barcode[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        
        $calculatedCheckDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === $calculatedCheckDigit;
    }
    
    private function validateUPCA(string $barcode): bool
    {
        $ean13 = '0' . $barcode;
        return $this->validateEAN13CheckDigit($ean13);
    }
    
    public function validateProduct(AbstractProduct $product): bool
    {
        $this->validationErrors = [];
        
        try {
            $this->validateSku($product->getSku());
            $this->validatePrice($product->getPrice());
            $this->validateSafetyStock($product->getSafetyStock());
            $this->validateFieldLength($product->getName(), 'name', 2, 100);
            $this->validateFieldLength($product->getBrand(), 'brand', 2, 50);
            
            if ($product->getColor() !== null) {
                $this->validateFieldLength($product->getColor(), 'color', 2, 30);
            }
            
            $this->validateBarcode($product->getBarcode());
            
        } catch (ValidationException | InvalidBarcodeException $e) {
            $this->validationErrors[] = $e->getMessage();
        }
        
        return empty($this->validationErrors);
    }
    
    private function validateSku(string $sku): void
    {
        if (!preg_match($this->validationRules['sku']['pattern'], $sku)) {
            throw new ValidationException($this->validationRules['sku']['message']);
        }
    }
    
    private function validatePrice(float $price): void
    {
        $rule = $this->validationRules['price'];
        if ($price < $rule['min'] || $price > $rule['max'] || round($price, 2) != $price) {
            throw new ValidationException($rule['message']);
        }
    }
    
    private function validateSafetyStock(int $quantity): void
    {
        $rule = $this->validationRules['quantity'];
        if ($quantity < $rule['min'] || $quantity > $rule['max']) {
            throw new ValidationException($rule['message']);
        }
    }
    
    private function validateFieldLength(?string $value, string $field, int $min, int $max): void
    {
        $length = strlen($value ?? '');
        if ($length < $min || $length > $max) {
            throw new ValidationException(
                ucfirst($field) . " must be between {$min} and {$max} characters"
            );
        }
    }
    
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}

/**
 * Example AbstractProduct class with required getters
 */
namespace App\Models;

abstract class AbstractProduct
{
    protected string $sku;
    protected string $name;
    protected string $brand;
    protected ?string $color;
    protected string $barcode;
    protected float $price;
    protected int $safetyStock;

    public function __construct(
        string $sku,
        string $name,
        string $brand,
        ?string $color,
        string $barcode,
        float $price,
        int $safetyStock
    ) {
        $this->sku = $sku;
        $this->name = $name;
        $this->brand = $brand;
        $this->color = $color;
        $this->barcode = $barcode;
        $this->price = $price;
        $this->safetyStock = $safetyStock;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getSafetyStock(): int
    {
        return $this->safetyStock;
    }
}