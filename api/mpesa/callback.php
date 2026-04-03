<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/mpesa_db_helper.php';

$rawPayload = file_get_contents('php://input') ?: '';

if (!is_dir(__DIR__ . '/../../logs')) {
    @mkdir(__DIR__ . '/../../logs', 0755, true);
}
@file_put_contents(__DIR__ . '/../../logs/mpesa_callback.log', '[' . date('c') . '] ' . $rawPayload . PHP_EOL, FILE_APPEND);

$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback payload']);
    exit;
}

$callback = $payload['Body']['stkCallback'] ?? null;
if (!is_array($callback)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing stkCallback payload']);
    exit;
}

if (!$pdo instanceof PDO || !mpesaTransactionsTableExists($pdo)) {
    http_response_code(503);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Database or mpesa_transactions table unavailable']);
    exit;
}

$checkoutRequestId = (string) ($callback['CheckoutRequestID'] ?? '');
$merchantRequestId = (string) ($callback['MerchantRequestID'] ?? '');
$resultCode = (int) ($callback['ResultCode'] ?? 1);
$resultDesc = substr((string) ($callback['ResultDesc'] ?? 'Callback received'), 0, 255);
$status = $resultCode === 0 ? 'completed' : 'failed';
$metadata = mpesaCallbackMetadataToMap($callback['CallbackMetadata']['Item'] ?? []);
$receiptNumber = isset($metadata['MpesaReceiptNumber']) ? (string) $metadata['MpesaReceiptNumber'] : null;
$phoneNumber = isset($metadata['PhoneNumber']) ? (string) $metadata['PhoneNumber'] : null;
$amount = isset($metadata['Amount']) ? (float) $metadata['Amount'] : null;
$transactionDate = mpesaParseTransactionDate($metadata['TransactionDate'] ?? null);

try {
    $pdo->beginTransaction();

    $selectStmt = $pdo->prepare("
        SELECT id, order_id
        FROM mpesa_transactions
        WHERE checkout_request_id = ?
           OR merchant_request_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $selectStmt->execute([$checkoutRequestId, $merchantRequestId]);
    $transaction = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        $pdo->commit();
        @file_put_contents(__DIR__ . '/../../logs/mpesa_callback_unmatched.log', '[' . date('c') . '] ' . $rawPayload . PHP_EOL, FILE_APPEND);
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit;
    }

    $orderId = (int) $transaction['order_id'];

    $updateTxnStmt = $pdo->prepare("
        UPDATE mpesa_transactions
        SET
            phone_number = COALESCE(?, phone_number),
            amount = COALESCE(?, amount),
            result_code = ?,
            result_desc = ?,
            mpesa_receipt_number = COALESCE(?, mpesa_receipt_number),
            transaction_date = COALESCE(?, transaction_date),
            status = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $updateTxnStmt->execute([
        $phoneNumber,
        $amount,
        $resultCode,
        $resultDesc,
        $receiptNumber,
        $transactionDate,
        $status,
        $transaction['id'],
    ]);

    $orderUpdates = [];
    $orderParams = [];

    if (mpesaColumnExists($pdo, 'orders', 'payment_method')) {
        $orderUpdates[] = 'payment_method = ?';
        $orderParams[] = 'mpesa';
    }

    if (mpesaColumnExists($pdo, 'orders', 'payment_status')) {
        $orderUpdates[] = 'payment_status = ?';
        $orderParams[] = $resultCode === 0 ? 'paid' : 'failed';
    }

    if ($resultCode === 0 && $receiptNumber !== null && mpesaColumnExists($pdo, 'orders', 'mpesa_receipt_number')) {
        $orderUpdates[] = 'mpesa_receipt_number = ?';
        $orderParams[] = $receiptNumber;
    }

    if (mpesaColumnExists($pdo, 'orders', 'status')) {
        $orderUpdates[] = 'status = ?';
        $orderParams[] = $resultCode === 0 ? 'processing' : 'pending';
    }

    if (mpesaColumnExists($pdo, 'orders', 'updated_at')) {
        $orderUpdates[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    if (!empty($orderUpdates)) {
        $orderParams[] = $orderId;
        $orderStmt = $pdo->prepare('UPDATE orders SET ' . implode(', ', $orderUpdates) . ' WHERE id = ?');
        $orderStmt->execute($orderParams);
    }

    $pdo->commit();

    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @file_put_contents(__DIR__ . '/../../logs/mpesa_callback_errors.log', '[' . date('c') . '] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Callback processing failed']);
}
