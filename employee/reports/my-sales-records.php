<?php
// employee/reports/my-sales-records.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "My Sales Records";
$user_id = get_user_id();

// Date filters with validation
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d');

// =========================================================
// INLINE DATE VALIDATION - NO EXTERNAL FUNCTIONS NEEDED
// =========================================================
$date_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['date_from']) || isset($_GET['date_to']))) {
    $today = date('Y-m-d');
    $one_year_ago = date('Y-m-d', strtotime('-1 year'));
    
    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $date_errors[] = "Invalid 'From Date' format";
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $date_errors[] = "Invalid 'To Date' format";
    }
    
    // If format is valid, do more checks
    if (empty($date_errors)) {
        // Check if From Date is not in future
        if ($date_from > $today) {
            $date_errors[] = "From Date cannot be in the future";
        }
        
        // Check if To Date is not in future
        if ($date_to > $today) {
            $date_errors[] = "To Date cannot be in the future";
        }
        
        // Check if From Date is not older than 1 year
        if ($date_from < $one_year_ago) {
            $date_errors[] = "From Date cannot be older than 1 year";
        }
        
        // Check if From Date is before or equal to To Date
        if ($date_from > $date_to) {
            $date_errors[] = "From Date cannot be later than To Date";
        }
        
        // Check if date range is not more than 1 year
        $from_time = strtotime($date_from);
        $to_time = strtotime($date_to);
        $diff_days = ($to_time - $from_time) / (60 * 60 * 24);
        
        if ($diff_days > 365) {
            $date_errors[] = "Date range cannot exceed 1 year (365 days)";
        }
    }
    
    // If validation failed, reset to defaults
    if (!empty($date_errors)) {
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-d');
    }
}

// Fetch my sales
$sales_sql = "SELECT s.*, c.customer_name, c.customer_type, c.phone,
                     COALESCE(SUM(p.amount_paid), 0) as total_paid,
                     (s.total_amount - COALESCE(SUM(p.amount_paid), 0)) as balance
              FROM sales s
              JOIN customer c ON s.customer_id = c.customer_id
              LEFT JOIN payment p ON s.sales_id = p.sales_id
              WHERE s.user_id = ? AND s.sales_date BETWEEN ? AND ?
              GROUP BY s.sales_id
              ORDER BY s.sales_date DESC, s.sales_id DESC";
$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$sales_stmt->execute();
$sales = $sales_stmt->get_result();
$sales_stmt->close();

// Summary stats
$summary_sql = "SELECT 
                  COUNT(*) as total_sales,
                  COALESCE(SUM(total_quantity), 0) as total_quantity,
                  COALESCE(SUM(total_amount), 0) as total_amount
                FROM sales 
                WHERE user_id = ? AND sales_date BETWEEN ? AND ?";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🛒 My Sales Records</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>My Sales</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="reports-view.php" class="btn btn-primary no-print">← Back to Reports</a>
        </div>
    </div>

    <!-- Date Validation Errors -->
    <?php if (!empty($date_errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Invalid Date Range!</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($date_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <small style="display: block; margin-top: 0.5rem;">
                    Showing results for default date range instead.
                </small>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🛒</div>
            <div class="stat-details">
                <span class="stat-label">Total Sales</span>
                <span class="stat-value"><?php echo $summary['total_sales']; ?></span>
                <small style="color: var(--text-medium);">Transactions</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Quantity Sold</span>
                <span class="stat-value"><?php echo number_format($summary['total_quantity'], 2); ?> L</span>
                <small style="color: var(--text-medium);">Milk sold</small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Total Revenue</span>
                <span class="stat-value">रू <?php echo number_format($summary['total_amount'], 2); ?></span>
                <small style="color: var(--text-medium);">Generated</small>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Filter by Date</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" action="" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>"
                               max="<?php echo date('Y-m-d'); ?>"
                               min="<?php echo date('Y-m-d', strtotime('-1 year')); ?>"
                               required>
                        <!-- <small class="form-hint">Max 1 year old, cannot be future</small> -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               max="<?php echo date('Y-m-d'); ?>"
                               required>
                        <!-- <small class="form-hint">Cannot be future or before From Date</small> -->
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="my-sales-records.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Sales List (<?php echo $summary['total_sales']; ?> records)</h3>
        </div>
        <div class="card-body">
            <?php if ($sales->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['sales_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['phone'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo get_customer_type_badge($row['customer_type']); ?></td>
                                    <td><strong><?php echo number_format($row['total_quantity'], 2); ?> L</strong></td>
                                    <td>रू <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="text-success">रू <?php echo number_format($row['total_paid'], 2); ?></td>
                                    <td class="<?php echo $row['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        रू <?php echo number_format($row['balance'], 2); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'Paid' => 'success',
                                            'Partial' => 'warning',
                                            'Due' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $status_class[$row['sales_status']]; ?>">
                                            <?php echo $row['sales_status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="3">Total:</td>
                                <td><?php echo number_format($summary['total_quantity'], 2); ?> L</td>
                                <td>रू <?php echo number_format($summary['total_amount'], 2); ?></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🛒</span>
                    <p>No sales found in this period</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// =========================================================
// INLINE DATE VALIDATION JAVASCRIPT - NO EXTERNAL FUNCTIONS
// =========================================================

document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
    const fromInput = document.getElementById('dateFrom');
    const toInput = document.getElementById('dateTo');
    
    const fromDate = new Date(fromInput.value);
    const toDate = new Date(toInput.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
    oneYearAgo.setHours(0, 0, 0, 0);
    
    let isValid = true;
    let errorMessage = '';
    
    // Check if From Date is in future
    if (fromDate > today) {
        errorMessage = '❌ From Date cannot be in the future';
        isValid = false;
    }
    
    // Check if To Date is in future
    else if (toDate > today) {
        errorMessage = '❌ To Date cannot be in the future';
        isValid = false;
    }
    
    // Check if From Date is older than 1 year
    else if (fromDate < oneYearAgo) {
        errorMessage = '❌ From Date cannot be older than 1 year';
        isValid = false;
    }
    
    // Check if From Date is after To Date
    else if (fromDate > toDate) {
        errorMessage = '❌ From Date cannot be later than To Date';
        isValid = false;
    }
    
    // Check if date range exceeds 1 year
    else {
        const diffTime = Math.abs(toDate - fromDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays > 365) {
            errorMessage = '❌ Date range cannot exceed 1 year (365 days)';
            isValid = false;
        }
    }
    
    if (!isValid) {
        e.preventDefault();
        alert(errorMessage);
        return false;
    }
    
    return true;
});

// Update To Date min attribute when From Date changes
document.getElementById('dateFrom').addEventListener('change', function() {
    const toDateInput = document.getElementById('dateTo');
    toDateInput.setAttribute('min', this.value);
});

// Update From Date max attribute when To Date changes
document.getElementById('dateTo').addEventListener('change', function() {
    const fromDateInput = document.getElementById('dateFrom');
    if (this.value) {
        fromDateInput.setAttribute('max', this.value);
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const fromDate = document.getElementById('dateFrom').value;
    const toDate = document.getElementById('dateTo').value;
    
    if (fromDate) {
        document.getElementById('dateTo').setAttribute('min', fromDate);
    }
    
    if (toDate) {
        document.getElementById('dateFrom').setAttribute('max', toDate);
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