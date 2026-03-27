<?php
/**
 * M-Pesa Callback Endpoint
 * Receives payment callback from M-Pesa
 */

// Set headers
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../modules/payment_module.php';

// Get callback data
$callbackData = json_decode(file_get_contents('php://input'), true);

// Log the callback for debugging
$logFile = __DIR__ . '/../../logs/mpesa_callback.log';
$logEntry = date('Y-m-d H:i:s') . ' - ' . json_encode($callbackData) . "\n";
@file_put_contents($logFile, $logEntry, FILE_APPEND);

// Check if we have valid data
if (!isset($callbackData['Body']['stkCallback'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid callback']);
    exit;
}

$stkCallback = $callbackData['Body']['stkCallback'];
$checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? '';
$merchantRequestId = $stkCallback['MerchantRequestID'] ?? '';
$callbackResultCode = $stkCallback['ResultCode'] ?? '';
$callbackResultDesc = $stkCallback['ResultDesc'] ?? '';

// Process the callback
try {
    $paymentModule = new PaymentModule($pdo);

    if ($callbackResultCode == 0) {
        // Payment successful
        $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
        
        $amount = 0;
        $mpesaReceiptNumber = '';
        $phoneNumber = '';
        
        foreach ($items as $item) {
            switch ($item['Name']) {
                case 'Amount':
                    $amount = $item['Value'];
                    break;
                case 'MpesaReceiptNumber':
                    $mpesaReceiptNumber = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $phoneNumber = $item['Value'];
                    break;
            }
        }
        
        $paymentModule->handleWebhook('mpesa', [
            'status' => 'completed',
            'transaction_id' => $mpesaReceiptNumber ?: $checkoutRequestId,
            'checkout_request_id' => $checkoutRequestId,
            'reference_number' => $mpesaReceiptNumber ?: $merchantRequestId,
            'amount' => $amount,
            'phone' => $phoneNumber,
            'result_code' => $callbackResultCode,
            'result_desc' => $callbackResultDesc,
            'callback_items' => $items,
            'raw_payload' => $callbackData,
        ]);

        if ($checkoutRequestId) {
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'completed',
                    transaction_id = ?,
                    receipt_number = ?,
                    phone = ?,
                    amount = ?,
                    completed_at = NOW()
                WHERE checkout_request_id = ?
            ");
            $stmt->execute([
                $mpesaReceiptNumber,
                $mpesaReceiptNumber,
                $phoneNumber,
                $amount,
                $checkoutRequestId
            ]);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment processed successfully'
        ]);
        
    } else {
        // Payment failed or cancelled
        $paymentModule->handleWebhook('mpesa', [
            'status' => 'failed',
            'transaction_id' => $checkoutRequestId,
            'checkout_request_id' => $checkoutRequestId,
            'reference_number' => $merchantRequestId,
            'result_code' => $callbackResultCode,
            'result_desc' => $callbackResultDesc,
            'raw_payload' => $callbackData,
        ]);

        if ($checkoutRequestId) {
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'failed',
                    result_code = ?,
                    result_desc = ?,
                    completed_at = NOW()
                WHERE checkout_request_id = ?
            ");
            $stmt->execute([
                $callbackResultCode,
                $callbackResultDesc,
                $checkoutRequestId
            ]);
        }
        
        echo json_encode([
            'status' => 'failed',
            'message' => $callbackResultDesc
        ]);
    }
    
} catch (PDOException $e) {
    // Log error
    error_log("M-Pesa callback error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error'
    ]);
}
