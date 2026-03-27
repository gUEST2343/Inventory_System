<?php
// admin/index.php
require_once 'auth.php';
$adminAuth->requireLogin();
require_once '../config/database.php';
require_once '../modules/order_module.php';
require_once '../modules/product_module.php';
require_once '../modules/auth_module.php';

$orderModule = new OrderModule($pdo);
$productModule = new ProductModule($pdo);

$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE payment_status = 'paid') as paid_orders,
        (SELECT COUNT(*) FROM orders WHERE payment_status = 'pending') as pending_orders,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid') as total_revenue,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM products WHERE stock_quantity < 10 AND status = 'active') as low_stock
")->fetch(PDO::FETCH_ASSOC);

$recentOrders = $orderModule->getOrders(null, null);
$recentOrders = array_slice($recentOrders, 0, 10);

$lowStockProducts = $pdo->query("
    SELECT * FROM products
    WHERE stock_quantity < 10 AND status = 'active'
    ORDER BY stock_quantity ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 1rem;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: #495057;
        }
        .sidebar .nav-link.active {
            background: #007bff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .stat-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">Admin Panel</h5>
                        <small class="text-white-50">Welcome, <?= $_SESSION['admin_username'] ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="bi bi-box"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="bi bi-cart"></i> Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payments.php">
                                <i class="bi bi-credit-card"></i> Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="bi bi-graph-up"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-primary text-white" onclick="window.location.href='orders.php'">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Orders</h6>
                                        <h2><?= $stats['total_orders'] ?></h2>
                                    </div>
                                    <i class="bi bi-cart fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-success text-white" onclick="window.location.href='orders.php?status=paid'">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Paid Orders</h6>
                                        <h2><?= $stats['paid_orders'] ?></h2>
                                    </div>
                                    <i class="bi bi-check-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-warning text-white" onclick="window.location.href='orders.php?status=pending'">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Pending Orders</h6>
                                        <h2><?= $stats['pending_orders'] ?></h2>
                                    </div>
                                    <i class="bi bi-hourglass fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-info text-white" onclick="window.location.href='reports.php'">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Revenue</h6>
                                        <h2>$<?= number_format($stats['total_revenue'], 2) ?></h2>
                                    </div>
                                    <i class="bi bi-currency-dollar fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" onclick="window.location.href='users.php'">
                            <div class="card-body">
                                <h6>Total Users</h6>
                                <h3><?= $stats['total_users'] ?></h3>
                                <small class="text-muted">Registered customers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" onclick="window.location.href='products.php'">
                            <div class="card-body">
                                <h6>Total Products</h6>
                                <h3><?= $stats['total_products'] ?></h3>
                                <small class="text-muted">Active products</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card bg-danger text-white" onclick="window.location.href='products.php?stock=low'">
                            <div class="card-body">
                                <h6>Low Stock Alert</h6>
                                <h3><?= $stats['low_stock'] ?></h3>
                                <small>Products with stock &lt; 10</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Recent Orders</h5>
                        <a href="orders.php" class="btn btn-sm btn-primary">View All Orders</a>
                    </div>
                    <div class="card-body">
                        <table id="recentOrdersTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Status</th>
                                    <th>Order Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="orders.php?id=<?= $order['id'] ?>">
                                            <?= htmlspecialchars($order['order_number'] ?: ('ORD-' . str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT))) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($order['customer_name'] ?? $order['username']) ?></td>
                                    <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = match($order['payment_status']) {
                                            'paid' => 'bg-success',
                                            'pending' => 'bg-warning',
                                            'failed' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($order['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= ucfirst($order['status']) ?></span>
                                    </td>
                                    <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info"
                                                onclick="viewOrder(<?= $order['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success"
                                                onclick="updateOrderStatus(<?= $order['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($lowStockProducts)): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center bg-warning">
                        <h5>Low Stock Alert</h5>
                        <a href="products.php" class="btn btn-sm btn-dark">Manage Products</a>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= $product['category'] ?></td>
                                    <td>
                                        <span class="badge bg-danger"><?= $product['stock_quantity'] ?></span>
                                    </td>
                                    <td>$<?= number_format($product['price'], 2) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"
                                                onclick="restockProduct(<?= $product['id'] ?>)">
                                            Restock
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="orders.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Order Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" id="status_order_id">
                        <div class="mb-3">
                            <label class="form-label">Order Status</label>
                            <select name="status" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#recentOrdersTable').DataTable({
                pageLength: 5,
                order: [[5, 'desc']]
            });
        });

        function viewOrder(orderId) {
            window.location.href = 'orders.php?id=' + orderId;
        }

        function updateOrderStatus(orderId) {
            document.getElementById('status_order_id').value = orderId;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        function restockProduct(productId) {
            let quantity = prompt('Enter quantity to add:');
            if (quantity && !isNaN(quantity) && quantity > 0) {
                window.location.href = 'products.php?action=restock&id=' + productId + '&quantity=' + quantity;
            }
        }
    </script>
</body>
</html>
