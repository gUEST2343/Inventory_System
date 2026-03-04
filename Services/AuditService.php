<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\ProductVariant;
use App\Enums\AdjustmentReason;
use App\Repositories\StockAdjustmentRepository;
use PDO;

class AuditService
{
    private StockAdjustmentRepository $repository;
    private array $auditLog = [];

    public function __construct(StockAdjustmentRepository $repository)
    {
        $this->repository = $repository;
    }

    public function logStockAdjustment(
        ProductVariant $variant,
        int $previousQuantity,
        int $newQuantity,
        int $adjustment,
        string $reason,
        string $notes = '',
        ?string $adjustedBy = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $metadata = []
    ): StockAdjustment {
        if (!AdjustmentReason::isValid($reason)) {
            throw new \InvalidArgumentException("Invalid adjustment reason: {$reason}");
        }

        $adjustedBy = $adjustedBy ?? 'system';
        $ipAddress = $ipAddress ?? '127.0.0.1';
        $userAgent = $userAgent ?? 'Unknown';

        $record = new StockAdjustment();
        $record->setProductVariantId($variant->getId())
               ->setBarcode($variant->getBarcode())
               ->setPreviousQuantity($previousQuantity)
               ->setNewQuantity($newQuantity)
               ->setAdjustment($adjustment)
               ->setReason($reason)
               ->setNotes($notes)
               ->setAdjustedBy($adjustedBy)
               ->setIpAddress($ipAddress)
               ->setUserAgent($userAgent)
               ->setMetadata(array_merge($metadata, [
                   'logged_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                   'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
               ]))
               ->setCreatedAt(new \DateTime());

        $this->repository->save($record);

        $this->addToAuditLog($record);

        if (count($this->auditLog) >= 100) {
            $this->persistAuditLog();
        }

        return $record;
    }

    public function getAuditTrail(
        string $barcode,
        ?\DateTime $start = null,
        ?\DateTime $end = null,
        ?string $reason = null
    ): array {
        return $this->repository->findByBarcode($barcode, $start, $end, $reason);
    }

    public function generateAuditReport(
        \DateTime $start,
        \DateTime $end,
        array $filters = []
    ): array {
        $items = $this->repository->findBetweenDates($start, $end);

        $report = [
            'summary' => [
                'total_adjustments'      => 0,
                'total_quantity_changed' => 0,
                'total_increases'        => 0,
                'total_decreases'        => 0,
                'by_reason'              => [],
                'by_user'                => [],
            ],
            'items' => [],
        ];

        foreach ($items as $adj) {
            if ($this->shouldFilterOut($adj, $filters)) {
                continue;
            }

            $delta = $adj['adjustment'] ?? 0;

            $report['summary']['total_adjustments']++;
            $report['summary']['total_quantity_changed'] += abs($delta);

            if ($delta > 0) {
                $report['summary']['total_increases']++;
            } else {
                $report['summary']['total_decreases']++;
            }

            $reasonStr = $adj['reason'] ?? '(unknown)';
            $report['summary']['by_reason'][$reasonStr] ??= 0;
            $report['summary']['by_reason'][$reasonStr]++;

            $userStr = $adj['adjusted_by'] ?? '(unknown)';
            $report['summary']['by_user'][$userStr] ??= 0;
            $report['summary']['by_user'][$userStr]++;

            $report['items'][] = [
                'id'                => $adj['id'],
                'when'              => $adj['created_at'] ?? '?',
                'barcode'           => $adj['barcode'] ?? '?',
                'product'           => $adj['product_name'] ?? '(missing)',
                'was'               => $adj['previous_quantity'] ?? 0,
                'now'               => $adj['new_quantity'] ?? 0,
                'changed'           => $delta,
                'reason'            => $reasonStr,
                'user'              => $userStr,
                'note'              => $adj['notes'] ?? '',
            ];
        }

        arsort($report['summary']['by_reason']);
        arsort($report['summary']['by_user']);

        return $report;
    }

    public function detectSuspiciousActivity(int $hours = 24): array
    {
        $end = new \DateTime();
        $start = (new \DateTime())->modify("-{$hours} hours");

        $rows = $this->repository->findSuspiciousActivity($start, $end);

        $users = [];
        $products = [];
        $alerts = [];

        foreach ($rows as $row) {
            $u = $row['adjusted_by'] ?? 'unknown';
            $b = $row['barcode'] ?? 'unknown';
            $count = $row['count'] ?? 0;
            $qty = $row['qty'] ?? 0;

            $users[$u] = ['count' => $count, 'qty' => $qty];
            $products[$b] = ['count' => $count, 'qty' => $qty];
        }

        foreach ($users as $user => $data) {
            if ($data['count'] > 50) {
                $alerts[] = ['type' => 'user-frequency', 'user' => $user, 'count' => $data['count']];
            }
            if ($data['qty'] > 1000) {
                $alerts[] = ['type' => 'user-volume', 'user' => $user, 'qty' => $data['qty']];
            }
        }

        foreach ($products as $barcode => $data) {
            if ($data['count'] > 20) {
                $alerts[] = ['type' => 'product-frequency', 'barcode' => $barcode, 'count' => $data['count']];
            }
        }

        return $alerts;
    }

    private function addToAuditLog(StockAdjustment $item): void
    {
        $this->auditLog[] = $item;
        if (count($this->auditLog) > 1000) {
            array_shift($this->auditLog);
        }
    }

    private function persistAuditLog(): void
    {
        $this->auditLog = []; // replace with real logging later
    }

    private function shouldFilterOut(array $item, array $filters): bool
    {
        foreach ($filters as $k => $v) {
            if ($k === 'reason' && ($item['reason'] ?? null) !== $v) return true;
            if ($k === 'user'   && ($item['adjusted_by'] ?? null) !== $v) return true;
            if ($k === 'min_qty' && abs($item['adjustment'] ?? 0) < $v) return true;
        }
        return false;
    }
}