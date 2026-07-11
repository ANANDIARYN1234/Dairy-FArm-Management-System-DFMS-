<?php
// employee/reports/milk-shift-summary.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Shift-wise Milk Report";
$user_id = get_user_id();
$user_name = get_user_name();
$errors = [];

// Date validation
$today = date('Y-m-d');
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : $today;

// Validate dates
if (!empty($date_from) && strtotime($date_from) > strtotime($today)) {
    $errors[] = "From date cannot be in the future";
    $date_from = $today;
}

if (!empty($date_to) && strtotime($date_to) > strtotime($today)) {
    $errors[] = "To date cannot be in the future";
    $date_to = $today;
}

if (!empty($date_from) && !empty($date_to) && strtotime($date_from) > strtotime($date_to)) {
    $errors[] = "From date cannot be greater than To date";
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Fetch shift-wise summary
$summary_sql = "SELECT 
                    shift,
                    COUNT(*) as total_collections,
                    COALESCE(SUM(quantity), 0) as total_quantity,
                    COALESCE(AVG(quantity), 0) as avg_quantity,
                    COALESCE(MIN(quantity), 0) as min_quantity,
                    COALESCE(MAX(quantity), 0) as max_quantity,
                    COUNT(DISTINCT collection_date) as days_worked,
                    COUNT(DISTINCT cattle_id) as unique_cattle
                FROM milk_collection
                WHERE user_id = ? AND collection_date BETWEEN ? AND ?
                GROUP BY shift";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();

$morning_stats = ['total_collections' => 0, 'total_quantity' => 0, 'avg_quantity' => 0, 'min_quantity' => 0, 'max_quantity' => 0, 'days_worked' => 0, 'unique_cattle' => 0];
$evening_stats = ['total_collections' => 0, 'total_quantity' => 0, 'avg_quantity' => 0, 'min_quantity' => 0, 'max_quantity' => 0, 'days_worked' => 0, 'unique_cattle' => 0];

while ($row = $summary_result->fetch_assoc()) {
    if ($row['shift'] === 'Morning') {
        $morning_stats = $row;
    } else {
        $evening_stats = $row;
    }
}
$summary_stmt->close();

// Fetch daily breakdown
$daily_sql = "SELECT 
                collection_date,
                SUM(CASE WHEN shift = 'Morning' THEN 1 ELSE 0 END) as morning_count,
                SUM(CASE WHEN shift = 'Evening' THEN 1 ELSE 0 END) as evening_count,
                SUM(CASE WHEN shift = 'Morning' THEN quantity ELSE 0 END) as morning_qty,
                SUM(CASE WHEN shift = 'Evening' THEN quantity ELSE 0 END) as evening_qty,
                SUM(quantity) as total_qty
              FROM milk_collection
              WHERE user_id = ? AND collection_date BETWEEN ? AND ?
              GROUP BY collection_date
              ORDER BY collection_date DESC";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->bind_param("iss", $user_id, $date_from, $date_to);
$daily_stmt->execute();
$daily_result = $daily_stmt->get_result();
$daily_stmt->close();

// Calculate totals
$total_collections = $morning_stats['total_collections'] + $evening_stats['total_collections'];
$total_quantity = $morning_stats['total_quantity'] + $evening_stats['total_quantity'];

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🌅 Shift-wise Milk Report</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="reports-view.php">Reports</a>
                <span>/</span>
                <span>Shift Summary</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="reports-view.php" class="btn btn-primary no-print">← Back to Reports</a>
        </div>
    </div>

    <!-- Validation Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠</span>
            <div class="alert-message">
                <strong>Date Validation Issues:</strong>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Report Header -->
    <div class="card">
        <div class="card-header" style="background: var(--accent-blue); color: white;">
            <h3>👤 Employee: <?php echo htmlspecialchars($user_name); ?></h3>
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
            <form method="GET" class="filter-form" onsubmit="return validateDates()">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo $date_from; ?>" max="<?php echo $today; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo $date_to; ?>" max="<?php echo $today; ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Search Report</button>
                        <a href="milk-shift-summary.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Shift Comparison -->
    <div class="customer-details">
        <!-- Morning Shift Stats -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
                <h3>🌅 Morning Shift</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Collections</div>
                    <div class="detail-value"><strong><?php echo $morning_stats['total_collections']; ?></strong></div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Milk</div>
                    <div class="detail-value"><strong><?php echo number_format($morning_stats['total_quantity'], 2); ?> L</strong></div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Average per Collection</div>
                    <div class="detail-value"><?php echo number_format($morning_stats['avg_quantity'], 2); ?> L</div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Min - Max</div>
                    <div class="detail-value">
                        <?php echo number_format($morning_stats['min_quantity'], 2); ?> L - 
                        <?php echo number_format($morning_stats['max_quantity'], 2); ?> L
                    </div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Days Worked</div>
                    <div class="detail-value"><?php echo $morning_stats['days_worked']; ?> days</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Cattle Handled</div>
                    <div class="detail-value"><?php echo $morning_stats['unique_cattle']; ?> cattle</div>
                </div>
            </div>
        </div>

        <!-- Evening Shift Stats -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #e67e22, #d35400); color: white;">
                <h3>🌆 Evening Shift</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Collections</div>
                    <div class="detail-value"><strong><?php echo $evening_stats['total_collections']; ?></strong></div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Milk</div>
                    <div class="detail-value"><strong><?php echo number_format($evening_stats['total_quantity'], 2); ?> L</strong></div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Average per Collection</div>
                    <div class="detail-value"><?php echo number_format($evening_stats['avg_quantity'], 2); ?> L</div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Min - Max</div>
                    <div class="detail-value">
                        <?php echo number_format($evening_stats['min_quantity'], 2); ?> L - 
                        <?php echo number_format($evening_stats['max_quantity'], 2); ?> L
                    </div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Days Worked</div>
                    <div class="detail-value"><?php echo $evening_stats['days_worked']; ?> days</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Cattle Handled</div>
                    <div class="detail-value"><?php echo $evening_stats['unique_cattle']; ?> cattle</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Collections</span>
                <span class="stat-value"><?php echo $total_collections; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Milk</span>
                <span class="stat-value"><?php echo number_format($total_quantity, 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🌅</div>
            <div class="stat-details">
                <span class="stat-label">Morning Share</span>
                <span class="stat-value">
                    <?php 
                    $morning_percent = $total_quantity > 0 ? ($morning_stats['total_quantity'] / $total_quantity) * 100 : 0;
                    echo number_format($morning_percent, 1); 
                    ?>%
                </span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🌆</div>
            <div class="stat-details">
                <span class="stat-label">Evening Share</span>
                <span class="stat-value">
                    <?php 
                    $evening_percent = $total_quantity > 0 ? ($evening_stats['total_quantity'] / $total_quantity) * 100 : 0;
                    echo number_format($evening_percent, 1); 
                    ?>%
                </span>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Daily Breakdown</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Morning Collections</th>
                            <th>Morning Qty (L)</th>
                            <th>Evening Collections</th>
                            <th>Evening Qty (L)</th>
                            <th>Total Qty (L)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daily_result->num_rows > 0): ?>
                            <?php while ($row = $daily_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y (D)', strtotime($row['collection_date'])); ?></td>
                                    <td><?php echo $row['morning_count']; ?></td>
                                    <td><?php echo number_format($row['morning_qty'], 2); ?></td>
                                    <td><?php echo $row['evening_count']; ?></td>
                                    <td><?php echo number_format($row['evening_qty'], 2); ?></td>
                                    <td><strong><?php echo number_format($row['total_qty'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td>Total:</td>
                                <td><?php echo $morning_stats['total_collections']; ?></td>
                                <td><?php echo number_format($morning_stats['total_quantity'], 2); ?></td>
                                <td><?php echo $evening_stats['total_collections']; ?></td>
                                <td><?php echo number_format($evening_stats['total_quantity'], 2); ?></td>
                                <td><?php echo number_format($total_quantity, 2); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="empty-state">
                                        <span class="empty-icon">📅</span>
                                        <p>No records for selected period</p>
                                    </div>
                                </td>
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
            <h3>📊 Shift Performance Analysis</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="info-box">
                <strong>Summary for Period: <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?></strong>
                <ul>
                    <li><strong>Total Collections:</strong> <?php echo $total_collections; ?> 
                        (Morning: <?php echo $morning_stats['total_collections']; ?>, 
                        Evening: <?php echo $evening_stats['total_collections']; ?>)</li>
                    <li><strong>Total Milk Collected:</strong> <?php echo number_format($total_quantity, 2); ?> Liters</li>
                    <li><strong>Morning Shift:</strong> <?php echo number_format($morning_stats['total_quantity'], 2); ?> L 
                        (<?php echo number_format($morning_percent, 1); ?>%)</li>
                    <li><strong>Evening Shift:</strong> <?php echo number_format($evening_stats['total_quantity'], 2); ?> L 
                        (<?php echo number_format($evening_percent, 1); ?>%)</li>
                    <li><strong>Better Performing Shift:</strong> 
                        <?php 
                        if ($morning_stats['total_quantity'] > $evening_stats['total_quantity']) {
                            echo "🌅 Morning (+" . number_format($morning_stats['total_quantity'] - $evening_stats['total_quantity'], 2) . " L)";
                        } elseif ($evening_stats['total_quantity'] > $morning_stats['total_quantity']) {
                            echo "🌆 Evening (+" . number_format($evening_stats['total_quantity'] - $morning_stats['total_quantity'], 2) . " L)";
                        } else {
                            echo "Both Equal";
                        }
                        ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function validateDates() {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const today = '<?php echo $today; ?>';
    
    if (!dateFrom || !dateTo) {
        alert('Both From and To dates are required');
        return false;
    }
    
    if (dateFrom > today) {
        alert('From date cannot be in the future');
        return false;
    }
    
    if (dateTo > today) {
        alert('To date cannot be in the future');
        return false;
    }
    
    if (dateFrom > dateTo) {
        alert('From date cannot be greater than To date');
        return false;
    }
    
    return true;
}

// Set max date to today on page load
document.addEventListener('DOMContentLoaded', function() {
    const today = '<?php echo $today; ?>';
    document.getElementById('dateFrom').max = today;
    document.getElementById('dateTo').max = today;
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
    .card { box-shadow: none; border: 1px solid var(--border-color); }
}
</style>

<?php
$conn->close();
include '../../includes/footer.php';
?>