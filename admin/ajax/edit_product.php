<?php

require_once __DIR__ . '/bootstrap.php';

$result = adminSaveProduct($pdo, $_POST);
jsonResponse($result, $result['success'] ? 200 : 422);
