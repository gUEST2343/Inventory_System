<?php

namespace App\DTOs;

use App\Enums\Color;
use App\Exceptions\ValidationException;

class ProductVariantDTO
{
    private string $barcode;
    private string $color;  // Changed from Color to string
    private int $quantity;
    private string $size;
    private float $costPrice;
    private float $sellingPrice;
    private ?string $sku;
    private ?string $location;
    private ?string $supplierCode;
    private ?\DateTime $manufacturedDate;
    private ?\DateTime $expiryDate;
    private ?string $notes;
    private int $reservedQuantity = 0;
    
    public function __construct(
        string $barcode,
        string $color,       // Changed from Color to string
        int $quantity,
        string $size,
        float $costPrice,
        float $sellingPrice,
        ?string $sku = null,
        ?string $location = null,
        ?string $supplierCode = null,
        ?\DateTime $manufacturedDate = null,
        ?\DateTime $expiryDate = null,
        ?string $notes = null,
        int $reservedQuantity = 0
    ) {
        // Validate inputs
        $this->validateInputs($barcode, $quantity, $costPrice, $sellingPrice, $size, $color);
        
        $this->barcode = $barcode;
        $this->color = $color;
        $this->quantity = $quantity;
        $this->size = $size;
        $this->costPrice = $costPrice;
        $this->sellingPrice = $sellingPrice;
        $this->sku = $sku;
        $this->location = $location;
        $this->supplierCode = $supplierCode;
        $this->manufacturedDate = $manufacturedDate;
        $this->expiryDate = $expiryDate;
        $this->notes = $notes;
        $this->reservedQuantity = $reservedQuantity;
    }
    
    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        // Validate required fields
        $required = ['barcode', 'color', 'quantity', 'size', 'cost_price', 'selling_price'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Field '$field' is required");
            }
        }
        
        // Validate color
        $colorValue = $data['color'];
        if (!Color::isValid($colorValue)) {
            $validColors = Color::getAll();
            throw new ValidationException(
                sprintf('Invalid color: %s. Valid colors: %s', 
                    $colorValue, 
                    implode(', ', $validColors))
            );
        }
        
        // Parse dates if provided
        $manufacturedDate = isset($data['manufactured_date']) 
            ? self::parseDate($data['manufactured_date'], 'manufactured_date') 
            : null;
            
        $expiryDate = isset($data['expiry_date']) 
            ? self::parseDate($data['expiry_date'], 'expiry_date') 
            : null;
        
        return new self(
            $data['barcode'],
            $colorValue,
            (int)$data['quantity'],
            $data['size'],
            (float)$data['cost_price'],
            (float)$data['selling_price'],
            $data['sku'] ?? null,
            $data['location'] ?? null,
            $data['supplier_code'] ?? null,
            $manufacturedDate,
            $expiryDate,
            $data['notes'] ?? null,
            isset($data['reserved_quantity']) ? (int)$data['reserved_quantity'] : 0
        );
    }
    
    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        $array = [
            'barcode' => $this->barcode,
            'color' => $this->color,
            'color_name' => $this->getColorName(),
            'color_hex' => $this->getColorHex(),
            'quantity' => $this->quantity,
            'available_quantity' => $this->getAvailableQuantity(),
            'reserved_quantity' => $this->reservedQuantity,
            'size' => $this->size,
            'cost_price' => $this->costPrice,
            'selling_price' => $this->sellingPrice,
            'profit_margin' => $this->calculateProfitMargin(),
            'profit_per_unit' => $this->calculateProfitPerUnit(),
            'stock_value' => $this->calculateStockValue(),
            'potential_revenue' => $this->calculatePotentialRevenue(),
            'sku' => $this->sku,
            'location' => $this->location,
            'supplier_code' => $this->supplierCode,
            'notes' => $this->notes,
            'has_expiry' => $this->hasExpiry(),
            'is_expired' => $this->isExpired(),
        ];
        
        // Add dates if they exist
        if ($this->manufacturedDate) {
            $array['manufactured_date'] = $this->manufacturedDate->format('Y-m-d');
            $array['manufactured_date_iso'] = $this->manufacturedDate->format('c');
        }
        
        if ($this->expiryDate) {
            $array['expiry_date'] = $this->expiryDate->format('Y-m-d');
            $array['expiry_date_iso'] = $this->expiryDate->format('c');
            $array['days_until_expiry'] = $this->getDaysUntilExpiry();
        }
        
        // Add validation flags
        $array['is_valid'] = $this->isValid();
        $array['validation_errors'] = $this->getValidationErrors();
        
        return $array;
    }
    
    /**
     * Calculate profit margin percentage
     */
    public function calculateProfitMargin(): float
    {
        if ($this->costPrice <= 0) {
            return 0;
        }
        return round((($this->sellingPrice - $this->costPrice) / $this->costPrice) * 100, 2);
    }
    
    /**
     * Calculate profit per unit
     */
    public function calculateProfitPerUnit(): float
    {
        return round($this->sellingPrice - $this->costPrice, 2);
    }
    
    /**
     * Calculate total stock value at cost price
     */
    public function calculateStockValue(): float
    {
        return round($this->quantity * $this->costPrice, 2);
    }
    
    /**
     * Calculate potential revenue if all items sold
     */
    public function calculatePotentialRevenue(): float
    {
        return round($this->quantity * $this->sellingPrice, 2);
    }
    
    /**
     * Get available quantity (total - reserved)
     */
    public function getAvailableQuantity(): int
    {
        return max(0, $this->quantity - $this->reservedQuantity);
    }
    
    /**
     * Check if variant has expiry date
     */
    public function hasExpiry(): bool
    {
        return $this->expiryDate !== null;
    }
    
    /**
     * Check if variant is expired
     */
    public function isExpired(): bool
    {
        if (!$this->hasExpiry()) {
            return false;
        }
        return $this->expiryDate < new \DateTime();
    }
    
    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->hasExpiry()) {
            return null;
        }
        
        $now = new \DateTime();
        $interval = $now->diff($this->expiryDate);
        
        if ($this->expiryDate < $now) {
            return -$interval->days; // Negative for expired
        }
        
        return $interval->days;
    }
    
    /**
     * Get color name (formatted)
     */
    public function getColorName(): string
    {
        return Color::getDisplayName($this->color);
    }
    
    /**
     * Validate DTO data
     */
    public function isValid(): bool
    {
        return empty($this->getValidationErrors());
    }
    
    /**
     * Get validation errors
     */
    public function getValidationErrors(): array
    {
        $errors = [];
        
        // Barcode validation
        if (strlen($this->barcode) < 8 || strlen($this->barcode) > 14) {
            $errors[] = 'Barcode must be 8-14 characters';
        }
        
        if (!ctype_alnum($this->barcode)) {
            $errors[] = 'Barcode must be alphanumeric';
        }
        
        // Color validation
        if (!Color::isValid($this->color)) {
            $validColors = Color::getAll();
            $errors[] = sprintf('Invalid color. Valid: %s', implode(', ', $validColors));
        }
        
        // Quantity validation
        if ($this->quantity < 0) {
            $errors[] = 'Quantity cannot be negative';
        }
        
        if ($this->quantity > 100000) {
            $errors[] = 'Quantity exceeds maximum limit (100,000)';
        }
        
        // Reserved quantity validation
        if ($this->reservedQuantity < 0) {
            $errors[] = 'Reserved quantity cannot be negative';
        }
        
        if ($this->reservedQuantity > $this->quantity) {
            $errors[] = 'Reserved quantity cannot exceed total quantity';
        }
        
        // Price validation
        if ($this->costPrice < 0) {
            $errors[] = 'Cost price cannot be negative';
        }
        
        if ($this->sellingPrice < 0) {
            $errors[] = 'Selling price cannot be negative';
        }
        
        if ($this->sellingPrice < $this->costPrice) {
            $errors[] = 'Selling price cannot be less than cost price';
        }
        
        // Date validation
        if ($this->manufacturedDate && $this->expiryDate) {
            if ($this->manufacturedDate > $this->expiryDate) {
                $errors[] = 'Manufactured date cannot be after expiry date';
            }
        }
        
        if ($this->expiryDate && $this->expiryDate < new \DateTime()) {
            $errors[] = 'Product is expired';
        }
        
        return $errors;
    }
    
    /**
     * Get color hex code based on color name
     */
    public function getColorHex(): string
    {
        return Color::getHexCode($this->color);
    }
    
    /**
     * Convert to JSON string
     */
    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $options);
    }
    
    // Getters
    public function getBarcode(): string { return $this->barcode; }
    public function getColor(): string { return $this->color; }
    public function getQuantity(): int { return $this->quantity; }
    public function getSize(): string { return $this->size; }
    public function getCostPrice(): float { return $this->costPrice; }
    public function getSellingPrice(): float { return $this->sellingPrice; }
    public function getSku(): ?string { return $this->sku; }
    public function getLocation(): ?string { return $this->location; }
    public function getSupplierCode(): ?string { return $this->supplierCode; }
    public function getManufacturedDate(): ?\DateTime { return $this->manufacturedDate; }
    public function getExpiryDate(): ?\DateTime { return $this->expiryDate; }
    public function getNotes(): ?string { return $this->notes; }
    public function getReservedQuantity(): int { return $this->reservedQuantity; }
    
    // Setters with validation
    public function setQuantity(int $quantity): void
    {
        if ($quantity < 0) {
            throw new ValidationException('Quantity cannot be negative');
        }
        if ($quantity > 100000) {
            throw new ValidationException('Quantity exceeds maximum limit (100,000)');
        }
        $this->quantity = $quantity;
    }
    
    public function setReservedQuantity(int $reservedQuantity): void
    {
        if ($reservedQuantity < 0) {
            throw new ValidationException('Reserved quantity cannot be negative');
        }
        if ($reservedQuantity > $this->quantity) {
            throw new ValidationException('Reserved quantity cannot exceed total quantity');
        }
        $this->reservedQuantity = $reservedQuantity;
    }
    
    public function setSellingPrice(float $sellingPrice): void
    {
        if ($sellingPrice < 0) {
            throw new ValidationException('Selling price cannot be negative');
        }
        if ($sellingPrice < $this->costPrice) {
            throw new ValidationException('Selling price cannot be less than cost price');
        }
        $this->sellingPrice = $sellingPrice;
    }
    
    public function setColor(string $color): void
    {
        if (!Color::isValid($color)) {
            $validColors = Color::getAll();
            throw new ValidationException(
                sprintf('Invalid color: %s. Valid colors: %s', 
                    $color, 
                    implode(', ', $validColors))
            );
        }
        $this->color = $color;
    }
    
    private function validateInputs(
        string $barcode,
        int $quantity,
        float $costPrice,
        float $sellingPrice,
        string $size,
        string $color
    ): void {
        if (empty($barcode)) {
            throw new ValidationException('Barcode is required');
        }
        
        if (!Color::isValid($color)) {
            $validColors = Color::getAll();
            throw new ValidationException(
                sprintf('Invalid color: %s. Valid colors: %s', 
                    $color, 
                    implode(', ', $validColors))
            );
        }
        
        if ($quantity < 0) {
            throw new ValidationException('Quantity cannot be negative');
        }
        
        if ($costPrice < 0) {
            throw new ValidationException('Cost price cannot be negative');
        }
        
        if ($sellingPrice < 0) {
            throw new ValidationException('Selling price cannot be negative');
        }
        
        if ($sellingPrice < $costPrice) {
            throw new ValidationException('Selling price cannot be less than cost price');
        }
        
        if (empty($size)) {
            throw new ValidationException('Size is required');
        }
    }
    
    private static function parseDate(string $dateString, string $fieldName): \DateTime
    {
        try {
            $date = new \DateTime($dateString);
            // Ensure it's a valid date
            if ($date->format('Y-m-d') !== date('Y-m-d', strtotime($dateString))) {
                throw new \Exception('Invalid date');
            }
            return $date;
        } catch (\Exception $e) {
            throw new ValidationException("Invalid $fieldName format. Use YYYY-MM-DD");
        }
    }
}