<?php

namespace App\DTOs;

class ScanResponseDTO
{
    private string $barcode;
    private string $productName;
    private string $productType;
    private string $size;
    private string $color;
    private int $quantity;
    private int $available;
    private bool $lowStock;
    private float $price;
    private ?string $lastRestocked;
    private ?string $expiryDate;
    
    public function __construct(
        string $barcode,
        string $productName,
        string $productType,
        string $size,
        string $color,
        int $quantity,
        int $available,
        bool $lowStock,
        float $price,
        ?string $lastRestocked = null,
        ?string $expiryDate = null
    ) {
        $this->barcode = $barcode;
        $this->productName = $productName;
        $this->productType = $productType;
        $this->size = $size;
        $this->color = $color;
        $this->quantity = $quantity;
        $this->available = $available;
        $this->lowStock = $lowStock;
        $this->price = $price;
        $this->lastRestocked = $lastRestocked;
        $this->expiryDate = $expiryDate;
    }
    
    public function toArray(): array
    {
        return [
            'scan_result' => [
                'barcode' => $this->barcode,
                'product' => $this->productName,
                'type' => $this->productType,
                'attributes' => [
                    'size' => $this->size,
                    'color' => $this->color,
                ],
                'stock' => [
                    'total' => $this->quantity,
                    'available' => $this->available,
                    'status' => $this->getStockStatus(),
                    'low_stock_warning' => $this->lowStock,
                ],
                'price' => $this->price,
                'metadata' => [
                    'last_restocked' => $this->lastRestocked,
                    'expiry_date' => $this->expiryDate,
                    'scanned_at' => date('Y-m-d H:i:s'),
                ]
            ]
        ];
    }
    
    private function getStockStatus(): string
    {
        if ($this->quantity === 0) {
            return 'OUT_OF_STOCK';
        }
        
        if ($this->lowStock) {
            return 'LOW_STOCK';
        }
        
        if ($this->quantity >= 100) {
            return 'HIGH_STOCK';
        }
        
        return 'IN_STOCK';
    }
}