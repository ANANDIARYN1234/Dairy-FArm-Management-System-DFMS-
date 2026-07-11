<?php
// admin/reports/milk/milk-wastage.php - FIXED VERSION
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Milk Wastage Report";

// Date filters with validation
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d');

// Validate dates
$errors = [];

if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $errors[] = "Invalid 'From Date' format";
    $date_from = date('Y-m-01');
}

if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $errors[] = "Invalid 'To Date' format";
    $date_to = date('Y-m-d');
}

if (strtotime($date_from) > strtotime($date_to)) {
    $errors[] = "'From Date' cannot be after 'To Date'";
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-d');
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
                  COALESCE(SUM(wasted_quantity), 0) as total_wasted,
                  COALESCE(SUM(estimated_loss_retail), 0) as total_loss
                FROM milk_wastage 
                WHERE collection_date BETWEEN ? AND ?";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("ss", $date_from, $date_to);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

include '../../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🗑️ Milk Wastage Report</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Milk Wastage</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="../reports-dashboard.php" class="btn btn-secondary">← Back to Reports</a>
            <!-- <button onclick="printReport()" class="btn btn-info no-print">🖨 Print</button> -->
            <!-- <button onclick="exportPDF()" class="btn btn-primary no-print">📄 Export PDF</button> -->
        </div>
    </div>

    <!-- Validation Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Validation Error!</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
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
                <span class="stat-value"><?php echo number_format($summary['total_wasted'] ?? 0, 2); ?> L</span>
                <small style="color: var(--text-medium);">Not sold in time</small>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">💸</div>
            <div class="stat-details">
                <span class="stat-label">Estimated Loss</span>
                <span class="stat-value">रू <?php echo number_format($summary['total_loss'] ?? 0, 2); ?></span>
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
            <form method="GET" action="" id="filterForm" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>"
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="filterBtn" class="btn btn-primary">
                            🔍 Filter
                        </button>
                        <a href="milk-wastage.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
                <div id="dateError" class="error-msg" style="color: var(--danger); margin-top: 0.5rem;"></div>
            </form>
        </div>
    </div>

    <!-- Wastage Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Expired Milk Records (<?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>)</h3>
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
                                            <?php echo $row['shift'] === 'Morning' ? '🌅' : '🌆'; ?>
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
                                <td colspan="6" style="text-align: right;">Total:</td>
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
                    <h3 style="color: var(--success); margin-top: 1rem;">Great News!</h3>
                    <p>No milk wastage in this period</p>
                    <small style="color: var(--text-medium);">All milk was sold within 24 hours!</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prevention Tips -->
    <!-- <div class="info-box">
        <strong>💡 Wastage Prevention Tips:</strong>
        <ul>
            <li><strong>Sell quickly:</strong> Prioritize older milk (12+ hours) in sales</li>
            <li><strong>Monitor daily:</strong> Check available milk at start and end of day</li>
            <li><strong>Adjust pricing:</strong> Consider discounts for milk nearing 24-hour mark</li>
            <li><strong>Plan production:</strong> Reduce collection if sales are low</li>
            <li><strong>Alternative uses:</strong> Convert near-expiry milk to products (yogurt, cheese)</li>
            <li><strong>First In First Out (FIFO):</strong> Always sell older batches first</li>
        </ul>
    </div> -->
</div>

<script>
// =========================================
// DATE FILTER VALIDATION
// =========================================
document.getElementById('filterForm').addEventListener('submit', function(e) {
    const filterBtn = document.getElementById('filterBtn');
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const errorDiv = document.getElementById('dateError');
    
    // Clear previous errors
    errorDiv.textContent = '';
    
    // Validate dates are filled
    if (!dateFrom || !dateTo) {
        e.preventDefault();
        errorDiv.textContent = '⚠️ Both From Date and To Date are required';
        return false;
    }
    
    // Validate from date is not after to date
    if (new Date(dateFrom) > new Date(dateTo)) {
        e.preventDefault();
        errorDiv.textContent = '⚠️ From Date cannot be after To Date';
        document.getElementById('dateFrom').focus();
        return false;
    }
    
    // Validate dates are not in future
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (new Date(dateFrom) > today || new Date(dateTo) > today) {
        e.preventDefault();
        errorDiv.textContent = '⚠️ Dates cannot be in the future';
        return false;
    }
    
    // Check date range (max 1 year)
    const daysDiff = Math.floor((new Date(dateTo) - new Date(dateFrom)) / (1000 * 60 * 60 * 24));
    if (daysDiff > 365) {
        e.preventDefault();
        errorDiv.textContent = '⚠️ Date range cannot exceed 365 days';
        return false;
    }
    
    // Prevent multiple clicks and show loading
    if (filterBtn.disabled) {
        e.preventDefault();
        return false;
    }
    
    filterBtn.disabled = true;
    filterBtn.innerHTML = '<span class="spinner">⏳</span> Please wait...';
    filterBtn.style.opacity = '0.7';
    
    // Form will submit, button will be re-enabled on page load
    return true;
});

// Auto-validate on date change
document.getElementById('dateFrom').addEventListener('change', function() {
    const dateTo = document.getElementById('dateTo');
    const errorDiv = document.getElementById('dateError');
    
    if (this.value && dateTo.value) {
        if (new Date(this.value) > new Date(dateTo.value)) {
            errorDiv.textContent = '⚠️ From Date cannot be after To Date';
            this.style.borderColor = 'var(--danger)';
        } else {
            errorDiv.textContent = '';
            this.style.borderColor = '';
            dateTo.style.borderColor = '';
        }
    }
});

document.getElementById('dateTo').addEventListener('change', function() {
    const dateFrom = document.getElementById('dateFrom');
    const errorDiv = document.getElementById('dateError');
    
    if (this.value && dateFrom.value) {
        if (new Date(dateFrom.value) > new Date(this.value)) {
            errorDiv.textContent = '⚠️ From Date cannot be after To Date';
            this.style.borderColor = 'var(--danger)';
        } else {
            errorDiv.textContent = '';
            this.style.borderColor = '';
            dateFrom.style.borderColor = '';
        }
    }
});

// =========================================
// PRINT FUNCTION WITH LOADING
// =========================================
function printReport() {
    // Show loading indicator
    const printBtn = event.target;
    const originalText = printBtn.innerHTML;
    
    printBtn.disabled = true;
    printBtn.innerHTML = '⏳ Preparing...';
    
    // Small delay to allow UI update
    setTimeout(function() {
        window.print();
        
        // Reset button after print dialog closes
        setTimeout(function() {
            printBtn.disabled = false;
            printBtn.innerHTML = originalText;
        }, 500);
    }, 100);
}

// Re-enable button on page load (in case of back button)
window.addEventListener('load', function() {
    const filterBtn = document.getElementById('filterBtn');
    if (filterBtn) {
        filterBtn.disabled = false;
        filterBtn.innerHTML = '🔍 Filter';
        filterBtn.style.opacity = '1';
    }
});
if (!isValid) {
        e.preventDefault();
    }
return isValid;
</script>

<!-- Print Styles -->
<style>
@media print {
    .no-print,
    .page-header .header-actions,
    .filter-form,
    .info-box,
    .breadcrumb {
        display: none !important;
    }
    
    .main-content {
        padding: 0;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    
    table {
        font-size: 0.85rem;
    }
}

/* Loading spinner */
.spinner {
    display: inline-block;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Error message styling */
.error-msg {
    font-size: 0.9rem;
    font-weight: 500;
    animation: shake 0.3s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
</style>
<<script>
    function exportPDF() {
    window.print();
}
</script>
<?php
$conn->close();
include '../../../includes/footer.php';
?>