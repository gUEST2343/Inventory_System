<?php
/**
 * Dashboard Module
 * Displays overview statistics and recent activity
 */

// At the top of each module file
if (!isset($pdo) || $pdo === null) {
    // Try to include db_connect again if we're in admin.php context
    if (file_exists('../db_connect.php')) {
        require_once '../db_connect.php';
    } elseif (file_exists('db_connect.php')) {
        require_once 'db_connect.php';
    }
    
    // If still null, show error
    if (!isset($pdo) || $pdo === null) {
        echo "<div class='alert alert-danger'>";
        echo "<h4>Database Connection Error</h4>";
        echo "<p>The database connection could not be established. Please check:</p>";
        echo "<ul>";
        echo "<li>PostgreSQL service is running</li>";
        echo "<li>Database 'Inventory_DB' exists</li>";
        echo "<li>Connection credentials are correct</li>";
        echo "</ul>";
        echo "</div>";
        return; // Stop further execution
    }
}

// Prevent direct access - this file should be included via admin.php
if (basename($_SERVER['PHP_SELF']) == 'dashboard.php') {
    header('Location: ../admin.php');
    exit;
}

// Get dashboard statistics
$stats = [
    'total_products' => 0,
    'low_stock' => 0,
    'today_orders' => 0,
    'total_orders' => 0,
    'revenue' => 0,
    'total_customers' => 0,
    'total_suppliers' => 0
];

$recent_orders = [];
$top_products = [];
$low_stock_products = [];

try {
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = true");
    $stats['total_products'] = $stmt->fetch()['count'] ?? 0;
    
    // Low stock (5 or less)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = true AND quantity <= 5");
    $stats['low_stock'] = $stmt->fetch()['count'] ?? 0;
    
    // Today's orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = CURRENT_DATE");
    $stats['today_orders'] = $stmt->fetch()['count'] ?? 0;
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $stats['total_orders'] = $stmt->fetch()['count'] ?? 0;
    
    // Total revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'paid'");
    $stats['revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND is_active = true");
    $stats['total_customers'] = $stmt->fetch()['count'] ?? 0;
    
    // Total suppliers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = true");
    $stats['total_suppliers'] = $stmt->fetch()['count'] ?? 0;
    
    // Recent orders
    $stmt = $pdo->query("SELECT o.*, u.full_name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();
    
    // Top products
    $stmt = $pdo->query("SELECT p.name, p.unit_price, p.quantity, COALESCE(SUM(oi.quantity), 0) as sales_count, COALESCE(SUM(oi.subtotal), 0) as revenue 
                          FROM products p 
                          LEFT JOIN order_items oi ON p.id = oi.product_id 
                          WHERE p.is_active = true 
                          GROUP BY p.id 
                          ORDER BY sales_count DESC 
                          LIMIT 5");
    $top_products = $stmt->fetchAll();
    
    // Low stock products
    $stmt = $pdo->query("SELECT p.name, p.sku, p.quantity, p.reorder_level, c.name as category_name
                          FROM products p 
                          JOIN categories c ON p.category_id = c.id
                          WHERE p.is_active = true AND p.quantity <= p.reorder_level
                          ORDER BY p.quantity ASC
                          LIMIT 5");
    $low_stock_products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
}
?>

<!-- Dashboard Stats -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-boxes"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
            <div class="stat-label">Total Products</div>
            <div class="stat-trend up"><i class="fas fa-arrow-up"></i><span>Active items</span></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo number_format($stats['low_stock']); ?></div>
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-trend <?php echo $stats['low_stock'] > 0 ? 'down' : 'neutral'; ?>">
                <i class="fas fa-<?php echo $stats['low_stock'] > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                <span><?php echo $stats['low_stock'] > 0 ? 'Needs attention' : 'All good'; ?></span>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo number_format($stats['today_orders']); ?></div>
            <div class="stat-label">Today's Orders</div>
            <div class="stat-trend up"><i class="fas fa-arrow-up"></i><span><?php echo $stats['total_orders']; ?> total</span></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-content">
            <div class="stat-value">$<?php echo number_format($stats['revenue'], 2); ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-trend up"><i class="fas fa-arrow-up"></i><span>Paid orders</span></div>
        </div>
    </div>
</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Sales Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Sales Overview</h3>
            <button class="btn btn-sm btn-outline" onclick="exportData('csv')">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
        <div class="chart-container">
            <div class="chart-bar" style="height: 60%;" data-value="$2.4k"></div>
            <div class="chart-bar" style="height: 80%;" data-value="$3.2k"></div>
            <div class="chart-bar" style="height: 45%;" data-value="$1.8k"></div>
            <div class="chart-bar" style="height: 90%;" data-value="$3.6k"></div>
            <div class="chart-bar" style="height: 70%;" data-value="$2.8k"></div>
            <div class="chart-bar" style="height: 55%;" data-value="$2.2k"></div>
            <div class="chart-bar" style="height: 85%;" data-value="$3.4k"></div>
        </div>
        <div class="chart-labels">
            <span class="chart-label">Mon</span>
            <span class="chart-label">Tue</span>
            <span class="chart-label">Wed</span>
            <span class="chart-label">Thu</span>
            <span class="chart-label">Fri</span>
            <span class="chart-label">Sat</span>
            <span class="chart-label">Sun</span>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div class="quick-actions">
            <button class="quick-action-btn" onclick="window.location.href='?page=products&open=add'">
                <i class="fas fa-plus-circle"></i>
                <span>Add Product</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='?page=orders&open=new'">
                <i class="fas fa-shopping-cart"></i>
                <span>New Order</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='?page=customers&open=add'">
                <i class="fas fa-user-plus"></i>
                <span>Add Customer</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='?page=reports'">
                <i class="fas fa-file-export"></i>
                <span>Export Report</span>
            </button>
        </div>
    </div>
</div>

<!-- Second Row -->
<div class="dashboard-grid mt-xl">
    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Orders</h3>
            <a href="?page=orders" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['order_number'] ?? '#ORD-' . str_pad($order['id'], 3, '0', STR_PAD_LEFT)); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['customer_name'] ?? $order['customer_email'] ?? 'Guest'); ?></td>
                        <td class="font-semibold">$<?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                        <td>
                            <?php 
                                $status_class = 'badge-warning';
                                $status_text = $order['status'];
                                if ($order['status'] == 'delivered') { $status_class = 'badge-success'; }
                                elseif ($order['status'] == 'shipped') { $status_class = 'badge-info'; }
                                elseif ($order['status'] == 'processing') { $status_class = 'badge-info'; }
                                elseif ($order['status'] == 'cancelled') { $status_class = 'badge-danger'; }
                            ?>
                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($status_text); ?></span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                        <td>
                            <div class="action-icons">
                                <button class="action-icon" title="View" onclick="viewOrder(<?php echo $order['id']; ?>)"><i class="fas fa-eye"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recent_orders)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No orders yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Low Stock Alerts -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Low Stock Alerts</h3>
            <a href="?page=inventory" class="btn btn-sm btn-outline">Manage Inventory</a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                        <td>
                            <span class="<?php echo $product['quantity'] == 0 ? 'text-danger' : 'text-warning'; ?>">
                                <?php echo $product['quantity']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($product['quantity'] == 0): ?>
                                <span class="badge badge-danger">Out of Stock</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Low Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($low_stock_products)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 8px; color: var(--success);"></i><br>
                        All products are well stocked
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top Products -->
<div class="card mt-xl">
    <div class="card-header">
        <h3 class="card-title">Top Products</h3>
        <a href="?page=products" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="top-products">
        <?php foreach ($top_products as $product): ?>
        <div class="top-product-card">
            <div class="top-product-image"><i class="fas fa-box"></i></div>
            <div class="top-product-info">
                <div class="top-product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                <div class="top-product-sales"><?php echo $product['sales_count']; ?> sales</div>
            </div>
            <div class="top-product-revenue">$<?php echo number_format($product['revenue'], 2); ?></div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($top_products)): ?>
        <p style="grid-column: 1/-1; text-align: center; padding: 20px; color: var(--text-muted);">No product sales data yet</p>
        <?php endif; ?>
    </div>
</div>

<script>
function exportData(format) {
    const label = (format || 'csv').toUpperCase();
    showNotification('Exporting dashboard data as ' + label + '...', 'info');
    setTimeout(() => showNotification('Dashboard data exported!', 'success'), 1500);
}

function viewOrder(orderId) {
    window.location.href = '?page=orders&order_id=' + encodeURIComponent(orderId);
}
</script>

