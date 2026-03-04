<?php

namespace App\Controllers\Api;

use App\Services\InventoryService;
use App\Enums\AdjustmentReason;
use App\Exceptions\ValidationException;
use App\Exceptions\InsufficientStockException;
use App\DTOs\StockAdjustmentDTO;
use App\DTOs\ErrorResponse;

class InventoryController
{
    private InventoryService $inventoryService;
    
    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }
    
    /**
     * POST /api/inventory/scan
     */
    public function scanItem(array $request): array
    {
        try {
            $barcode = $request['barcode'] ?? '';
            
            if (empty($barcode)) {
                throw new ValidationException('Barcode is required');
            }
            
            $result = $this->inventoryService->scanItem($barcode);
            
            return [
                'success' => true,
                'data' => $result,
                'timestamp' => date('c'),
            ];
            
        } catch (ValidationException $e) {
            return ErrorResponse::create('VALIDATION_ERROR', $e->getMessage(), 400)->toArray();
        } catch (\Exception $e) {
            return ErrorResponse::create('SERVER_ERROR', 'Internal server error', 500)->toArray();
        }
    }
    
    /**
     * POST /api/inventory/adjust
     */
    public function updateStock(array $request): array
    {
        try {
            // Validate required fields
            $required = ['barcode', 'quantity', 'reason'];
            foreach ($required as $field) {
                if (!isset($request[$field]) || $request[$field] === '') {
                    throw new ValidationException("Field '$field' is required");
                }
            }
            
            // Validate reason
            $reason = AdjustmentReason::tryFrom($request['reason']);
            if (!$reason) {
                $validReasons = AdjustmentReason::cases();
                throw new ValidationException(
                    "Invalid reason. Must be one of: " . implode(', ', $validReasons)
                );
            }
            
            // Validate quantity
            $quantity = (int)$request['quantity'];
            if (!is_numeric($request['quantity']) || $quantity != $request['quantity']) {
                throw new ValidationException('Quantity must be an integer');
            }
            
            $dto = new StockAdjustmentDTO(
                $request['barcode'],
                $quantity,
                $reason,
                $request['notes'] ?? '',
                $request['adjusted_by'] ?? 'api_user'
            );
            
            $adjustment = $this->inventoryService->adjustStock(
                $dto->getBarcode(),
                $dto->getQuantity(),
                $dto->getReason(),
                $dto->getNotes(),
                $dto->getAdjustedBy()
            );
            
            // Get reason as string for response
            $reasonValue = $adjustment->getReason();
            $reasonString = is_object($reasonValue) ? get_class($reasonValue) : (string)$reasonValue;
            
            return [
                'success' => true,
                'data' => [
                    'adjustment_id' => $adjustment->getId(),
                    'barcode' => $dto->getBarcode(),
                    'previous_quantity' => $adjustment->getPreviousQuantity(),
                    'new_quantity' => $adjustment->getNewQuantity(),
                    'reason' => $reasonString,
                    'adjusted_at' => $adjustment->getCreatedAt()->format('c'),
                    'audit_trail_id' => $adjustment->getId(),
                ],
                'message' => 'Stock successfully updated',
                'timestamp' => date('c'),
            ];
            
        } catch (ValidationException $e) {
            return ErrorResponse::create('VALIDATION_ERROR', $e->getMessage(), 400)->toArray();
        } catch (InsufficientStockException $e) {
            return ErrorResponse::create('INSUFFICIENT_STOCK', $e->getMessage(), 409)->toArray();
        } catch (\Exception $e) {
            error_log("Stock adjustment error: " . $e->getMessage());
            return ErrorResponse::create('SERVER_ERROR', 'Failed to update stock', 500)->toArray();
        }
    }
    
    /**
     * GET /api/inventory/low-stock-alerts
     */
    public function getLowStockAlerts(array $request): array
    {
        try {
            $threshold = isset($request['threshold']) 
                ? (int)$request['threshold'] 
                : null;
            
            $alerts = $this->inventoryService->getLowStockAlerts($threshold);
            
            return [
                'success' => true,
                'count' => count($alerts),
                'data' => $alerts,
                'timestamp' => date('c'),
            ];
            
        } catch (\Exception $e) {
            return ErrorResponse::create('SERVER_ERROR', 'Failed to retrieve alerts', 500)->toArray();
        }
    }
}
