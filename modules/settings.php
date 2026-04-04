<?php
/**
 * Settings Module
 * System settings and configuration
 */

if (!isset($pdo) || $pdo === null) {
    if (file_exists('../db_connect.php')) {
        require_once '../db_connect.php';
    } elseif (file_exists('db_connect.php')) {
        require_once 'db_connect.php';
    }

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
        return;
    }
}

require_once __DIR__ . '/../includes/settings_helper.php';

if (basename($_SERVER['PHP_SELF']) === 'settings.php') {
    header('Location: ../admin.php');
    exit;
}

$settings = getAppSettings($pdo);

$users = [];
try {
    $stmt = $pdo->query("
        SELECT id, username, email, full_name, role, is_active, created_at
        FROM users
        WHERE role IN ('admin', 'manager', 'staff')
        ORDER BY created_at DESC, id DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

$formatStoredDate = static function (?string $value): string {
    if (!$value) {
        return 'Not available';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'Not available';
    }

    return date('Y-m-d H:i:s', $timestamp);
};

$lastBackupAt = $formatStoredDate($settings['last_backup_at'] ?? '');
$lastRestoredAt = $formatStoredDate($settings['last_restored_at'] ?? '');
?>

<div class="page-section active" id="page-settings">
    <div class="settings-tabs">
        <button type="button" class="settings-tab active" data-tab="general" onclick="switchSettingsTab(event, 'general')">
            <i class="fas fa-cog"></i> General
        </button>
        <button type="button" class="settings-tab" data-tab="users" onclick="switchSettingsTab(event, 'users')">
            <i class="fas fa-users"></i> Users
        </button>
        <button type="button" class="settings-tab" data-tab="notifications" onclick="switchSettingsTab(event, 'notifications')">
            <i class="fas fa-bell"></i> Notifications
        </button>
        <button type="button" class="settings-tab" data-tab="backup" onclick="switchSettingsTab(event, 'backup')">
            <i class="fas fa-database"></i> Backup
        </button>
    </div>

    <div class="settings-panel active" id="settings-general">
        <div class="settings-section">
            <h3 class="settings-section-title">Store Information</h3>
            <form id="generalSettingsForm" onsubmit="saveGeneralSettings(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" value="<?php echo htmlspecialchars($settings['store_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Store Email</label>
                        <input type="email" name="store_email" value="<?php echo htmlspecialchars($settings['store_email']); ?>" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency">
                            <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                            <option value="GBP" <?php echo ($settings['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                            <option value="KES" <?php echo ($settings['currency'] ?? '') === 'KES' ? 'selected' : ''; ?>>KES (KSh)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone">
                            <option value="Africa/Nairobi" <?php echo ($settings['timezone'] ?? '') === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi</option>
                            <option value="UTC" <?php echo ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Low Stock Threshold</label>
                    <input
                        type="number"
                        name="low_stock_threshold"
                        value="<?php echo (int) ($settings['low_stock_threshold'] ?? 5); ?>"
                        min="1"
                        style="width: 150px;"
                        required
                    >
                    <p class="form-text">Products with stock at or below this level will be marked as low stock.</p>
                </div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>

    <div class="settings-panel" id="settings-users">
        <div class="settings-section">
            <div class="section-header">
                <h3 class="settings-section-title">User Management</h3>
                <button type="button" class="btn btn-primary" onclick="showModal('addUserModal')">
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
                        <?php if ($users === []): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No users found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($users as $user): ?>
                            <?php
                            $roleClass = 'badge-info';
                            if (($user['role'] ?? '') === 'admin') {
                                $roleClass = 'badge-warning';
                            } elseif (($user['role'] ?? '') === 'manager') {
                                $roleClass = 'badge-success';
                            }
                            ?>
                            <tr>
                                <td><?php echo (int) $user['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $roleClass; ?>">
                                        <?php echo ucfirst((string) ($user['role'] ?? 'staff')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($user['is_active'])): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-icons">
                                        <button
                                            type="button"
                                            class="action-icon"
                                            title="Edit"
                                            data-id="<?php echo (int) $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-full-name="<?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-email="<?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-role="<?php echo htmlspecialchars((string) ($user['role'] ?? 'staff'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-is-active="<?php echo !empty($user['is_active']) ? '1' : '0'; ?>"
                                            onclick="openEditUserModal(this)"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (($user['role'] ?? '') !== 'admin'): ?>
                                            <button
                                                type="button"
                                                class="action-icon delete"
                                                title="Delete"
                                                data-id="<?php echo (int) $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="confirmDeleteUser(this)"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <div class="settings-panel" id="settings-notifications">
        <div class="settings-section">
            <h3 class="settings-section-title">Notification Preferences</h3>
            <form id="notificationSettingsForm" onsubmit="saveNotificationSettings(event)">
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="notify_new_orders" value="1" <?php echo isAppSettingEnabled($settings, 'notify_new_orders') ? 'checked' : ''; ?>>
                        New order notifications
                    </label>
                    <label>
                        <input type="checkbox" name="notify_low_stock" value="1" <?php echo isAppSettingEnabled($settings, 'notify_low_stock') ? 'checked' : ''; ?>>
                        Low stock alerts
                    </label>
                    <label>
                        <input type="checkbox" name="notify_daily_sales_report" value="1" <?php echo isAppSettingEnabled($settings, 'notify_daily_sales_report') ? 'checked' : ''; ?>>
                        Daily sales report
                    </label>
                    <label>
                        <input type="checkbox" name="notify_weekly_summary" value="1" <?php echo isAppSettingEnabled($settings, 'notify_weekly_summary') ? 'checked' : ''; ?>>
                        Weekly summary
                    </label>
                    <label>
                        <input type="checkbox" name="notify_order_status_changes" value="1" <?php echo isAppSettingEnabled($settings, 'notify_order_status_changes') ? 'checked' : ''; ?>>
                        Order status changes
                    </label>
                    <label>
                        <input type="checkbox" name="notify_inventory_updates" value="1" <?php echo isAppSettingEnabled($settings, 'notify_inventory_updates') ? 'checked' : ''; ?>>
                        Inventory updates
                    </label>
                    <label>
                        <input type="checkbox" name="notify_user_activity" value="1" <?php echo isAppSettingEnabled($settings, 'notify_user_activity') ? 'checked' : ''; ?>>
                        User activity
                    </label>
                </div>
                <button type="submit" class="btn btn-primary mt-lg">Save Preferences</button>
            </form>
        </div>
    </div>

    <div class="settings-panel" id="settings-backup">
        <div class="settings-section">
            <h3 class="settings-section-title">Settings Backup</h3>
            <p class="text-muted mb-lg">Create and download a backup of your saved settings, or restore them from a previous backup file.</p>

            <div class="d-flex gap-md">
                <button type="button" class="btn btn-primary" onclick="createBackup()">
                    <i class="fas fa-database"></i> Create Backup
                </button>
                <button type="button" class="btn btn-outline" onclick="restoreBackup()">
                    <i class="fas fa-upload"></i> Restore Backup
                </button>
            </div>

            <input type="file" id="settingsBackupFile" accept="application/json,.json" style="display:none" onchange="handleBackupSelection(this)">

            <div class="metrics-summary mt-lg">
                <div class="metric-item">
                    <span class="metric-label">Last Backup</span>
                    <span class="metric-value" id="lastBackupValue"><?php echo htmlspecialchars($lastBackupAt); ?></span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Last Restore</span>
                    <span class="metric-value" id="lastRestoreValue"><?php echo htmlspecialchars($lastRestoredAt); ?></span>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3 class="settings-section-title">System Information</h3>
            <div class="metrics-summary">
                <div class="metric-item">
                    <span class="metric-label">PHP Version</span>
                    <span class="metric-value"><?php echo htmlspecialchars((string) phpversion()); ?></span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Database</span>
                    <span class="metric-value">PostgreSQL</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Server Time</span>
                    <span class="metric-value"><?php echo htmlspecialchars((string) date('Y-m-d H:i:s')); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button type="button" class="modal-close" onclick="hideModal('addUserModal')">&times;</button>
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
                    <input type="password" name="password" required minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit User</h3>
            <button type="button" class="modal-close" onclick="hideModal('editUserModal')">&times;</button>
        </div>
        <form id="editUserForm" onsubmit="updateUser(event)">
            <input type="hidden" name="id" id="editUserId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" id="editUsername" required>
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="editFullName" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="editEmail" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="editRole" required>
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="is_active" id="editIsActive" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" id="editPassword" minlength="6">
                    <p class="form-text">Leave blank to keep the current password.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const SETTINGS_TAB_STORAGE_KEY = 'stockflowSettingsActiveTab';

function switchSettingsTab(e, tabId) {
    document.querySelectorAll('#page-settings .settings-tab').forEach((tab) => {
        tab.classList.remove('active');
    });

    if (e && e.currentTarget) {
        e.currentTarget.classList.add('active');
    } else {
        const matchingTab = document.querySelector(`#page-settings .settings-tab[data-tab="${tabId}"]`);
        matchingTab?.classList.add('active');
    }

    document.querySelectorAll('#page-settings .settings-panel').forEach((panel) => {
        panel.classList.remove('active');
    });

    document.getElementById('settings-' + tabId)?.classList.add('active');
    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, tabId);
}

function initializeSettingsPage() {
    const savedTab = window.localStorage.getItem(SETTINGS_TAB_STORAGE_KEY) || 'general';
    switchSettingsTab(null, savedTab);
}

function postSettingsRequest(data, useFormData = false) {
    const options = {
        method: 'POST'
    };

    if (useFormData) {
        options.body = data;
    } else {
        options.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
        options.body = new URLSearchParams(data);
    }

    return fetch('admin.php', options)
        .then((response) => response.json())
        .catch(() => ({ success: false, message: 'The server returned an unexpected response.' }));
}

function saveGeneralSettings(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    data.action = 'save_settings';
    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'general');

    postSettingsRequest(data).then((result) => {
        if (result.success) {
            showNotification(result.message || 'Settings saved successfully', 'success');
        } else {
            showNotification(result.message || 'Unable to save settings.', 'error');
        }
    });
}

function saveNotificationSettings(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        action: 'save_notification_settings',
        notify_new_orders: form.notify_new_orders.checked ? '1' : '0',
        notify_low_stock: form.notify_low_stock.checked ? '1' : '0',
        notify_daily_sales_report: form.notify_daily_sales_report.checked ? '1' : '0',
        notify_weekly_summary: form.notify_weekly_summary.checked ? '1' : '0',
        notify_order_status_changes: form.notify_order_status_changes.checked ? '1' : '0',
        notify_inventory_updates: form.notify_inventory_updates.checked ? '1' : '0',
        notify_user_activity: form.notify_user_activity.checked ? '1' : '0'
    };

    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'notifications');

    postSettingsRequest(data).then((result) => {
        if (result.success) {
            showNotification(result.message || 'Notification preferences saved successfully', 'success');
        } else {
            showNotification(result.message || 'Unable to save notification preferences.', 'error');
        }
    });
}

function addUser(e) {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = 'add_user';
    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'users');

    postSettingsRequest(data).then((result) => {
        if (result.success) {
            showNotification(result.message || 'User added successfully', 'success');
            hideModal('addUserModal');
            form.reset();
            window.setTimeout(() => window.location.reload(), 700);
        } else {
            showNotification(result.message || 'Unable to add user.', 'error');
        }
    });
}

function openEditUserModal(button) {
    document.getElementById('editUserId').value = button.dataset.id || '';
    document.getElementById('editUsername').value = button.dataset.username || '';
    document.getElementById('editFullName').value = button.dataset.fullName || '';
    document.getElementById('editEmail').value = button.dataset.email || '';
    document.getElementById('editRole').value = button.dataset.role || 'staff';
    document.getElementById('editIsActive').value = button.dataset.isActive || '1';
    document.getElementById('editPassword').value = '';
    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'users');
    showModal('editUserModal');
}

function updateUser(e) {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = 'update_user';
    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'users');

    postSettingsRequest(data).then((result) => {
        if (result.success) {
            showNotification(result.message || 'User updated successfully', 'success');
            hideModal('editUserModal');
            window.setTimeout(() => window.location.reload(), 700);
        } else {
            showNotification(result.message || 'Unable to update user.', 'error');
        }
    });
}

function confirmDeleteUser(button) {
    const userId = button.dataset.id;
    const username = button.dataset.username || 'this user';

    if (!window.confirm(`Delete ${username}? The account will be marked inactive.`)) {
        return;
    }

    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'users');

    postSettingsRequest({
        action: 'delete_user',
        id: userId
    }).then((result) => {
        if (result.success) {
            showNotification(result.message || 'User deleted successfully', 'success');
            window.setTimeout(() => window.location.reload(), 700);
        } else {
            showNotification(result.message || 'Unable to delete user.', 'error');
        }
    });
}

function formatBackupTimestamp(value) {
    if (!value) {
        return 'Not available';
    }

    const parsedDate = new Date(value);
    if (Number.isNaN(parsedDate.getTime())) {
        return 'Not available';
    }

    return parsedDate.toLocaleString();
}

function createBackup() {
    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'backup');

    postSettingsRequest({ action: 'create_settings_backup' }).then((result) => {
        if (!result.success || !result.backup) {
            showNotification(result.message || 'Unable to create backup.', 'error');
            return;
        }

        const backupBlob = new Blob([JSON.stringify(result.backup, null, 2)], { type: 'application/json' });
        const downloadUrl = URL.createObjectURL(backupBlob);
        const downloadLink = document.createElement('a');
        downloadLink.href = downloadUrl;
        downloadLink.download = result.filename || 'stockflow-settings-backup.json';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        downloadLink.remove();
        URL.revokeObjectURL(downloadUrl);

        document.getElementById('lastBackupValue').textContent = formatBackupTimestamp(result.backup.generated_at);
        showNotification(result.message || 'Backup created successfully', 'success');
    });
}

function restoreBackup() {
    window.localStorage.setItem(SETTINGS_TAB_STORAGE_KEY, 'backup');
    document.getElementById('settingsBackupFile')?.click();
}

function handleBackupSelection(input) {
    const selectedFile = input.files && input.files[0];
    if (!selectedFile) {
        return;
    }

    if (!window.confirm('Restore settings from this backup file? Your current saved settings will be replaced.')) {
        input.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'restore_settings_backup');
    formData.append('backup_file', selectedFile);

    postSettingsRequest(formData, true).then((result) => {
        input.value = '';

        if (result.success) {
            if (result.settings && result.settings.last_restored_at) {
                document.getElementById('lastRestoreValue').textContent = formatBackupTimestamp(result.settings.last_restored_at);
            }

            showNotification(result.message || 'Backup restored successfully', 'success');
            window.setTimeout(() => window.location.reload(), 700);
        } else {
            showNotification(result.message || 'Unable to restore backup.', 'error');
        }
    });
}

initializeSettingsPage();
</script>
