<?php

namespace App\Models;

use App\Enums\AdjustmentReason;

class StockAdjustment
{
    private ?int $id;
    private int $variantId;
    private int $previousQuantity;
    private int $newQuantity;
    private int $adjustment;
    private AdjustmentReason $reason;
    private ?string $notes;
    private string $adjustedBy;
    private \DateTime $createdAt;
    
    public function __construct()
    {
        $this->id = null;
        $this->notes = null;
        $this->createdAt = new \DateTime();
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getVariantId(): int
    {
        return $this->variantId;
    }
    
    public function setVariantId(int $variantId): self
    {
        $this->variantId = $variantId;
        return $this;
    }
    
    public function getPreviousQuantity(): int
    {
        return $this->previousQuantity;
    }
    
    public function setPreviousQuantity(int $previousQuantity): self
    {
        $this->previousQuantity = $previousQuantity;
        return $this;
    }
    
    public function getNewQuantity(): int
    {
        return $this->newQuantity;
    }
    
    public function setNewQuantity(int $newQuantity): self
    {
        $this->newQuantity = $newQuantity;
        return $this;
    }
    
    public function getAdjustment(): int
    {
        return $this->adjustment;
    }
    
    public function setAdjustment(int $adjustment): self
    {
        $this->adjustment = $adjustment;
        return $this;
    }
    
    public function getReason(): AdjustmentReason
    {
        return $this->reason;
    }
    
    public function setReason(AdjustmentReason $reason): self
    {
        $this->reason = $reason;
        return $this;
    }
    
    public function getNotes(): ?string
    {
        return $this->notes;
    }
    
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }
    
    public function getAdjustedBy(): string
    {
        return $this->adjustedBy;
    }
    
    public function setAdjustedBy(string $adjustedBy): self
    {
        $this->adjustedBy = $adjustedBy;
        return $this;
    }
    
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}