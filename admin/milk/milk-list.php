<?php
/**
 * =========================================================
 * Milk Collection List
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/database-helpers.php';

require_admin();

// Filters - Default to today's date
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d');
$shift_filter = isset($_GET['shift']) ? clean($_GET['shift']) : '';
$cattle_filter = isset($_GET['cattle_id']) ? (int)$_GET['cattle_id'] : 0;
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Validate date range
$date_error = '';
if ($date_from && $date_to) {
    if (strtotime($date_from) > strtotime($date_to)) {
        $date_error = 'Start date cannot be after end date';
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
    }
    
    // Prevent future dates
    $today = date('Y-m-d');
    if (strtotime($date_from) > strtotime($today)) {
        $date_error = 'Start date cannot be in the future';
        $date_from = $today;
    }
    if (strtotime($date_to) > strtotime($today)) {
        $date_error = 'End date cannot be in the future';
        $date_to = $today;
    }
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($date_from && !$date_error) {
    $where_conditions[] = "mc.collection_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to && !$date_error) {
    $where_conditions[] = "mc.collection_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if ($shift_filter) {
    $where_conditions[] = "mc.shift = ?";
    $params[] = $shift_filter;
    $types .= "s";
}

if ($cattle_filter > 0) {
    $where_conditions[] = "mc.cattle_id = ?";
    $params[] = $cattle_filter;
    $types .= "i";
}

if ($search) {
    $where_conditions[] = "(c.tag_id LIKE ? OR ct.type_name LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total records
$count_sql = "SELECT COUNT(*) as total
              FROM milk_collection mc
              JOIN cattle c ON mc.cattle_id = c.cattle_id
              JOIN cattle_type ct ON c.type_id = ct.type_id
              JOIN user u ON mc.user_id = u.user_id
              WHERE {$where_clause}";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $records_per_page);

// Get paginated records
$sql = "SELECT mc.*, c.tag_id, ct.type_name, u.full_name as collected_by
        FROM milk_collection mc
        JOIN cattle c ON mc.cattle_id = c.cattle_id
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN user u ON mc.user_id = u.user_id
        WHERE {$where_clause}
        ORDER BY mc.collection_date DESC, mc.shift DESC
        LIMIT ? OFFSET ?";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $params[] = $records_per_page;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $records_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Get statistics for filtered results
$filtered_stats_sql = "SELECT 
                        COUNT(*) as collections,
                        COALESCE(SUM(mc.quantity), 0) as total_milk
                      FROM milk_collection mc
                      JOIN cattle c ON mc.cattle_id = c.cattle_id
                      JOIN cattle_type ct ON c.type_id = ct.type_id
                      WHERE {$where_clause}";

// Prepare params for stats queries (remove LIMIT params)
$stats_params = [];
$stats_types = "";
if (!empty($params)) {
    // Remove the last 2 params (LIMIT and OFFSET)
    $stats_params = array_slice($params, 0, -2);
    $stats_types = substr($types, 0, -2);
}

if (!empty($stats_params)) {
    $filtered_stats_stmt = $conn->prepare($filtered_stats_sql);
    $filtered_stats_stmt->bind_param($stats_types, ...$stats_params);
    $filtered_stats_stmt->execute();
    $filtered_stats = $filtered_stats_stmt->get_result()->fetch_assoc();
    $filtered_stats_stmt->close();
} else {
    $filtered_stats = $conn->query($filtered_stats_sql)->fetch_assoc();
}

// Get morning milk for filtered results
$morning_sql = "SELECT COALESCE(SUM(mc.quantity), 0) as total 
                FROM milk_collection mc
                JOIN cattle c ON mc.cattle_id = c.cattle_id
                JOIN cattle_type ct ON c.type_id = ct.type_id
                WHERE {$where_clause} AND mc.shift = 'Morning'";

if (!empty($stats_params)) {
    $morning_stmt = $conn->prepare($morning_sql);
    $morning_stmt->bind_param($stats_types, ...$stats_params);
    $morning_stmt->execute();
    $filtered_morning_milk = $morning_stmt->get_result()->fetch_assoc()['total'];
    $morning_stmt->close();
} else {
    $filtered_morning_milk = $conn->query($morning_sql)->fetch_assoc()['total'];
}

// Get evening milk for filtered results
$evening_sql = "SELECT COALESCE(SUM(mc.quantity), 0) as total 
                FROM milk_collection mc
                JOIN cattle c ON mc.cattle_id = c.cattle_id
                JOIN cattle_type ct ON c.type_id = ct.type_id
                WHERE {$where_clause} AND mc.shift = 'Evening'";

if (!empty($stats_params)) {
    $evening_stmt = $conn->prepare($evening_sql);
    $evening_stmt->bind_param($stats_types, ...$stats_params);
    $evening_stmt->execute();
    $filtered_evening_milk = $evening_stmt->get_result()->fetch_assoc()['total'];
    $evening_stmt->close();
} else {
    $filtered_evening_milk = $conn->query($evening_sql)->fetch_assoc()['total'];
}

// Get total for filtered results (for table footer)
$total_sql = "SELECT COALESCE(SUM(mc.quantity), 0) as total
              FROM milk_collection mc
              JOIN cattle c ON mc.cattle_id = c.cattle_id
              JOIN cattle_type ct ON c.type_id = ct.type_id
              WHERE {$where_clause}";

if (!empty($stats_params)) {
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->bind_param($stats_types, ...$stats_params);
    $total_stmt->execute();
    $filtered_total = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $total_stmt->close();
} else {
    $filtered_total = $conn->query($total_sql)->fetch_assoc()['total'] ?? 0;
}

// Check if filters are active
$filters_active = ($date_from != date('Y-m-d')) || ($date_to != date('Y-m-d')) || $shift_filter || $cattle_filter || $search;

$page_title = 'Milk Collection Records';
include '../../includes/header.php';
?>

<div class="page-header">
    <div class="header-content">
        <h1>🥛 Milk Collection Management</h1>
        <div class="breadcrumb">
            <a href="../dashboard.php">Dashboard</a>
            <span>/</span>
            <span>Milk Collection Management</span>
        </div>
    </div>
</div>

<!-- Filtered Statistics Alert -->
<!-- <?php if ($filters_active): ?>
    <div class="alert alert-info" style="margin-bottom: 1rem;">
        <span class="alert-icon">ℹ</span>
        <div class="alert-message">
            <strong>Filtered Results:</strong> Statistics below show filtered data only. 
            <a href="milk-list.php" style="color: var(--info); text-decoration: underline;">Clear filters</a> to see today's totals.
        </div>
    </div>
<?php endif; ?> -->

<!-- Statistics Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">
    <div class="stat-card success">
        <div class="stat-header">
            <span class="stat-title"><?php echo $filters_active ? 'Filtered' : "Today's"; ?> Total</span>
            <span class="stat-icon">🥛</span>
        </div>
        <div class="stat-value"><?php echo format_quantity($filtered_stats['total_milk']); ?> L</div>
        <div class="stat-label"><?php echo $filtered_stats['collections']; ?> collections</div>
    </div>

    <div class="stat-card info">
        <div class="stat-header">
            <span class="stat-title">Morning Shift</span>
            <span class="stat-icon">🌅</span>
        </div>
        <div class="stat-value"><?php echo format_quantity($filtered_morning_milk); ?> L</div>
        <div class="stat-label">Morning collection</div>
    </div>

    <div class="stat-card warning">
        <div class="stat-header">
            <span class="stat-title">Evening Shift</span>
            <span class="stat-icon">🌆</span>
        </div>
        <div class="stat-value"><?php echo format_quantity($filtered_evening_milk); ?> L</div>
        <div class="stat-label">Evening collection</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Milk Collection Records (<?php echo number_format($total_records); ?>)</h3>
        <a href="milk-add.php" class="btn btn-primary btn-sm">+ Add Collection</a>
    </div>

    <!-- Filters -->
    <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); background: var(--bg-tertiary);">
        <?php if ($date_error): ?>
            <div class="alert alert-error" style="margin-bottom: 1rem;">
                <span class="alert-icon">✕</span>
                <span class="alert-message"><?php echo $date_error; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>
        
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
            
            <div class="form-group" style="margin: 0;">
                <label style="display: block; margin-bottom: 0.35rem; font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">From Date</label>
                <input 
                    type="date" 
                    name="date_from" 
                    value="<?php echo htmlspecialchars($date_from); ?>"
                    max="<?php echo date('Y-m-d'); ?>"
                    style="height: 42px; padding: 0.5rem 1rem; width: 100%;"
                >
            </div>

            <div class="form-group" style="margin: 0;">
                <label style="display: block; margin-bottom: 0.35rem; font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">To Date</label>
                <input 
                    type="date" 
                    name="date_to" 
                    value="<?php echo htmlspecialchars($date_to); ?>"
                    max="<?php echo date('Y-m-d'); ?>"
                    style="height: 42px; padding: 0.5rem 1rem; width: 100%;"
                >
            </div>

            <div class="form-group" style="margin: 0;">
                <label style="display: block; margin-bottom: 0.35rem; font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">Shift</label>
                <select name="shift" style="height: 42px; padding: 0.5rem 2.5rem 0.5rem 1rem; width: 100%;">
                    <option value="">All Shifts</option>
                    <option value="Morning" <?php echo $shift_filter === 'Morning' ? 'selected' : ''; ?>>🌅 Morning</option>
                    <option value="Evening" <?php echo $shift_filter === 'Evening' ? 'selected' : ''; ?>>🌆 Evening</option>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label style="display: block; margin-bottom: 0.35rem; font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">Search Cattle</label>
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Tag ID or Type..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="height: 42px; padding: 0.5rem 1rem; width: 100%;"
                >
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.5rem; flex: 1;">
                    🔍 Filter
                </button>
                <a href="milk-list.php" class="btn btn-secondary" style="height: 42px; padding: 0 1.5rem; line-height: 42px; text-align: center; flex: 1; text-decoration: none;">
                    🔄 Reset
                </a>
            </div>
        </form>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <!-- Pagination Info -->
        <div style="padding: 1rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div style="color: var(--text-medium); font-size: 0.9rem;">
                📄 Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $records_per_page, $total_records); ?></strong> of <strong><?php echo number_format($total_records); ?></strong> records
            </div>
            <div style="color: var(--text-medium); font-size: 0.9rem;">
                📖 Page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Cattle Tag</th>
                        <th>Type</th>
                        <th>Quantity (L)</th>
                        <th>Collected By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($milk = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo display_date($milk['collection_date'], 'd M Y'); ?></td>
                            <td>
                                <span class="badge <?php echo $milk['shift'] === 'Morning' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo $milk['shift'] === 'Morning' ? '🌅' : '🌆'; ?> <?php echo $milk['shift']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="../cattle/cattle-view.php?id=<?php echo $milk['cattle_id']; ?>" style="color: var(--accent-blue); font-weight: 600;">
                                    <?php echo htmlspecialchars($milk['tag_id']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($milk['type_name']); ?></td>
                            <td><strong style="color: var(--success);"><?php echo format_quantity($milk['quantity']); ?> L</strong></td>
                            <td><?php echo htmlspecialchars($milk['collected_by']); ?></td>
                            <td>
                                <div style="display: flex; gap: 0.25rem;">
                                    <a href="milk-view.php?id=<?php echo $milk['milk_id']; ?>" class="btn btn-info btn-sm" title="View Details">👁️</a>
                                    <a href="milk-edit.php?id=<?php echo $milk['milk_id']; ?>" class="btn btn-secondary btn-sm" title="Edit">✏️</a>
                                    <a href="milk-delete.php?id=<?php echo $milk['milk_id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       title="Delete"
                                       onclick="return confirm('⚠️ Are you sure you want to delete this milk collection record?\n\nThis action cannot be undone!')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr style="background: var(--bg-tertiary); font-weight: bold;">
                        <td colspan="4" style="text-align: right; padding: 1rem;">
                            <strong>Total (Filtered Results):</strong>
                        </td>
                        <td colspan="3" style="padding: 1rem;">
                            <strong style="color: var(--success); font-size: 1.15rem;">
                                🥛 <?php echo format_quantity($filtered_total); ?> L
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div style="padding: 1.5rem; background: var(--bg-secondary); border-top: 1px solid var(--border-color);">
                <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <?php
                    // Build query string for pagination links
                    $query_params = [];
                    if ($date_from) $query_params['date_from'] = $date_from;
                    if ($date_to) $query_params['date_to'] = $date_to;
                    if ($shift_filter) $query_params['shift'] = $shift_filter;
                    if ($cattle_filter) $query_params['cattle_id'] = $cattle_filter;
                    if ($search) $query_params['search'] = $search;
                    
                    function build_pagination_url($page_num, $params) {
                        $params['page'] = $page_num;
                        return 'milk-list.php?' . http_build_query($params);
                    }
                    ?>
                    
                    <!-- First Page -->
                    <?php if ($page > 1): ?>
                        <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" title="First Page">⏮️ First</a>
                        <a href="<?php echo build_pagination_url($page - 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Previous">◀️ Prev</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">⏮️ First</button>
                        <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">◀️ Prev</button>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $range = 2; // Show 2 pages before and after current page
                    $start = max(1, $page - $range);
                    $end = min($total_pages, $page + $range);
                    
                    if ($start > 1): ?>
                        <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;">1</a>
                        <?php if ($start > 2): ?>
                            <span style="padding: 0.5rem; color: var(--text-medium); font-weight: bold;">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <button class="btn btn-primary btn-sm" style="min-width: 40px; font-weight: bold;"><?php echo $i; ?></button>
                        <?php else: ?>
                            <a href="<?php echo build_pagination_url($i, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <span style="padding: 0.5rem; color: var(--text-medium); font-weight: bold;">...</span>
                        <?php endif; ?>
                        <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                    
                    <!-- Next & Last Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo build_pagination_url($page + 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Next">Next ▶️</a>
                        <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm" title="Last Page">Last ⏭️</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">Next ▶️</button>
                        <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">Last ⏭️</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div style="text-align: center; padding: 4rem 2rem; color: var(--text-medium);">
            <p style="font-size: 4rem; margin-bottom: 1rem;">🥛</p>
            <p style="font-size: 1.3rem; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">No milk collection records found</p>
            <?php if ($date_from || $date_to || $shift_filter || $search): ?>
                <p style="margin-bottom: 1.5rem; color: var(--text-medium);">No records match your filter criteria</p>
                <a href="milk-list.php" class="btn btn-secondary">🔄 Clear All Filters</a>
            <?php else: ?>
                <p style="margin-bottom: 1.5rem; color: var(--text-medium);">Start tracking your daily milk production</p>
                <a href="milk-add.php" class="btn btn-primary">+ Add First Collection</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
if (isset($stmt)) $stmt->close();
$conn->close();
include '../../includes/footer.php'; 
?>