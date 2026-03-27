<?php
/**
 * Delete Product API Endpoint
 * Deletes a product from the database
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

// Get JSON input from request body
$input = json_decode(file_get_contents('php://input'), true);

// Check if request method is DELETE or POST
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Use DELETE or POST.'
    ]);
    exit;
}

// Validate required field: id
if (!isset($input['id']) || $input['id'] === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]);
    exit;
}

// Sanitize input
$id = intval($input['id']);

// Validate ID
if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product ID'
    ]);
    exit;
}

try {
    // Prepare SQL statement with prepared statement for security
    $sql = "DELETE FROM products WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    // Execute with bound parameter
    $stmt->execute([':id' => $id]);
    
    // Check if any row was affected
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Product deleted successfully',
        'product_id' => $id
    ]);
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete product',
        'error' => $e->getMessage()
    ]);
}
