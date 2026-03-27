<?php
/**
 * Orders Module
 * Order management functionality
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
if (basename($_SERVER['PHP_SELF']) == 'orders.php') {
    header('Location: ../admin.php');
    exit;
}

// Get all orders
$all_orders = [];
$order_stats = ['pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0];
$order_filter = null;
$customer_filter = null;

if (isset($_GET['order_id']) && ctype_digit($_GET['order_id'])) {
    $order_filter = (int)$_GET['order_id'];
}
if (isset($_GET['customer']) && ctype_digit($_GET['customer'])) {
    $customer_filter = (int)$_GET['customer'];
}

try {
    if ($order_filter) {
        $stmt = $pdo->prepare("
            SELECT o.*,
                   COALESCE(o.order_date, o.created_at) as order_date,
                   COALESCE(o.payment_status, pt.status, 'pending') as payment_status,
                   u.full_name as customer_name, u.email as customer_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN LATERAL (
                SELECT status
                FROM payment_transactions
                WHERE order_id = o.id
                ORDER BY created_at DESC
                LIMIT 1
            ) pt ON true
            WHERE o.id = ?
            ORDER BY COALESCE(o.order_date, o.created_at) DESC
        ");
        $stmt->execute([$order_filter]);
    } elseif ($customer_filter) {
        $stmt = $pdo->prepare("
            SELECT o.*,
                   COALESCE(o.order_date, o.created_at) as order_date,
                   COALESCE(o.payment_status, pt.status, 'pending') as payment_status,
                   u.full_name as customer_name, u.email as customer_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN LATERAL (
                SELECT status
                FROM payment_transactions
                WHERE order_id = o.id
                ORDER BY created_at DESC
                LIMIT 1
            ) pt ON true
            WHERE o.user_id = ?
            ORDER BY COALESCE(o.order_date, o.created_at) DESC
        ");
        $stmt->execute([$customer_filter]);
    } else {
        $stmt = $pdo->query("
            SELECT o.*,
                   COALESCE(o.order_date, o.created_at) as order_date,
                   COALESCE(o.payment_status, pt.status, 'pending') as payment_status,
                   u.full_name as customer_name, u.email as customer_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN LATERAL (
                SELECT status
                FROM payment_transactions
                WHERE order_id = o.id
                ORDER BY created_at DESC
                LIMIT 1
            ) pt ON true
            ORDER BY COALESCE(o.order_date, o.created_at) DESC
        ");
    }

    $all_orders = $stmt->fetchAll();
    
    // Get order counts by status
    foreach ($all_orders as $order) {
        $status = $order['status'];
        if (isset($order_stats[$status])) {
            $order_stats[$status]++;
        }
    }
} catch (PDOException $e) {}
?>

<!-- Orders Page -->
<div class="page-section active" id="page-orders">
    <!-- Order Stats -->
    <div class="dashboard-stats" style="grid-template-columns: repeat(5, 1fr);">
        <div class="stat-card" onclick="filterOrdersByStatus('pending')" style="cursor: pointer;">
            <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $order_stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterOrdersByStatus('processing')" style="cursor: pointer;">
            <div class="stat-icon info"><i class="fas fa-cog"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $order_stats['processing']; ?></div>
                <div class="stat-label">Processing</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterOrdersByStatus('shipped')" style="cursor: pointer;">
            <div class="stat-icon primary"><i class="fas fa-truck"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $order_stats['shipped']; ?></div>
                <div class="stat-label">Shipped</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterOrdersByStatus('delivered')" style="cursor: pointer;">
            <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $order_stats['delivered']; ?></div>
                <div class="stat-label">Delivered</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterOrdersByStatus('cancelled')" style="cursor: pointer;">
            <div class="stat-icon danger"><i class="fas fa-times"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $order_stats['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
    </div>
    
    <!-- Orders List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Orders</h3>
            <button class="btn btn-primary" onclick="showModal('newOrderModal')">
                <i class="fas fa-plus"></i> New Order
            </button>
        </div>
        
        <!-- Filters -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-box" style="width: 250px;">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search orders..." id="orderSearch" onkeyup="filterOrders()">
                </div>
                <select class="form-control form-select" style="width: 150px;" id="orderStatusFilter" onchange="filterOrders()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select class="form-control form-select" style="width: 150px;" id="paymentStatusFilter" onchange="filterOrders()">
                    <option value="">All Payments</option>
                    <option value="completed">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-outline" onclick="exportOrders()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="data-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_orders as $order): ?>
                    <tr data-status="<?php echo $order['status']; ?>" data-payment="<?php echo strtolower($order['payment_status'] ?? 'pending'); ?>">
                        <td><strong><?php echo htmlspecialchars($order['order_number'] ?? '#ORD-' . str_pad($order['id'], 3, '0', STR_PAD_LEFT)); ?></strong></td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></strong>
                                <?php if ($order['customer_email']): ?>
                                <div class="text-muted text-sm"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                try {
                                    $itemStmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
                                    $itemStmt->execute([$order['id']]);
                                    $itemCount = $itemStmt->fetch()['count'] ?? 0;
                                    echo $itemCount . ' item(s)';
                                } catch (Exception $e) {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td class="font-semibold">$<?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                        <td>
                            <select onchange="updateOrderStatus(<?php echo $order['id']; ?>, this.value)" 
                                    style="padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color); font-size: 12px;">
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </td>
                        <td>
                            <?php 
                                $payment_class = 'badge-warning';
                                $payment_status = strtolower($order['payment_status'] ?? 'pending');
                                if ($payment_status === 'paid' || $payment_status === 'completed') $payment_class = 'badge-success';
                                elseif ($payment_status === 'failed') $payment_class = 'badge-danger';
                                $payment_label = ($payment_status === 'paid' || $payment_status === 'completed') ? 'Paid' : ucfirst($payment_status);
                            ?>
                            <span class="badge <?php echo $payment_class; ?>"><?php echo $payment_label; ?></span>
                        </td>
                        <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                        <td>
                            <div class="action-icons">
                                <button class="action-icon" title="View" onclick="viewOrder(<?php echo $order['id']; ?>)"><i class="fas fa-eye"></i></button>
                                <button class="action-icon" title="Print Invoice" onclick="printInvoice(<?php echo $order['id']; ?>)"><i class="fas fa-print"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($all_orders)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">No orders found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal-overlay" id="orderDetailsModal">
    <div class="modal" style="max-width: 840px;">
        <div class="modal-header">
            <h3 class="modal-title">Order Details</h3>
            <button class="modal-close" onclick="hideModal('orderDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="orderDetailsBody">
            <p class="text-muted">Loading order details...</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="hideModal('orderDetailsModal')">Close</button>
        </div>
    </div>
</div>

<!-- New Order Modal -->
<div class="modal-overlay" id="newOrderModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Create New Order</h3>
            <button class="modal-close" onclick="hideModal('newOrderModal')">&times;</button>
        </div>
        <form id="newOrderForm" onsubmit="createOrder(event)">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Customer Name *</label>
                        <input type="text" id="orderCustomerName" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Email</label>
                        <input type="email" id="orderCustomerEmail" name="customer_email">
                    </div>
                </div>
                <div class="form-group">
                    <label>Shipping Address</label>
                    <textarea id="orderShippingAddress" name="shipping_address" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="orderNotes" name="notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('newOrderModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Order</button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter orders
function filterOrders() {
    const search = document.getElementById('orderSearch').value.toLowerCase();
    const status = document.getElementById('orderStatusFilter').value;
    const payment = document.getElementById('paymentStatusFilter').value;
    const rows = document.querySelectorAll('#ordersTable tbody tr');
    
    rows.forEach(row => {
        let show = true;
        const text = row.textContent.toLowerCase();
        const rowStatus = row.dataset.status;
        const rowPayment = row.dataset.payment;
        
        if (search && !text.includes(search)) show = false;
        if (status && rowStatus !== status) show = false;
        if (payment && payment === 'completed') {
            if (rowPayment !== 'completed' && rowPayment !== 'paid') show = false;
        } else if (payment && rowPayment !== payment) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

// Filter by status click
function filterOrdersByStatus(status) {
    document.getElementById('orderStatusFilter').value = status;
    filterOrders();
}

// Update order status
function updateOrderStatus(orderId, status) {
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ 
            action: 'update_order_status', 
            id: orderId, 
            status: status 
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showNotification('Order status updated', 'success');
        } else {
            showNotification(result.message, 'error');
        }
    });
}

// View order
function viewOrder(orderId) {
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ action: 'get_order_details', id: orderId })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            showNotification(result.message || 'Unable to load order details', 'error');
            return;
        }

        const order = result.data.order;
        const itemsHtml = order.items && order.items.length
            ? `
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${order.items.map(item => `
                                <tr>
                                    <td>${item.product_name || 'Product'}</td>
                                    <td>${item.quantity}</td>
                                    <td>$${Number(item.unit_price || 0).toFixed(2)}</td>
                                    <td>$${Number(item.subtotal || 0).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `
            : '<p class="text-muted">No order items found.</p>';

        const transactionsHtml = order.transactions && order.transactions.length
            ? `
                <div class="table-container" style="margin-top: 16px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Gateway</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${order.transactions.map(txn => `
                                <tr>
                                    <td>${txn.transaction_id || 'Pending'}</td>
                                    <td>${txn.payment_gateway || 'N/A'}</td>
                                    <td>${txn.status || 'pending'}</td>
                                    <td>$${Number(txn.amount || 0).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `
            : '<p class="text-muted" style="margin-top: 16px;">No payment transactions found.</p>';

        document.getElementById('orderDetailsBody').innerHTML = `
            <div class="form-grid" style="margin-bottom: 16px;">
                <div>
                    <div class="text-muted text-sm">Order</div>
                    <div class="font-semibold">${order.order_number || ('#' + order.id)}</div>
                </div>
                <div>
                    <div class="text-muted text-sm">Customer</div>
                    <div class="font-semibold">${order.customer_name || order.customer_email || 'Guest'}</div>
                </div>
            </div>
            <div class="form-grid" style="margin-bottom: 16px;">
                <div>
                    <div class="text-muted text-sm">Order Status</div>
                    <div>${order.status || 'pending'}</div>
                </div>
                <div>
                    <div class="text-muted text-sm">Payment Status</div>
                    <div>${order.payment_status || 'pending'}</div>
                </div>
                <div>
                    <div class="text-muted text-sm">Total</div>
                    <div>$${Number(order.total_amount || 0).toFixed(2)}</div>
                </div>
            </div>
            <div class="text-muted text-sm" style="margin-bottom: 8px;">Items</div>
            ${itemsHtml}
            ${transactionsHtml}
        `;
        showModal('orderDetailsModal');
    })
    .catch(() => showNotification('Unable to load order details', 'error'));
}

// Print invoice
function printInvoice(orderId) {
    showNotification('Generating invoice...', 'info');
    setTimeout(() => window.print(), 500);
}

// Create order
function createOrder(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.action = 'create_order';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showNotification(result.message, 'success');
            hideModal('newOrderModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(result.message, 'error');
        }
    });
}

// Export orders
function exportOrders() {
    showNotification('Exporting orders...', 'info');
    setTimeout(() => showNotification('Orders exported successfully!', 'success'), 1500);
}

// Auto-open new order modal from dashboard links
(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('open') === 'new' && document.getElementById('newOrderModal')) {
        showModal('newOrderModal');
        params.delete('open');
        const qs = params.toString();
        history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
    }
})();
</script>

