<?php
// admin/reports/milk/collection-summary.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Milk Collection Summary";

// Date filters with validation
$errors = [];
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Validate dates if form is submitted
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $today = date('Y-m-d');
    
    // Check if dates are empty
    if (empty($date_from)) {
        $errors['date_from'] = 'From date is required';
    }
    if (empty($date_to)) {
        $errors['date_to'] = 'To date is required';
    }
    
    // Check if dates are valid format
    if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $errors['date_from'] = 'Invalid date format';
    }
    if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $errors['date_to'] = 'Invalid date format';
    }
    
    // Check if dates are in the future
    if (!empty($date_from) && $date_from > $today) {
        $errors['date_from'] = 'From date cannot be in the future';
    }
    if (!empty($date_to) && $date_to > $today) {
        $errors['date_to'] = 'To date cannot be in the future';
    }
    
    // Check if from date is after to date
    if (empty($errors) && !empty($date_from) && !empty($date_to) && $date_from > $date_to) {
        $errors['date_range'] = 'From date cannot be later than To date';
    }
    
    // Check if date range is too large (optional - prevent performance issues)
    if (empty($errors) && !empty($date_from) && !empty($date_to)) {
        $date1 = new DateTime($date_from);
        $date2 = new DateTime($date_to);
        $diff = $date1->diff($date2);
        if ($diff->days > 365) {
            $errors['date_range'] = 'Date range cannot exceed 365 days';
        }
    }
}

// Initialize default values
$summary = [
    'total_collections' => 0,
    'active_cattle' => 0,
    'total_milk' => 0,
    'avg_per_collection' => 0,
    'first_date' => null,
    'last_date' => null
];
$by_type = null;
$by_shift = null;

// Only fetch data if no validation errors
if (empty($errors)) {
    // Overall summary
    $summary_sql = "SELECT 
                    COUNT(*) as total_collections,
                    COUNT(DISTINCT cattle_id) as active_cattle,
                    COALESCE(SUM(quantity), 0) as total_milk,
                    COALESCE(AVG(quantity), 0) as avg_per_collection,
                    MIN(collection_date) as first_date,
                    MAX(collection_date) as last_date
                    FROM milk_collection
                    WHERE collection_date BETWEEN ? AND ?";
    $summary_stmt = $conn->prepare($summary_sql);
    $summary_stmt->bind_param("ss", $date_from, $date_to);
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc();

    // By cattle type
    $by_type_sql = "SELECT 
                    ct.type_name,
                    COUNT(*) as collections,
                    COUNT(DISTINCT mc.cattle_id) as cattle_count,
                    COALESCE(SUM(mc.quantity), 0) as total_milk,
                    COALESCE(AVG(mc.quantity), 0) as avg_milk
                    FROM milk_collection mc
                    JOIN cattle c ON mc.cattle_id = c.cattle_id
                    JOIN cattle_type ct ON c.type_id = ct.type_id
                    WHERE mc.collection_date BETWEEN ? AND ?
                    GROUP BY ct.type_name
                    ORDER BY total_milk DESC";
    $by_type_stmt = $conn->prepare($by_type_sql);
    $by_type_stmt->bind_param("ss", $date_from, $date_to);
    $by_type_stmt->execute();
    $by_type = $by_type_stmt->get_result();

    // By shift
    $by_shift_sql = "SELECT 
                     shift,
                     COUNT(*) as collections,
                     COALESCE(SUM(quantity), 0) as total_milk,
                     COALESCE(AVG(quantity), 0) as avg_milk
                     FROM milk_collection
                     WHERE collection_date BETWEEN ? AND ?
                     GROUP BY shift
                     ORDER BY shift";
    $by_shift_stmt = $conn->prepare($by_shift_sql);
    $by_shift_stmt->bind_param("ss", $date_from, $date_to);
    $by_shift_stmt->execute();
    $by_shift = $by_shift_stmt->get_result();
}

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📊 Milk Collection Summary</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Collection Summary</span>
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
            <?php if (!empty($errors['date_range'])): ?>
                <div class="alert alert-error" style="margin-bottom: 1rem;">
                    <span class="alert-icon">✕</span>
                    <span><?php echo $errors['date_range']; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="GET" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date <span style="color: red;">*</span></label>
                        <input 
                            type="date" 
                            name="date_from" 
                            id="date_from"
                            class="form-control" 
                            value="<?php echo htmlspecialchars($date_from); ?>" 
                            max="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                        <?php if (isset($errors['date_from'])): ?>
                            <span class="error-msg" style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                <?php echo $errors['date_from']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date <span style="color: red;">*</span></label>
                        <input 
                            type="date" 
                            name="date_to" 
                            id="date_to"
                            class="form-control" 
                            value="<?php echo htmlspecialchars($date_to); ?>" 
                            max="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                        <?php if (isset($errors['date_to'])): ?>
                            <span class="error-msg" style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                <?php echo $errors['date_to']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="collection-summary.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Overall Summary -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Collections</span>
                <span class="stat-value"><?php echo number_format($summary['total_collections']); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Milk</span>
                <span class="stat-value"><?php echo number_format($summary['total_milk'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Active Cattle</span>
                <span class="stat-value"><?php echo number_format($summary['active_cattle']); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📈</div>
            <div class="stat-details">
                <span class="stat-label">Avg per Collection</span>
                <span class="stat-value"><?php echo number_format($summary['avg_per_collection'], 2); ?> L</span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors) && empty($errors['date_range'])): ?>
        <div class="card">
            <div class="card-body">
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠</span>
                    <span>Please correct the date validation errors above to view the report</span>
                </div>
            </div>
        </div>
    <?php elseif (empty($errors)): ?>
        <div class="customer-details">
            <!-- By Cattle Type -->
            <div class="card">
                <div class="card-header">
                    <h3>🐄 By Cattle Type</h3>
                    <p style="color: var(--text-medium); font-size: 0.9rem; margin-top: 0.5rem;">
                        Period: <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?>
                    </p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Cattle Type</th>
                                    <th>Cattle Count</th>
                                    <th>Collections</th>
                                    <th>Total Milk (L)</th>
                                    <th>Avg per Collection</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($by_type && $by_type->num_rows > 0): ?>
                                    <?php while ($row = $by_type->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['type_name']); ?></strong></td>
                                            <td><?php echo number_format($row['cattle_count']); ?></td>
                                            <td><?php echo number_format($row['collections']); ?></td>
                                            <td><strong><?php echo number_format($row['total_milk'], 2); ?></strong></td>
                                            <td><?php echo number_format($row['avg_milk'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <span class="empty-icon">🐄</span>
                                                <p>No data available for selected period</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- By Shift -->
            <div class="card">
                <div class="card-header">
                    <h3>🌅 By Shift</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Shift</th>
                                    <th>Collections</th>
                                    <th>Total Milk (L)</th>
                                    <th>Avg per Collection</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($by_shift && $by_shift->num_rows > 0): ?>
                                    <?php while ($row = $by_shift->fetch_assoc()): ?>
                                        <?php 
                                        $percentage = $summary['total_milk'] > 0 
                                            ? ($row['total_milk'] / $summary['total_milk']) * 100 
                                            : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge <?php echo $row['shift'] === 'Morning' ? 'badge-info' : 'badge-warning'; ?>">
                                                    <?php echo $row['shift'] === 'Morning' ? '🌅' : '🌆'; ?> <?php echo $row['shift']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($row['collections']); ?></td>
                                            <td><strong><?php echo number_format($row['total_milk'], 2); ?></strong></td>
                                            <td><?php echo number_format($row['avg_milk'], 2); ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div style="flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                                        <div style="width: <?php echo $percentage; ?>%; height: 100%; background: <?php echo $row['shift'] === 'Morning' ? '#2196F3' : '#FF9800'; ?>; transition: width 0.3s;"></div>
                                                    </div>
                                                    <span style="min-width: 50px; text-align: right; font-weight: 500;">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <span class="empty-icon">🌅</span>
                                                <p>No shift data available for selected period</p>
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
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('dateFilterForm');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    const today = new Date().toISOString().split('T')[0];
    
    // Helper function to show error
    function showError(element, message) {
        // Remove existing error
        const existingError = element.parentElement.querySelector('.error-msg');
        if (existingError) {
            existingError.remove();
        }
        
        // Add new error
        const errorSpan = document.createElement('span');
        errorSpan.className = 'error-msg';
        errorSpan.style.color = '#dc3545';
        errorSpan.style.fontSize = '0.875rem';
        errorSpan.style.marginTop = '0.25rem';
        errorSpan.style.display = 'block';
        errorSpan.textContent = message;
        element.parentElement.appendChild(errorSpan);
        element.style.borderColor = '#dc3545';
    }
    
    // Helper function to clear error
    function clearError(element) {
        const errorSpan = element.parentElement.querySelector('.error-msg');
        if (errorSpan && !errorSpan.textContent.includes('required')) {
            errorSpan.remove();
        }
        element.style.borderColor = '';
    }
    
    // Validate on change
    if (dateFrom) {
        dateFrom.addEventListener('change', function() {
            clearError(this);
            
            if (this.value > today) {
                showError(this, 'From date cannot be in the future');
            } else if (dateTo.value && this.value > dateTo.value) {
                showError(this, 'From date cannot be later than To date');
            }
        });
    }
    
    if (dateTo) {
        dateTo.addEventListener('change', function() {
            clearError(this);
            
            if (this.value > today) {
                showError(this, 'To date cannot be in the future');
            } else if (dateFrom.value && this.value < dateFrom.value) {
                showError(this, 'To date cannot be earlier than From date');
            }
        });
    }
    
    // Validate on submit
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Clear all dynamic errors
            document.querySelectorAll('.error-msg').forEach(el => {
                if (!el.textContent.includes('required')) {
                    el.remove();
                }
            });
            
            // Validate from date
            if (!dateFrom.value) {
                showError(dateFrom, 'From date is required');
                isValid = false;
            } else if (dateFrom.value > today) {
                showError(dateFrom, 'From date cannot be in the future');
                isValid = false;
            }
            
            // Validate to date
            if (!dateTo.value) {
                showError(dateTo, 'To date is required');
                isValid = false;
            } else if (dateTo.value > today) {
                showError(dateTo, 'To date cannot be in the future');
                isValid = false;
            }
            
            // Validate date range
            if (dateFrom.value && dateTo.value) {
                if (dateFrom.value > dateTo.value) {
                    showError(dateFrom, 'From date cannot be later than To date');
                    isValid = false;
                }
                
                // Check if range is too large (365 days)
                const date1 = new Date(dateFrom.value);
                const date2 = new Date(dateTo.value);
                const diffTime = Math.abs(date2 - date1);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 365) {
                    showError(dateTo, 'Date range cannot exceed 365 days');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                
                // Scroll to first error
                const firstError = document.querySelector('.error-msg');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
});
// Validate form submission
if (!isValid) {
        e.preventDefault();
    }
    return isValid;

function exportPDF() {
    window.print();
}
</script>

<?php
if (isset($summary_stmt)) $summary_stmt->close();
if (isset($by_type_stmt)) $by_type_stmt->close();
if (isset($by_shift_stmt)) $by_shift_stmt->close();
$conn->close();
include '../../../includes/footer.php';
?>