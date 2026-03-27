<?php

require_once __DIR__ . '/bootstrap.php';

$result = adminCancelOrder($pdo, (int)($_POST['order_id'] ?? 0), trim((string)($_POST['reason'] ?? 'No reason provided')));
jsonResponse($result, $result['success'] ? 200 : 422);
