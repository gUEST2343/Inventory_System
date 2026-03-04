<?php

namespace App\Models;

use App\Enums\Color;
use App\Exceptions\ValidationException;

class ProductVariant
{
    private int $id;
    private AbstractProduct $product;
    private string $barcode;
    private Color $color;
    private int $quantity;
    private int $reservedQuantity = 0;
    private \DateTime $lastRestocked;
    private int $version = 0; // For optimistic locking
    const MAX_QUANTITY = 10000;
    public function __construct(
        AbstractProduct $product,
        string $barcode,
        Color $color,
        int $initialQuantity = 0
    ) {
        $this->product = $product;
        $this->setBarcode($barcode);
        $this->color = $color;
        $this->setQuantity($initialQuantity);
        $this->lastRestocked = new \DateTime();
    }
    
    // Strict barcode validation
    public function setBarcode(string $barcode): void
    {
        if (!preg_match('/^[0-9]{12,14}$/', $barcode)) {
            throw new ValidationException(
                'Barcode must be 12-14 numeric digits (EAN/UPC format)'
            );
        }
        
        // Validate check digit for EAN-13
        if (strlen($barcode) === 13 && !$this->isValidEAN13($barcode)) {
            throw new ValidationException('Invalid EAN-13 check digit');
        }
        
        $this->barcode = $barcode;
    }
    
    private function isValidEAN13(string $barcode): bool
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
    
    // Stock management with business rules
    public function setQuantity(int $quantity): void
    {
        if ($quantity < 0) {
            throw new ValidationException('Stock quantity cannot be negative');
        }
        
        if ($quantity > 10000) {
            throw new ValidationException('Stock quantity exceeds maximum limit (10,000)');
        }
        
        $this->quantity = $quantity;
        $this->version++; // Increment version for optimistic locking
    }
    
    public function adjustQuantity(int $delta, string $reason): void
    {
        $newQuantity = $this->quantity + $delta;
        
        if ($newQuantity < 0) {
            throw new ValidationException(
                sprintf('Insufficient stock. Current: %d, Required: %d',
                    $this->quantity,
                    abs($delta))
            );
        }
        
        if ($newQuantity > 10000) {
            throw new ValidationException('Stock would exceed maximum capacity');
        }
        
        $this->quantity = $newQuantity;
        
        if ($delta > 0) {
            $this->lastRestocked = new \DateTime();
        }
        
        $this->version++;
    }
    
    public function reserve(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new ValidationException('Reservation quantity must be positive');
        }
        
        $available = $this->quantity - $this->reservedQuantity;
        if ($quantity > $available) {
            throw new ValidationException(
                sprintf('Cannot reserve %d items. Available: %d',
                    $quantity,
                    $available)
            );
        }
        
        $this->reservedQuantity += $quantity;
    }
    
    public function releaseReservation(int $quantity): void
    {
        if ($quantity > $this->reservedQuantity) {
            throw new ValidationException(
                sprintf('Cannot release %d items. Reserved: %d',
                    $quantity,
                    $this->reservedQuantity)
            );
        }
        
        $this->reservedQuantity -= $quantity;
    }
    
    public function getAvailableStock(): int
    {
        return $this->quantity - $this->reservedQuantity;
    }
    
    public function isLowStock(): bool
    {
        return $this->getAvailableStock() <= $this->product->getSafetyStock();
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getProduct(): AbstractProduct { return $this->product; }
    public function getBarcode(): string { return $this->barcode; }
    public function getColor(): Color { return $this->color; }
    public function getQuantity(): int { return $this->quantity; }
    public function getReservedQuantity(): int { return $this->reservedQuantity; }
    public function getLastRestocked(): \DateTime { return $this->lastRestocked; }
    public function getVersion(): int { return $this->version; }

    public function getProductId(): int
    {
        return $this->product->getId();
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->lastRestocked = $updatedAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->lastRestocked;
    }

    public function setReservedQuantity(int $reservedQuantity): void
    {
        $this->reservedQuantity = $reservedQuantity;
    }
}
