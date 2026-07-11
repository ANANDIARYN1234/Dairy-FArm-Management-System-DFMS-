<?php
// employee/reports/my-activity-log.php - Complete with all activities
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/date-validation.php';

checkAuth();
checkRole(['Employee']);

$page_title = "My Activity Log";
$user_id = get_user_id();
$user_name = get_user_name();

// Date validation and filters
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

$activity_filter = isset($_GET['activity']) ? clean($_GET['activity']) : '';

// Fetch all activities
$activities = [];

// 1. Milk Collections
if (empty($activity_filter) || $activity_filter === 'milk') {
    $milk_sql = "SELECT 
                    mc.milk_id as id,
                    mc.collection_date as activity_date,
                    mc.created_at,
                    mc.shift,
                    mc.quantity,
                    c.tag_id,
                    ct.type_name,
                    'milk' as activity_type,
                    CONCAT(mc.shift, ' shift - Cattle: ', c.tag_id, ' - ', mc.quantity, ' L') as description
                 FROM milk_collection mc
                 JOIN cattle c ON mc.cattle_id = c.cattle_id
                 JOIN cattle_type ct ON c.type_id = ct.type_id
                 WHERE mc.user_id = ? AND mc.collection_date BETWEEN ? AND ?";
    $milk_stmt = $conn->prepare($milk_sql);
    $milk_stmt->bind_param("iss", $user_id, $date_from, $date_to);
    $milk_stmt->execute();
    $milk_result = $milk_stmt->get_result();
    while ($row = $milk_result->fetch_assoc()) {
        $activities[] = $row;
    }
    $milk_stmt->close();
}

// 2. Customer Additions (based on first sale)
if (empty($activity_filter) || $activity_filter === 'customer') {
    $customer_sql = "SELECT 
                        c.customer_id as id,
                        MIN(s.sales_date) as activity_date,
                        MIN(s.created_at) as created_at,
                        c.customer_name,
                        c.customer_type,
                        c.phone,
                        'customer' as activity_type,
                        CONCAT('Added customer: ', c.customer_name, ' (', c.customer_type, ')') as description
                     FROM customer c
                     INNER JOIN sales s ON c.customer_id = s.customer_id
                     WHERE s.user_id = ?
                     GROUP BY c.customer_id
                     HAVING MIN(s.sales_date) BETWEEN ? AND ?";
    $customer_stmt = $conn->prepare($customer_sql);
    $customer_stmt->bind_param("iss", $user_id, $date_from, $date_to);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    while ($row = $customer_result->fetch_assoc()) {
        $activities[] = $row;
    }
    $customer_stmt->close();
}

// 3. Sales Transactions
if (empty($activity_filter) || $activity_filter === 'sales') {
    $sales_sql = "SELECT 
                    s.sales_id as id,
                    s.sales_date as activity_date,
                    s.created_at,
                    s.total_quantity,
                    s.total_amount,
                    s.sales_type,
                    s.sales_status,
                    c.customer_name,
                    c.customer_type,
                    'sales' as activity_type,
                    CONCAT('Sale to ', c.customer_name, ' - ', s.total_quantity, ' L - रू ', s.total_amount) as description
                  FROM sales s
                  JOIN customer c ON s.customer_id = c.customer_id
                  WHERE s.user_id = ? AND s.sales_date BETWEEN ? AND ?";
    $sales_stmt = $conn->prepare($sales_sql);
    $sales_stmt->bind_param("iss", $user_id, $date_from, $date_to);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();
    while ($row = $sales_result->fetch_assoc()) {
        $activities[] = $row;
    }
    $sales_stmt->close();
}

// 4. Payment Collections
if (empty($activity_filter) || $activity_filter === 'payment') {
    $payment_sql = "SELECT 
                      p.payment_id as id,
                      p.payment_date as activity_date,
                      p.payment_date as created_at,
                      p.amount_paid,
                      p.payment_method,
                      c.customer_name,
                      s.sales_id,
                      s.total_amount as sale_amount,
                      'payment' as activity_type,
                      CONCAT('Payment from ', c.customer_name, ' - रू ', p.amount_paid, ' (', p.payment_method, ')') as description
                    FROM payment p
                    JOIN sales s ON p.sales_id = s.sales_id
                    JOIN customer c ON s.customer_id = c.customer_id
                    WHERE p.user_id = ? AND p.payment_date BETWEEN ? AND ?";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("iss", $user_id, $date_from, $date_to);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    while ($row = $payment_result->fetch_assoc()) {
        $activities[] = $row;
    }
    $payment_stmt->close();
}

// 5. Inventory Usage - FIXED
if (empty($activity_filter) || $activity_filter === 'inventory') {
    $inv_sql = "SELECT 
                  it.transaction_id as id,
                  it.transaction_date as activity_date,
                  it.created_at as created_at,  
                  it.transaction_type,
                  it.quantity,
                  it.remarks,
                  i.item_name,
                  i.category,
                  i.unit,
                  'inventory' as activity_type,
                  CONCAT('Used ', i.item_name, ' - ', it.quantity, ' ', i.unit) as description
                FROM inventory_transaction it
                JOIN inventory i ON it.inventory_id = i.inventory_id
                WHERE it.user_id = ? AND it.transaction_date BETWEEN ? AND ?
                AND it.transaction_type = 'OUT'";
    $inv_stmt = $conn->prepare($inv_sql);
    $inv_stmt->bind_param("iss", $user_id, $date_from, $date_to);
    $inv_stmt->execute();
    $inv_result = $inv_stmt->get_result();
    while ($row = $inv_result->fetch_assoc()) {
        $activities[] = $row;
    }
    $inv_stmt->close();
}

// Sort activities by date and time (newest first)
usort($activities, function($a, $b) {
    $date_diff = strtotime($b['activity_date']) - strtotime($a['activity_date']);
    if ($date_diff === 0) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    }
    return $date_diff;
});

// Calculate statistics
$total_activities = count($activities);
$milk_count = $customer_count = $sales_count = $payment_count = $inventory_count = 0;
$total_milk = $total_sales_amount = $total_payment_amount = 0;

foreach ($activities as $activity) {
    switch ($activity['activity_type']) {
        case 'milk':
            $milk_count++;
            $total_milk += $activity['quantity'];
            break;
        case 'customer':
            $customer_count++;
            break;
        case 'sales':
            $sales_count++;
            $total_sales_amount += $activity['total_amount'];
            break;
        case 'payment':
            $payment_count++;
            $total_payment_amount += $activity['amount_paid'];
            break;
        case 'inventory':
            $inventory_count++;
            break;
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📋 My Activity Log</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>Activity Log</span>
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
                <ul>
                    <?php foreach ($date_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Report Header -->
    <div class="card">
        <div class="card-header" style="background: var(--accent-blue); color: white;">
            <h3>👤 Activity Log - <?php echo htmlspecialchars($user_name); ?></h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div>
                    <strong>Report Period:</strong><br>
                    <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?>
                </div>
                <div>
                    <strong>Generated On:</strong><br>
                    <?php echo date('d M Y, h:i A'); ?>
                </div>
                <div>
                    <strong>Total Activities:</strong><br>
                    <?php echo $total_activities; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card no-print">
        <div class="card-body">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo $date_from; ?>" 
                               max="<?php echo get_max_date_today(); ?>"
                               data-max-today="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo $date_to; ?>" 
                               max="<?php echo get_max_date_today(); ?>"
                               data-max-today="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Activity Type</label>
                        <select name="activity" class="form-control">
                            <option value="">All Activities</option>
                            <option value="milk" <?php echo $activity_filter === 'milk' ? 'selected' : ''; ?>>🥛 Milk Collections</option>
                            <option value="customer" <?php echo $activity_filter === 'customer' ? 'selected' : ''; ?>>👥 Customer Additions</option>
                            <option value="sales" <?php echo $activity_filter === 'sales' ? 'selected' : ''; ?>>🛒 Sales</option>
                            <option value="payment" <?php echo $activity_filter === 'payment' ? 'selected' : ''; ?>>💰 Payments</option>
                            <option value="inventory" <?php echo $activity_filter === 'inventory' ? 'selected' : ''; ?>>📦 Inventory Usage</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="my-activity-log.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Activities</span>
                <span class="stat-value"><?php echo $total_activities; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Milk Collections</span>
                <span class="stat-value"><?php echo $milk_count; ?></span>
                <small style="color: var(--text-medium);"><?php echo number_format($total_milk, 2); ?> L</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">👥</div>
            <div class="stat-details">
                <span class="stat-label">Customers Added</span>
                <span class="stat-value"><?php echo $customer_count; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-primary">
            <div class="stat-icon">🛒</div>
            <div class="stat-details">
                <span class="stat-label">Sales Made</span>
                <span class="stat-value"><?php echo $sales_count; ?></span>
                <small style="color: var(--text-medium);">रू <?php echo number_format($total_sales_amount, 2); ?></small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Payments Recorded</span>
                <span class="stat-value"><?php echo $payment_count; ?></span>
                <small style="color: var(--text-medium);">रू <?php echo number_format($total_payment_amount, 2); ?></small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📦</div>
            <div class="stat-details">
                <span class="stat-label">Inventory Usage</span>
                <span class="stat-value"><?php echo $inventory_count; ?></span>
            </div>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="card">
        <div class="card-header">
            <h3>🕒 Activity Timeline</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($activities)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date & Time</th>
                                <th>Activity Type</th>
                                <th>Description</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            $current_date = '';
                            foreach ($activities as $activity): 
                                $date_display = date('d M Y', strtotime($activity['activity_date']));
                                $show_date = ($current_date !== $date_display);
                                $current_date = $date_display;
                            ?>
                                <?php if ($show_date): ?>
                                    <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                        <td colspan="5" style="padding: 0.75rem;">
                                            📅 <?php echo $date_display; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td>
                                        <?php echo date('h:i A', strtotime($activity['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'milk' => '<span class="badge badge-success">🥛 Milk</span>',
                                            'customer' => '<span class="badge badge-info">👥 Customer</span>',
                                            'sales' => '<span class="badge badge-primary">🛒 Sale</span>',
                                            'payment' => '<span class="badge badge-success">💰 Payment</span>',
                                            'inventory' => '<span class="badge badge-warning">📦 Inventory</span>'
                                        ];
                                        echo $badges[$activity['activity_type']];
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                    <td>
                                        <small>
                                        <?php if ($activity['activity_type'] === 'milk'): ?>
                                            <strong>Cattle:</strong> <?php echo htmlspecialchars($activity['tag_id']); ?><br>
                                            <strong>Type:</strong> <?php echo htmlspecialchars($activity['type_name']); ?><br>
                                            <strong>Shift:</strong> <?php echo $activity['shift']; ?><br>
                                            <strong>Qty:</strong> <?php echo number_format($activity['quantity'], 2); ?> L
                                        
                                        <?php elseif ($activity['activity_type'] === 'customer'): ?>
                                            <strong>Name:</strong> <?php echo htmlspecialchars($activity['customer_name']); ?><br>
                                            <strong>Type:</strong> <?php echo get_customer_type_badge($activity['customer_type']); ?><br>
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($activity['phone'] ?? 'N/A'); ?>
                                        
                                        <?php elseif ($activity['activity_type'] === 'sales'): ?>
                                            <strong>Customer:</strong> <?php echo htmlspecialchars($activity['customer_name']); ?><br>
                                            <strong>Type:</strong> <?php echo get_customer_type_badge($activity['customer_type']); ?><br>
                                            <strong>Quantity:</strong> <?php echo number_format($activity['total_quantity'], 2); ?> L<br>
                                            <strong>Amount:</strong> रू <?php echo number_format($activity['total_amount'], 2); ?><br>
                                            <strong>Status:</strong> 
                                            <?php
                                            $status_badges = [
                                                'Paid' => 'success',
                                                'Partial' => 'warning',
                                                'Due' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge badge-<?php echo $status_badges[$activity['sales_status']]; ?>">
                                                <?php echo $activity['sales_status']; ?>
                                            </span>
                                        
                                        <?php elseif ($activity['activity_type'] === 'payment'): ?>
                                            <strong>Customer:</strong> <?php echo htmlspecialchars($activity['customer_name']); ?><br>
                                            <strong>Sale ID:</strong> #<?php echo $activity['sales_id']; ?><br>
                                            <strong>Amount Paid:</strong> रू <?php echo number_format($activity['amount_paid'], 2); ?><br>
                                            <strong>Method:</strong> <span class="badge badge-info"><?php echo $activity['payment_method']; ?></span><br>
                                            <strong>Sale Amount:</strong> रू <?php echo number_format($activity['sale_amount'], 2); ?>
                                        
                                        <?php else: // inventory ?>
                                            <strong>Item:</strong> <?php echo htmlspecialchars($activity['item_name']); ?><br>
                                            <strong>Category:</strong> <?php echo htmlspecialchars($activity['category']); ?><br>
                                            <strong>Quantity:</strong> <?php echo number_format($activity['quantity'], 2); ?> <?php echo $activity['unit']; ?>
                                            <?php if (!empty($activity['remarks'])): ?>
                                                <br><strong>Note:</strong> <?php echo htmlspecialchars($activity['remarks']); ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📋</span>
                    <p>No activities found for the selected period</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Validate date range
document.getElementById('filterForm').addEventListener('submit', function(e) {
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
}
</style>

<?php
$conn->close();
include '../../includes/footer.php';
?>