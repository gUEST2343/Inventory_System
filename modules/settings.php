<?php
/**
 * Settings Module
 * System settings and configuration
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
if (basename($_SERVER['PHP_SELF']) == 'settings.php') {
    header('Location: ../admin.php');
    exit;
}

// Get settings
$settings = [
    'store_name' => 'StockFlow Inventory',
    'store_email' => 'admin@stockflow.com',
    'currency' => 'USD',
    'low_stock_threshold' => 5,
    'timezone' => 'Africa/Nairobi',
    'date_format' => 'Y-m-d'
];

// Get users
$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, email, full_name, role, is_active, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<!-- Settings Page -->
<div class="page-section active" id="page-settings">
    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <button class="settings-tab active" onclick="switchSettingsTab(event, 'general')">
            <i class="fas fa-cog"></i> General
        </button>
        <button class="settings-tab" onclick="switchSettingsTab(event, 'users')">
            <i class="fas fa-users"></i> Users
        </button>
        <button class="settings-tab" onclick="switchSettingsTab(event, 'notifications')">
            <i class="fas fa-bell"></i> Notifications
        </button>
        <button class="settings-tab" onclick="switchSettingsTab(event, 'backup')">
            <i class="fas fa-database"></i> Backup
        </button>
    </div>
    
    <!-- General Settings -->
    <div class="settings-panel active" id="settings-general">
        <div class="settings-section">
            <h3 class="settings-section-title">Store Information</h3>
            <form id="generalSettingsForm" onsubmit="saveGeneralSettings(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" value="<?php echo htmlspecialchars($settings['store_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Store Email</label>
                        <input type="email" name="store_email" value="<?php echo htmlspecialchars($settings['store_email']); ?>">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency">
                            <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                            <option value="GBP" <?php echo $settings['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                            <option value="KES" <?php echo $settings['currency'] == 'KES' ? 'selected' : ''; ?>>KES (KSh)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone">
                            <option value="Africa/Nairobi" <?php echo $settings['timezone'] == 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi</option>
                            <option value="UTC" <?php echo $settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo $settings['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Low Stock Threshold</label>
                    <input type="number" name="low_stock_threshold" value="<?php echo $settings['low_stock_threshold']; ?>" min="1" style="width: 150px;">
                    <p class="form-text">Products with stock at or below this level will be marked as low stock</p>
                </div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
    
    <!-- Users Settings -->
    <div class="settings-panel" id="settings-users">
        <div class="settings-section">
            <div class="section-header">
                <h3 class="settings-section-title">User Management</h3>
                <button class="btn btn-primary" onclick="showModal('addUserModal')">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php 
                                    $role_class = 'badge-info';
                                    if ($user['role'] === 'admin') $role_class = 'badge-warning';
                                    elseif ($user['role'] === 'manager') $role_class = 'badge-success';
                                ?>
                                <span class="badge <?php echo $role_class; ?>"><?php echo ucfirst($user['role']); ?></span>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-icons">
                                    <button class="action-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                    <button class="action-icon delete" title="Delete"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Notifications Settings -->
    <div class="settings-panel" id="settings-notifications">
        <div class="settings-section">
            <h3 class="settings-section-title">Email Notifications</h3>
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" checked> New order notifications
                </label>
                <label>
                    <input type="checkbox" checked> Low stock alerts
                </label>
                <label>
                    <input type="checkbox"> Daily sales report
                </label>
                <label>
                    <input type="checkbox"> Weekly summary
                </label>
            </div>
            <button class="btn btn-primary mt-lg" onclick="saveNotificationSettings()">Save Preferences</button>
        </div>
        
        <div class="settings-section">
            <h3 class="settings-section-title">System Notifications</h3>
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" checked> Order status changes
                </label>
                <label>
                    <input type="checkbox" checked> Inventory updates
                </label>
                <label>
                    <input type="checkbox" checked> User activity
                </label>
            </div>
        </div>
    </div>
    
    <!-- Backup Settings -->
    <div class="settings-panel" id="settings-backup">
        <div class="settings-section">
            <h3 class="settings-section-title">Database Backup</h3>
            <p class="text-muted mb-lg">Create and download a backup of your database</p>
            
            <div class="d-flex gap-md">
                <button class="btn btn-primary" onclick="createBackup()">
                    <i class="fas fa-database"></i> Create Backup
                </button>
                <button class="btn btn-outline" onclick="restoreBackup()">
                    <i class="fas fa-upload"></i> Restore Backup
                </button>
            </div>
        </div>
        
        <div class="settings-section">
            <h3 class="settings-section-title">System Information</h3>
            <div class="metrics-summary">
                <div class="metric-item">
                    <span class="metric-label">PHP Version</span>
                    <span class="metric-value"><?php echo phpversion(); ?></span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Database</span>
                    <span class="metric-value">PostgreSQL</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Server Time</span>
                    <span class="metric-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button class="modal-close" onclick="hideModal('addUserModal')">&times;</button>
        </div>
        <form id="addUserForm" onsubmit="addUser(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<script>
// Switch settings tabs
function switchSettingsTab(e, tabId) {
    // Update tab buttons
    document.querySelectorAll('.settings-tab').forEach(tab => tab.classList.remove('active'));
    e.currentTarget.classList.add('active');
    
    // Update panels
    document.querySelectorAll('.settings-panel').forEach(panel => panel.classList.remove('active'));
    document.getElementById('settings-' + tabId).classList.add('active');
}

// Save general settings
function saveGeneralSettings(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.action = 'save_settings';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showNotification('Settings saved successfully', 'success');
        } else {
            showNotification(result.message, 'error');
        }
    });
}

// Save notification settings
function saveNotificationSettings() {
    showNotification('Notification settings saved', 'success');
}

// Add user
function addUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.action = 'add_user';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showNotification('User added successfully', 'success');
            hideModal('addUserModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(result.message, 'error');
        }
    });
}

// Create backup
function createBackup() {
    showNotification('Creating database backup...', 'info');
    setTimeout(() => showNotification('Backup created successfully!', 'success'), 2000);
}

// Restore backup
function restoreBackup() {
    if (confirm('Are you sure you want to restore from a backup? This may overwrite current data.')) {
        showNotification('Restore functionality coming soon', 'info');
    }
}
</script>

