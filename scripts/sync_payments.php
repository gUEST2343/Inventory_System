<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/payment_module.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo "Database connection is not available.\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$paymentModule = new PaymentModule($pdo);
$report = $paymentModule->synchronizeExistingPayments([
    'dry_run' => $dryRun,
]);

echo "Payment Synchronization Report\n";
echo "==============================\n";
echo 'Dry run: ' . ($dryRun ? 'yes' : 'no') . "\n";
echo 'Success: ' . (!empty($report['success']) ? 'yes' : 'no') . "\n";
echo 'Checked orders: ' . (int)($report['checked_orders'] ?? 0) . "\n";
echo 'Created transactions: ' . (int)($report['created_transactions'] ?? 0) . "\n";
echo 'Updated transactions: ' . (int)($report['updated_transactions'] ?? 0) . "\n";
echo 'Updated orders: ' . (int)($report['updated_orders'] ?? 0) . "\n";
echo "\nIssues:\n";

if (empty($report['issues'])) {
    echo "- None detected.\n";
} else {
    foreach ($report['issues'] as $issue) {
        echo '- Order #' . (string)($issue['order_id'] ?? 'N/A')
            . ' [' . (string)($issue['action'] ?? 'unknown') . ']';

        if (!empty($issue['message'])) {
            echo ' ' . $issue['message'];
        }

        echo "\n";
    }
}
