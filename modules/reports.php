<?php
/**
 * Reports Module
 * Reports and analytics functionality
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
if (basename($_SERVER['PHP_SELF']) == 'reports.php') {
    header('Location: ../admin.php');
    exit;
}

// Date range handling
$range = $_GET['range'] ?? 'week';
$customStart = $_GET['start'] ?? null;
$customEnd = $_GET['end'] ?? null;
$dateExpr = "COALESCE(o.order_date, o.created_at)";
$dateFilterSql = '';
$dateParams = [];

switch ($range) {
    case 'today':
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        $dateFilterSql = "$dateExpr BETWEEN ? AND ?";
        $dateParams = [$start, $end];
        break;
    case 'month':
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-t 23:59:59');
        $dateFilterSql = "$dateExpr BETWEEN ? AND ?";
        $dateParams = [$start, $end];
        break;
    case 'year':
        $start = date('Y-01-01 00:00:00');
        $end = date('Y-12-31 23:59:59');
        $dateFilterSql = "$dateExpr BETWEEN ? AND ?";
        $dateParams = [$start, $end];
        break;
    case 'custom':
        if ($customStart && $customEnd) {
            $start = $customStart . ' 00:00:00';
            $end = $customEnd . ' 23:59:59';
            $dateFilterSql = "$dateExpr BETWEEN ? AND ?";
            $dateParams = [$start, $end];
        }
        break;
    case 'week':
    default:
        $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $end = date('Y-m-d 23:59:59');
        $dateFilterSql = "$dateExpr BETWEEN ? AND ?";
        $dateParams = [$start, $end];
        $range = 'week';
        break;
}

$selectedReport = $_GET['report'] ?? null;
$export = $_GET['export'] ?? null;

function fetchReportData(PDO $pdo, string $type, string $dateExpr, string $dateFilterSql, array $dateParams): array
{
    $headers = [];
    $rows = [];
    static $productColumns = null;

    if ($productColumns === null) {
        $productColumns = [];
        try {
            $colStmt = $pdo->query("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = 'products'
            ");
            foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
                $productColumns[$col] = true;
            }
        } catch (PDOException $e) {
            $productColumns = [];
        }
    }

    if ($type === 'sales') {
        $headers = ['Date', 'Orders', 'Total Sales'];
        $sql = "
            SELECT DATE($dateExpr) as day, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as total
            FROM orders o
            WHERE o.payment_status IN ('paid', 'completed')
        ";
        if ($dateFilterSql) {
            $sql .= " AND $dateFilterSql";
        }
        $sql .= " GROUP BY DATE($dateExpr) ORDER BY day";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dateParams);
        $rows = $stmt->fetchAll();
    } elseif ($type === 'inventory') {
        $hasName = isset($productColumns['name']);
        $hasCategory = isset($productColumns['category']);
        $hasCategoryId = isset($productColumns['category_id']);
        $hasType = isset($productColumns['type']);
        $hasQuantity = isset($productColumns['quantity']);
        $hasStockQuantity = isset($productColumns['stock_quantity']);
        $hasReorderLevel = isset($productColumns['reorder_level']);
        $hasSafetyStock = isset($productColumns['safety_stock']);
        $hasIsActive = isset($productColumns['is_active']);

        $nameSelect = $hasName ? 'p.name' : 'p.id::text';
        if ($hasCategory) {
            $categorySelect = 'p.category';
            $categoryHeader = 'Category';
        } elseif ($hasCategoryId) {
            $categorySelect = 'p.category_id::text';
            $categoryHeader = 'Category ID';
        } elseif ($hasType) {
            $categorySelect = 'p.type';
            $categoryHeader = 'Type';
        } else {
            $categorySelect = "''";
            $categoryHeader = 'Category';
        }

        if ($hasQuantity) {
            $quantitySelect = 'p.quantity';
        } elseif ($hasStockQuantity) {
            $quantitySelect = 'p.stock_quantity';
        } elseif ($hasSafetyStock) {
            $quantitySelect = 'p.safety_stock';
        } else {
            $quantitySelect = '0';
        }

        if ($hasReorderLevel) {
            $reorderSelect = 'p.reorder_level';
        } elseif ($hasSafetyStock) {
            $reorderSelect = 'p.safety_stock';
        } else {
            $reorderSelect = '0';
        }

        $headers = ['Product', $categoryHeader, 'Quantity', 'Reorder Level'];

        $sql = "
            SELECT
                {$nameSelect} as name,
                {$categorySelect} as category,
                {$quantitySelect} as quantity,
                {$reorderSelect} as reorder_level
            FROM products p
        ";
        if ($hasIsActive) {
            $sql .= " WHERE p.is_active = true";
        }
        $sql .= " ORDER BY quantity ASC";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();
    } elseif ($type === 'products') {
        $headers = ['Product', 'Units Sold', 'Revenue'];
        $sql = "
            SELECT p.name,
                   COALESCE(SUM(oi.quantity), 0) as units_sold,
                   COALESCE(SUM(oi.subtotal), 0) as revenue
            FROM products p
            LEFT JOIN order_items oi ON oi.product_id = p.id
            LEFT JOIN orders o ON o.id = oi.order_id
            WHERE 1=1
        ";
        if ($dateFilterSql) {
            $sql .= " AND ($dateFilterSql)";
        }
        $sql .= " GROUP BY p.id ORDER BY units_sold DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dateParams);
        $rows = $stmt->fetchAll();
    } elseif ($type === 'customers') {
        $headers = ['Customer', 'Email', 'Orders', 'Total Spent'];
        $sql = "
            SELECT u.full_name, u.email,
                   COUNT(o.id) as orders,
                   COALESCE(SUM(o.total_amount), 0) as spent
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id AND o.payment_status IN ('paid', 'completed')
        ";
        if ($dateFilterSql) {
            $sql .= " AND ($dateFilterSql)";
        }
        $sql .= " GROUP BY u.id ORDER BY spent DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dateParams);
        $rows = $stmt->fetchAll();
    }

    return ['headers' => $headers, 'rows' => $rows];
}

// Export is handled via reports_export.php

// Get report data
$report_data = [
    'total_sales' => 0,
    'total_orders' => 0,
    'average_order' => 0,
    'top_product' => ''
];

try {
    // Total sales
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders o WHERE o.payment_status IN ('paid','completed')";
    if ($dateFilterSql) {
        $sql .= " AND $dateFilterSql";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $report_data['total_sales'] = $stmt->fetch()['total'] ?? 0;
    
    // Total orders
    $sql = "SELECT COUNT(*) as count FROM orders o";
    if ($dateFilterSql) {
        $sql .= " WHERE $dateFilterSql";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $report_data['total_orders'] = $stmt->fetch()['count'] ?? 0;
    
    // Average order value
    if ($report_data['total_orders'] > 0) {
        $report_data['average_order'] = $report_data['total_sales'] / $report_data['total_orders'];
    }
    
    // Top selling product
    $stmt = $pdo->query("
        SELECT p.name, SUM(oi.quantity) as total_sold
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 1
    ");
    $topProduct = $stmt->fetch();
    $report_data['top_product'] = $topProduct['name'] ?? 'N/A';
    
} catch (PDOException $e) {}

// Get sales by day for chart
$sales_by_day = [];
try {
    $sql = "
        SELECT DATE($dateExpr) as day, SUM(total_amount) as sales
        FROM orders o
        WHERE o.payment_status IN ('paid','completed')
    ";
    if ($dateFilterSql) {
        $sql .= " AND $dateFilterSql";
    }
    $sql .= " GROUP BY DATE($dateExpr) ORDER BY day";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $sales_by_day = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<!-- Reports Page -->
<div class="page-section active" id="page-reports">
    <!-- Report Header -->
    <div class="reports-header">
        <h2 class="page-title">Reports & Analytics</h2>
        <div class="date-range-picker">
            <button class="btn btn-sm btn-outline <?php echo $range === 'today' ? 'active' : ''; ?>" onclick="setDateRange('today')">Today</button>
            <button class="btn btn-sm btn-outline <?php echo $range === 'week' ? 'active' : ''; ?>" onclick="setDateRange('week')">This Week</button>
            <button class="btn btn-sm btn-outline <?php echo $range === 'month' ? 'active' : ''; ?>" onclick="setDateRange('month')">This Month</button>
            <button class="btn btn-sm btn-outline <?php echo $range === 'year' ? 'active' : ''; ?>" onclick="setDateRange('year')">This Year</button>
            <button class="btn btn-sm btn-outline" onclick="showModal('customRangeModal')">
                <i class="fas fa-calendar"></i> Custom
            </button>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="dashboard-stats" style="grid-template-columns: repeat(4, 1fr);">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-content">
                <div class="stat-value">$<?php echo number_format($report_data['total_sales'], 2); ?></div>
                <div class="stat-label">Total Sales</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($report_data['total_orders']); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-chart-line"></i></div>
            <div class="stat-content">
                <div class="stat-value">$<?php echo number_format($report_data['average_order'], 2); ?></div>
                <div class="stat-label">Average Order</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="fas fa-star"></i></div>
            <div class="stat-content">
                <div class="stat-value" style="font-size: 18px;"><?php echo htmlspecialchars($report_data['top_product']); ?></div>
                <div class="stat-label">Top Product</div>
            </div>
        </div>
    </div>
    
    <!-- Report Cards -->
    <div class="report-cards">
        <div class="report-card" onclick="generateReport('sales')">
            <div class="report-icon sales"><i class="fas fa-chart-line"></i></div>
            <div class="report-info">
                <h4>Sales Report</h4>
                <p>View sales analytics and trends</p>
                <span class="report-link">Generate <i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
        <div class="report-card" onclick="generateReport('inventory')">
            <div class="report-icon inventory"><i class="fas fa-warehouse"></i></div>
            <div class="report-info">
                <h4>Inventory Report</h4>
                <p>Stock levels and movements</p>
                <span class="report-link">Generate <i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
        <div class="report-card" onclick="generateReport('products')">
            <div class="report-icon products"><i class="fas fa-box"></i></div>
            <div class="report-info">
                <h4>Product Performance</h4>
                <p>Product sales and analytics</p>
                <span class="report-link">Generate <i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
        <div class="report-card" onclick="generateReport('customers')">
            <div class="report-icon customers"><i class="fas fa-users"></i></div>
            <div class="report-info">
                <h4>Customer Report</h4>
                <p>Customer analytics</p>
                <span class="report-link">Generate <i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
    </div>
    
    <?php if ($selectedReport): ?>
    <?php $reportTable = fetchReportData($pdo, $selectedReport, $dateExpr, $dateFilterSql, $dateParams); ?>
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3 class="card-title"><?php echo ucfirst($selectedReport); ?> Report</h3>
        </div>
        <div class="card-body table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php foreach ($reportTable['headers'] as $header): ?>
                            <th><?php echo htmlspecialchars($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportTable['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?php echo htmlspecialchars((string)$cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reportTable['rows'])): ?>
                    <tr><td colspan="<?php echo count($reportTable['headers']); ?>" style="text-align:center;color:var(--text-muted);">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Sales Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Sales Trend (Last 7 Days)</h3>
        </div>
        <div class="chart-container">
            <div class="chart-area">
                <?php 
                $max_sales = 1;
                foreach ($sales_by_day as $day) {
                    if ($day['sales'] > $max_sales) $max_sales = $day['sales'];
                }
                foreach ($sales_by_day as $day): 
                    $height = $max_sales > 0 ? ($day['sales'] / $max_sales) * 100 : 0;
                ?>
                <div class="chart-bar" style="height: <?php echo $height; ?>%;" data-value="$<?php echo number_format($day['sales'], 0); ?>"></div>
                <?php endforeach; ?>
                
                <?php if (empty($sales_by_day)): ?>
                <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No sales data available</p>
                <?php endif; ?>
            </div>
            <div class="chart-labels">
                <?php foreach ($sales_by_day as $day): ?>
                <span class="chart-label"><?php echo date('M d', strtotime($day['day'])); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Export Section -->
    <div class="export-section">
        <h4><i class="fas fa-download"></i> Export Reports</h4>
        <div class="export-buttons">
            <button class="btn btn-outline" onclick="exportReport('pdf')">
                <i class="fas fa-file-pdf"></i> Export as PDF
            </button>
            <button class="btn btn-outline" onclick="exportReport('excel')">
                <i class="fas fa-file-excel"></i> Export as Excel
            </button>
            <button class="btn btn-outline" onclick="exportReport('csv')">
                <i class="fas fa-file-csv"></i> Export as CSV
            </button>
        </div>
    </div>
</div>

<!-- Custom Date Range Modal -->
<div class="modal-overlay" id="customRangeModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Custom Date Range</h3>
            <button class="modal-close" onclick="hideModal('customRangeModal')">&times;</button>
        </div>
        <form onsubmit="applyCustomRange(event)">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" id="rangeStartDate" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" id="rangeEndDate" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('customRangeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
// Set date range
function setDateRange(range) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', 'reports');
    params.set('range', range);
    params.delete('start');
    params.delete('end');
    window.location.search = params.toString();
}

// Generate report
function generateReport(type) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', 'reports');
    params.set('report', type);
    if (!params.get('range')) params.set('range', 'week');
    window.location.search = params.toString();
}

// Export report
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    const report = params.get('report') || 'sales';
    const range = params.get('range') || 'week';
    const start = params.get('start') || '';
    const end = params.get('end') || '';
    const exportParams = new URLSearchParams({
        report: report,
        range: range,
        export: format
    });
    if (start && end) {
        exportParams.set('start', start);
        exportParams.set('end', end);
    }
    const url = 'reports_export.php?' + exportParams.toString();
    if (format === 'pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}

// Apply custom date range
function applyCustomRange(e) {
    e.preventDefault();
    const start = document.getElementById('rangeStartDate').value;
    const end = document.getElementById('rangeEndDate').value;
    
    if (start && end) {
        const params = new URLSearchParams(window.location.search);
        params.set('page', 'reports');
        params.set('range', 'custom');
        params.set('start', start);
        params.set('end', end);
        window.location.search = params.toString();
    }
}
</script>

