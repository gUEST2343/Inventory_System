<?php

namespace App\Repositories;

use App\Models\StockAdjustment;
use App\Enums\AdjustmentReason;
use PDO;

class StockAdjustmentRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function save(StockAdjustment $adjustment): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_adjustments
            (variant_id, barcode, previous_quantity, new_quantity, adjustment, reason, notes, adjusted_by, ip_address, user_agent, metadata, created_at)
            VALUES (:variant_id, :barcode, :previous_quantity, :new_quantity, :adjustment, :reason, :notes, :adjusted_by, :ip_address, :user_agent, :metadata, :created_at)
        ");

        return $stmt->execute([
            'variant_id' => $adjustment->getProductVariantId(),
            'barcode' => $adjustment->getBarcode(),
            'previous_quantity' => $adjustment->getPreviousQuantity(),
            'new_quantity' => $adjustment->getNewQuantity(),
            'adjustment' => $adjustment->getAdjustment(),
            'reason' => $adjustment->getReason(),
            'notes' => $adjustment->getNotes(),
            'adjusted_by' => $adjustment->getAdjustedBy(),
            'ip_address' => $adjustment->getIpAddress(),
            'user_agent' => $adjustment->getUserAgent(),
            'metadata' => json_encode($adjustment->getMetadata()),
            'created_at' => $adjustment->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }
    
    public function findRecentAdjustments(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                v.barcode,
                p.name as product_name,
                p.sku
            FROM stock_adjustments a
            INNER JOIN product_variants v ON a.variant_id = v.id
            INNER JOIN products p ON v.product_id = p.id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findAdjustmentsByVariantId(int $variantId, int $days = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stock_adjustments
            WHERE variant_id = :variant_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC
        ");
        $stmt->execute([
            'variant_id' => $variantId,
            'days' => $days
        ]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $adjustments = [];
        foreach ($data as $row) {
            $adjustments[] = $this->hydrateAdjustment($row);
        }

        return $adjustments;
    }

    public function findByBarcode(string $barcode, ?\DateTime $start = null, ?\DateTime $end = null, ?string $reason = null): array
    {
        $query = "SELECT * FROM stock_adjustments WHERE barcode = :barcode";
        $params = ['barcode' => $barcode];

        if ($start) {
            $query .= " AND created_at >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
        }
        if ($end) {
            $query .= " AND created_at <= :end";
            $params['end'] = $end->format('Y-m-d H:i:s');
        }
        if ($reason) {
            $query .= " AND reason = :reason";
            $params['reason'] = $reason;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $adjustments = [];
        foreach ($data as $row) {
            $adjustments[] = $this->hydrateAdjustment($row);
        }

        return $adjustments;
    }

    public function findBetweenDates(\DateTime $start, \DateTime $end): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, v.barcode, p.name as product_name, p.sku
            FROM stock_adjustments a
            LEFT JOIN product_variants v ON a.variant_id = v.id
            LEFT JOIN products p ON v.product_id = p.id
            WHERE a.created_at BETWEEN :start AND :end
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSuspiciousActivity(\DateTime $start, \DateTime $end): array
    {
        $stmt = $this->pdo->prepare("
            SELECT adjusted_by, barcode, COUNT(*) as count, SUM(ABS(adjustment)) as qty
            FROM stock_adjustments
            WHERE created_at BETWEEN :start AND :end
            GROUP BY adjusted_by, barcode
        ");
        $stmt->execute([
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function createReservation(int $variantId, int $orderId, int $quantity): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_reservations 
            (variant_id, order_id, quantity, created_at)
            VALUES (:variant_id, :order_id, :quantity, NOW())
        ");
        
        return $stmt->execute([
            'variant_id' => $variantId,
            'order_id' => $orderId,
            'quantity' => $quantity
        ]);
    }
    
    public function findReservation(int $variantId, int $orderId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stock_reservations 
            WHERE variant_id = :variant_id AND order_id = :order_id
        ");
        $stmt->execute([
            'variant_id' => $variantId,
            'order_id' => $orderId
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function releaseReservation(int $variantId, int $orderId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE stock_reservations 
            SET released_at = NOW() 
            WHERE variant_id = :variant_id AND order_id = :order_id
        ");
        
        return $stmt->execute([
            'variant_id' => $variantId,
            'order_id' => $orderId
        ]);
    }
    
    private function hydrateAdjustment(array $data): StockAdjustment
    {
        $adjustment = new StockAdjustment();
        $adjustment->setId($data['id'] ?? null)
                  ->setProductVariantId($data['variant_id'] ?? 0)
                  ->setBarcode($data['barcode'] ?? '')
                  ->setPreviousQuantity($data['previous_quantity'] ?? 0)
                  ->setNewQuantity($data['new_quantity'] ?? 0)
                  ->setAdjustment($data['adjustment'] ?? 0)
                  ->setReason($data['reason'] ?? 'audit')
                  ->setNotes($data['notes'] ?? null)
                  ->setAdjustedBy($data['adjusted_by'] ?? 'system')
                  ->setIpAddress($data['ip_address'] ?? null)
                  ->setUserAgent($data['user_agent'] ?? null)
                  ->setMetadata(json_decode($data['metadata'] ?? '{}', true))
                  ->setCreatedAt(new \DateTime($data['created_at']));

        return $adjustment;
    }
}
