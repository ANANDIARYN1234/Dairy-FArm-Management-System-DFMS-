<?php
// employee/reports/milk-wastage.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/date-validation.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Milk Wastage Report";

// Date filters with validation
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d');

// Validate date range if form submitted
$date_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['date_from']) || isset($_GET['date_to']))) {
    $validation = validate_report_date_range($date_from, $date_to);
    if (!$validation['valid']) {
        $date_errors[] = $validation['error'];
        // Reset to default if invalid
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-d');
    }
}

// Fetch wastage data
$wastage_sql = "SELECT * FROM milk_wastage 
                WHERE collection_date BETWEEN ? AND ?
                ORDER BY collection_date DESC, shift DESC";
$wastage_stmt = $conn->prepare($wastage_sql);
$wastage_stmt->bind_param("ss", $date_from, $date_to);
$wastage_stmt->execute();
$wastage_records = $wastage_stmt->get_result();
$wastage_stmt->close();

// Get summary
$summary_sql = "SELECT 
                  COUNT(*) as total_records,
                  SUM(wasted_quantity) as total_wasted,
                  SUM(estimated_loss_retail) as total_loss
                FROM milk_wastage 
                WHERE collection_date BETWEEN ? AND ?";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("ss", $date_from, $date_to);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🗑️ Milk Wastage Report</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>Wastage</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="reports-view.php" class="btn btn-primary no-print">← Back</a>
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

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-danger">
            <div class="stat-icon">🗑️</div>
            <div class="stat-details">
                <span class="stat-label">Expired Records</span>
                <span class="stat-value"><?php echo $summary['total_records']; ?></span>
                <small style="color: var(--text-medium);">Milk batches</small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Wasted</span>
                <span class="stat-value"><?php echo number_format($summary['total_wasted'], 2); ?> L</span>
                <small style="color: var(--text-medium);">Not sold in time</small>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">💸</div>
            <div class="stat-details">
                <span class="stat-label">Estimated Loss</span>
                <span class="stat-value">रू <?php echo number_format($summary['total_loss'], 2); ?></span>
                <small style="color: var(--text-medium);">@ Retail price</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">⏱️</div>
            <div class="stat-details">
                <span class="stat-label">Shelf Life</span>
                <span class="stat-value">24 Hours</span>
                <small style="color: var(--text-medium);">From collection</small>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Filter by Date Range</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" action="" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>"
                               max="<?php echo get_max_date_today(); ?>"
                               data-max-today="true">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               max="<?php echo get_max_date_today(); ?>"
                               data-max-today="true">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="milk-wastage.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Wastage Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Expired Milk Records</h3>
        </div>
        <div class="card-body">
            <?php if ($wastage_records->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Collection Date</th>
                                <th>Shift</th>
                                <th>Cattle Tag</th>
                                <th>Type/Breed</th>
                                <th>Total Quantity</th>
                                <th>Sold</th>
                                <th>Wasted</th>
                                <th>Hours Old</th>
                                <th>Est. Loss (रू)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $wastage_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['collection_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['shift'] === 'Morning' ? 'info' : 'warning'; ?>">
                                            <?php echo $row['shift']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['tag_id']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['type_name']); ?> / 
                                        <?php echo htmlspecialchars($row['breed_name']); ?>
                                    </td>
                                    <td><?php echo number_format($row['total_quantity'], 2); ?> L</td>
                                    <td class="text-success"><?php echo number_format($row['sold_quantity'], 2); ?> L</td>
                                    <td class="text-danger">
                                        <strong><?php echo number_format($row['wasted_quantity'], 2); ?> L</strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-danger">
                                            🔴 <?php echo $row['hours_since_collection']; ?>h
                                        </span>
                                    </td>
                                    <td class="text-danger">
                                        <strong>रू <?php echo number_format($row['estimated_loss_retail'], 2); ?></strong>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="6">Total:</td>
                                <td class="text-danger"><?php echo number_format($summary['total_wasted'], 2); ?> L</td>
                                <td></td>
                                <td class="text-danger">रू <?php echo number_format($summary['total_loss'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">✅</span>
                    <p>No wastage recorded in this period</p>
                    <small style="color: var(--text-medium);">All milk was sold within 24 hours!</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prevention Tips -->
    <div class="info-box">
        <strong>💡 Prevention Tips:</strong>
        <ul>
            <li><strong>Sell quickly:</strong> Prioritize older milk (12+ hours) in sales</li>
            <li><strong>Monitor daily:</strong> Check available milk at start and end of day</li>
            <li><strong>Adjust pricing:</strong> Consider discounts for milk nearing 24-hour mark</li>
            <li><strong>Plan production:</strong> Reduce collection if sales are low</li>
            <li><strong>Alternative uses:</strong> Convert near-expiry milk to products (yogurt, cheese)</li>
        </ul>
    </div>
</div>

<script>
// Validate date range before form submission
document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
    if (!validateCompleteDateRange('dateFrom', 'dateTo')) {
        e.preventDefault();
        return false;
    }
});
//
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