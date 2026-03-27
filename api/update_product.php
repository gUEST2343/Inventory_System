<?php
/**
 * Update Product API Endpoint
 * Updates an existing product in the database
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

// Check if request method is PUT or POST
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Use PUT or POST.'
    ]);
    exit;
}

// Validate required fields (id is required for update)
$required_fields = ['id', 'name', 'quantity', 'price'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields',
        'missing_fields' => $missing_fields
    ]);
    exit;
}

// Sanitize and validate input
$id = intval($input['id']);
$name = trim($input['name']);
$category = isset($input['category']) ? trim($input['category']) : '';
$quantity = intval($input['quantity']);
$price = floatval($input['price']);

// Additional validation
if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product ID'
    ]);
    exit;
}

if ($quantity < 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Quantity cannot be negative'
    ]);
    exit;
}

if ($price < 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Price cannot be negative'
    ]);
    exit;
}

if (empty($name)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Name cannot be empty'
    ]);
    exit;
}

try {
    // Prepare SQL statement with prepared statement for security
    $sql = "UPDATE products SET name = :name, category = :category, quantity = :quantity, price = :price, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    // Execute with bound parameters
    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':category' => $category,
        ':quantity' => $quantity,
        ':price' => $price
    ]);
    
    // Check if any row was affected
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Product not found or no changes made'
        ]);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully',
        'product_id' => $id
    ]);
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update product',
        'error' => $e->getMessage()
    ]);
}
