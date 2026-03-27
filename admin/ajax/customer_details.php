<?php

require_once __DIR__ . '/bootstrap.php';

$customer = adminGetCustomer($pdo, (int)($_POST['id'] ?? 0));
if (!$customer) {
    jsonError('Customer not found.', 404);
}

jsonSuccess('Customer loaded.', ['customer' => $customer]);
