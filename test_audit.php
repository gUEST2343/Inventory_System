<?php

require_once 'config/app.php';
require_once 'src/Models/Traits/SizeConversion.php';
require_once 'src/Models/AbstractProduct.php';
require_once 'src/Models/ProductVariant.php';
require_once 'src/Models/StockAdjustment.php';
require_once 'src/Models/Shoe.php';
require_once 'Enums/AdjustmentReason.php';
require_once 'Enums/Color.php';
require_once 'Enums/ShoeType.php';
require_once 'Enums/SizeCategory.php';
require_once 'Repositories/StockAdjustmentRepository.php';
require_once 'Services/AuditService.php';

// Mock PDO for testing
class MockPDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new MockStmt();
    }
}

class MockStmt extends PDOStatement {
    #[\ReturnTypeWillChange]
    public function execute(?array $params = null): bool { 
        return true; 
    }
    
    #[\ReturnTypeWillChange]
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool { 
        return true; 
    }
    
    #[\ReturnTypeWillChange]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { 
        return []; 
    }
    
    #[\ReturnTypeWillChange]
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { 
        return null; 
    }
}

class MockVariant extends App\Models\ProductVariant {
    private int $mockId;
    private string $mockBarcode;

    public function __construct(int $id, string $barcode)
    {
        $this->mockId = $id;
        $this->mockBarcode = $barcode;
    }

    public function getId(): int
    {
        return $this->mockId;
    }

    public function getBarcode(): string
    {
        return $this->mockBarcode;
    }
}

// Test basic instantiation
try {
    $pdo = new MockPDO();
    $repo = new App\Repositories\StockAdjustmentRepository($pdo);
    $auditService = new App\Services\AuditService($repo);

    echo "AuditService instantiated successfully.\n";

    // Test logStockAdjustment
    $variant = new MockVariant(1, '123456789012');
    $adjustment = $auditService->logStockAdjustment($variant, 50, 60, 10, 'received', 'Test adjustment');

    echo "logStockAdjustment executed successfully.\n";

    // Test getAuditTrail
    $trail = $auditService->getAuditTrail('123456789012');
    echo "getAuditTrail executed successfully. Returned " . count($trail) . " items.\n";

    // Test generateAuditReport
    $start = new DateTime('2023-01-01');
    $end = new DateTime('2023-12-31');
    $report = $auditService->generateAuditReport($start, $end);
    echo "generateAuditReport executed successfully.\n";

    // Test detectSuspiciousActivity
    $alerts = $auditService->detectSuspiciousActivity(24);
    echo "detectSuspiciousActivity executed successfully. Found " . count($alerts) . " alerts.\n";

    echo "All basic tests passed.\n";

    // Edge cases
    try {
        // Invalid reason
        $auditService->logStockAdjustment($variant, 50, 60, 10, 'invalid_reason');
        echo "ERROR: Should have thrown exception for invalid reason.\n";
    } catch (InvalidArgumentException $e) {
        echo "Correctly threw exception for invalid reason: " . $e->getMessage() . "\n";
    }

    // Test with filters
    $reportWithFilters = $auditService->generateAuditReport($start, $end, ['reason' => 'received']);
    echo "generateAuditReport with filters executed successfully.\n";

    echo "Edge case tests passed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
