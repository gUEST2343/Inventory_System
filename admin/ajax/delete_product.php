<?php

require_once __DIR__ . '/bootstrap.php';

$result = adminDeleteProduct($pdo, (int)($_POST['id'] ?? 0));
jsonResponse($result, $result['success'] ? 200 : 422);
