<?php

namespace App\Controllers\Api;

use App\Services\InventoryService;
use App\Services\AuditService;
use App\Services\ValidationService;
use App\Enums\AdjustmentReason;
use App\Exceptions\ValidationException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\InvalidBarcodeException;

class StockController
{
    private InventoryService $inventoryService;
    private AuditService $auditService;
    private ValidationService $validationService;
    
    public function __construct(
        InventoryService $inventoryService,
        AuditService $auditService,
        ValidationService $validationService
    ) {
        $this->inventoryService = $inventoryService;
        $this->auditService = $auditService;
        $this->validationService = $validationService;
    }
    
    /**
     * POST /api/stock/scan
     * Scan a barcode and get product information
     */
    public function scanItem(array $request): array
    {
        try {
            // Validate request
            if (empty($request['barcode'])) {
                throw new ValidationException('Barcode is required');
            }
            
            $barcode = trim($request['barcode']);
            
            // Validate barcode format
            $this->validationService->validateBarcode($barcode);
            
            // Scan item
            $result = $this->inventoryService->scanItem($barcode);
            
            return [
                'success' => true,
                'data' => $result,
                'timestamp' => date('c'),
                'message' => 'Item scanned successfully',
            ];
            
        } catch (InvalidBarcodeException $e) {
            return $this->errorResponse(
                'INVALID_BARCODE',
                $e->getMessage(),
                400,
                [
                    'barcode' => $request['barcode'] ?? null,
                    'suggestions' => $e->getSuggestions(),
                    'possible_types' => $e->getPossibleTypes(),
                ]
            );
        } catch (ProductNotFoundException $e) {
            return $this->errorResponse(
                'PRODUCT_NOT_FOUND',
                $e->getMessage(),
                404,
                [
                    'identifier' => $request['barcode'] ?? null,
                    'identifier_type' => 'barcode',
                    'suggestions' => $e->getSuggestions(),
                    'alternative_terms' => $e->getAlternativeSearchTerms(),
                ]
            );
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                $e->getMessage(),
                400,
                ['errors' => $e->getErrors()]
            );
        } catch (\Exception $e) {
            error_log("Scan error: " . $e->getMessage());
            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Failed to scan item',
                500
            );
        }
    }
    
    /**
     * POST /api/stock/adjust
     * Adjust stock quantity with audit trail
     */
    public function adjustStock(array $request): array
    {
        try {
            // Validate required fields
            $requiredFields = ['barcode', 'quantity', 'reason'];
            foreach ($requiredFields as $field) {
                if (!isset($request[$field]) || $request[$field] === '') {
                    throw new ValidationException("Field '$field' is required");
                }
            }
            
            $barcode = trim($request['barcode']);
            $quantity = (int)$request['quantity'];
            $reasonValue = trim($request['reason']);
            
            // Validate barcode
            $this->validationService->validateBarcode($barcode);
            
            // Validate reason using AdjustmentReason enum
            if (!AdjustmentReason::isValid($reasonValue)) {
                $validReasons = AdjustmentReason::getAll();
                throw new ValidationException(
                    "Invalid reason. Must be one of: " . implode(', ', $validReasons)
                );
            }
            
            // Get reason string (already validated)
            $reason = AdjustmentReason::from($reasonValue);
            
            // Additional validations
            $notes = trim($request['notes'] ?? '');
            $adjustedBy = trim($request['adjusted_by'] ?? 'api_user');
            
            if (strlen($notes) > 500) {
                throw new ValidationException('Notes cannot exceed 500 characters');
            }
            
            // Perform stock adjustment
            $adjustment = $this->inventoryService->adjustStock(
                $barcode,
                $quantity,
                $reason,
                $notes,
                $adjustedBy
            );
            
            return [
                'success' => true,
                'data' => [
                    'adjustment_id' => $adjustment->getId(),
                    'barcode' => $barcode,
                    'previous_quantity' => $adjustment->getPreviousQuantity(),
                    'new_quantity' => $adjustment->getNewQuantity(),
                    'adjustment' => $adjustment->getAdjustment(),
                    'reason' => $reasonValue,
                    'reason_description' => AdjustmentReason::getDescription($reasonValue),
                    'notes' => $notes,
                    'adjusted_by' => $adjustedBy,
                    'adjusted_at' => $adjustment->getCreatedAt()->format('c'),
                    'audit_trail_id' => $adjustment->getId(),
                ],
                'message' => 'Stock successfully adjusted',
                'timestamp' => date('c'),
            ];
            
        } catch (InsufficientStockException $e) {
            return $this->errorResponse(
                'INSUFFICIENT_STOCK',
                $e->getUserMessage(),
                409,
                [
                    'current_stock' => $e->getCurrentStock(),
                    'requested_quantity' => abs($request['quantity'] ?? 0),
                    'shortage' => $e->getShortage(),
                    'barcode' => $request['barcode'] ?? null,
                    'is_out_of_stock' => $e->isOutOfStock(),
                    'is_partial_stock' => $e->isPartialStock(),
                ]
            );
        } catch (ProductNotFoundException $e) {
            return $this->errorResponse(
                'PRODUCT_NOT_FOUND',
                $e->getMessage(),
                404,
                [
                    'barcode' => $request['barcode'] ?? null,
                    'suggestions' => $e->getSuggestions(),
                ]
            );
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                $e->getMessage(),
                400,
                [
                    'errors' => $e->getErrors(),
                    'field_errors' => $e->getFieldErrors(),
                ]
            );
        } catch (\Exception $e) {
            error_log("Stock adjustment error: " . $e->getMessage());
            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Failed to adjust stock',
                500
            );
        }
    }
    
    /**
     * GET /api/stock/low-stock-alerts
     * Get low stock alerts with optional threshold
     */
    public function getLowStockAlerts(array $request): array
    {
        try {
            // Parse query parameters
            $threshold = isset($request['threshold']) 
                ? (int)$request['threshold'] 
                : null;
            
            $page = isset($request['page']) 
                ? max(1, (int)$request['page']) 
                : 1;
            
            $perPage = isset($request['per_page']) 
                ? min(max(1, (int)$request['per_page']), 100) 
                : 20;
            
            $type = isset($request['type']) 
                ? trim($request['type']) 
                : null;
            
            $urgency = isset($request['urgency']) 
                ? trim($request['urgency']) 
                : null;
            
            // Get low stock alerts
            $alerts = $this->inventoryService->getLowStockAlerts($threshold);
            
            // Apply filters
            $filteredAlerts = array_filter($alerts, function($alert) use ($type, $urgency) {
                if ($type && ($alert['product_type'] ?? null) !== $type) {
                    return false;
                }
                if ($urgency && ($alert['urgency'] ?? null) !== $urgency) {
                    return false;
                }
                return true;
            });
            
            // Paginate results
            $total = count($filteredAlerts);
            $offset = ($page - 1) * $perPage;
            $paginatedAlerts = array_slice($filteredAlerts, $offset, $perPage);
            
            return [
                'success' => true,
                'data' => $paginatedAlerts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $total > 0 ? ceil($total / $perPage) : 0,
                ],
                'summary' => [
                    'total_alerts' => $total,
                    'critical_count' => count(array_filter($filteredAlerts, fn($a) => ($a['urgency'] ?? '') === 'CRITICAL')),
                    'high_count' => count(array_filter($filteredAlerts, fn($a) => ($a['urgency'] ?? '') === 'HIGH')),
                    'medium_count' => count(array_filter($filteredAlerts, fn($a) => ($a['urgency'] ?? '') === 'MEDIUM')),
                ],
                'timestamp' => date('c'),
            ];
            
        } catch (\Exception $e) {
            error_log("Low stock alerts error: " . $e->getMessage());
            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Failed to retrieve low stock alerts',
                500
            );
        }
    }
    
    /**
     * GET /api/stock/audit-trail
     * Get audit trail for a product
     */
    public function getAuditTrail(array $request): array
    {
        try {
            // Validate required field
            if (empty($request['barcode'])) {
                throw new ValidationException('Barcode is required');
            }
            
            $barcode = trim($request['barcode']);
            
            // Validate barcode
            $this->validationService->validateBarcode($barcode);
            
            // Parse optional parameters
            $startDate = isset($request['start_date']) 
                ? new \DateTime($request['start_date']) 
                : null;
            
            $endDate = isset($request['end_date']) 
                ? new \DateTime($request['end_date']) 
                : null;
            
            $reason = isset($request['reason']) 
                ? trim($request['reason']) 
                : null;
            
            $limit = isset($request['limit']) 
                ? min(max(1, (int)$request['limit']), 1000) 
                : 100;
            
            $page = isset($request['page']) 
                ? max(1, (int)$request['page']) 
                : 1;
            
            // Validate reason if provided
            $reasonEnum = null;
            if ($reason && AdjustmentReason::isValid($reason)) {
                $reasonEnum = AdjustmentReason::from($reason);
            }
            
            // Get audit trail
            $adjustments = $this->auditService->getAuditTrail(
                $barcode,
                $startDate,
                $endDate,
                $reasonEnum
            );
            
            // Paginate results
            $total = count($adjustments);
            $offset = ($page - 1) * $limit;
            $paginatedAdjustments = array_slice($adjustments, $offset, $limit);
            
            // Calculate summary
            $summary = [
                'total_adjustments' => $total,
                'total_increases' => 0,
                'total_decreases' => 0,
                'net_change' => 0,
            ];
            
            foreach ($adjustments as $adjustment) {
                $quantity = $adjustment->getAdjustment();
                $summary['net_change'] += $quantity;
                
                if ($quantity > 0) {
                    $summary['total_increases']++;
                } else {
                    $summary['total_decreases']++;
                }
            }
            
            return [
                'success' => true,
                'data' => array_map(function($adjustment) {
                    return [
                        'adjustment_id' => $adjustment->getId(),
                        'previous_quantity' => $adjustment->getPreviousQuantity(),
                        'new_quantity' => $adjustment->getNewQuantity(),
                        'adjustment' => $adjustment->getAdjustment(),
                        'reason' => $adjustment->getReason(),
                        'notes' => $adjustment->getNotes(),
                        'adjusted_by' => $adjustment->getAdjustedBy(),
                        'adjusted_at' => $adjustment->getCreatedAt()->format('c'),
                    ];
                }, $paginatedAdjustments),  
                'pagination' => [
                    'page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => $total > 0 ? ceil($total / $limit) : 0,
                ],
                'summary' => $summary,
                'timestamp' => date('c'),
            ];
               
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                $e->getMessage(),
                400,
                ['errors' => $e->getErrors()]
            );
        } catch (ProductNotFoundException $e) {
            return $this->errorResponse(
                'PRODUCT_NOT_FOUND',
                $e->getMessage(),
                404,
                [
                    'barcode' => $request['barcode'] ?? null,
                    'suggestions' => $e->getSuggestions(),
                ]
            );
        } catch (\Exception $e) {
            error_log("Audit trail error: " . $e->getMessage());
            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Failed to retrieve audit trail',
                500
            );
        }   

    }

    /**
     * Helper method to format error responses
     */ 
    private function errorResponse(
        string $errorCode,
        string $message,
        int $httpStatus = 400,
        array $details = []
    ): array {
        return [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message,
                'details' => $details,
            ],
            'timestamp' => date('c'),
        ];
    }
}     
