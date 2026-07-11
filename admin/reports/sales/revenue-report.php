<?php
// admin/reports/sales/revenue-report.php - FIXED
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Revenue Report";

// Date filters - Default to today
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Validate dates
if (strtotime($date_from) > strtotime($date_to)) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// ========================================
// FIX: Revenue summary with CORRECT JOIN
// ========================================
$revenue_sql = "SELECT 
                COUNT(DISTINCT s.sales_id) as total_sales,
                COUNT(DISTINCT s.customer_id) as unique_customers,
                COALESCE(SUM(s.total_amount), 0) as gross_revenue,
                (SELECT COALESCE(SUM(p.amount_paid), 0) 
                 FROM payment p 
                 JOIN sales s2 ON p.sales_id = s2.sales_id 
                 WHERE s2.sales_date BETWEEN ? AND ?) as cash_received
                FROM sales s
                WHERE s.sales_date BETWEEN ? AND ?";

$revenue_stmt = $conn->prepare($revenue_sql);
$revenue_stmt->bind_param("ssss", $date_from, $date_to, $date_from, $date_to);
$revenue_stmt->execute();
$revenue = $revenue_stmt->get_result()->fetch_assoc();
$revenue_stmt->close();

// Calculate outstanding
$revenue['outstanding'] = $revenue['gross_revenue'] - $revenue['cash_received'];

// ========================================
// FIX: Daily revenue trend with CORRECT aggregation
// ========================================
$daily_sql = "SELECT 
              s.sales_date,
              COUNT(DISTINCT s.sales_id) as sales_count,
              COALESCE(SUM(s.total_amount), 0) as daily_revenue,
              (SELECT COALESCE(SUM(p2.amount_paid), 0) 
               FROM payment p2 
               JOIN sales s2 ON p2.sales_id = s2.sales_id 
               WHERE s2.sales_date = s.sales_date) as daily_cash
              FROM sales s
              WHERE s.sales_date BETWEEN ? AND ?
              GROUP BY s.sales_date
              ORDER BY s.sales_date DESC
              LIMIT 30";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->bind_param("ss", $date_from, $date_to);
$daily_stmt->execute();
$daily = $daily_stmt->get_result();
$daily_stmt->close();

// Revenue by customer type
$type_sql = "SELECT 
             c.customer_type,
             COUNT(s.sales_id) as sales_count,
             COALESCE(SUM(s.total_amount), 0) as type_revenue,
             COALESCE(SUM(s.total_quantity), 0) as type_quantity
             FROM sales s
             JOIN customer c ON s.customer_id = c.customer_id
             WHERE s.sales_date BETWEEN ? AND ?
             GROUP BY c.customer_type
             ORDER BY type_revenue DESC";
$type_stmt = $conn->prepare($type_sql);
$type_stmt->bind_param("ss", $date_from, $date_to);
$type_stmt->execute();
$type_revenue = $type_stmt->get_result();
$type_stmt->close();

include '../../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>💵 Revenue Report</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Revenue Report</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary no-print">← Back</a>
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
        </div>
    </div>

    <!-- Revenue Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Gross Revenue</span>
                <span class="stat-value">रू <?php echo number_format($revenue['gross_revenue'], 2); ?></span>
                <small style="color: var(--text-medium);"><?php echo $revenue['total_sales']; ?> sales</small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">Cash Received</span>
                <span class="stat-value">रू <?php echo number_format($revenue['cash_received'], 2); ?></span>
                <small style="color: var(--text-medium);">Collected</small>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">⚠</div>
            <div class="stat-details">
                <span class="stat-label">Outstanding</span>
                <span class="stat-value">रू <?php echo number_format($revenue['outstanding'], 2); ?></span>
                <small style="color: var(--text-medium);">Pending</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">👥</div>
            <div class="stat-details">
                <span class="stat-label">Customers</span>
                <span class="stat-value"><?php echo $revenue['unique_customers']; ?></span>
                <small style="color: var(--text-medium);">Unique</small>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
     <div class="card no-print">
        <div class="card-header">
            <h3>📅 Date Filter</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <button type="button" onclick="resetFilter()" class="btn btn-secondary">🔄 Reset</button>
                    </div>
                </div>
            </form>
            
            <!-- Quick Date Buttons -->
            <!-- <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                <button onclick="setDateRange('today')" class="btn btn-sm btn-info">Today</button>
                <button onclick="setDateRange('yesterday')" class="btn btn-sm btn-info">Yesterday</button>
                <button onclick="setDateRange('week')" class="btn btn-sm btn-info">This Week</button>
                <button onclick="setDateRange('month')" class="btn btn-sm btn-info">This Month</button>
                <button onclick="setDateRange('year')" class="btn btn-sm btn-info">This Year</button>
            </div> -->
        </div>
    </div>

    <!-- Current Period Display -->
    <div class="info-box" style="background: #e3f2fd; border-color: #2196f3;">
        <strong>📊 Showing data for:</strong>
        <span style="font-size: 1.1rem; margin-left: 0.5rem;">
            <?php 
            if ($date_from === $date_to) {
                echo date('d F Y', strtotime($date_from));
            } else {
                echo date('d M Y', strtotime($date_from)) . ' to ' . date('d M Y', strtotime($date_to));
            }
            $days = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1;
            echo ' (' . $days . ' day' . ($days > 1 ? 's' : '') . ')';
            ?>
        </span>
    </div>

    <!-- Collection Rate -->
    <div class="card">
        <div class="card-header">
            <h3>📈 Collection Rate</h3>
        </div>
        <div class="card-body" style="padding: 2rem;">
            <?php 
            $collection_rate = $revenue['gross_revenue'] > 0 ? ($revenue['cash_received'] / $revenue['gross_revenue']) * 100 : 0;
            ?>
            <div style="margin-bottom: 1rem;">
                <h4 style="margin: 0; font-size: 1.5rem;">
                    Collection Rate: 
                    <span style="color: <?php echo $collection_rate >= 80 ? 'var(--success)' : ($collection_rate >= 50 ? 'var(--warning)' : 'var(--danger)'); ?>">
                        <?php echo number_format($collection_rate, 1); ?>%
                    </span>
                </h4>
                <p style="color: var(--text-medium); margin: 0.5rem 0 0 0;">
                    रू <?php echo number_format($revenue['cash_received'], 2); ?> collected out of 
                    रू <?php echo number_format($revenue['gross_revenue'], 2); ?> revenue
                </p>
            </div>
            <div style="width: 100%; height: 40px; background: var(--border-color); border-radius: 20px; overflow: hidden;">
                <div style="height: 100%; 
                            background: linear-gradient(90deg, 
                                <?php echo $collection_rate >= 80 ? 'var(--success)' : ($collection_rate >= 50 ? 'var(--warning)' : 'var(--danger)'); ?>, 
                                var(--info)); 
                            width: <?php echo min($collection_rate, 100); ?>%; 
                            display: flex; align-items: center; justify-content: center; 
                            color: white; font-weight: bold; font-size: 1.1rem;
                            transition: width 0.5s ease;">
                    <?php echo number_format($collection_rate, 1); ?>%
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue by Customer Type -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Revenue by Customer Type</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer Type</th>
                            <th>Sales Count</th>
                            <th>Quantity Sold (L)</th>
                            <th>Revenue</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($type_revenue->num_rows > 0): ?>
                            <?php while ($type = $type_revenue->fetch_assoc()): 
                                $percentage = $revenue['gross_revenue'] > 0 ? ($type['type_revenue'] / $revenue['gross_revenue']) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo get_customer_type_badge($type['customer_type']); ?></td>
                                    <td><strong><?php echo $type['sales_count']; ?></strong></td>
                                    <td><?php echo number_format($type['type_quantity'], 2); ?> L</td>
                                    <td><strong>रू <?php echo number_format($type['type_revenue'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo number_format($percentage, 1); ?>%</span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <span class="empty-icon">📊</span>
                                        <p>No sales data for selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Daily Revenue Trend -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Daily Revenue Trend</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sales Count</th>
                            <th>Daily Revenue</th>
                            <th>Cash Received</th>
                            <th>Collection %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daily->num_rows > 0): ?>
                            <?php 
                            $total_daily_revenue = 0;
                            $total_daily_cash = 0;
                            while ($row = $daily->fetch_assoc()): 
                                $daily_rate = $row['daily_revenue'] > 0 ? ($row['daily_cash'] / $row['daily_revenue']) * 100 : 0;
                                $total_daily_revenue += $row['daily_revenue'];
                                $total_daily_cash += $row['daily_cash'];
                            ?>
                                <tr>
                                    <td><?php echo date('d M Y (l)', strtotime($row['sales_date'])); ?></td>
                                    <td><strong><?php echo $row['sales_count']; ?></strong></td>
                                    <td><strong>रू <?php echo number_format($row['daily_revenue'], 2); ?></strong></td>
                                    <td class="text-success">रू <?php echo number_format($row['daily_cash'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $daily_rate >= 80 ? 'success' : ($daily_rate >= 50 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($daily_rate, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td>Total:</td>
                                <td><?php echo $revenue['total_sales']; ?></td>
                                <td>रू <?php echo number_format($total_daily_revenue, 2); ?></td>
                                <td>रू <?php echo number_format($total_daily_cash, 2); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo $total_daily_revenue > 0 ? number_format(($total_daily_cash / $total_daily_revenue) * 100, 1) : '0'; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <span class="empty-icon">📊</span>
                                        <p>No revenue data for selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Print Info -->
    <!-- <div class="info-box no-print">
        <strong>ℹ Tips:</strong>
        <ul>
            <li>Use quick date buttons for common date ranges</li>
            <li>Click "Print" to generate a printable report</li>
            <li>Collection rate shows percentage of revenue collected</li>
            <li>Outstanding amount = Gross Revenue - Cash Received</li>
        </ul>
    </div> -->
</div>

<script>
// Reset filter to today
function resetFilter() {
    const today = '<?php echo date("Y-m-d"); ?>';
    document.getElementById('dateFrom').value = today;
    document.getElementById('dateTo').value = today;
    document.getElementById('filterForm').submit();
}

// Quick date range selector
function setDateRange(range) {
    const today = new Date();
    let fromDate, toDate;
    
    switch(range) {
        case 'today':
            fromDate = toDate = today;
            break;
            
        case 'yesterday':
            fromDate = toDate = new Date(today.setDate(today.getDate() - 1));
            break;
            
        case 'week':
            toDate = new Date();
            fromDate = new Date(today.setDate(today.getDate() - 7));
            break;
            
        case 'month':
            toDate = new Date();
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            break;
            
        case 'year':
            toDate = new Date();
            fromDate = new Date(today.getFullYear(), 0, 1);
            break;
    }
    
    document.getElementById('dateFrom').value = formatDate(fromDate);
    document.getElementById('dateTo').value = formatDate(toDate);
    document.getElementById('filterForm').submit();
}

// Format date to YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Validate date range
document.getElementById('filterForm').addEventListener('submit', function(e) {
    const fromDate = new Date(document.getElementById('dateFrom').value);
    const toDate = new Date(document.getElementById('dateTo').value);
    
    if (fromDate > toDate) {
        e.preventDefault();
        alert('From Date cannot be after To Date');
        return false;
    }
});
if (!isValid) {
        e.preventDefault();
    }
return isValid;

// Print function
window.onbeforeprint = function() {
    document.title = 'Revenue Report - <?php echo date("d M Y", strtotime($date_from)); ?> to <?php echo date("d M Y", strtotime($date_to)); ?>';
};
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .page-header .header-actions {
        display: none;
    }
    
    body {
        font-size: 12pt;
    }
    
    .card {
        page-break-inside: avoid;
        margin-bottom: 1rem;
    }
    
    .stat-card {
        border: 1px solid #ddd;
    }
}
</style>
<script>
    
function exportPDF() {
    window.print();
}
</script>
<?php
$conn->close();
include '../../../includes/footer.php';
?>