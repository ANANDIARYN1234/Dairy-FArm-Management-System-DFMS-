<?php
// employee/milk/my-collections.php 
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "My Collections";
$user_id = get_user_id();

// ========================================
// DATE VALIDATION & SANITIZATION
// ========================================
$today = date('Y-m-d');
$errors = [];

// Get and validate dates
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : $today;

// Validate date format
if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-01');
    $errors[] = "Invalid from date format";
}

if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = $today;
    $errors[] = "Invalid to date format";
}

// Prevent future dates
if ($date_from > $today) {
    $date_from = $today;
    $errors[] = "From date cannot be in the future";
}

if ($date_to > $today) {
    $date_to = $today;
    $errors[] = "To date cannot be in the future";
}

// Prevent from_date > to_date
if ($date_from > $date_to) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
    $errors[] = "From date cannot be after To date. Dates have been swapped.";
}

// Limit date range to 1 year
$date_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
if ($date_diff > 365) {
    $date_from = date('Y-m-01');
    $date_to = $today;
    $errors[] = "Date range cannot exceed 1 year. Reset to current month.";
}

// Other filters
$shift_filter = isset($_GET['shift']) && in_array($_GET['shift'], ['Morning', 'Evening']) ? $_GET['shift'] : '';

// ========================================
// PAGINATION - Default 10 records
// ========================================
$records_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause
$where_conditions = ["mc.user_id = ?"];
$params = [$user_id];
$types = 'i';

if (!empty($date_from)) {
    $where_conditions[] = "mc.collection_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "mc.collection_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($shift_filter)) {
    $where_conditions[] = "mc.shift = ?";
    $params[] = $shift_filter;
    $types .= 's';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM milk_collection mc $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_records / $records_per_page));
$count_stmt->close();

// Ensure page is within valid range
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
}

// Fetch collections
$sql = "SELECT mc.*, c.tag_id, ct.type_name, b.breed_name
        FROM milk_collection mc
        JOIN cattle c ON mc.cattle_id = c.cattle_id
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        $where_clause
        ORDER BY mc.collection_date DESC, mc.shift DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$list_params = array_merge($params, [$records_per_page, $offset]);
$list_types = $types . 'ii';
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_collections,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(AVG(quantity), 0) as avg_quantity,
                COUNT(DISTINCT collection_date) as days_worked
              FROM milk_collection mc
              $where_clause";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param($types, ...$params);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Build query params for pagination
$query_params = [];
if ($date_from) $query_params['date_from'] = $date_from;
if ($date_to) $query_params['date_to'] = $date_to;
if ($shift_filter) $query_params['shift'] = $shift_filter;

function build_pagination_url($page_num, $params) {
    $params['page'] = $page_num;
    return 'my-collections.php?' . http_build_query($params);
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📋 My Milk Collections</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>My Collections</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="milk-add.php" class="btn btn-primary">➕ Add Collection</a>
        </div>
    </div>

    <!-- Validation Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠</span>
            <div class="alert-message">
                <strong>Date Validation:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
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

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Filter Records</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>"
                               max="<?php echo $today; ?>">
                        <!-- <small class="form-hint">Cannot be future date</small> -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               max="<?php echo $today; ?>">
                        <!-- <small class="form-hint">Cannot be future date</small> -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Shift</label>
                        <select name="shift" class="form-control">
                            <option value="">All Shifts</option>
                            <option value="Morning" <?php echo $shift_filter === 'Morning' ? 'selected' : ''; ?>>🌅 Morning</option>
                            <option value="Evening" <?php echo $shift_filter === 'Evening' ? 'selected' : ''; ?>>🌆 Evening</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="my-collections.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Collections Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Collection Records</h3>
        </div>
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
                <!-- Pagination Info -->
                <div style="padding: 1rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: var(--text-medium); font-size: 0.9rem;">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo number_format($total_records); ?> records
                    </div>
                    <div style="color: var(--text-medium); font-size: 0.9rem;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Cattle Tag</th>
                                <th>Type / Breed</th>
                                <th>Quantity (L)</th>
                                <th>Recorded At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = $offset + 1;
                            $current_date = '';
                            while ($row = $result->fetch_assoc()): 
                                $date_display = date('d M Y', strtotime($row['collection_date']));
                                $show_date = ($current_date !== $date_display);
                                $current_date = $date_display;
                            ?>
                                <?php if ($show_date): ?>
                                    <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                        <td colspan="7" style="padding: 0.75rem;">
                                            📅 <?php echo $date_display; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['collection_date'])); ?></td>
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
                                    <td><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="5" style="text-align: right;">Total for Period:</td>
                                <td><strong style="color: var(--success); font-size: 1.1rem;"><?php echo number_format($stats['total_quantity'], 2); ?> L</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Enhanced Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="padding: 1rem; background: var(--bg-secondary); border-top: 1px solid var(--border-color);">
                        <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <!-- First & Previous -->
                            <?php if ($page > 1): ?>
                                <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" title="First Page">⏮️</a>
                                <a href="<?php echo build_pagination_url($page - 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Previous">◀️</a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>⏮️</button>
                                <button class="btn btn-secondary btn-sm" disabled>◀️</button>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end = min($total_pages, $page + $range);
                            
                            if ($start > 1): ?>
                                <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm">1</a>
                                <?php if ($start > 2): ?>
                                    <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <button class="btn btn-primary btn-sm" style="min-width: 40px;"><?php echo $i; ?></button>
                                <?php else: ?>
                                    <a href="<?php echo build_pagination_url($i, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                                <?php endif; ?>
                                <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm"><?php echo $total_pages; ?></a>
                            <?php endif; ?>
                            
                            <!-- Next & Last -->
                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo build_pagination_url($page + 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Next">▶️</a>
                                <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm" title="Last Page">⏭️</a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>▶️</button>
                                <button class="btn btn-secondary btn-sm" disabled>⏭️</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🥛</span>
                    <p>No collections found for the selected period</p>
                    <?php if ($date_from || $date_to || $shift_filter): ?>
                        <p style="margin-top: 0.5rem; color: var(--text-medium);">Try adjusting your filters</p>
                        <a href="my-collections.php" class="btn btn-secondary" style="margin-top: 1rem;">🔄 Clear Filters</a>
                    <?php else: ?>
                        <a href="milk-add.php" class="btn btn-primary" style="margin-top: 1rem;">➕ Add Your First Collection</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Performance Summary -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>📊 Performance Summary</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="info-box">
                <strong>Period: <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?></strong>
                <ul>
                    <li><strong>Total Collections:</strong> <?php echo $stats['total_collections']; ?></li>
                    <li><strong>Total Milk Collected:</strong> <?php echo number_format($stats['total_quantity'], 2); ?> Liters</li>
                    <li><strong>Average per Collection:</strong> <?php echo number_format($stats['avg_quantity'], 2); ?> Liters</li>
                    <li><strong>Days Worked:</strong> <?php echo $stats['days_worked']; ?> days</li>
                    <?php if ($stats['days_worked'] > 0): ?>
                        <li><strong>Average per Day:</strong> <?php echo number_format($stats['total_quantity'] / $stats['days_worked'], 2); ?> Liters</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div> -->

<script>
// ========================================
// CLIENT-SIDE DATE VALIDATION
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const today = '<?php echo $today; ?>';
    
    // Validate on form submit
    form.addEventListener('submit', function(e) {
        let errors = [];
        
        // Check if dates are in future
        if (dateFrom.value > today) {
            errors.push('From date cannot be in the future');
            dateFrom.value = today;
        }
        
        if (dateTo.value > today) {
            errors.push('To date cannot be in the future');
            dateTo.value = today;
        }
        
        // Check if from_date > to_date
        if (dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
            errors.push('From date cannot be after To date');
            // Swap dates
            const temp = dateFrom.value;
            dateFrom.value = dateTo.value;
            dateTo.value = temp;
        }
        
        // Check date range (1 year max)
        if (dateFrom.value && dateTo.value) {
            const diff = (new Date(dateTo.value) - new Date(dateFrom.value)) / (1000 * 60 * 60 * 24);
            if (diff > 365) {
                errors.push('Date range cannot exceed 1 year');
                e.preventDefault();
            }
        }
        
        if (errors.length > 0) {
            alert('Date Validation:\n• ' + errors.join('\n• '));
            if (errors.length === 1 && errors[0].includes('swap')) {
                // Allow submit after swapping
                return true;
            }
        }
    });
    
    // Real-time validation
    dateFrom.addEventListener('change', function() {
        if (this.value > today) {
            alert('From date cannot be in the future');
            this.value = today;
        }
        if (dateTo.value && this.value > dateTo.value) {
            alert('From date cannot be after To date');
            this.value = dateTo.value;
        }
    });
    
    dateTo.addEventListener('change', function() {
        if (this.value > today) {
            alert('To date cannot be in the future');
            this.value = today;
        }
        if (dateFrom.value && this.value < dateFrom.value) {
            alert('To date cannot be before From date');
            this.value = dateFrom.value;
        }
    });
});
</script>

<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>