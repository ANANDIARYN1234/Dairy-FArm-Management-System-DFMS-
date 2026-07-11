<?php
// admin/reports/sales/customer-balance.php - FIXED VERSION
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Customer Balance Report";

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

// Initialize date filters - default to today
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$errors = [];
$validation_passed = true;

// Validate dates if form is submitted
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $validation_passed = validateDates($date_from, $date_to, $errors);
}

if ($validation_passed) {
    // =========================================================
    // CUSTOMER BALANCE - REAL-TIME CALCULATION
    // =========================================================
    $sql = "SELECT 
                c.customer_id,
                c.customer_name,
                c.phone,
                c.status,
                c.advance_balance,
                COUNT(DISTINCT s.sales_id) as total_sales,
                COALESCE(SUM(s.total_amount), 0) as total_sales_amount,
                COALESCE((
                    SELECT SUM(p.amount_paid)
                    FROM payment p
                    INNER JOIN sales s2 ON p.sales_id = s2.sales_id
                    WHERE s2.customer_id = c.customer_id
                    AND s2.sales_date BETWEEN ? AND ?
                ), 0) as total_paid
            FROM customer c
            LEFT JOIN sales s ON c.customer_id = s.customer_id 
                AND s.sales_date BETWEEN ? AND ?
            GROUP BY c.customer_id, c.customer_name, c.phone, c.status, c.advance_balance
            HAVING total_sales > 0
            ORDER BY c.customer_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $date_from, $date_to, $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();

    // Store results for display and totals calculation
    $customers = [];
    $totals = [
        'total_advance' => 0,
        'total_sales' => 0,
        'total_paid' => 0,
        'total_outstanding' => 0,
        'total_due' => 0
    ];

    while ($row = $result->fetch_assoc()) {
        // Calculate derived values
        $row['outstanding_balance'] = $row['total_sales_amount'] - $row['total_paid'];
        $row['due_balance'] = max(0, $row['outstanding_balance']); // Only positive values
        
        // Add to totals
        $totals['total_advance'] += $row['advance_balance'];
        $totals['total_sales'] += $row['total_sales_amount'];
        $totals['total_paid'] += $row['total_paid'];
        $totals['total_outstanding'] += $row['outstanding_balance'];
        $totals['total_due'] += $row['due_balance'];
        
        $customers[] = $row;
    }
    
    // Sort by outstanding balance (descending)
    usort($customers, function($a, $b) {
        return $b['outstanding_balance'] <=> $a['outstanding_balance'];
    });
}

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>👥 Customer Balance Report</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Customer Balance</span>
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
                        <button type="button" onclick="resetToToday()" class="btn btn-secondary">🔄 Reset</button>
                        <!-- <button type="button" onclick="resetToThisMonth()" class="btn btn-info">📅 This Month</button> -->
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
                    <span class="stat-label">Total Sales (Selected Period)</span>
                    <span class="stat-value">रू <?php echo number_format($totals['total_sales'], 2); ?></span>
                    <small style="color: var(--text-medium);">
                        From <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?>
                    </small>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">💰</div>
                <div class="stat-details">
                    <span class="stat-label">Total Paid</span>
                    <span class="stat-value">रू <?php echo number_format($totals['total_paid'], 2); ?></span>
                    <small style="color: var(--text-medium);">
                        Actual payments received
                    </small>
                </div>
            </div>
            
            <div class="stat-card stat-danger">
                <div class="stat-icon">⚠</div>
                <div class="stat-details">
                    <span class="stat-label">Outstanding</span>
                    <span class="stat-value">रू <?php echo number_format($totals['total_outstanding'], 2); ?></span>
                    <small style="color: var(--text-medium);">
                        Sales - Payments
                    </small>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">📊</div>
                <div class="stat-details">
                    <span class="stat-label">Advance Balance</span>
                    <span class="stat-value">रू <?php echo number_format($totals['total_advance'], 2); ?></span>
                    <small style="color: var(--text-medium);">
                        Pre-paid by customers
                    </small>
                </div>
            </div>
        </div>

        <!-- Customer Balance Table -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Customer Balance Summary (<?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Total Sales</th>
                                <th>Sales Amount</th>
                                <th>Paid</th>
                                <th>Outstanding</th>
                                <th>Advance</th>
                                <th>Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($customers)): ?>
                                <?php 
                                $serial = 1;
                                foreach ($customers as $row): 
                                ?>
                                    <tr>
                                        <td><?php echo $serial++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo $row['total_sales']; ?></td>
                                        <td>रू <?php echo number_format($row['total_sales_amount'], 2); ?></td>
                                        <td class="text-success">रू <?php echo number_format($row['total_paid'], 2); ?></td>
                                        <td class="<?php echo $row['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <strong>रू <?php echo number_format($row['outstanding_balance'], 2); ?></strong>
                                        </td>
                                        <td class="text-success">रू <?php echo number_format($row['advance_balance'], 2); ?></td>
                                        <td class="text-danger">रू <?php echo number_format($row['due_balance'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                    <td colspan="4" style="text-align: right;">Total:</td>
                                    <td>रू <?php echo number_format($totals['total_sales'], 2); ?></td>
                                    <td class="text-success">रू <?php echo number_format($totals['total_paid'], 2); ?></td>
                                    <td class="text-danger">रू <?php echo number_format($totals['total_outstanding'], 2); ?></td>
                                    <td class="text-success">रू <?php echo number_format($totals['total_advance'], 2); ?></td>
                                    <td class="text-danger">रू <?php echo number_format($totals['total_due'], 2); ?></td>
                                    <td></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <span class="empty-icon">👥</span>
                                            <p>No customer data for selected period</p>
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
    
    if (diffDays > 365) {
        alert('Date range cannot exceed 1 year (365 days).');
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

function resetToToday() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date_from').value = today;
    document.getElementById('date_to').value = today;
    document.getElementById('dateFilterForm').submit();
}

function resetToThisMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('date_from').value = firstDay.toISOString().split('T')[0];
    document.getElementById('date_to').value = today.toISOString().split('T')[0];
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
$conn->close();
include '../../../includes/footer.php';
?>