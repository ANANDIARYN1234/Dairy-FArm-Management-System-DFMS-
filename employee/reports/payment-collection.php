<?php
// employee/reports/payment-collection.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Payment Collection";
$user_id = get_user_id();

// Date filters with inline validation
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d');
$date_errors = [];

// Validate dates if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['date_from']) || isset($_GET['date_to']))) {
    
    // Check date formats
    if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $date_errors[] = "Invalid 'From Date' format";
    }
    
    if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $date_errors[] = "Invalid 'To Date' format";
    }
    
    // Validate from date is not in future
    if (empty($date_errors) && strtotime($date_from) > strtotime(date('Y-m-d'))) {
        $date_errors[] = "From Date cannot be in the future";
    }
    
    // Validate to date is not in future
    if (empty($date_errors) && strtotime($date_to) > strtotime(date('Y-m-d'))) {
        $date_errors[] = "To Date cannot be in the future";
    }
    
    // Validate from date is not more than 1 year old
    $one_year_ago = strtotime('-1 year');
    if (empty($date_errors) && strtotime($date_from) < $one_year_ago) {
        $date_errors[] = "From Date cannot be older than 1 year";
    }
    
    // Validate from date <= to date
    if (empty($date_errors) && strtotime($date_from) > strtotime($date_to)) {
        $date_errors[] = "From Date cannot be later than To Date";
    }
    
    // Validate date range is not more than 1 year
    $days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
    if (empty($date_errors) && $days_diff > 365) {
        $date_errors[] = "Date range cannot exceed 365 days";
    }
    
    // Reset to defaults if there are errors
    if (!empty($date_errors)) {
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-d');
    }
}

// Fetch my payments
$payments_sql = "SELECT p.*, s.sales_id, s.sales_date, s.total_amount, 
                        c.customer_name, c.customer_type
                 FROM payment p
                 JOIN sales s ON p.sales_id = s.sales_id
                 JOIN customer c ON s.customer_id = c.customer_id
                 WHERE p.user_id = ? AND p.payment_date BETWEEN ? AND ?
                 ORDER BY p.payment_date DESC, p.payment_id DESC";
$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$payments_stmt->execute();
$payments = $payments_stmt->get_result();
$payments_stmt->close();

// Summary stats
$summary_sql = "SELECT 
                  COUNT(*) as total_payments,
                  COALESCE(SUM(amount_paid), 0) as total_collected,
                  COUNT(CASE WHEN payment_method = 'Cash' THEN 1 END) as cash_count,
                  COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount_paid ELSE 0 END), 0) as cash_amount
                FROM payment 
                WHERE user_id = ? AND payment_date BETWEEN ? AND ?";
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
            <h1>💰 My Payment Collection</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>Payments</span>
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
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Total Collected</span>
                <span class="stat-value">रू <?php echo number_format($summary['total_collected'], 2); ?></span>
                <small style="color: var(--text-medium);"><?php echo $summary['total_payments']; ?> payments</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">Cash Collected</span>
                <span class="stat-value">रू <?php echo number_format($summary['cash_amount'], 2); ?></span>
                <small style="color: var(--text-medium);"><?php echo $summary['cash_count']; ?> cash payments</small>
            </div>
        </div>
        
        <div class="stat-card stat-primary">
            <div class="stat-icon">🏦</div>
            <div class="stat-details">
                <span class="stat-label">Other Methods</span>
                <span class="stat-value">रू <?php echo number_format($summary['total_collected'] - $summary['cash_amount'], 2); ?></span>
                <small style="color: var(--text-medium);">Bank/Digital/Cheque</small>
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
                               min="<?php echo date('Y-m-d', strtotime('-1 year')); ?>">
                        <!-- <small class="form-hint">Max 1 year old, cannot be future</small> -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                        <!-- <small class="form-hint">Cannot be future date</small> -->
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="payment-collection.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Payment Records (<?php echo $summary['total_payments']; ?> payments)</h3>
        </div>
        <div class="card-body">
            <?php if ($payments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Sale Date</th>
                                <th>Sale Amount</th>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                    <td><?php echo get_customer_type_badge($row['customer_type']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['sales_date'])); ?></td>
                                    <td>रू <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="text-success">
                                        <strong>रू <?php echo number_format($row['amount_paid'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $method_class = [
                                            'Cash' => 'success',
                                            'Bank' => 'info',
                                            'Cheque' => 'warning',
                                            'Digital' => 'primary'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $method_class[$row['payment_method']] ?? 'secondary'; ?>">
                                            <?php echo $row['payment_method']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="5">Total Collected:</td>
                                <td class="text-success">रू <?php echo number_format($summary['total_collected'], 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">💰</span>
                    <p>No payments found in this period</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Method Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Payment Method Breakdown</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <?php
            // Get method-wise breakdown
            $method_sql = "SELECT 
                            payment_method,
                            COUNT(*) as count,
                            COALESCE(SUM(amount_paid), 0) as total
                           FROM payment
                           WHERE user_id = ? AND payment_date BETWEEN ? AND ?
                           GROUP BY payment_method
                           ORDER BY total DESC";
            $method_stmt = $conn->prepare($method_sql);
            $method_stmt->bind_param("iss", $user_id, $date_from, $date_to);
            $method_stmt->execute();
            $methods = $method_stmt->get_result();
            $method_stmt->close();
            ?>
            
            <?php if ($methods->num_rows > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <?php while ($method = $methods->fetch_assoc()): ?>
                        <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; text-align: center;">
                            <div style="font-size: 0.9rem; color: var(--text-medium); margin-bottom: 0.5rem;">
                                <?php echo $method['payment_method']; ?>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--success); margin-bottom: 0.25rem;">
                                रू <?php echo number_format($method['total'], 2); ?>
                            </div>
                            <small style="color: var(--text-medium);">
                                <?php echo $method['count']; ?> payment<?php echo $method['count'] > 1 ? 's' : ''; ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-medium);">No payment data available</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info -->
    <div class="info-box">
        <strong>ℹ About This Report:</strong>
        <ul>
            <li>Shows all payments you have recorded in the system</li>
            <li>Displays payment method breakdown (Cash, Bank, Cheque, Digital)</li>
            <li>Links each payment to its original sale transaction</li>
            <li>Helps track daily cash collections and other payment methods</li>
            <li><strong>Date restrictions:</strong> Cannot select future dates or dates older than 1 year</li>
        </ul>
    </div>
</div>

<script>
// Client-side date validation
document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
    const fromDate = document.getElementById('dateFrom').value;
    const toDate = document.getElementById('dateTo').value;
    const today = new Date().toISOString().split('T')[0];
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
    const minDate = oneYearAgo.toISOString().split('T')[0];
    
    let isValid = true;
    
    // Check if dates are provided
    if (!fromDate || !toDate) {
        alert('❌ Please select both From Date and To Date');
        e.preventDefault();
        return false;
    }
    
    // Check future dates
    if (fromDate > today) {
        alert('❌ From Date cannot be in the future');
        document.getElementById('dateFrom').focus();
        e.preventDefault();
        return false;
    }
    
    if (toDate > today) {
        alert('❌ To Date cannot be in the future');
        document.getElementById('dateTo').focus();
        e.preventDefault();
        return false;
    }
    
    // Check if from date is more than 1 year old
    if (fromDate < minDate) {
        alert('❌ From Date cannot be older than 1 year');
        document.getElementById('dateFrom').focus();
        e.preventDefault();
        return false;
    }
    
    // Check if from date is after to date
    if (fromDate > toDate) {
        alert('❌ From Date cannot be later than To Date');
        document.getElementById('dateFrom').focus();
        e.preventDefault();
        return false;
    }
    
    // Check if date range exceeds 365 days
    const from = new Date(fromDate);
    const to = new Date(toDate);
    const diffTime = Math.abs(to - from);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > 365) {
        alert('❌ Date range cannot exceed 365 days');
        e.preventDefault();
        return false;
    }
    
    return true;
});

// Auto-update To Date min attribute when From Date changes
document.getElementById('dateFrom').addEventListener('change', function() {
    document.getElementById('dateTo').setAttribute('min', this.value);
});

// Auto-update From Date max attribute when To Date changes
document.getElementById('dateTo').addEventListener('change', function() {
    document.getElementById('dateFrom').setAttribute('max', this.value);
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