<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/payment_module.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo "Database connection is not available.\n";
    exit(1);
}

$paymentModule = new PaymentModule($pdo);
$report = $paymentModule->getIntegrityReport(50);

echo "Payment Integrity Report\n";
echo "========================\n";
echo 'Orphan orders: ' . (int)($report['summary']['orphan_orders'] ?? 0) . "\n";
echo 'Status mismatches: ' . (int)($report['summary']['mismatches'] ?? 0) . "\n";
echo 'Orphan transactions: ' . (int)($report['summary']['orphan_transactions'] ?? 0) . "\n";
echo "\n";

if (!empty($report['orphan_orders'])) {
    echo "Orders without payment transactions:\n";
    foreach ($report['orphan_orders'] as $order) {
        echo '- ' . (string)($order['order_number'] ?? $order['id']) . "\n";
    }
    echo "\n";
}

if (!empty($report['mismatches'])) {
    echo "Order/payment mismatches:\n";
    foreach ($report['mismatches'] as $mismatch) {
        echo '- ' . (string)($mismatch['order_number'] ?? $mismatch['id'])
            . ': order=' . (string)($mismatch['order_payment_status'] ?? 'pending')
            . ', expected=' . (string)($mismatch['expected_payment_status'] ?? 'pending') . "\n";
    }
    echo "\n";
}

if (!empty($report['orphan_transactions'])) {
    echo "Transactions without orders:\n";
    foreach ($report['orphan_transactions'] as $transaction) {
        echo '- ' . (string)($transaction['transaction_id'] ?? $transaction['id']) . "\n";
    }
}
