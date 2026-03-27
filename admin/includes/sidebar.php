<?php
/**
 * Admin Sidebar
 * Common sidebar navigation for admin pages
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo APP_URL; ?>/admin/index.php" class="d-flex align-items-center text-decoration-none">
            <div class="sidebar-logo">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <span class="ms-2 fw-bold">Inventory System</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['index', 'dashboard']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/index.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Users Management -->
            <?php if (isAdmin() || isManager()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['users', 'users-add', 'users-edit']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Products -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['products', 'products-add', 'products-edit', 'products-view']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/products.php">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            
            <!-- Categories -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['categories', 'categories-add']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            
            <!-- Stock Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['stock', 'stock-adjustment', 'stock-transfer']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/stock.php">
                    <i class="fas fa-warehouse"></i>
                    <span>Stock Management</span>
                </a>
            </li>
            
            <!-- Orders -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['orders', 'orders-view']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>
            
            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['reports', 'reports-stock', 'reports-sales']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <!-- Analytics -->
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'analytics' ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/analytics.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
            </li>
            
            <!-- Payments / M-Pesa -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['payments', 'mpesa-transactions']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/payments.php">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Payments</span>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-divider">
                <hr>
            </li>
            
            <!-- Settings -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['settings', 'general-settings', 'security-settings']) ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <!-- System Info -->
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'system-info' ? 'active' : ''; ?>" 
                   href="<?php echo APP_URL; ?>/admin/system-info.php">
                    <i class="fas fa-server"></i>
                    <span>System Info</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle fa-2x"></i>
            <div class="user-details">
                <div class="user-name"><?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['role'] ?? 'User'); ?></div>
            </div>
        </div>
        <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-sm btn-outline-danger w-100 mt-3">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</aside>

<style>
    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        color: #fff;
        z-index: 200;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease;
    }
    
    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-logo {
        width: 40px;
        height: 40px;
        background: var(--primary-color);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 16px 0;
    }
    
    .sidebar .nav-item {
        margin: 4px 12px;
    }
    
    .sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    
    .sidebar .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }
    
    .sidebar .nav-link.active {
        background: var(--primary-color);
        color: #fff;
    }
    
    .sidebar .nav-link i {
        width: 20px;
        text-align: center;
    }
    
    .sidebar .nav-divider {
        padding: 16px 24px;
    }
    
    .sidebar .nav-divider hr {
        border-color: rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-footer {
        padding: 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .sidebar .user-info i {
        color: rgba(255, 255, 255, 0.7);
    }
    
    .sidebar .user-details .user-name {
        font-weight: 600;
        font-size: 14px;
    }
    
    .sidebar .user-details .user-role {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.5);
    }
    
    /* Mobile Styles */
    @media (max-width: 991.98px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .top-header {
            left: 0;
        }
    }
</style>

<script>
    // Toggle sidebar on mobile
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show');
    });
</script>
