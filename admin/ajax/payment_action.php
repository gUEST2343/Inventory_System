<?php

require_once __DIR__ . '/bootstrap.php';

$action = $_POST['action_type'] ?? 'details';
$paymentId = (int)($_POST['payment_id'] ?? 0);

switch ($action) {
    case 'details':
        $payment = adminGetPayment($pdo, $paymentId);
        if (!$payment) {
            jsonError('Payment not found.', 404);
        }
        jsonSuccess('Payment loaded.', ['payment' => $payment]);
        break;

    case 'refund':
        $result = adminRefundPayment($pdo, $paymentId, trim((string)($_POST['reason'] ?? 'Refund requested by admin')));
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    case 'capture':
        $result = adminCapturePayment($pdo, $paymentId);
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    default:
        jsonError('Unsupported payment action.', 400);
}
