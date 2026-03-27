<?php

require_once __DIR__ . '/bootstrap.php';

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);

switch ($type) {
    case 'customer':
        $result = adminRestoreCustomer($pdo, $id);
        break;

    case 'product':
        $result = adminRestoreProduct($pdo, $id);
        break;

    default:
        jsonError('Unsupported undo action.', 400);
}

jsonResponse($result, $result['success'] ? 200 : 422);
