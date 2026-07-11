<?php
// admin/reports/cattle/cattle-summary.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Cattle Inventory Summary";

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

// Fetch summary with date filter
$sql = "SELECT 
            ct.type_name,
            b.breed_name,
            c.life_status as status,
            c.gender,
            COUNT(*) as count,
            AVG(TIMESTAMPDIFF(YEAR, c.dob, CURDATE())) as avg_age_years
        FROM cattle c
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        WHERE c.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY ct.type_name, b.breed_name, c.life_status, c.gender
        ORDER BY ct.type_name, b.breed_name, c.life_status, c.gender";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

// Calculate totals
$totals_sql = "SELECT 
                COUNT(DISTINCT ct.type_name) as total_types,
                COUNT(DISTINCT b.breed_name) as total_breeds,
                COUNT(*) as total_cattle
               FROM cattle c
               JOIN cattle_type ct ON c.type_id = ct.type_id
               JOIN breed b ON c.breed_id = b.breed_id
               WHERE c.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
$totals_stmt = $conn->prepare($totals_sql);
$totals_stmt->bind_param("ss", $date_from, $date_to);
$totals_stmt->execute();
$totals = $totals_stmt->get_result()->fetch_assoc();

// Status breakdown
$status_sql = "SELECT 
                SUM(CASE WHEN life_status = 'Alive' THEN 1 ELSE 0 END) as alive_count,
                SUM(CASE WHEN life_status = 'Pregnant' OR is_pregnant = 1 THEN 1 ELSE 0 END) as pregnant_count,
                SUM(CASE WHEN life_status = 'Sold' THEN 1 ELSE 0 END) as sold_count,
                SUM(CASE WHEN life_status = 'Dead' THEN 1 ELSE 0 END) as dead_count
               FROM cattle
               WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("ss", $date_from, $date_to);
$status_stmt->execute();
$status_data = $status_stmt->get_result()->fetch_assoc();

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📊 Cattle Summary</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Cattle Summary</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
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
                        <a href="cattle-summary.php" class="btn btn-secondary">🔄 Reset</a>
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

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Total Cattle</span>
                <span class="stat-value"><?php echo number_format($totals['total_cattle'] ?? 0); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">✓</div>
            <div class="stat-details">
                <span class="stat-label">Alive</span>
                <span class="stat-value"><?php echo number_format($status_data['alive_count'] ?? 0); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Pregnant</span>
                <span class="stat-value"><?php echo number_format($status_data['pregnant_count'] ?? 0); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📋</div>
            <div class="stat-details">
                <span class="stat-label">Types/Breeds</span>
                <span class="stat-value"><?php echo ($totals['total_types'] ?? 0); ?> / <?php echo ($totals['total_breeds'] ?? 0); ?></span>
            </div>
        </div>
    </div>

    <!-- Summary Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Detailed Summary (<?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Breed</th>
                            <th>Status</th>
                            <th>Gender</th>
                            <th>Count</th>
                            <th>Avg Age (Years)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php 
                            $current_type = '';
                            $type_total = 0;
                            while ($row = $result->fetch_assoc()): 
                                // Group by type
                                if ($current_type !== '' && $current_type !== $row['type_name']) {
                                    echo '<tr style="background: var(--bg-tertiary); font-weight: bold;">
                                            <td colspan="4" style="text-align: right;">' . htmlspecialchars($current_type) . ' Subtotal:</td>
                                            <td>' . $type_total . '</td>
                                            <td>-</td>
                                          </tr>';
                                    $type_total = 0;
                                }
                                $current_type = $row['type_name'];
                                $type_total += $row['count'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['breed_name']); ?></td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'Alive' => 'success',
                                            'Pregnant' => 'warning',
                                            'Sold' => 'info',
                                            'Dead' => 'secondary'
                                        ];
                                        $badge_class = $status_class[$row['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['gender'] === 'Male' ? 'info' : 'primary'; ?>">
                                            <?php echo $row['gender'] === 'Male' ? '♂ Male' : '♀ Female'; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $row['count']; ?></strong></td>
                                    <td><?php echo number_format($row['avg_age_years'], 1); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <!-- Last subtotal -->
                            <?php if ($current_type !== ''): ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="4" style="text-align: right;"><?php echo htmlspecialchars($current_type); ?> Subtotal:</td>
                                <td><?php echo $type_total; ?></td>
                                <td>-</td>
                            </tr>
                            <?php endif; ?>
                            <!-- Grand total -->
                            <tr style="background: var(--accent-blue); color: white; font-weight: bold;">
                                <td colspan="4" style="text-align: right;">GRAND TOTAL:</td>
                                <td><?php echo $totals['total_cattle']; ?></td>
                                <td>-</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <span class="empty-icon">🐄</span>
                                        <p>No cattle records found for selected date range</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Status Breakdown Chart -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Status Breakdown</h3>
        </div>
        <div class="card-body" style="padding: 2rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                <div style="text-align: center; padding: 1.5rem; background: #e8f5e9; border-radius: 12px;">
                    <div style="font-size: 2.5rem; color: #388e3c; margin-bottom: 0.5rem;">✓</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #388e3c;"><?php echo number_format($status_data['alive_count'] ?? 0); ?></div>
                    <div style="color: #666;">Alive</div>
                </div>
                
                <div style="text-align: center; padding: 1.5rem; background: #fff3e0; border-radius: 12px;">
                    <div style="font-size: 2.5rem; color: #f57c00; margin-bottom: 0.5rem;">🐄</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #f57c00;"><?php echo number_format($status_data['pregnant_count'] ?? 0); ?></div>
                    <div style="color: #666;">Pregnant</div>
                </div>
                
                <div style="text-align: center; padding: 1.5rem; background: #e3f2fd; border-radius: 12px;">
                    <div style="font-size: 2.5rem; color: #1976d2; margin-bottom: 0.5rem;">🪙</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #1976d2;"><?php echo number_format($status_data['sold_count'] ?? 0); ?></div>
                    <div style="color: #666;">Sold</div>
                </div>
                
                <div style="text-align: center; padding: 1.5rem; background: #f5f5f5; border-radius: 12px;">
                    <div style="font-size: 2.5rem; color: #616161; margin-bottom: 0.5rem;">✕</div>
                    <div style="font-size: 2rem; font-weight: bold; color: #616161;"><?php echo number_format($status_data['dead_count'] ?? 0); ?></div>
                    <div style="color: #666;">Dead</div>
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

function exportPDF() {
    window.print();
}
</script>

<?php
$stmt->close();
$conn->close();
include '../../../includes/footer.php';
?>