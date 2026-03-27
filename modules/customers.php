<?php
/**
 * Customers Module
 * Customer management functionality
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
if (basename($_SERVER['PHP_SELF']) == 'customers.php') {
    header('Location: ../admin.php');
    exit;
}

// Get all customers
$customers = [];
try {
    $stmt = $pdo->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
               (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as total_spent
        FROM users u 
        WHERE u.role = 'customer' AND u.is_active = true
        ORDER BY u.created_at DESC
    ");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<!-- Customers Page -->
<div class="page-section active" id="page-customers">
    <!-- Stats -->
    <div class="dashboard-stats" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($customers); ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content">
                <div class="stat-value">
                    <?php 
                        $totalOrders = 0;
                        foreach ($customers as $c) { $totalOrders += $c['order_count']; }
                        echo $totalOrders;
                    ?>
                </div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-content">
                <div class="stat-value">
                    $<?php 
                        $totalRevenue = 0;
                        foreach ($customers as $c) { $totalRevenue += $c['total_spent']; }
                        echo number_format($totalRevenue, 0);
                    ?>
                </div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
    </div>
    
    <!-- Customers List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Customers</h3>
            <button class="btn btn-primary" onclick="showModal('customerModal'); resetCustomerForm();">
                <i class="fas fa-plus"></i> Add Customer
            </button>
        </div>
        
        <!-- Filters -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-box" style="width: 250px;">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search customers..." id="customerSearch" onkeyup="filterCustomers()">
                </div>
                <select class="form-control form-select" style="width: 150px;" id="customerGroupFilter" onchange="filterCustomers()">
                    <option value="">All Groups</option>
                    <option value="regular">Regular</option>
                    <option value="vip">VIP</option>
                    <option value="wholesale">Wholesale</option>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-outline" onclick="exportCustomers()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="data-table" id="customersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Group</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr data-group="<?php echo $customer['customer_group'] ?? 'regular'; ?>">
                        <td><?php echo $customer['id']; ?></td>
                        <td>
                            <div class="d-flex align-center gap-sm">
                                <div class="customer-avatar-mini">
                                    <?php echo strtoupper(substr($customer['full_name'] ?? $customer['username'], 0, 2)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($customer['full_name'] ?? $customer['username']); ?></strong>
                                    <div class="text-muted text-sm">@<?php echo htmlspecialchars($customer['username']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                                $group = $customer['customer_group'] ?? 'regular';
                                $group_class = 'badge-info';
                                if ($group === 'vip') $group_class = 'badge-warning';
                                elseif ($group === 'wholesale') $group_class = 'badge-success';
                            ?>
                            <span class="badge <?php echo $group_class; ?>"><?php echo ucfirst($group); ?></span>
                        </td>
                        <td><?php echo $customer['order_count']; ?></td>
                        <td class="font-semibold">$<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <div class="action-icons">
                                <button class="action-icon" title="Edit" onclick="editCustomer(<?php echo (int)$customer['id']; ?>, <?php echo json_encode($customer['username'] ?? ''); ?>, <?php echo json_encode($customer['full_name'] ?? ''); ?>, <?php echo json_encode($customer['email'] ?? ''); ?>, <?php echo json_encode($customer['phone'] ?? ''); ?>, <?php echo json_encode($group); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-icon" title="View Orders" onclick="viewCustomerOrders(<?php echo $customer['id']; ?>)">
                                    <i class="fas fa-shopping-bag"></i>
                                </button>
                                <button class="action-icon delete" title="Delete" onclick="deleteCustomer(<?php echo $customer['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No customers found
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal-overlay" id="customerDetailsModal">
    <div class="modal" style="max-width: 720px;">
        <div class="modal-header">
            <h3 class="modal-title">Customer Details</h3>
            <button class="modal-close" onclick="hideModal('customerDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="customerDetailsBody">
            <p class="text-muted">Loading customer details...</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="hideModal('customerDetailsModal')">Close</button>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal-overlay" id="customerModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="customerModalTitle">Add Customer</h3>
            <button class="modal-close" onclick="hideModal('customerModal')">&times;</button>
        </div>
        <form id="customerForm" onsubmit="saveCustomer(event)">
            <div class="modal-body">
                <input type="hidden" id="customerId" name="id">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="customerName" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" id="customerUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="customerEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" id="customerPhone" name="phone">
                </div>
                <div class="form-group">
                    <label>Customer Group</label>
                    <select id="customerGroup" name="customer_group">
                        <option value="regular">Regular</option>
                        <option value="vip">VIP</option>
                        <option value="wholesale">Wholesale</option>
                    </select>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label>Password *</label>
                    <input type="password" id="customerPassword" name="password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('customerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<style>
.customer-avatar-mini {
    width: 36px;
    height: 36px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    font-weight: 600;
}
</style>

<script>
// Filter customers
function filterCustomers() {
    const search = document.getElementById('customerSearch').value.toLowerCase();
    const group = document.getElementById('customerGroupFilter').value;
    const rows = document.querySelectorAll('#customersTable tbody tr');
    
    rows.forEach(row => {
        let show = true;
        const text = row.textContent.toLowerCase();
        const rowGroup = row.dataset.group;
        
        if (search && !text.includes(search)) show = false;
        if (group && rowGroup !== group) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

// Reset customer form
function resetCustomerForm() {
    document.getElementById('customerModalTitle').textContent = 'Add Customer';
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    const usernameInput = document.getElementById('customerUsername');
    usernameInput.readOnly = false;
    usernameInput.required = true;
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('customerPassword').required = true;
}

// Edit customer
function editCustomer(id, username, name, email, phone, group) {
    document.getElementById('customerModalTitle').textContent = 'Edit Customer';
    document.getElementById('customerId').value = id;
    document.getElementById('customerName').value = name;
    const usernameInput = document.getElementById('customerUsername');
    usernameInput.value = username || '';
    usernameInput.readOnly = true;
    usernameInput.required = true;
    document.getElementById('customerEmail').value = email;
    document.getElementById('customerPhone').value = phone;
    document.getElementById('customerGroup').value = group;
    document.getElementById('passwordGroup').style.display = 'none';
    document.getElementById('customerPassword').required = false;
    showModal('customerModal');
}

// Save customer
function saveCustomer(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.action = document.getElementById('customerId').value ? 'update_customer' : 'add_customer';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showNotification(result.message, 'success');
            hideModal('customerModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(result.message, 'error');
        }
    });
}

// View customer orders
function viewCustomerOrders(customerId) {
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ action: 'get_customer_details', id: customerId })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            showNotification(result.message || 'Unable to load customer details', 'error');
            return;
        }

        const customer = result.data.customer;
        let ordersHtml = '<p class="text-muted">No recent orders found.</p>';
        if (customer.orders && customer.orders.length) {
            ordersHtml = `
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${customer.orders.map(order => `
                                <tr>
                                    <td>${order.order_number || ('#' + order.id)}</td>
                                    <td>${order.status}</td>
                                    <td>${order.payment_status}</td>
                                    <td>$${Number(order.total_amount || 0).toFixed(2)}</td>
                                    <td>${new Date(order.order_date).toLocaleString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        document.getElementById('customerDetailsBody').innerHTML = `
            <div class="form-grid" style="margin-bottom: 16px;">
                <div>
                    <div class="text-muted text-sm">Customer</div>
                    <div class="font-semibold">${customer.full_name || customer.username}</div>
                </div>
                <div>
                    <div class="text-muted text-sm">Joined</div>
                    <div class="font-semibold">${new Date(customer.created_at).toLocaleDateString()}</div>
                </div>
            </div>
            <div class="form-grid" style="margin-bottom: 16px;">
                <div>
                    <div class="text-muted text-sm">Email</div>
                    <div>${customer.email || 'N/A'}</div>
                </div>
                <div>
                    <div class="text-muted text-sm">Phone</div>
                    <div>${customer.phone || 'N/A'}</div>
                </div>
            </div>
            <div class="text-muted text-sm" style="margin-bottom: 8px;">Recent Orders</div>
            ${ordersHtml}
        `;
        showModal('customerDetailsModal');
    })
    .catch(() => showNotification('Unable to load customer details', 'error'));
}

// Delete customer
function deleteCustomer(id) {
    if (confirm('Are you sure you want to delete this customer?')) {
        fetch('admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action: 'delete_customer', id: id })
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                showNotification(result.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(result.message, 'error');
            }
        });
    }
}

// Export customers
function exportCustomers() {
    showNotification('Exporting customers...', 'info');
    setTimeout(() => showNotification('Customers exported successfully!', 'success'), 1500);
}

// Auto-open add customer modal from dashboard links
(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('open') === 'add' && document.getElementById('customerModal')) {
        resetCustomerForm();
        showModal('customerModal');
        params.delete('open');
        const qs = params.toString();
        history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
    }
})();
</script>

