<?php
/**
 * Get All Products API Endpoint
 * Returns all products as JSON
 */

header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../db_connect.php';

// Check if database is connected
if (!isDBConnected()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => getDBError()
    ]);
    exit;
}

try {
    // Prepare and execute query
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
    $products = $stmt->fetchAll();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products)
    ]);
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch products',
        'error' => $e->getMessage()
    ]);
}
