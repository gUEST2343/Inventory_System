<?php

require_once __DIR__ . '/bootstrap.php';

$order = adminGetOrder($pdo, (int)($_POST['order_id'] ?? 0));
if (!$order) {
    jsonError('Order not found.', 404);
}

jsonSuccess('Order loaded.', ['order' => $order]);
