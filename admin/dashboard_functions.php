<?php
/**
 * Dashboard Functions - Uses existing db_connect.php
 * Provides helper functions for the dashboard system
 */

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Location: index.php');
    exit;
}

// Include the existing database connection (DO NOT redeclare getDBConnection)
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/../db_connect.php';
}

// Get all products with optional filters
function getProducts($pdo, $search = '') {
    if (!$pdo) return [];
    
    $query = "SELECT * FROM products WHERE is_active = true";
    $params = [];
    
    if ($search) {
        $query .= " AND (name ILIKE ? OR sku ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY name LIMIT 50";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching products: " . $e->getMessage());
        return [];
    }
}

// Get product categories
function getCategories($pdo) {
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->query("SELECT DISTINCT category_id FROM products WHERE is_active = true ORDER BY category_id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

// Get cart total
function getCartTotal($pdo, $cartItems) {
    $total = 0;
    foreach ($cartItems as $item) {
        if (isset($item['unit_price'])) {
            $total += $item['unit_price'] * $item['quantity'];
        }
    }
    return $total;
}

// Create order
function createOrder($pdo, $userId, $items, $total) {
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_date, status, total_amount) VALUES (?, NOW(), 'pending', ?)");
        $stmt->execute([$userId, $total]);
        $orderId = $pdo->lastInsertId();
        
        foreach ($items as $item) {
            $stmt = $pdo->prepare("SELECT unit_price FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch();
            
            if ($product) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $product['unit_price'], $product['unit_price'] * $item['quantity']]);
                
                // Update stock
                $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
        }
        
        $pdo->commit();
        return $orderId;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Order creation error: " . $e->getMessage());
        return false;
    }
}

// Get user orders
function getUserOrders($pdo, $userId) {
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 10");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Admin: Get all orders
function getAllOrders($pdo, $status = '', $search = '') {
    if (!$pdo) return [];
    
    $query = "SELECT o.*, u.username, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
    $params = [];
    
    if ($status) {
        $query .= " AND o.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $query .= " AND (o.id::text LIKE ? OR u.username ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY o.order_date DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Admin: Update order status
function updateOrderStatus($pdo, $orderId, $status) {
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $orderId]);
    } catch (PDOException $e) {
        return false;
    }
}

// Admin: Get all users
function getAllUsers($pdo, $search = '') {
    if (!$pdo) return [];
    
    $query = "SELECT * FROM users WHERE is_active = true";
    $params = [];
    
    if ($search) {
        $query .= " AND (username ILIKE ? OR email ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Admin: Update user
function updateUser($pdo, $userId, $data) {
    if (!$pdo) return false;
    
    $fields = [];
    $params = [];
    
    foreach ($data as $key => $value) {
        $fields[] = "$key = ?";
        $params[] = $value;
    }
    
    $params[] = $userId;
    $query = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($query);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

// Admin: Delete user
function deleteUser($pdo, $userId) {
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = false WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        return false;
    }
}

// Admin: Add/Update product
function saveProduct($pdo, $data, $id = null) {
    if (!$pdo) return false;
    
    try {
        if ($id) {
            $fields = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
            
            $params[] = $id;
            $query = "UPDATE products SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($query);
            return $stmt->execute($params);
        } else {
            $query = "INSERT INTO products (" . implode(', ', array_keys($data)) . ", created_at, updated_at) VALUES (" . implode(', ', array_fill(0, count($data), '?')) . ", NOW(), NOW())";
            $stmt = $pdo->prepare($query);
            return $stmt->execute(array_values($data));
        }
    } catch (PDOException $e) {
        return false;
    }
}

// Admin: Delete product
function deleteProduct($pdo, $productId) {
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET is_active = false WHERE id = ?");
        return $stmt->execute([$productId]);
    } catch (PDOException $e) {
        return false;
    }
}

// Admin: Get dashboard stats
function getDashboardStats($pdo) {
    $stats = ['products' => 0, 'orders' => 0, 'users' => 0, 'revenue' => 0, 'low_stock' => 0];
    if (!$pdo) return $stats;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = true");
        $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
        $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = true AND role = 'staff'");
        $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders");
        $stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE quantity <= reorder_level AND is_active = true");
        $stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// Admin: Get low stock products
function getLowStockProducts($pdo) {
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE quantity <= reorder_level AND is_active = true ORDER BY quantity ASC LIMIT 10");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Admin: Get sales data for charts
function getSalesData($pdo, $days = 30) {
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->query("SELECT DATE(order_date) as date, COUNT(*) as orders, SUM(total_amount) as revenue FROM orders WHERE order_date >= NOW() - INTERVAL '30 days' GROUP BY DATE(order_date) ORDER BY date");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Admin: Get category distribution
function getCategoryDistribution($pdo) {
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->query("SELECT category_id, COUNT(*) as count FROM products WHERE is_active = true GROUP BY category_id ORDER BY count DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Get user by ID
function getUserById($pdo, $userId) {
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// Update user profile
function updateUserProfile($pdo, $userId, $data) {
    if (!$pdo) return false;
    
    $fields = [];
    $params = [];
    
    foreach ($data as $key => $value) {
        $fields[] = "$key = ?";
        $params[] = $value;
    }
    
    $params[] = $userId;
    $query = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($query);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}
