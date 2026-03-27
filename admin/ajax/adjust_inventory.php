<?php

require_once __DIR__ . '/bootstrap.php';

$result = adminAdjustInventory(
    $pdo,
    (int)($_POST['id'] ?? 0),
    (int)($_POST['quantity_delta'] ?? 0),
    trim((string)($_POST['reason'] ?? ''))
);

jsonResponse($result, $result['success'] ? 200 : 422);
