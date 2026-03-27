<?php
// admin/reports.php
require_once 'auth.php';
$adminAuth->requireLogin();
require_once '../config/database.php';

$range = $_GET['range'] ?? 'week';
$customStart = $_GET['start'] ?? null;
$customEnd = $_GET['end'] ?? null;
$reportType = $_GET['report'] ?? null;
$export = $_GET['export'] ?? null;
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

if ($export && $reportType) {
    $report = fetchReportData($pdo, $reportType, $dateExpr, $dateFilterSql, $dateParams);
    $filename = $reportType . '_report_' . date('Ymd');

    if ($export === 'csv' || $export === 'excel') {
        $contentType = $export === 'excel' ? 'application/vnd.ms-excel' : 'text/csv';
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $report['headers']);
        foreach ($report['rows'] as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    if ($export === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><html><head><title>Report</title>
              <style>body{font-family:Arial} table{border-collapse:collapse;width:100%;}
              th,td{border:1px solid #ccc;padding:8px;text-align:left;}
              th{background:#f5f5f5}</style></head><body>";
        echo "<h2>" . ucfirst($reportType) . " Report</h2>";
        echo "<table><thead><tr>";
        foreach ($report['headers'] as $h) {
            echo "<th>" . htmlspecialchars($h) . "</th>";
        }
        echo "</tr></thead><tbody>";
        foreach ($report['rows'] as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars((string)$cell) . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table><script>window.onload=function(){window.print();}</script></body></html>";
        exit;
    }
}

$ordersSql = "SELECT COUNT(*) FROM orders o";
if ($dateFilterSql) {
    $ordersSql .= " WHERE $dateFilterSql";
}
$stmt = $pdo->prepare($ordersSql);
$stmt->execute($dateParams);
$totalOrders = (int)$stmt->fetchColumn();

$paidSql = "SELECT COUNT(*) FROM orders o WHERE o.payment_status IN ('paid', 'completed')";
if ($dateFilterSql) {
    $paidSql .= " AND $dateFilterSql";
}
$stmt = $pdo->prepare($paidSql);
$stmt->execute($dateParams);
$paidOrders = (int)$stmt->fetchColumn();

$revenueSql = "SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.payment_status IN ('paid', 'completed')";
if ($dateFilterSql) {
    $revenueSql .= " AND $dateFilterSql";
}
$stmt = $pdo->prepare($revenueSql);
$stmt->execute($dateParams);
$revenue = (float)$stmt->fetchColumn();

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

$summary = [
    'total_orders' => $totalOrders,
    'paid_orders' => $paidOrders,
    'revenue' => $revenue,
    'total_users' => $totalUsers,
    'total_products' => $totalProducts
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
                        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="products.php"><i class="bi bi-box"></i> Products</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php"><i class="bi bi-cart"></i> Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-people"></i> Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="payments.php"><i class="bi bi-credit-card"></i> Payments</a></li>
                        <li class="nav-item"><a class="nav-link active" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <li class="nav-item mt-4"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reports</h1>
                    <div class="btn-group">
                        <a class="btn btn-sm btn-outline-secondary <?php echo $range === 'today' ? 'active' : ''; ?>" href="?range=today">Today</a>
                        <a class="btn btn-sm btn-outline-secondary <?php echo $range === 'week' ? 'active' : ''; ?>" href="?range=week">This Week</a>
                        <a class="btn btn-sm btn-outline-secondary <?php echo $range === 'month' ? 'active' : ''; ?>" href="?range=month">This Month</a>
                        <a class="btn btn-sm btn-outline-secondary <?php echo $range === 'year' ? 'active' : ''; ?>" href="?range=year">This Year</a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Total Orders</h6>
                                <h3><?= $summary['total_orders'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Paid Orders</h6>
                                <h3><?= $summary['paid_orders'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Total Revenue</h6>
                                <h3>$<?= number_format($summary['revenue'], 2) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Total Users</h6>
                                <h3><?= $summary['total_users'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Total Products</h6>
                                <h3><?= $summary['total_products'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-primary" href="?report=sales&range=<?= htmlspecialchars($range) ?>">Create Sales Report</a>
                            <a class="btn btn-outline-primary" href="?report=inventory&range=<?= htmlspecialchars($range) ?>">Inventory Report</a>
                            <a class="btn btn-outline-primary" href="?report=products&range=<?= htmlspecialchars($range) ?>">Product Performance</a>
                            <a class="btn btn-outline-primary" href="?report=customers&range=<?= htmlspecialchars($range) ?>">Customer Report</a>
                        </div>
                    </div>
                </div>

                <?php if ($reportType): ?>
                <?php $reportTable = fetchReportData($pdo, $reportType, $dateExpr, $dateFilterSql, $dateParams); ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= ucfirst($reportType) ?> Report</h5>
                        <div class="btn-group">
                            <a class="btn btn-sm btn-outline-success" href="?report=<?= htmlspecialchars($reportType) ?>&range=<?= htmlspecialchars($range) ?>&export=csv">Export CSV</a>
                            <a class="btn btn-sm btn-outline-success" href="?report=<?= htmlspecialchars($reportType) ?>&range=<?= htmlspecialchars($range) ?>&export=excel">Export Excel</a>
                            <a class="btn btn-sm btn-outline-success" href="?report=<?= htmlspecialchars($reportType) ?>&range=<?= htmlspecialchars($range) ?>&export=pdf">Export PDF</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <?php foreach ($reportTable['headers'] as $header): ?>
                                            <th><?= htmlspecialchars($header) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportTable['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td><?= htmlspecialchars((string)$cell) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($reportTable['rows'])): ?>
                                    <tr><td colspan="<?= count($reportTable['headers']) ?>" class="text-center text-muted">No data available</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
