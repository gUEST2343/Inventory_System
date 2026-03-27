<?php

require_once __DIR__ . '/ajax/bootstrap.php';

$action = $_POST['action'] ?? 'save';

switch ($action) {
    case 'save':
        $result = adminSaveCustomer($pdo, $_POST);
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    case 'view':
        $customer = adminGetCustomer($pdo, (int)($_POST['id'] ?? 0));
        if (!$customer) {
            jsonError('Customer not found.', 404);
        }
        jsonSuccess('Customer loaded.', ['customer' => $customer]);
        break;

    case 'restore':
        $result = adminRestoreCustomer($pdo, (int)($_POST['id'] ?? 0));
        jsonResponse($result, $result['success'] ? 200 : 422);
        break;

    default:
        jsonError('Unsupported customer action.', 400);
}
