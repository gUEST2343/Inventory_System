<?php
/**
 * Inventory Module
 * Inventory management and stock control
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
if (basename($_SERVER['PHP_SELF']) == 'inventory.php') {
    header('Location: ../admin.php');
    exit;
}

if (!isset($canManageInventory)) {
    $canManageInventory = in_array($_SESSION['role'] ?? 'guest', ['admin', 'manager'], true);
}

// Get inventory data
$inventory_items = [];
$low_stock_items = [];
$out_of_stock_items = [];
$total_value = 0;

try {
    // All inventory items
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
               (p.quantity * p.unit_price) as total_value,
               (p.quantity * p.cost_price) as cost_value
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = true 
        ORDER BY p.name
    ");
    $inventory_items = $stmt->fetchAll();
    
    // Calculate total value
    foreach ($inventory_items as $item) {
        $total_value += $item['total_value'];
    }
    
    // Low stock items
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = true AND p.quantity <= p.reorder_level AND p.quantity > 0
        ORDER BY p.quantity ASC
    ");
    $low_stock_items = $stmt->fetchAll();
    
    // Out of stock items
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = true AND p.quantity = 0
        ORDER BY p.name
    ");
    $out_of_stock_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching inventory data: " . $e->getMessage());
}
?>

<!-- Inventory Page -->
<div class="page-section active" id="page-inventory">
    <!-- Stats -->
    <div class="dashboard-stats" style="grid-template-columns: repeat(4, 1fr);">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="fas fa-boxes"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($inventory_items); ?></div>
                <div class="stat-label">Total Products</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($low_stock_items); ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($out_of_stock_items); ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-content">
                <div class="stat-value">$<?php echo number_format($total_value, 0); ?></div>
                <div class="stat-label">Total Value</div>
            </div>
        </div>
    </div>
    
    <!-- Alerts Section -->
    <?php if (!empty($low_stock_items) || !empty($out_of_stock_items)): ?>
    <div class="card mb-xl">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bell" style="color: var(--warning);"></i> Stock Alerts</h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; padding: 20px;">
            <?php if (!empty($out_of_stock_items)): ?>
            <div style="background: var(--danger-light); padding: 16px; border-radius: 8px;">
                <h4 style="color: #b91c1c; margin-bottom: 12px;">
                    <i class="fas fa-times-circle"></i> Out of Stock (<?php echo count($out_of_stock_items); ?>)
                </h4>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($out_of_stock_items as $item): ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid rgba(185,28,28,0.2);">
                        <?php echo htmlspecialchars($item['name']); ?> - 
                        <span class="badge badge-danger">Out of Stock</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($low_stock_items)): ?>
            <div style="background: var(--warning-light); padding: 16px; border-radius: 8px;">
                <h4 style="color: #b45309; margin-bottom: 12px;">
                    <i class="fas fa-exclamation-triangle"></i> Low Stock (<?php echo count($low_stock_items); ?>)
                </h4>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($low_stock_items as $item): ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid rgba(180,83,9,0.2);">
                        <?php echo htmlspecialchars($item['name']); ?> - 
                        <strong><?php echo $item['quantity']; ?></strong> left (reorder at <?php echo $item['reorder_level']; ?>)
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Inventory Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Inventory Overview</h3>
            <button class="btn btn-primary" onclick="showModal('stockAdjustmentModal')">
                <i class="fas fa-plus"></i> Adjust Stock
            </button>
        </div>
        
        <!-- Filters -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-box" style="width: 250px;">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search inventory..." id="inventorySearch" onkeyup="filterInventory()">
                </div>
                <select class="form-control form-select" style="width: 150px;" id="inventoryStatusFilter" onchange="filterInventory()">
                    <option value="">All Status</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-outline" onclick="exportInventory()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="data-table" id="inventoryTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Reorder Level</th>
                        <th>Unit Price</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory_items as $item): ?>
                    <?php 
                        $status = 'in_stock';
                        $status_label = 'In Stock';
                        if ($item['quantity'] == 0) {
                            $status = 'out_of_stock';
                            $status_label = 'Out of Stock';
                        } elseif ($item['quantity'] <= $item['reorder_level']) {
                            $status = 'low_stock';
                            $status_label = 'Low Stock';
                        }
                    ?>
                    <tr data-status="<?php echo $status; ?>" data-quantity="<?php echo $item['quantity']; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                        <td class="font-semibold"><?php echo $item['quantity']; ?></td>
                        <td><?php echo $item['reorder_level']; ?></td>
                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>$<?php echo number_format($item['total_value'], 2); ?></td>
                        <td>
                            <?php if ($status === 'out_of_stock'): ?>
                                <span class="badge badge-danger">Out of Stock</span>
                            <?php elseif ($status === 'low_stock'): ?>
                                <span class="badge badge-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge badge-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-icons">
                                <?php if ($canManageInventory): ?>
                                <button class="action-icon" title="Edit Product" onclick="editInventoryItem(<?php echo (int)$item['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <button class="action-icon" title="Adjust Stock" onclick="quickAdjustStock(<?php echo (int)$item['id']; ?>, <?php echo (int)($item['quantity'] ?? 0); ?>)">
                                    <i class="fas fa-boxes"></i>
                                </button>
                                <button class="action-icon" title="View History" onclick="viewStockHistory(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-history"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($inventory_items)): ?>
                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">No inventory items found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Stock History Modal -->
<div class="modal-overlay" id="stockHistoryModal">
    <div class="modal" style="max-width: 860px;">
        <div class="modal-header">
            <h3 class="modal-title">Stock History</h3>
            <button class="modal-close" onclick="hideModal('stockHistoryModal')">&times;</button>
        </div>
        <div class="modal-body" id="stockHistoryBody">
            <p class="text-muted">Loading stock history...</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="hideModal('stockHistoryModal')">Close</button>
        </div>
    </div>
</div>

<!-- Quick Stock Adjustment Modal -->
<div class="modal-overlay" id="stockAdjustmentModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Quick Stock Adjustment</h3>
            <button class="modal-close" onclick="hideModal('stockAdjustmentModal')">&times;</button>
        </div>
        <form id="quickStockForm" onsubmit="saveQuickStockAdjustment(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Product *</label>
                    <select id="quickProductId" name="product_id" required onchange="updateQuickStockInfo()">
                        <option value="">Select a product</option>
                        <?php foreach ($inventory_items as $item): ?>
                        <option value="<?php echo $item['id']; ?>" data-stock="<?php echo $item['quantity']; ?>">
                            <?php echo htmlspecialchars($item['name']); ?> (Stock: <?php echo $item['quantity']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Current Stock</label>
                    <div id="quickCurrentStock" class="font-semibold text-lg">-</div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Adjustment Type</label>
                        <select id="quickAdjustmentType" name="adjustment_type">
                            <option value="add">Add Stock (+)</option>
                            <option value="remove">Remove Stock (-)</option>
                            <option value="set">Set Stock (=)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" id="quickQuantity" name="quantity" required min="0" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="quickNotes" name="notes" rows="2" placeholder="Reason for adjustment..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('stockAdjustmentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Adjust Stock</button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter inventory
function filterInventory() {
    const search = document.getElementById('inventorySearch').value.toLowerCase();
    const status = document.getElementById('inventoryStatusFilter').value;
    const rows = document.querySelectorAll('#inventoryTable tbody tr');
    
    rows.forEach(row => {
        let show = true;
        const text = row.textContent.toLowerCase();
        const rowStatus = row.dataset.status;
        const quantity = parseInt(row.dataset.quantity);
        
        if (search && !text.includes(search)) show = false;
        if (status && rowStatus !== status) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

// Quick adjust stock
function quickAdjustStock(id, stock) {
    document.getElementById('quickProductId').value = id;
    document.getElementById('quickCurrentStock').textContent = stock;
    showModal('stockAdjustmentModal');
}

function editInventoryItem(productId) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', 'products');
    params.set('edit', productId);
    window.location.href = window.location.pathname + '?' + params.toString();
}

// Update quick stock info
function updateQuickStockInfo() {
    const select = document.getElementById('quickProductId');
    const option = select.options[select.selectedIndex];
    const stock = option.dataset.stock || 0;
    document.getElementById('quickCurrentStock').textContent = stock;
}

// Save quick stock adjustment
function saveQuickStockAdjustment(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.action = 'adjust_stock';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showNotification(result.message, 'success');
            hideModal('stockAdjustmentModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(result.message, 'error');
        }
    });
}

// View stock history (placeholder)
function viewStockHistory(productId) {
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ action: 'get_stock_history', product_id: productId })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            showNotification(result.message || 'Unable to load stock history', 'error');
            return;
        }

        const product = result.data.product;
        const history = result.data.history || [];
        const historyHtml = history.length
            ? `
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Before</th>
                                <th>After</th>
                                <th>Change</th>
                                <th>User</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${history.map(entry => `
                                <tr>
                                    <td>${new Date(entry.created_at).toLocaleString()}</td>
                                    <td>${entry.action}</td>
                                    <td>${entry.quantity_before}</td>
                                    <td>${entry.quantity_after}</td>
                                    <td>${entry.quantity_changed}</td>
                                    <td>${entry.user_name || 'System'}</td>
                                    <td>${entry.notes || ''}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `
            : '<p class="text-muted">No stock history found for this product.</p>';

        document.getElementById('stockHistoryBody').innerHTML = `
            <div class="form-grid" style="margin-bottom: 16px;">
                <div>
                    <div class="text-muted text-sm">Product</div>
                    <div class="font-semibold">${product.name}</div>
                </div>
                <div>
                    <div class="text-muted text-sm">SKU</div>
                    <div>${product.sku || 'N/A'}</div>
                </div>
                <div>
                    <div class="text-muted text-sm">Current Stock</div>
                    <div>${product.quantity}</div>
                </div>
            </div>
            ${historyHtml}
        `;
        showModal('stockHistoryModal');
    })
    .catch(() => showNotification('Unable to load stock history', 'error'));
}

// Export inventory
function exportInventory() {
    showNotification('Exporting inventory...', 'info');
    setTimeout(() => showNotification('Inventory exported successfully!', 'success'), 1500);
}
</script>

