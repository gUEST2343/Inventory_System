<?php
/**
 * M-Pesa STK Push API Endpoint
 * Initiates STK Push payment request
 */

// Set headers
header('Content-Type: application/json');

// Start session
session_start();

// Include required files
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../classes/Mpesa.php';
require_once __DIR__ . '/../../modules/payment_module.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

$phone = $input['phone'] ?? '';
$amount = $input['amount'] ?? 0;
$accountReference = $input['account_reference'] ?? '';
$transactionDesc = $input['description'] ?? '';

// Validate input
$errors = [];

if (empty($phone)) {
    $errors[] = 'Phone number is required';
}

if (empty($amount) || $amount <= 0) {
    $errors[] = 'Valid amount is required';
}

if (empty($accountReference)) {
    $errors[] = 'Account reference is required';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $errors
    ]);
    exit;
}

// Initialize M-Pesa
$mpesa = new Mpesa();
$paymentModule = new PaymentModule($pdo);

// Validate phone number
if (!$mpesa->validatePhoneNumber($phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid phone number format. Use format: 07XXXXXXXX or 254XXXXXXXXX'
    ]);
    exit;
}

// Initiate STK Push
$result = $mpesa->stkPush($phone, $amount, $accountReference, $transactionDesc);

if ($result['success']) {
    $paymentModule->registerGatewayRequest($accountReference, 'mpesa', [
        'status' => 'pending',
        'transaction_id' => $result['checkout_request_id'] ?? null,
        'checkout_request_id' => $result['checkout_request_id'] ?? null,
        'reference_number' => $accountReference,
        'amount' => $amount,
        'gateway_response' => [
            'phone' => $phone,
            'description' => $transactionDesc,
            'mpesa_response' => $result,
        ],
    ]);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO payments (order_id, phone, amount, payment_method, status, transaction_id, checkout_request_id, created_at)
            VALUES (?, ?, ?, 'mpesa', 'pending', ?, ?, NOW())
        ");
        $stmt->execute([
            $accountReference,
            $phone,
            $amount,
            $result['checkout_request_id'] ?? null,
            $result['checkout_request_id'] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log payment: " . $e->getMessage());
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment request sent. Please check your phone and enter PIN.',
        'checkout_request_id' => $result['checkout_request_id']
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}
