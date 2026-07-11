<?php
// employee/reports/my-milk-records.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "My Milk Records Report";
$user_id = get_user_id();
$user_name = get_user_name();

// Date filters with validation
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$date_errors = [];

// Validate dates if form is submitted
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $today = date('Y-m-d');
    
    // Validate date_from
    if (empty($date_from)) {
        $date_errors[] = "From date is required";
    } elseif ($date_from > $today) {
        $date_errors[] = "From date cannot be in the future";
        $date_from = date('Y-m-01'); // Reset to default
    }
    
    // Validate date_to
    if (empty($date_to)) {
        $date_errors[] = "To date is required";
    } elseif ($date_to > $today) {
        $date_errors[] = "To date cannot be in the future";
        $date_to = date('Y-m-d'); // Reset to default
    }
    
    // Validate date range
    if (empty($date_errors) && $date_from > $date_to) {
        $date_errors[] = "From date cannot be later than To date";
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-d');
    }
}

// Fetch statistics
$stats_sql = "SELECT 
                COUNT(*) as total_collections,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(AVG(quantity), 0) as avg_quantity,
                COUNT(DISTINCT collection_date) as days_worked,
                COUNT(DISTINCT cattle_id) as unique_cattle,
                SUM(CASE WHEN shift = 'Morning' THEN 1 ELSE 0 END) as morning_count,
                SUM(CASE WHEN shift = 'Evening' THEN 1 ELSE 0 END) as evening_count,
                SUM(CASE WHEN shift = 'Morning' THEN quantity ELSE 0 END) as morning_quantity,
                SUM(CASE WHEN shift = 'Evening' THEN quantity ELSE 0 END) as evening_quantity
              FROM milk_collection
              WHERE user_id = ? AND collection_date BETWEEN ? AND ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Fetch daily summary
$daily_sql = "SELECT 
                collection_date,
                COUNT(*) as collections,
                SUM(quantity) as total_quantity,
                SUM(CASE WHEN shift = 'Morning' THEN quantity ELSE 0 END) as morning_qty,
                SUM(CASE WHEN shift = 'Evening' THEN quantity ELSE 0 END) as evening_qty
              FROM milk_collection
              WHERE user_id = ? AND collection_date BETWEEN ? AND ?
              GROUP BY collection_date
              ORDER BY collection_date DESC";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$daily_stmt->execute();
$daily_result = $daily_stmt->get_result();
$daily_stmt->close();

// Fetch cattle-wise summary
$cattle_sql = "SELECT 
                c.tag_id,
                ct.type_name,
                b.breed_name,
                COUNT(mc.milk_id) as collections,
                SUM(mc.quantity) as total_quantity,
                AVG(mc.quantity) as avg_quantity
              FROM milk_collection mc
              JOIN cattle c ON mc.cattle_id = c.cattle_id
              JOIN cattle_type ct ON c.type_id = ct.type_id
              JOIN breed b ON c.breed_id = b.breed_id
              WHERE mc.user_id = ? AND mc.collection_date BETWEEN ? AND ?
              GROUP BY c.cattle_id
              ORDER BY total_quantity DESC";
$cattle_stmt = $conn->prepare($cattle_sql);
$cattle_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$cattle_stmt->execute();
$cattle_result = $cattle_stmt->get_result();
$cattle_stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🥛 My Milk Records Report</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>My Milk Records</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="reports-view.php" class="btn btn-primary no-print">← Back to Reports</a>
        </div>
    </div>

    <!-- Date Validation Errors -->
    <?php if (!empty($date_errors)): ?>
        <div class="alert alert-error no-print">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Date Validation Error!</strong>
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
            <h3>📊 Employee: <?php echo htmlspecialchars($user_name); ?></h3>
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
                    <strong>Total Days:</strong><br>
                    <?php 
                    $days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1;
                    echo floor($days_diff); ?> days
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
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo $date_to; ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="my-milk-records.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Collections</span>
                <span class="stat-value"><?php echo $stats['total_collections']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Milk</span>
                <span class="stat-value"><?php echo number_format($stats['total_quantity'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📈</div>
            <div class="stat-details">
                <span class="stat-label">Average per Collection</span>
                <span class="stat-value"><?php echo number_format($stats['avg_quantity'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📅</div>
            <div class="stat-details">
                <span class="stat-label">Days Worked</span>
                <span class="stat-value"><?php echo $stats['days_worked']; ?></span>
            </div>
        </div>
    </div>

    <!-- Shift-wise Breakdown -->
    <div class="customer-details">
        <div class="card">
            <div class="card-header">
                <h3>🌅 Morning Shift</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Collections</div>
                    <div class="detail-value"><?php echo $stats['morning_count']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Quantity</div>
                    <div class="detail-value"><?php echo number_format($stats['morning_quantity'] ?? 0, 2); ?> Liters</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>🌆 Evening Shift</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Collections</div>
                    <div class="detail-value"><?php echo $stats['evening_count']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Quantity</div>
                    <div class="detail-value"><?php echo number_format($stats['evening_quantity'] ?? 0, 2); ?> Liters</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Summary -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Daily Summary</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Collections</th>
                            <th>Morning (L)</th>
                            <th>Evening (L)</th>
                            <th>Total (L)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daily_result->num_rows > 0): ?>
                            <?php while ($row = $daily_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y (D)', strtotime($row['collection_date'])); ?></td>
                                    <td><?php echo $row['collections']; ?></td>
                                    <td><?php echo number_format($row['morning_qty'], 2); ?></td>
                                    <td><?php echo number_format($row['evening_qty'], 2); ?></td>
                                    <td><strong><?php echo number_format($row['total_quantity'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td>Total:</td>
                                <td><?php echo $stats['total_collections']; ?></td>
                                <td><?php echo number_format($stats['morning_quantity'], 2); ?></td>
                                <td><?php echo number_format($stats['evening_quantity'], 2); ?></td>
                                <td><?php echo number_format($stats['total_quantity'], 2); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No records for selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Cattle-wise Summary -->
    <div class="card">
        <div class="card-header">
            <h3>🐄 Cattle-wise Summary</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cattle Tag</th>
                            <th>Type / Breed</th>
                            <th>Collections</th>
                            <th>Total Quantity (L)</th>
                            <th>Average (L)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cattle_result->num_rows > 0): ?>
                            <?php 
                            $serial = 1;
                            while ($row = $cattle_result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['tag_id']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['type_name']); ?> / 
                                        <?php echo htmlspecialchars($row['breed_name']); ?>
                                    </td>
                                    <td><?php echo $row['collections']; ?></td>
                                    <td><strong><?php echo number_format($row['total_quantity'], 2); ?></strong></td>
                                    <td><?php echo number_format($row['avg_quantity'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No cattle records for selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Performance Analysis -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Performance Analysis</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="info-box">
                <strong>Summary:</strong>
                <ul>
                    <li>Total Collections: <strong><?php echo $stats['total_collections']; ?></strong></li>
                    <li>Total Milk Collected: <strong><?php echo number_format($stats['total_quantity'], 2); ?> Liters</strong></li>
                    <li>Days Worked: <strong><?php echo $stats['days_worked']; ?> days</strong></li>
                    <li>Unique Cattle Handled: <strong><?php echo $stats['unique_cattle']; ?></strong></li>
                    <li>Average per Collection: <strong><?php echo number_format($stats['avg_quantity'], 2); ?> Liters</strong></li>
                    <?php if ($stats['days_worked'] > 0): ?>
                        <li>Average per Day: <strong><?php echo number_format($stats['total_quantity'] / $stats['days_worked'], 2); ?> Liters</strong></li>
                    <?php endif; ?>
                    <li>Morning Collections: <strong><?php echo $stats['morning_count'] ?? 0; ?></strong> (<?php echo number_format((float)($stats['morning_quantity'] ?? 0), 2); ?> L)</li>
                    <li>Evening Collections: <strong><?php echo $stats['evening_count'] ?? 0; ?></strong> (<?php echo number_format((float)($stats['evening_quantity'] ?? 0), 2); ?> L)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .page-header, .breadcrumb { display: none; }
    .card { box-shadow: none; border: 1px solid var(--border-color); }
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
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
                return false;
            }
        });
        
        // Real-time validation for date_from
        dateFrom.addEventListener('change', function() {
            if (this.value > today) {
                alert('From date cannot be in the future');
                this.value = '<?php echo date('Y-m-01'); ?>';
            }
            if (dateTo.value && this.value > dateTo.value) {
                alert('From date cannot be later than To date');
                this.value = dateTo.value;
            }
        });
        
        // Real-time validation for date_to
        dateTo.addEventListener('change', function() {
            if (this.value > today) {
                alert('To date cannot be in the future');
                this.value = today;
            }
            if (dateFrom.value && this.value < dateFrom.value) {
                alert('To date cannot be earlier than From date');
                this.value = dateFrom.value;
            }
        });
    }
});
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