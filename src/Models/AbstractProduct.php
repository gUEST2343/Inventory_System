<?php

namespace App\Models;

use App\Enums\Color;
use App\Exceptions\ValidationException;

abstract class AbstractProduct
{
    protected int $id;
    protected string $sku;
    protected string $name;
    protected string $brand;
    protected float $price;
    protected int $safetyStock;
    protected \DateTime $createdAt;
    protected \DateTime $updatedAt;
     
    // Strict type validation in setters
    public function setSku(string $sku): void
    {
        if (!preg_match('/^[A-Z0-9]{8,12}$/', $sku)) {
            throw new ValidationException('SKU must be 8-12 uppercase alphanumeric characters');
        }
        $this->sku = $sku;
    }
    
    public function setPrice(float $price): void
    {
        if ($price < 0) {
            throw new ValidationException('Price cannot be negative');
        }
        if ($price > 10000) {
            throw new ValidationException('Price exceeds maximum limit');
        }
        $this->price = round($price, 2);
    }
    
    public function setSafetyStock(int $safetyStock): void
    {
        if ($safetyStock < 0) {
            throw new ValidationException('Safety stock cannot be negative');
        }
        if ($safetyStock > 1000) {
            throw new ValidationException('Safety stock exceeds maximum limit');
        }
        $this->safetyStock = $safetyStock;
    }
    
    // Abstract methods enforce implementation
    abstract public function getType(): string;
    abstract public function validate(): bool;
    
    // Getters
    public function getId(): int { return $this->id; }
    public function getSku(): string { return $this->sku; }
    public function getName(): string { return $this->name; }
    public function getBrand(): string { return $this->brand; }
    public function getPrice(): float { return $this->price; }
    public function getSafetyStock(): int { return $this->safetyStock; }
}