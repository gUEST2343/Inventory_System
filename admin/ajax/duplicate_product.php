<?php

require_once __DIR__ . '/bootstrap.php';

$result = adminDuplicateProduct($pdo, (int)($_POST['id'] ?? 0));
jsonResponse($result, $result['success'] ? 200 : 422);
