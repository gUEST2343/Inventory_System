<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Enums\AdjustmentReason;
use App\Enums\ProductType;
use App\Enums\Color;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ValidationException;
use App\Repositories\ProductRepository;
use App\Repositories\StockAdjustmentRepository;
use DateTime;
use PDOException;

class InventoryService
{
    private ProductRepository $productRepository;
    private StockAdjustmentRepository $adjustmentRepository;
    private ValidationService $validationService;
    
    public function __construct(
        ProductRepository $productRepository,
        StockAdjustmentRepository $adjustmentRepository,
        ValidationService $validationService
    ) {
        $this->productRepository = $productRepository;
        $this->adjustmentRepository = $adjustmentRepository;
        $this->validationService = $validationService;
    }
    
    /**
     * Adjust stock with comprehensive validation and audit trail
     */
    public function adjustStock(
        string $barcode,
        int $quantity,
        string $reason,
        string $notes = '',
        string $adjustedBy = 'system'
    ): StockAdjustment {
        // Input validation
        if ($quantity === 0) {
            throw new ValidationException('Adjustment quantity cannot be zero');
        }
        
        // Find variant
        $variant = $this->productRepository->findVariantByBarcode($barcode);
        if (!$variant) {
            throw new ValidationException("Product variant not found for barcode: $barcode");
        }

        // Business rule validation
        $this->validateAdjustmentRules($variant, $quantity, $reason);
        
        // Get current state before adjustment
        $previousQuantity = $variant->getQuantity();
        
        try {
            $newQuantity = $previousQuantity + $quantity;
            
            // Final validation before committing
            if ($newQuantity < 0) {
                throw new InsufficientStockException(
                    "Cannot reduce stock below 0. Current: $previousQuantity, Adjustment: $quantity",
                    $barcode,
                    abs($quantity),
                    $previousQuantity
                );
            }
            
            // Update variant
            $variant->setQuantity($newQuantity);
            $variant->setUpdatedAt(new DateTime());
            
            if (!$this->productRepository->saveVariant($variant)) {
                throw new ValidationException('Failed to save product variant');
            }
            
        } catch (PDOException $e) {
            // Handle database errors
            if (strpos($e->getMessage(), 'version') !== false || 
                strpos($e->getMessage(), 'lock') !== false) {
                throw new ValidationException(
                    'Inventory was modified by another user. Please retry.'
                );
            }
            throw $e;
        }
        
        // Create audit trail
        $adjustment = new StockAdjustment();
        $adjustment->setVariantId($variant->getId())
                   ->setPreviousQuantity($previousQuantity)
                   ->setNewQuantity($newQuantity)
                   ->setAdjustment($quantity)
                   ->setReason($reason)
                   ->setNotes($notes)
                   ->setAdjustedBy($adjustedBy)
                   
                   ->setCreatedAt(new DateTime());
        
        if (!$this->adjustmentRepository->save($adjustment)) {
            throw new ValidationException('Failed to save stock adjustment');
        }
        
        // Check for low stock alert
        if ($this->isLowStock($variant)) {
            $this->triggerLowStockAlert($variant);
        }
        
        return $adjustment;
    }
    
    private function validateAdjustmentRules(
        ProductVariant $variant,
        int $quantity,
        string $reason
    ): void {
        $rules = [
            // Damaged items can only reduce stock
            'damaged' => fn($q) => $q > 0
                ? 'Damaged items cannot increase stock'
                : null,

            // Returns can only increase stock
            'returned' => fn($q) => $q < 0
                ? 'Returns cannot decrease stock'
                : null,

            // Initial count must be positive
            'initial_count' => fn($q) => $q < 0
                ? 'Initial count must be positive'
                : null,

            // Sales can only decrease stock
            'sold' => fn($q) => $q > 0
                ? 'Sales cannot increase stock'
                : null,

            // Transfer out reduces stock
            'transfer_out' => fn($q) => $q > 0
                ? 'Transfer out cannot increase stock'
                : null,

            // Transfer in increases stock
            'transfer_in' => fn($q) => $q < 0
                ? 'Transfer in cannot decrease stock'
                : null,

            // Audit adjustments can be positive or negative
            'audit' => fn($q) => null,

            // Restock must be positive
            'restock' => fn($q) => $q < 0
                ? 'Restock must be positive'
                : null,

            // Lost items can only reduce stock
            'lost' => fn($q) => $q > 0
                ? 'Lost items cannot increase stock'
                : null,

            // Found items can only increase stock
            'found' => fn($q) => $q < 0
                ? 'Found items cannot decrease stock'
                : null,
        ];

        if (isset($rules[$reason])) {
            $error = $rules[$reason]($quantity);
            if ($error) {
                throw new ValidationException($error);
            }
        }
    }
    
    public function scanItem(string $barcode): array
    {
        $this->validationService->validateBarcode($barcode);
        
        $variant = $this->productRepository->findVariantByBarcode($barcode);
        if (!$variant) {
            throw new ValidationException("Item not found in inventory: $barcode");
        }
        
        $product = $this->productRepository->findById($variant->getProductId());
        if (!$product) {
            throw new ValidationException("Product not found for variant: $barcode");
        }
        
        return [
            'barcode' => $barcode,
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type' => $product->getType(),
            'color' => $variant->getColor(),
            'quantity' => $variant->getQuantity(),
            'available' => $this->calculateAvailableStock($variant),
            'low_stock' => $this->isLowStock($variant),
            'last_restocked' => $variant->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
    
    public function getLowStockAlerts(?int $threshold = null): array
    {
        $variants = $this->productRepository->findLowStockVariants($threshold);
        
        $alerts = [];
        foreach ($variants as $variant) {
            $product = $this->productRepository->findById($variant->getProductId());
            if (!$product) {
                continue;
            }
            
            $alerts[] = [
                'product_id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'barcode' => $variant->getBarcode(),
                'current_stock' => $variant->getQuantity(),
                'safety_stock' => $product->getSafetyStock() ?? 0,
                'available' => $this->calculateAvailableStock($variant),
                'days_of_supply' => $this->calculateDaysOfSupply($variant),
                'urgency' => $this->calculateUrgency($variant),
            ];
        }
        
        usort($alerts, fn($a, $b) => $this->compareUrgency($a['urgency'], $b['urgency']));
        
        return $alerts;
    }
    
    private function compareUrgency(string $urgencyA, string $urgencyB): int
    {
        $urgencyOrder = [
            'CRITICAL' => 0,
            'HIGH' => 1,
            'MEDIUM' => 2,
            'LOW' => 3,
            'NORMAL' => 4,
        ];
        
        return ($urgencyOrder[$urgencyA] ?? 5) <=> ($urgencyOrder[$urgencyB] ?? 5);
    }
    
    public function bulkAdjustStock(array $adjustments, string $adjustedBy = 'system'): array
    {
        $results = [];
        
        foreach ($adjustments as $adjustment) {
            try {
                if (!isset($adjustment['barcode']) || !isset($adjustment['quantity']) || !isset($adjustment['reason'])) {
                    throw new ValidationException('Invalid adjustment data: missing required fields');
                }
                
                $reason = $adjustment['reason'] instanceof AdjustmentReason 
                    ? $adjustment['reason']
                    : AdjustmentReason::from($adjustment['reason']);
                
                $result = $this->adjustStock(
                    $adjustment['barcode'],
                    $adjustment['quantity'],
                    $reason,
                    $adjustment['notes'] ?? '',
                    $adjustedBy
                );
                
                $results[] = [
                    'success' => true,
                    'barcode' => $adjustment['barcode'],
                    'adjustment_id' => $result->getId(),
                    'new_quantity' => $result->getNewQuantity(),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'barcode' => $adjustment['barcode'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    public function transferStock(
        string $sourceBarcode,
        string $destinationBarcode,
        int $quantity,
        string $notes = '',
        string $transferredBy = 'system'
    ): array {
        if ($quantity <= 0) {
            throw new ValidationException('Transfer quantity must be positive');
        }
        
        // First reduce stock from source
        $sourceAdjustment = $this->adjustStock(
            $sourceBarcode,
            -$quantity,
            'transfer_out',
            "Transfer to $destinationBarcode: $notes",
            $transferredBy
        );

        // Then add to destination
        $destinationAdjustment = $this->adjustStock(
            $destinationBarcode,
            $quantity,
            'transfer_in',
            "Transfer from $sourceBarcode: $notes",
            $transferredBy
        );
        
        return [
            'source_adjustment' => $sourceAdjustment,
            'destination_adjustment' => $destinationAdjustment,
            'transfer_complete' => true,
        ];
    }
    
    public function reserveStock(string $barcode, int $quantity, int $orderId): bool
    {
        if ($quantity <= 0) {
            throw new ValidationException('Reservation quantity must be positive');
        }
        
        $variant = $this->productRepository->findVariantByBarcode($barcode);
        if (!$variant) {
            throw new ValidationException("Product variant not found for barcode: $barcode");
        }
        
        $availableStock = $this->calculateAvailableStock($variant);
        if ($availableStock < $quantity) {
            throw new InsufficientStockException(
                "Cannot reserve $quantity items. Available: $availableStock",
                $barcode,
                $quantity,
                $availableStock
            );
        }
        
        // Update reserved quantity
        $currentReserved = $variant->getReservedQuantity() ?? 0;
        $variant->setReservedQuantity($currentReserved + $quantity);
        $variant->setUpdatedAt(new DateTime());
        
        if (!$this->productRepository->saveVariant($variant)) {
            throw new ValidationException('Failed to save reservation');
        }
        
        // Create reservation record
        if (!$this->adjustmentRepository->createReservation($variant->getId(), $orderId, $quantity)) {
            throw new ValidationException('Failed to create reservation record');
        }
        
        return true;
    }
    
    public function releaseStock(string $barcode, int $orderId): bool
    {
        $variant = $this->productRepository->findVariantByBarcode($barcode);
        if (!$variant) {
            throw new ValidationException("Product variant not found for barcode: $barcode");
        }
        
        // Get reservation details
        $reservation = $this->adjustmentRepository->findReservation($variant->getId(), $orderId);
        if (!$reservation) {
            throw new ValidationException("No reservation found for order: $orderId");
        }
        
        // Release the reserved quantity
        $currentReserved = $variant->getReservedQuantity() ?? 0;
        $reservedQty = $reservation['quantity'] ?? 0;
        $newReserved = max(0, $currentReserved - $reservedQty);
        
        $variant->setReservedQuantity($newReserved);
        $variant->setUpdatedAt(new DateTime());
        
        if (!$this->productRepository->saveVariant($variant)) {
            throw new ValidationException('Failed to update variant after releasing reservation');
        }
        
        // Mark reservation as released
        if (!$this->adjustmentRepository->releaseReservation($variant->getId(), $orderId)) {
            throw new ValidationException('Failed to release reservation');
        }
        
        return true;
    }
    
    public function getInventorySummary(): array
    {
        $totalVariants = $this->productRepository->countAllVariants();
        $totalStockValue = $this->productRepository->calculateTotalStockValue() ?? 0.0;
        $lowStockCount = $this->productRepository->countLowStockVariants();
        $outOfStockCount = $this->productRepository->countOutOfStockVariants();
        
        $recentAdjustments = $this->adjustmentRepository->findRecentAdjustments(10);
        $topSelling = $this->productRepository->findTopSellingProducts(5);
        
        return [
            'total_variants' => $totalVariants,
            'total_stock_value' => round($totalStockValue, 2),
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'stock_turnover_rate' => $this->calculateTurnoverRate(),
            'recent_adjustments' => $recentAdjustments,
            'top_selling' => $topSelling,
        ];
    }
    
    public function getStockHistory(string $barcode, int $days = 30): array
    {
        $variant = $this->productRepository->findVariantByBarcode($barcode);
        if (!$variant) {
            throw new ValidationException("Product variant not found for barcode: $barcode");
        }
        
        $adjustments = $this->adjustmentRepository->findAdjustmentsByVariantId(
            $variant->getId(),
            $days
        );
        
        $history = [];
        
        foreach ($adjustments as $adjustment) {
            $history[] = [
                'date' => $adjustment->getCreatedAt()->format('Y-m-d H:i:s'),
                'type' => $adjustment->getReason(),
                'adjustment' => $adjustment->getAdjustment(),
                'previous_quantity' => $adjustment->getPreviousQuantity(),
                'new_quantity' => $adjustment->getNewQuantity(),
                'notes' => $adjustment->getNotes(),
                'adjusted_by' => $adjustment->getAdjustedBy(),
            ];
        }
        
        usort($history, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
        
        return [
            'current_stock' => $variant->getQuantity(),
            'available_stock' => $this->calculateAvailableStock($variant),
            'reserved_stock' => $variant->getReservedQuantity() ?? 0,
            'history' => $history,
        ];
    }
    
    private function isLowStock(ProductVariant $variant): bool
    {
        $product = $this->productRepository->findById($variant->getProductId());
        if (!$product) {
            return false;
        }
        
        $availableStock = $this->calculateAvailableStock($variant);
        $safetyStock = $product->getSafetyStock() ?? 0;
        
        return $availableStock <= $safetyStock;
    }
    
    private function calculateAvailableStock(ProductVariant $variant): int
    {
        $quantity = $variant->getQuantity() ?? 0;
        $reserved = $variant->getReservedQuantity() ?? 0;
        return max(0, $quantity - $reserved);
    }
    
    private function triggerLowStockAlert(ProductVariant $variant): void
    {
        $product = $this->productRepository->findById($variant->getProductId());
        if (!$product) {
            return;
        }
        
        error_log(sprintf(
            'LOW STOCK ALERT: %s (Barcode: %s) - Stock: %d, Available: %d, Safety: %d',
            $product->getName(),
            $variant->getBarcode(),
            $variant->getQuantity(),
            $this->calculateAvailableStock($variant),
            $product->getSafetyStock() ?? 0
        ));
    }
    
    private function calculateDaysOfSupply(ProductVariant $variant): int
    {
        $product = $this->productRepository->findById($variant->getProductId());
        if (!$product) {
            return 999;
        }
        
        $dailyAverage = $this->productRepository->getAverageDailySales($product->getId());
        
        if ($dailyAverage <= 0) {
            return 999;
        }
        
        $availableStock = $this->calculateAvailableStock($variant);
        $days = floor($availableStock / $dailyAverage);
        return max(0, (int)$days);
    }
    
    private function calculateUrgency(ProductVariant $variant): string
    {
        $product = $this->productRepository->findById($variant->getProductId());
        if (!$product) {
            return 'NORMAL';
        }
        
        $availableStock = $this->calculateAvailableStock($variant);
        $safetyStock = $product->getSafetyStock() ?? 0;
        
        if ($safetyStock <= 0) {
            return 'NORMAL';
        }
        
        $ratio = $availableStock / $safetyStock;
        
        return match(true) {
            $ratio <= 0.2 => 'CRITICAL',
            $ratio <= 0.5 => 'HIGH',
            $ratio <= 0.8 => 'MEDIUM',
            $ratio <= 1.0 => 'LOW',
            default => 'NORMAL',
        };
    }
    
    private function calculateTurnoverRate(): float
    {
        $totalSales = $this->productRepository->getTotalSalesLastMonth() ?? 0.0;
        $averageStock = $this->productRepository->getAverageStockValue() ?? 0.0;
        
        if ($averageStock <= 0) {
            return 0.0;
        }
        
        return round($totalSales / $averageStock, 2);
    }
    
    public function reconcileInventory(string $barcode, int $physicalCount, string $notes = ''): array
    {
        $variant = $this->productRepository->findVariantByBarcode($barcode);
        if (!$variant) {
            throw new ValidationException("Product variant not found for barcode: $barcode");
        }
        
        $systemCount = $variant->getQuantity();
        $difference = $physicalCount - $systemCount;
        
        if ($difference !== 0) {
            $adjustment = $this->adjustStock(
                $barcode,
                $difference,
                'audit',
                "Reconciliation: System=$systemCount, Physical=$physicalCount. $notes",
                'system'
            );
            
            return [
                'reconciled' => true,
                'difference' => $difference,
                'adjustment_id' => $adjustment->getId(),
                'new_quantity' => $adjustment->getNewQuantity(),
            ];
        }
        
        return [
            'reconciled' => true,
            'difference' => 0,
            'adjustment_id' => null,
            'new_quantity' => $systemCount,
        ];
    }
    
    public function setSafetyStock(int $productId, int $safetyStock): bool
    {
        $product = $this->productRepository->findById($productId);
        if (!$product) {
            throw new ValidationException("Product not found: $productId");
        }
        
        if ($safetyStock < 0) {
            throw new ValidationException("Safety stock cannot be negative");
        }
        
        $product->setSafetyStock($safetyStock);
        
        if (!$this->productRepository->save($product)) {
            throw new ValidationException('Failed to update safety stock');
        }
        
        // Check if we need to trigger low stock alerts
        $variants = $this->productRepository->findVariantsByProductId($productId);
        foreach ($variants as $variant) {
            if ($this->isLowStock($variant)) {
                $this->triggerLowStockAlert($variant);
            }
        }
        
        return true;
    }
    
    public function getVariantByBarcode(string $barcode): ?ProductVariant
    {
        return $this->productRepository->findVariantByBarcode($barcode);
    }
    
    public function getProductVariants(int $productId): array
    {
        return $this->productRepository->findVariantsByProductId($productId);
    }
    
    public function itemExists(string $barcode): bool
    {
        return $this->productRepository->findVariantByBarcode($barcode) !== null;
    }
    
    public function getTotalInventoryValue(): float
    {
        return $this->productRepository->calculateTotalStockValue() ?? 0.0;
    }
    
    public function getInventoryValueByCategory(): array
    {
        return $this->productRepository->getStockValueByCategory();
    }
}   