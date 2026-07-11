<?php
// admin/reports/sales/sales-analysis.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Sales Analysis Report";

// Date validation function
function validateDates($from, $to, &$errors) {
    $today = date('Y-m-d');
    $min_date = '2000-01-01'; // Minimum acceptable date
    
    // Check if dates are provided
    if (empty($from) || empty($to)) {
        $errors[] = "Both start and end dates are required.";
        return false;
    }
    
    // Validate date format
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
    
    // Prevent future dates
    if ($from > $today) {
        $errors[] = "'From Date' cannot be in the future.";
        return false;
    }
    
    if ($to > $today) {
        $errors[] = "'To Date' cannot be in the future.";
        return false;
    }
    
    // Prevent dates too far in the past
    if ($from < $min_date) {
        $errors[] = "'From Date' cannot be before year 2000.";
        return false;
    }
    
    if ($to < $min_date) {
        $errors[] = "'To Date' cannot be before year 2000.";
        return false;
    }
    
    // Check if from_date is not after to_date (reversed dates)
    if ($from > $to) {
        $errors[] = "'From Date' cannot be after 'To Date'.";
        return false;
    }
    
    // Check maximum date range (e.g., 2 years)
    $diff = $from_date->diff($to_date);
    $days_diff = $diff->days;
    
    if ($days_diff > 730) { // 2 years
        $errors[] = "Date range cannot exceed 2 years (730 days).";
        return false;
    }
    
    return true;
}

// Initialize variables
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$errors = [];
$validation_passed = true;
$form_submitted = false;

// Validate dates if form is submitted
if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
    $form_submitted = true;
    $validation_passed = validateDates($date_from, $date_to, $errors);
}

// Only execute queries if validation passed
if ($validation_passed) {
    // Sales by type
    $by_type_sql = "SELECT 
                    sales_type,
                    COUNT(*) as total_sales,
                    COALESCE(SUM(total_quantity), 0) as total_quantity,
                    COALESCE(SUM(total_amount), 0) as total_amount,
                    COALESCE(AVG(total_amount), 0) as avg_sale_value
                    FROM sales
                    WHERE sales_date BETWEEN ? AND ?
                    GROUP BY sales_type
                    ORDER BY total_amount DESC";
    $by_type_stmt = $conn->prepare($by_type_sql);
    $by_type_stmt->bind_param("ss", $date_from, $date_to);
    $by_type_stmt->execute();
    $by_type = $by_type_stmt->get_result();

    // Sales by status
    $by_status_sql = "SELECT 
                      sales_status,
                      COUNT(*) as total_sales,
                      COALESCE(SUM(total_amount), 0) as total_amount
                      FROM sales
                      WHERE sales_date BETWEEN ? AND ?
                      GROUP BY sales_status";
    $by_status_stmt = $conn->prepare($by_status_sql);
    $by_status_stmt->bind_param("ss", $date_from, $date_to);
    $by_status_stmt->execute();
    $by_status = $by_status_stmt->get_result();

    // Top customers
    $top_customers_sql = "SELECT 
                          c.customer_name,
                          COUNT(s.sales_id) as total_sales,
                          COALESCE(SUM(s.total_amount), 0) as total_amount,
                          COALESCE(SUM(s.total_quantity), 0) as total_quantity
                          FROM sales s
                          JOIN customer c ON s.customer_id = c.customer_id
                          WHERE s.sales_date BETWEEN ? AND ?
                          GROUP BY c.customer_id
                          ORDER BY total_amount DESC
                          LIMIT 10";
    $top_customers_stmt = $conn->prepare($top_customers_sql);
    $top_customers_stmt->bind_param("ss", $date_from, $date_to);
    $top_customers_stmt->execute();
    $top_customers = $top_customers_stmt->get_result();

    // Overall stats
    $overall_sql = "SELECT 
                    COUNT(*) as total_sales,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COALESCE(SUM(total_quantity), 0) as total_quantity,
                    COALESCE(AVG(total_amount), 0) as avg_sale
                    FROM sales
                    WHERE sales_date BETWEEN ? AND ?";
    $overall_stmt = $conn->prepare($overall_sql);
    $overall_stmt->bind_param("ss", $date_from, $date_to);
    $overall_stmt->execute();
    $overall = $overall_stmt->get_result()->fetch_assoc();
}

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📊 Sales Analysis Report</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Sales Analysis</span>
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
            <?php if ($form_submitted && !empty($errors)): ?>
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
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <button type="button" onclick="resetDates()" class="btn btn-secondary">🔄 Reset</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($validation_passed): ?>
        <!-- Overall Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">📊</div>
                <div class="stat-details">
                    <span class="stat-label">Total Sales</span>
                    <span class="stat-value"><?php echo $overall['total_sales']; ?></span>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">💵</div>
                <div class="stat-details">
                    <span class="stat-label">Total Revenue</span>
                    <span class="stat-value">रू <?php echo number_format($overall['total_revenue'], 2); ?></span>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">🥛</div>
                <div class="stat-details">
                    <span class="stat-label">Quantity Sold</span>
                    <span class="stat-value"><?php echo number_format($overall['total_quantity'], 2); ?> L</span>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">📈</div>
                <div class="stat-details">
                    <span class="stat-label">Avg Sale Value</span>
                    <span class="stat-value">रू <?php echo number_format($overall['avg_sale'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="customer-details">
            <!-- By Sales Type -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 Sales by Type</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Sales Type</th>
                                    <th>Total Sales</th>
                                    <th>Quantity Sold</th>
                                    <th>Total Amount</th>
                                    <th>Avg Sale Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($by_type->num_rows > 0): ?>
                                    <?php while ($row = $by_type->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($row['sales_type']); ?></span></td>
                                            <td><?php echo $row['total_sales']; ?></td>
                                            <td><?php echo number_format($row['total_quantity'], 2); ?> L</td>
                                            <td><strong>रू <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                            <td>रू <?php echo number_format($row['avg_sale_value'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No data available for selected date range</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- By Status -->
            <div class="card">
                <div class="card-header">
                    <h3>💰 Sales by Payment Status</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Total Sales</th>
                                    <th>Total Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($by_status->num_rows > 0): ?>
                                    <?php while ($row = $by_status->fetch_assoc()): 
                                        $percentage = $overall['total_revenue'] > 0 ? ($row['total_amount'] / $overall['total_revenue']) * 100 : 0;
                                        $status_class = ['Paid' => 'success', 'Partial' => 'warning', 'Due' => 'danger'];
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo $status_class[$row['sales_status']] ?? 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($row['sales_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['total_sales']; ?></td>
                                            <td><strong>रू <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                            <td><?php echo number_format($percentage, 1); ?>%</td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No data available for selected date range</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Customers -->
            <div class="card">
                <div class="card-header">
                    <h3>🏆 Top 10 Customers</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Customer Name</th>
                                    <th>Total Sales</th>
                                    <th>Quantity</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top_customers->num_rows > 0): ?>
                                    <?php 
                                    $rank = 1;
                                    while ($row = $top_customers->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank <= 3): ?>
                                                    <span style="font-size: 1.3rem;">
                                                        <?php echo $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?php echo $rank; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                            <td><?php echo $row['total_sales']; ?></td>
                                            <td><?php echo number_format($row['total_quantity'], 2); ?> L</td>
                                            <td><strong>रू <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                        </tr>
                                    <?php 
                                    $rank++;
                                    endwhile; 
                                    ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No data available for selected date range</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
    
    // Check if dates are provided
    if (!fromDate || !toDate) {
        alert('Both dates are required.');
        e.preventDefault();
        return;
    }
    
    // Check future dates
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
    
    // Check reversed dates
    if (fromDate > toDate) {
        alert('From Date cannot be after To Date.');
        e.preventDefault();
        return;
    }
    
    // Check maximum range (730 days = 2 years)
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

// Update To Date minimum when From Date changes
document.getElementById('date_from').addEventListener('change', function() {
    document.getElementById('date_to').min = this.value;
});

// Update From Date maximum when To Date changes
document.getElementById('date_to').addEventListener('change', function() {
    document.getElementById('date_from').max = this.value;
});

function resetDates() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    // Set the date values
    document.getElementById('date_from').value = firstDay.toISOString().split('T')[0];
    document.getElementById('date_to').value = today.toISOString().split('T')[0];
    
    // Submit the form to reload with default dates
    document.getElementById('dateFilterForm').submit();
}
// //prevent Double Submission.
// if (!isValid) {
//         e.preventDefault();
//     }
// return isValid;
</script>

<?php
$conn->close();
include '../../../includes/footer.php';
?>