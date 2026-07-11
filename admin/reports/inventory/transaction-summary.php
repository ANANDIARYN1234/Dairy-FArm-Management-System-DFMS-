<?php
// admin/reports/inventory/transaction-summary.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Inventory Transaction Summary";

// Date filters with validation
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Validation: Check if dates are swapped
$date_error = '';
if (strtotime($date_from) > strtotime($date_to)) {
    $date_error = 'Error: "From Date" cannot be later than "To Date". Please correct the date range.';
    // Swap dates automatically
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_error = 'Invalid date format. Please use a valid date.';
    $date_from = date('Y-m-d');
    $date_to = date('Y-m-d');
}

// Fetch transaction summary with date filter
$sql = "SELECT 
            i.item_name,
            i.category,
            i.unit,
            i.current_quantity,
            COALESCE(SUM(CASE WHEN it.transaction_type = 'IN' THEN it.quantity ELSE 0 END), 0) as total_in,
            COALESCE(SUM(CASE WHEN it.transaction_type = 'OUT' THEN it.quantity ELSE 0 END), 0) as total_out,
            COUNT(it.transaction_id) as transaction_count,
            MAX(it.transaction_date) as last_transaction_date
        FROM inventory i
        LEFT JOIN inventory_transaction it ON i.inventory_id = it.inventory_id 
            AND it.transaction_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY i.inventory_id, i.item_name, i.category, i.unit, i.current_quantity
        ORDER BY i.category, i.item_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📜 Transaction Summary</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Transaction Summary</span>
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
            <form method="GET" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="transaction-summary.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
            
            <?php if ($date_error): ?>
                <div class="alert alert-error" style="margin-top: 1rem;">
                    <span class="alert-icon">⚠️</span>
                    <span><?php echo htmlspecialchars($date_error); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction Summary Table -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Inventory Transaction Summary (<?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Total IN</th>
                            <th>Total OUT</th>
                            <th>Transactions</th>
                            <th>Last Transaction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = 1;
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                    <td><strong><?php echo number_format($row['current_quantity'], 2); ?></strong> <?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td class="text-success"><?php echo number_format($row['total_in'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td class="text-danger"><?php echo number_format($row['total_out'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td><?php echo $row['transaction_count']; ?></td>
                                    <td><?php echo $row['last_transaction_date'] ? date('d M Y', strtotime($row['last_transaction_date'])) : 'No transactions'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <span class="empty-icon">📦</span>
                                        <p>No transaction data for selected date range</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Client-side date validation
document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
    const dateFrom = new Date(document.getElementById('date_from').value);
    const dateTo = new Date(document.getElementById('date_to').value);
    
    if (dateFrom > dateTo) {
        e.preventDefault();
        alert('Error: "From Date" cannot be later than "To Date". Please correct the date range.');
        return false;
    }
});
if (!isValid) {
        e.preventDefault();
    }
return isValid;
</script>

//pdf export function
<script>
    function exportPDF() {
    window.print();
}
</script>

<?php
$stmt->close();
$conn->close();
include '../../../includes/footer.php';
?>