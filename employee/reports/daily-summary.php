<?php
// employee/reports/daily-summary.php - Complete with Sales & Payments
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/date-validation.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Daily Work Summary";
$user_id = get_user_id();
$errors = [];

// Get selected date or default to today
$today = date('Y-m-d');
$selected_date = isset($_GET['date']) ? clean($_GET['date']) : $today;

// Validate date
$date_validation = validate_not_future_date($selected_date, 'Selected date');
if (!$date_validation['valid']) {
    $errors[] = $date_validation['error'];
    $selected_date = $today;
}

// Get milk collections for the day
$milk_sql = "SELECT mc.*, c.tag_id, ct.type_name, b.breed_name
             FROM milk_collection mc
             JOIN cattle c ON mc.cattle_id = c.cattle_id
             JOIN cattle_type ct ON c.type_id = ct.type_id
             JOIN breed b ON c.breed_id = b.breed_id
             WHERE mc.user_id = ? AND mc.collection_date = ?
             ORDER BY mc.shift, mc.created_at";
$milk_stmt = $conn->prepare($milk_sql);
$milk_stmt->bind_param("is", $user_id, $selected_date);
$milk_stmt->execute();
$milk_collections = $milk_stmt->get_result();
$milk_stmt->close();

// Get sales for the day
$sales_sql = "SELECT s.*, c.customer_name, c.customer_type
              FROM sales s
              JOIN customer c ON s.customer_id = c.customer_id
              WHERE s.user_id = ? AND s.sales_date = ?
              ORDER BY s.created_at";
$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param("is", $user_id, $selected_date);
$sales_stmt->execute();
$sales_records = $sales_stmt->get_result();
$sales_stmt->close();

// Get payments for the day
$payments_sql = "SELECT p.*, s.sales_id, c.customer_name
                 FROM payment p
                 JOIN sales s ON p.sales_id = s.sales_id
                 JOIN customer c ON s.customer_id = c.customer_id
                 WHERE p.user_id = ? AND p.payment_date = ?
                 ORDER BY p.payment_id";
$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param("is", $user_id, $selected_date);
$payments_stmt->execute();
$payment_records = $payments_stmt->get_result();
$payments_stmt->close();

// Get inventory usage for the day
$inv_sql = "SELECT it.*, i.item_name, i.category, i.unit
            FROM inventory_transaction it
            JOIN inventory i ON it.inventory_id = i.inventory_id
            WHERE it.user_id = ? AND it.transaction_date = ?
            AND it.transaction_type = 'OUT'
            ORDER BY it.transaction_id";
$inv_stmt = $conn->prepare($inv_sql);
$inv_stmt->bind_param("is", $user_id, $selected_date);
$inv_stmt->execute();
$inventory_usage = $inv_stmt->get_result();
$inv_stmt->close();

// Calculate statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ? AND collection_date = ?) as milk_count,
                (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ? AND collection_date = ?) as total_milk,
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ? AND collection_date = ? AND shift = 'Morning') as morning_count,
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ? AND collection_date = ? AND shift = 'Evening') as evening_count,
                (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ? AND collection_date = ? AND shift = 'Morning') as morning_qty,
                (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ? AND collection_date = ? AND shift = 'Evening') as evening_qty,
                (SELECT COUNT(*) FROM sales WHERE user_id = ? AND sales_date = ?) as sales_count,
                (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE user_id = ? AND sales_date = ?) as sales_amount,
                (SELECT COUNT(*) FROM payment WHERE user_id = ? AND payment_date = ?) as payment_count,
                (SELECT COALESCE(SUM(amount_paid), 0) FROM payment WHERE user_id = ? AND payment_date = ?) as payment_amount,
                (SELECT COUNT(*) FROM inventory_transaction WHERE user_id = ? AND transaction_date = ? AND transaction_type = 'OUT') as inv_count";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("isisisisisisisisisisis", 
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date,
    $user_id, $selected_date
);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$is_today = ($selected_date === $today);

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📊 Daily Work Summary</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>Daily Summary</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="reports-view.php" class="btn btn-primary no-print">← Back</a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠</span>
            <span class="alert-message">
                <?php echo implode(', ', $errors); ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Date Filter -->
    <div class="card no-print">
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Select Date</label>
                        <input type="date" name="date" class="form-control" 
                               value="<?php echo $selected_date; ?>" 
                               max="<?php echo get_max_date_today(); ?>"
                               data-max-today="true">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">📅 View Summary</button>
                        <a href="daily-summary.php" class="btn btn-secondary">📆 Today's Summary</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Header -->
    <div class="card">
        <div class="card-header" style="background: var(--accent-blue); color: white;">
            <h3>
                <?php echo $is_today ? '📅 Today\'s' : '📆'; ?> 
                Work Summary - 
                <?php echo date('l, d M Y', strtotime($selected_date)); ?>
            </h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div>
                    <strong>Date:</strong><br>
                    <?php echo date('d M Y', strtotime($selected_date)); ?>
                </div>
                <div>
                    <strong>Day Status:</strong><br>
                    <?php echo $is_today ? 'Current Day' : 'Historical Record'; ?>
                </div>
                <div>
                    <strong>Generated:</strong><br>
                    <?php echo date('h:i A'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Milk Collections</span>
                <span class="stat-value"><?php echo $stats['milk_count']; ?></span>
                <small style="color: var(--text-medium);"><?php echo number_format($stats['total_milk'], 2); ?> L</small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🛒</div>
            <div class="stat-details">
                <span class="stat-label">Sales Recorded</span>
                <span class="stat-value"><?php echo $stats['sales_count']; ?></span>
                <small style="color: var(--text-medium);">रू <?php echo number_format($stats['sales_amount'], 2); ?></small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Payments Collected</span>
                <span class="stat-value"><?php echo $stats['payment_count']; ?></span>
                <small style="color: var(--text-medium);">रू <?php echo number_format($stats['payment_amount'], 2); ?></small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📦</div>
            <div class="stat-details">
                <span class="stat-label">Inventory Usage</span>
                <span class="stat-value"><?php echo $stats['inv_count']; ?></span>
                <small style="color: var(--text-medium);">Items used</small>
            </div>
        </div>
    </div>

    <!-- Shift Status -->
    <div class="customer-details">
        <div class="card">
            <div class="card-header">
                <h3>🌅 Morning Shift</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php if ($stats['morning_count'] > 0): ?>
                            <span class="badge badge-success">✓ Completed</span>
                        <?php else: ?>
                            <span class="badge badge-warning">⏳ Not Recorded</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Collections</div>
                    <div class="detail-value"><?php echo $stats['morning_count']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Milk</div>
                    <div class="detail-value"><?php echo number_format($stats['morning_qty'], 2); ?> Liters</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>🌆 Evening Shift</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php if ($stats['evening_count'] > 0): ?>
                            <span class="badge badge-success">✓ Completed</span>
                        <?php else: ?>
                            <span class="badge badge-warning">⏳ Not Recorded</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Collections</div>
                    <div class="detail-value"><?php echo $stats['evening_count']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Milk</div>
                    <div class="detail-value"><?php echo number_format($stats['evening_qty'], 2); ?> Liters</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Milk Collections Detail -->
    <div class="card">
        <div class="card-header">
            <h3>🥛 Milk Collections (<?php echo $stats['milk_count']; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if ($milk_collections->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Time</th>
                                <th>Shift</th>
                                <th>Cattle Tag</th>
                                <th>Type / Breed</th>
                                <th>Quantity (L)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            $milk_collections->data_seek(0);
                            while ($row = $milk_collections->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['shift'] === 'Morning' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo $row['shift'] === 'Morning' ? '🌅' : '🌆'; ?> 
                                            <?php echo $row['shift']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['tag_id']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['type_name']); ?> / 
                                        <?php echo htmlspecialchars($row['breed_name']); ?>
                                    </td>
                                    <td><strong><?php echo number_format($row['quantity'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="5" style="text-align: right;">Total:</td>
                                <td><?php echo number_format($stats['total_milk'], 2); ?> L</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🥛</span>
                    <p>No milk collections recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sales Detail -->
    <div class="card">
        <div class="card-header">
            <h3>🛒 Sales Transactions (<?php echo $stats['sales_count']; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if ($sales_records->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            $sales_records->data_seek(0);
                            while ($row = $sales_records->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                    <td><?php echo get_customer_type_badge($row['customer_type']); ?></td>
                                    <td><?php echo number_format($row['total_quantity'], 2); ?> L</td>
                                    <td>रू <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status_class = ['Paid' => 'success', 'Partial' => 'warning', 'Due' => 'danger'];
                                        ?>
                                        <span class="badge badge-<?php echo $status_class[$row['sales_status']]; ?>">
                                            <?php echo $row['sales_status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="4" style="text-align: right;">Total:</td>
                                <td>रू <?php echo number_format($stats['sales_amount'], 2); ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🛒</span>
                    <p>No sales recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payments Detail -->
    <div class="card">
        <div class="card-header">
            <h3>💰 Payments Collected (<?php echo $stats['payment_count']; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if ($payment_records->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Sale ID</th>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            $payment_records->data_seek(0);
                            while ($row = $payment_records->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                    <td>#<?php echo $row['sales_id']; ?></td>
                                    <td class="text-success"><strong>रू <?php echo number_format($row['amount_paid'], 2); ?></strong></td>
                                    <td>
                                        <?php
                                        $method_class = ['Cash' => 'success', 'Bank' => 'info', 'Cheque' => 'warning', 'Digital' => 'primary'];
                                        ?>
                                        <span class="badge badge-<?php echo $method_class[$row['payment_method']] ?? 'secondary'; ?>">
                                            <?php echo $row['payment_method']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="3" style="text-align: right;">Total Collected:</td>
                                <td class="text-success">रू <?php echo number_format($stats['payment_amount'], 2); ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">💰</span>
                    <p>No payments collected</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inventory Usage Detail -->
    <div class="card">
        <div class="card-header">
            <h3>📦 Inventory Usage (<?php echo $stats['inv_count']; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if ($inventory_usage->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            $inventory_usage->data_seek(0);
                            while ($row = $inventory_usage->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $row['category']; ?></span></td>
                                    <td><?php echo number_format($row['quantity'], 2); ?> <?php echo $row['unit']; ?></td>
                                    <td><?php echo htmlspecialchars($row['remarks'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📦</span>
                    <p>No inventory usage recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Validate date
document.querySelector('form').addEventListener('submit', function(e) {
    const dateInput = document.querySelector('input[name="date"]');
    if (!validateNotFutureDate(dateInput.id || 'date', 'Selected date')) {
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
    .card { box-shadow: none; border: 1px solid var(--border-color); page-break-inside: avoid; }
}
</style>

<?php
$conn->close();
include '../../includes/footer.php';
?>