<?php
// employee/reports/customer-analytics.php - Walk-in Customers I Added
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/date-validation.php';

checkAuth();
checkRole(['Employee']);

$page_title = "My Added Customers";
$user_id = get_user_id();

// Date filters with validation
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d');

// Validate date range
$date_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['date_from']) || isset($_GET['date_to']))) {
    $validation = validate_report_date_range($date_from, $date_to);
    if (!$validation['valid']) {
        $date_errors[] = $validation['error'];
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-d');
    }
}

// Note: We need to track who added customers
// For now, we'll show all active customers added during the date range
// TODO: Add a 'created_by' column to customer table to track who added them

// Get customers added in date range
// Since customer table doesn't have created_by field, we'll show customers
// who had their FIRST sale from this employee in the date range
$customers_sql = "SELECT 
                    c.customer_id,
                    c.customer_name,
                    c.customer_type,
                    c.phone,
                    c.address,
                    c.status,
                    MIN(s.sales_date) as first_sale_date,
                    COUNT(DISTINCT s.sales_id) as total_sales,
                    COALESCE(SUM(s.total_amount), 0) as total_purchase
                  FROM customer c
                  INNER JOIN sales s ON c.customer_id = s.customer_id
                  WHERE s.user_id = ?
                  GROUP BY c.customer_id
                  HAVING first_sale_date BETWEEN ? AND ?
                  ORDER BY first_sale_date DESC";
$customers_stmt = $conn->prepare($customers_sql);
$customers_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$customers_stmt->execute();
$customers = $customers_stmt->get_result();
$customers_stmt->close();

// Summary stats
$summary_sql = "SELECT 
                  COUNT(DISTINCT c.customer_id) as total_customers,
                  COUNT(DISTINCT CASE WHEN c.customer_type = 'Retail' THEN c.customer_id END) as retail_count,
                  COUNT(DISTINCT CASE WHEN c.customer_type = 'Wholesale' THEN c.customer_id END) as wholesale_count,
                  COUNT(DISTINCT CASE WHEN c.customer_type = 'Dairy' THEN c.customer_id END) as dairy_count
                FROM customer c
                INNER JOIN sales s ON c.customer_id = s.customer_id
                WHERE s.user_id = ?
                AND s.sales_date IN (
                    SELECT MIN(s2.sales_date) 
                    FROM sales s2 
                    WHERE s2.customer_id = c.customer_id 
                    AND s2.user_id = ?
                )
                AND s.sales_date BETWEEN ? AND ?";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("iiss", $user_id, $user_id, $date_from, $date_to);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👥 My Added Customers</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>Walk-in Customers</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="reports-view.php" class="btn btn-primary no-print">← Back</a>
        </div>
    </div>

    <!-- Date Validation Errors -->
    <?php if (!empty($date_errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Invalid Date Range!</strong>
                <ul>
                    <?php foreach ($date_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">👥</div>
            <div class="stat-details">
                <span class="stat-label">Total Customers Added</span>
                <span class="stat-value"><?php echo $summary['total_customers']; ?></span>
                <small style="color: var(--text-medium);">In selected period</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🛒</div>
            <div class="stat-details">
                <span class="stat-label">Retail Customers</span>
                <span class="stat-value"><?php echo $summary['retail_count']; ?></span>
                <small style="color: var(--text-medium);">रू 80/L pricing</small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📦</div>
            <div class="stat-details">
                <span class="stat-label">Wholesale Customers</span>
                <span class="stat-value"><?php echo $summary['wholesale_count']; ?></span>
                <small style="color: var(--text-medium);">रू 75/L pricing</small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🏭</div>
            <div class="stat-details">
                <span class="stat-label">Dairy Customers</span>
                <span class="stat-value"><?php echo $summary['dairy_count']; ?></span>
                <small style="color: var(--text-medium);">रू 70/L pricing</small>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Filter by Date Added</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" action="" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>"
                               max="<?php echo get_max_date_today(); ?>"
                               data-max-today="true">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               max="<?php echo get_max_date_today(); ?>"
                               data-max-today="true">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="customer-analytics.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer Table -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Walk-in Customers I Added (<?php echo $summary['total_customers']; ?> customers)</h3>
        </div>
        <div class="card-body">
            <?php if ($customers->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date Added</th>
                                <th>Customer Name</th>
                                <th>Type & Price</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Total Sales</th>
                                <th>Total Purchase</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $customers->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['first_sale_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                    <td>
                                        <?php echo get_customer_type_badge($row['customer_type']); ?>
                                        <br>
                                        <small style="color: var(--success); font-weight: bold;">
                                            रू <?php echo number_format(get_price_by_customer_type($row['customer_type']), 2); ?>/L
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($row['address'] ?? 'N/A', 0, 30)); ?></small>
                                        <?php if (!empty($row['address']) && strlen($row['address']) > 30): ?>
                                            <small>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo $row['total_sales']; ?></strong></td>
                                    <td class="text-success">
                                        <strong>रू <?php echo number_format($row['total_purchase'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">👥</span>
                    <p>No customers added in this period</p>
                    <a href="../customers/customer-add.php" class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Add Your First Customer
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Customer Type Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Customer Type Distribution</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="padding: 1.5rem; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border-radius: 12px; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛒</div>
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">Retail</div>
                    <div style="font-size: 2rem; font-weight: bold; margin-bottom: 0.25rem;">
                        <?php echo $summary['retail_count']; ?>
                    </div>
                    <small style="opacity: 0.8;">रू 80 per liter</small>
                </div>
                
                <div style="padding: 1.5rem; background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border-radius: 12px; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📦</div>
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">Wholesale</div>
                    <div style="font-size: 2rem; font-weight: bold; margin-bottom: 0.25rem;">
                        <?php echo $summary['wholesale_count']; ?>
                    </div>
                    <small style="opacity: 0.8;">रू 75 per liter</small>
                </div>
                
                <div style="padding: 1.5rem; background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; border-radius: 12px; text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🏭</div>
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">Dairy</div>
                    <div style="font-size: 2rem; font-weight: bold; margin-bottom: 0.25rem;">
                        <?php echo $summary['dairy_count']; ?>
                    </div>
                    <small style="opacity: 0.8;">रू 70 per liter</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="info-box">
        <strong>ℹ About This Report:</strong>
        <ul>
            <li><strong>Walk-in Customers:</strong> Shows customers you added to the system (based on first sale date)</li>
            <li><strong>Date Filter:</strong> Filter by when you first added/served the customer</li>
            <li><strong>Customer Types:</strong> Retail (रू 80/L), Wholesale (रू 75/L), Dairy (रू 70/L)</li>
            <li><strong>Total Sales:</strong> Number of transactions with each customer</li>
            <li><strong>Total Purchase:</strong> Revenue generated from each customer</li>
            <li><strong>Auto-Pricing:</strong> Price is automatically set based on customer type during sales</li>
        </ul>
    </div>

    <!-- Note about tracking -->
    <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
        <strong>📌 Note:</strong>
        <p style="margin-bottom: 0;">
            This report shows customers based on when you first made a sale for them. 
            To improve tracking, consider adding a "created_by" field to the customer table 
            to accurately track who added each customer to the system.
        </p>
    </div>
</div>

<script>
// Validate date range
document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
    if (!validateCompleteDateRange('dateFrom', 'dateTo')) {
        e.preventDefault();
        return false;
    }
});
if (!isValid) {
        e.preventDefault();
    }
return isValid;
</script>

<style>
@media print {
    .no-print { display: none !important; }
    .page-header, .breadcrumb { display: none; }
    .card { box-shadow: none; border: 1px solid var(--border-color); }
}
</style>

<?php
$conn->close();
include '../../includes/footer.php';
?>