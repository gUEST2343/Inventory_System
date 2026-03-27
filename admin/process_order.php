<?php

require_once __DIR__ . '/ajax/bootstrap.php';

$action = $_POST['action'] ?? 'update';

switch ($action) {
    case 'update':
        $result = adminUpdateOrder($pdo, $_POST);
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    case 'cancel':
        $result = adminCancelOrder($pdo, (int)($_POST['order_id'] ?? 0), trim((string)($_POST['reason'] ?? 'No reason provided')));
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    case 'details':
        $order = adminGetOrder($pdo, (int)($_POST['order_id'] ?? 0));
        if (!$order) {
            jsonError('Order not found.', 404);
        }
        jsonSuccess('Order loaded.', ['order' => $order]);
        break;

    default:
        jsonError('Unsupported order action.', 400);
}
