<?php
/**
 * Add Product API Endpoint
 * Inserts a new product into the database
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

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Use POST.'
    ]);
    exit;
}

// Validate required fields
$required_fields = ['name', 'quantity', 'price'];
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
$name = trim($input['name']);
$category = isset($input['category']) ? trim($input['category']) : '';
$quantity = intval($input['quantity']);
$price = floatval($input['price']);

// Additional validation
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
    $sql = "INSERT INTO products (name, category, quantity, price) VALUES (:name, :category, :quantity, :price)";
    $stmt = $pdo->prepare($sql);
    
    // Execute with bound parameters
    $stmt->execute([
        ':name' => $name,
        ':category' => $category,
        ':quantity' => $quantity,
        ':price' => $price
    ]);
    
    // Get the inserted ID
    $inserted_id = $pdo->lastInsertId();
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Product added successfully',
        'product_id' => $inserted_id
    ]);
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add product',
        'error' => $e->getMessage()
    ]);
}
