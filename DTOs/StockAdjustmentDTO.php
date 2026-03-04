<?php

namespace App\DTOs;

use App\Enums\AdjustmentReason;
use App\Exceptions\ValidationException;

class StockAdjustmentDTO
{
    private string $barcode;
    private int $quantity;
    private string $reason;
    private string $notes;
    private string $adjustedBy;
    
    public function __construct(
        string $barcode,
        int $quantity,
        string $reason,
        string $notes = '',
        string $adjustedBy = 'system'
    ) {
        $this->barcode = $barcode;
        $this->setQuantity($quantity);
        $this->setReason($reason);
        $this->notes = $notes;
        $this->adjustedBy = $adjustedBy;
    }
    
    public static function fromArray(array $data): self
    {
        return new self(
            $data['barcode'],
            (int)$data['quantity'],
            $data['reason'],
            $data['notes'] ?? '',
            $data['adjusted_by'] ?? 'system'
        );
    }
    
    public function toArray(): array
    {
        return [
            'barcode' => $this->barcode,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'reason_description' => AdjustmentReason::getDescription($this->reason),
            'notes' => $this->notes,
            'adjusted_by' => $this->adjustedBy,
        ];
    }
    
    public function validate(): void
    {
        if (empty($this->barcode)) {
            throw new ValidationException('Barcode is required');
        }
        
        if ($this->quantity === 0) {
            throw new ValidationException('Adjustment quantity cannot be zero');
        }
        
        if (!AdjustmentReason::isValid($this->reason)) {
            throw new ValidationException('Invalid adjustment reason');
        }
    }
    
    public function setQuantity(int $quantity): void
    {
        if ($quantity === 0) {
            throw new ValidationException('Adjustment quantity cannot be zero');
        }
        $this->quantity = $quantity;
    }
    
    public function setReason(string $reason): void
    {
        if (!AdjustmentReason::isValid($reason)) {
            throw new ValidationException('Invalid adjustment reason');
        }
        $this->reason = $reason;
    }
    
    // Getters
    public function getBarcode(): string { return $this->barcode; }
    public function getQuantity(): int { return $this->quantity; }
    public function getReason(): string { return $this->reason; }
    public function getNotes(): string { return $this->notes; }
    public function getAdjustedBy(): string { return $this->adjustedBy; }
    
    public function isIncrease(): bool
    {
        return $this->quantity > 0;
    }
    
    public function isDecrease(): bool
    {
        return $this->quantity < 0;
    }
}