<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InsufficientStockException extends Exception
{
    private ?int $currentStock;
    private ?int $requestedQuantity;
    private ?string $barcode;
    private ?string $sku;
    
    public function __construct(
        string $message = "Insufficient stock available",
        int $code = 0,
        Throwable $previous = null,
        int $currentStock = null,
        int $requestedQuantity = null,
        string $barcode = null,
        string $sku = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->currentStock = $currentStock;
        $this->requestedQuantity = $requestedQuantity;
        $this->barcode = $barcode;
        $this->sku = $sku;
    }
    
    public function getCurrentStock(): ?int
    {
        return $this->currentStock;
    }
    
    public function getRequestedQuantity(): ?int
    {
        return $this->requestedQuantity;
    }
    
    public function getBarcode(): ?string
    {
        return $this->barcode;
    }
    
    public function getSku(): ?string
    {
        return $this->sku;
    }
    
    public function getShortage(): ?int
    {
        if ($this->currentStock !== null && $this->requestedQuantity !== null) {
            return $this->requestedQuantity - $this->currentStock;
        }
        return null;
    }
    
    public function toArray(): array
    {
        return [
            'error' => 'INSUFFICIENT_STOCK',
            'message' => $this->getMessage(),
            'current_stock' => $this->currentStock,
            'requested_quantity' => $this->requestedQuantity,
            'shortage' => $this->getShortage(),
            'barcode' => $this->barcode,
            'sku' => $this->sku,
        ];
    }
    
    /**
     * Create a user-friendly error message
     */
    public function getUserMessage(): string
    {
        if ($this->currentStock !== null && $this->requestedQuantity !== null) {
            $shortage = $this->getShortage();
            return sprintf(
                'Cannot process request for %d items. Only %d available. Shortage: %d',
                $this->requestedQuantity,
                $this->currentStock,
                $shortage
            );
        }
        
        return $this->getMessage();
    }
    
    /**
     * Check if this is a complete out-of-stock situation
     */
    public function isOutOfStock(): bool
    {
        return $this->currentStock === 0;
    }
    
    /**
     * Check if this is a partial fulfillment situation
     */
    public function isPartialStock(): bool
    {
        return $this->currentStock !== null && 
               $this->requestedQuantity !== null &&
               $this->currentStock > 0 &&
               $this->currentStock < $this->requestedQuantity;
    }
}