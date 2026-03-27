<?php

require_once __DIR__ . '/bootstrap.php';

$product = adminGetProduct($pdo, (int)($_POST['id'] ?? 0));
if (!$product) {
    jsonError('Product not found.', 404);
}

jsonSuccess('Product loaded.', ['product' => $product]);
