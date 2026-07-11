<?php
// employee/reports/inventory-usage.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "My Inventory Usage Report";
$user_id = get_user_id();
$errors = [];

// Date validation
$today = date('Y-m-d');
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : $today;

// Validate dates if form is submitted
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    // Validate date_from
    if (empty($date_from)) {
        $errors[] = "From date is required";
    } elseif (strtotime($date_from) > strtotime($today)) {
        $errors[] = "From date cannot be in the future";
        $date_from = date('Y-m-01'); // Auto-reset
    }

    // Validate date_to
    if (empty($date_to)) {
        $errors[] = "To date is required";
    } elseif (strtotime($date_to) > strtotime($today)) {
        $errors[] = "To date cannot be in the future";
        $date_to = $today; // Auto-reset
    }

    // Validate date range
    if (empty($errors) && strtotime($date_from) > strtotime($date_to)) {
        $errors[] = "From date cannot be later than To date";
        $date_from = date('Y-m-01'); // Auto-reset
        $date_to = $today; // Auto-reset
    }
}

// Fetch inventory usage
$sql = "SELECT it.*, i.item_name, i.category, i.unit
        FROM inventory_transaction it
        JOIN inventory i ON it.inventory_id = i.inventory_id
        WHERE it.user_id = ? AND it.transaction_date BETWEEN ? AND ?
        AND it.transaction_type = 'OUT'
        ORDER BY it.transaction_date DESC, i.item_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

// Get summary by category
$summary_sql = "SELECT i.category, i.unit,
                COUNT(it.transaction_id) as usage_count,
                SUM(it.quantity) as total_quantity
                FROM inventory_transaction it
                JOIN inventory i ON it.inventory_id = i.inventory_id
                WHERE it.user_id = ? AND it.transaction_date BETWEEN ? AND ?
                AND it.transaction_type = 'OUT'
                GROUP BY i.category, i.unit
                ORDER BY total_quantity DESC";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$summary_stmt->execute();
$summary = $summary_stmt->get_result();
$summary_stmt->close();

$total_usage = $result->num_rows;

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📦 My Inventory Usage Report</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>Inventory Usage</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="reports-view.php" class="btn btn-primary no-print">← Back</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error no-print">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Date Validation Error!</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <small style="display: block; margin-top: 0.5rem;">Dates have been automatically reset to valid values.</small>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Report Info Card -->
    <div class="card">
        <div class="card-body" style="padding: 1rem;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div>
                    <strong>Report Period:</strong><br>
                    <span style="color: var(--accent-blue);">
                        <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?>
                    </span>
                </div>
                <div>
                    <strong>Generated On:</strong><br>
                    <span style="color: var(--text-medium);">
                        <?php echo date('d M Y, h:i A'); ?>
                    </span>
                </div>
                <div>
                    <strong>Total Days:</strong><br>
                    <span style="color: var(--success);">
                        <?php 
                        $days = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1;
                        echo floor($days); ?> days
                    </span>
                </div>
            </div>
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
                               value="<?php echo $date_from; ?>" 
                               max="<?php echo $today; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo $date_to; ?>" 
                               max="<?php echo $today; ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="inventory-usage.php" class="btn btn-secondary">🔄 Reset</a>
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
                <span class="stat-label">Total Usage Transactions</span>
                <span class="stat-value"><?php echo $total_usage; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">📦</div>
            <div class="stat-details">
                <span class="stat-label">Categories Used</span>
                <span class="stat-value"><?php 
                    mysqli_data_seek($summary, 0);
                    echo mysqli_num_rows($summary); 
                ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📅</div>
            <div class="stat-details">
                <span class="stat-label">Report Period</span>
                <span class="stat-value" style="font-size: 1.2rem;">
                    <?php echo floor($days); ?> days
                </span>
            </div>
        </div>
    </div>

    <!-- Category-wise Summary -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Category-wise Summary</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category</th>
                            <th>Usage Count</th>
                            <th>Total Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($summary, 0);
                        if (mysqli_num_rows($summary) > 0):
                            $serial = 1;
                            while ($row = $summary->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $serial++; ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td><?php echo $row['usage_count']; ?></td>
                                <td><strong><?php echo number_format($row['total_quantity'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></strong></td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" class="text-center">No category data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Usage Details -->
    <div class="card">
        <div class="card-header">
            <h3>📦 Usage Details</h3>
        </div>
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['transaction_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                    <td><?php echo number_format($row['quantity'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td><?php echo htmlspecialchars($row['remarks'] ?? '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📦</span>
                    <p>No inventory usage recorded for this period</p>
                    <small style="color: var(--text-medium); display: block; margin-top: 0.5rem;">
                        Try selecting a different date range
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .page-header { margin-bottom: 1rem; }
    .card { box-shadow: none; border: 1px solid var(--border-color); page-break-inside: avoid; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('dateFilterForm');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    const today = new Date().toISOString().split('T')[0];
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = '';
            
            // Validate date_from
            if (!dateFrom.value) {
                errorMessage = 'From date is required';
                isValid = false;
            } else if (dateFrom.value > today) {
                errorMessage = 'From date cannot be in the future';
                isValid = false;
                dateFrom.value = '<?php echo date('Y-m-01'); ?>';
            }
            
            // Validate date_to
            if (!dateTo.value) {
                errorMessage = 'To date is required';
                isValid = false;
            } else if (dateTo.value > today) {
                errorMessage = 'To date cannot be in the future';
                isValid = false;
                dateTo.value = today;
            }
            
            // Validate date range
            if (isValid && dateFrom.value > dateTo.value) {
                errorMessage = 'From date cannot be later than To date';
                isValid = false;
                dateFrom.value = '<?php echo date('Y-m-01'); ?>';
                dateTo.value = today;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage + '\n\nDates have been automatically reset to valid values. Please submit again.');
                return false;
            }
        });
        
        // Real-time validation for date_from
        dateFrom.addEventListener('change', function() {
            if (this.value > today) {
                alert('From date cannot be in the future. Resetting to first day of current month.');
                this.value = '<?php echo date('Y-m-01'); ?>';
            }
            if (dateTo.value && this.value > dateTo.value) {
                alert('From date cannot be later than To date. Adjusting dates.');
                this.value = dateTo.value;
            }
        });
        
        // Real-time validation for date_to
        dateTo.addEventListener('change', function() {
            if (this.value > today) {
                alert('To date cannot be in the future. Resetting to today.');
                this.value = today;
            }
            if (dateFrom.value && this.value < dateFrom.value) {
                alert('To date cannot be earlier than From date. Adjusting dates.');
                this.value = dateFrom.value;
            }
        });
        
        // Prevent manual input of invalid dates
        dateFrom.addEventListener('blur', function() {
            if (this.value > today) {
                this.value = '<?php echo date('Y-m-01'); ?>';
            }
        });
        
        dateTo.addEventListener('blur', function() {
            if (this.value > today) {
                this.value = today;
            }
        });
    }
});
</script>

<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>