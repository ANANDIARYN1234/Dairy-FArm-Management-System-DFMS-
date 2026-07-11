<?php
// admin/reports/sales/monthly-sales.php - FIXED VERSION
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Monthly Sales Report";

// Date validation function
function validateDates($from, $to, &$errors) {
    $today = date('Y-m-d');
    $min_date = '2000-01-01';
    
    if (empty($from) || empty($to)) {
        $errors[] = "Both start and end dates are required.";
        return false;
    }
    
    $from_date = DateTime::createFromFormat('Y-m-d', $from);
    $to_date = DateTime::createFromFormat('Y-m-d', $to);
    
    if (!$from_date || $from_date->format('Y-m-d') !== $from) {
        $errors[] = "Invalid 'From Date' format.";
        return false;
    }
    
    if (!$to_date || $to_date->format('Y-m-d') !== $to) {
        $errors[] = "Invalid 'To Date' format.";
        return false;
    }
    
    if ($from > $today) {
        $errors[] = "'From Date' cannot be in the future.";
        return false;
    }
    
    if ($to > $today) {
        $errors[] = "'To Date' cannot be in the future.";
        return false;
    }
    
    if ($from < $min_date) {
        $errors[] = "'From Date' cannot be before year 2000.";
        return false;
    }
    
    if ($to < $min_date) {
        $errors[] = "'To Date' cannot be before year 2000.";
        return false;
    }
    
    if ($from > $to) {
        $errors[] = "'From Date' cannot be after 'To Date'.";
        return false;
    }
    
    $diff = $from_date->diff($to_date);
    if ($diff->days > 730) {
        $errors[] = "Date range cannot exceed 2 years (730 days).";
        return false;
    }
    
    return true;
}

// Initialize date filters - default to current month
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$errors = [];
$validation_passed = true;

// Validate dates if form is submitted
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $validation_passed = validateDates($date_from, $date_to, $errors);
}

if ($validation_passed) {
    // =========================================================
    // MONTHLY SALES - SIMPLIFIED REAL-TIME CALCULATION
    // =========================================================
    $sql = "SELECT 
                DATE_FORMAT(s.sales_date, '%Y-%m') as month_year,
                s.sales_type,
                COUNT(*) as total_transactions,
                COALESCE(SUM(s.total_quantity), 0) as total_quantity_sold,
                COALESCE(SUM(s.total_amount), 0) as total_revenue,
                COALESCE(SUM(
                    (SELECT COALESCE(SUM(p.amount_paid), 0)
                     FROM payment p 
                     WHERE p.sales_id = s.sales_id)
                ), 0) as paid_amount,
                COALESCE(AVG(s.total_amount), 0) as avg_transaction_value
            FROM sales s
            WHERE s.sales_date BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(s.sales_date, '%Y-%m'), s.sales_type
            ORDER BY month_year DESC, s.sales_type";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();

    // Store results for display and totals calculation
    $monthly_data = [];
    $totals = [
        'total_revenue' => 0,
        'total_quantity' => 0,
        'total_paid' => 0,
        'total_due' => 0
    ];

    while ($row = $result->fetch_assoc()) {
        // Calculate due amount
        $row['due_amount'] = $row['total_revenue'] - $row['paid_amount'];
        
        // Add to totals
        $totals['total_revenue'] += $row['total_revenue'];
        $totals['total_quantity'] += $row['total_quantity_sold'];
        $totals['total_paid'] += $row['paid_amount'];
        $totals['total_due'] += $row['due_amount'];
        
        $monthly_data[] = $row;
    }
}

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📈 Monthly Sales Report</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Monthly Sales</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card no-print">
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>⚠️ Validation Error:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="GET" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" 
                               name="date_from" 
                               id="date_from"
                               class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>" 
                               min="2000-01-01"
                               max="<?php echo date('Y-m-d'); ?>"
                               required>
                        <!-- <small class="form-text">Cannot be in the future</small> -->
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" 
                               name="date_to" 
                               id="date_to"
                               class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>" 
                               min="2000-01-01"
                               max="<?php echo date('Y-m-d'); ?>"
                               required>
                        <!-- <small class="form-text">Cannot be in the future</small> -->
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <button type="button" onclick="resetToCurrentMonth()" class="btn btn-secondary">📅 This Month</button>
                        <button type="button" onclick="resetToToday()" class="btn btn-info">📆 Today</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($validation_passed): ?>
        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">💵</div>
                <div class="stat-details">
                    <span class="stat-label">Total Revenue (Selected Period)</span>
                    <span class="stat-value">रू <?php echo number_format($totals['total_revenue'], 2); ?></span>
                    <small style="color: var(--text-medium);">
                        From <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?>
                    </small>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">💰</div>
                <div class="stat-details">
                    <span class="stat-label">Amount Paid</span>
                    <span class="stat-value">रू <?php echo number_format($totals['total_paid'], 2); ?></span>
                    <small style="color: var(--text-medium);">
                        Actual payments received
                    </small>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">⚠</div>
                <div class="stat-details">
                    <span class="stat-label">Amount Due</span>
                    <span class="stat-value">रू <?php echo number_format($totals['total_due'], 2); ?></span>
                    <small style="color: var(--text-medium);">
                        Revenue - Paid
                    </small>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">🥛</div>
                <div class="stat-details">
                    <span class="stat-label">Quantity Sold</span>
                    <span class="stat-value"><?php echo number_format($totals['total_quantity'], 2); ?> L</span>
                    <small style="color: var(--text-medium);">
                        Total milk sold
                    </small>
                </div>
            </div>
        </div>

        <!-- Monthly Sales Table -->
        <div class="card">
            <div class="card-header">
                <h3>📊 Monthly Breakdown (<?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Type</th>
                                <th>Transactions</th>
                                <th>Quantity</th>
                                <th>Revenue</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Avg Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($monthly_data)): ?>
                                <?php foreach ($monthly_data as $row): ?>
                                    <tr>
                                        <td><?php echo date('M Y', strtotime($row['month_year'] . '-01')); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($row['sales_type']); ?></span></td>
                                        <td><?php echo $row['total_transactions']; ?></td>
                                        <td><?php echo number_format($row['total_quantity_sold'], 2); ?> L</td>
                                        <td><strong>रू <?php echo number_format($row['total_revenue'], 2); ?></strong></td>
                                        <td class="text-success">रू <?php echo number_format($row['paid_amount'], 2); ?></td>
                                        <td class="text-danger">रू <?php echo number_format($row['due_amount'], 2); ?></td>
                                        <td>रू <?php echo number_format($row['avg_transaction_value'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
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
    <?php endif; ?>
</div>

<style>
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.form-text {
    display: block;
    margin-top: 5px;
    color: #6c757d;
    font-size: 0.875rem;
}
</style>

<script>
// Client-side validation
document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
    const fromDate = document.getElementById('date_from').value;
    const toDate = document.getElementById('date_to').value;
    const today = new Date().toISOString().split('T')[0];
    
    if (!fromDate || !toDate) {
        alert('Both dates are required.');
        e.preventDefault();
        return;
    }
    
    if (fromDate > today) {
        alert('From Date cannot be in the future.');
        e.preventDefault();
        return;
    }
    
    if (toDate > today) {
        alert('To Date cannot be in the future.');
        e.preventDefault();
        return;
    }
    
    if (fromDate > toDate) {
        alert('From Date cannot be after To Date.');
        e.preventDefault();
        return;
    }
    
    const from = new Date(fromDate);
    const to = new Date(toDate);
    const diffTime = Math.abs(to - from);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > 730) {
        alert('Date range cannot exceed 2 years (730 days).');
        e.preventDefault();
        return;
    }
});

document.getElementById('date_from').addEventListener('change', function() {
    document.getElementById('date_to').min = this.value;
});

document.getElementById('date_to').addEventListener('change', function() {
    document.getElementById('date_from').max = this.value;
});

function resetToCurrentMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('date_from').value = firstDay.toISOString().split('T')[0];
    document.getElementById('date_to').value = today.toISOString().split('T')[0];
    document.getElementById('dateFilterForm').submit();
}

function resetToToday() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date_from').value = today;
    document.getElementById('date_to').value = today;
    document.getElementById('dateFilterForm').submit();
}
// if (!isValid) {
//         e.preventDefault();
//     }
// return isValid;
</script>

<script>
    function exportPDF() {
        window.print();
    }
</script>

<?php
if (isset($stmt)) $stmt->close();
$conn->close();
include '../../../includes/footer.php';
?>