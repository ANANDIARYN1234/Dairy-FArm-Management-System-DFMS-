<?php
// employee/milk/milk-list.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "View Milk Records";
$user_id = get_user_id();

// Pagination
$records_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$shift_filter = isset($_GET['shift']) ? $_GET['shift'] : '';
$cattle_filter = isset($_GET['cattle']) ? $_GET['cattle'] : '';

// Build WHERE clause - Employee can only see their own records
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

if (!empty($cattle_filter)) {
    $where_conditions[] = "c.tag_id LIKE ?";
    $cattle_param = "%$cattle_filter%";
    $params[] = $cattle_param;
    $types .= 's';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM milk_collection mc
              JOIN cattle c ON mc.cattle_id = c.cattle_id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch milk records
$sql = "SELECT mc.*, c.tag_id, ct.type_name, b.breed_name
        FROM milk_collection mc
        JOIN cattle c ON mc.cattle_id = c.cattle_id
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        $where_clause
        ORDER BY mc.collection_date DESC, mc.shift DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics for selected period
$stats_sql = "SELECT 
                COUNT(*) as total_collections,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(AVG(quantity), 0) as avg_quantity,
                COUNT(DISTINCT cattle_id) as unique_cattle
              FROM milk_collection mc
              $where_clause";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param($types, ...$params);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🥛 View Milk Records</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Milk Records</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="my-collections.php" class="btn btn-info">📋 My Collections</a>
            <a href="milk-add.php" class="btn btn-primary">➕ Add Collection</a>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info">
        <span class="alert-icon">ℹ</span>
        <div class="alert-message">
            <strong>Your Records:</strong>
            You are viewing only the milk collections that you have recorded. 
            Use filters to search specific dates or cattle.
        </div>
    </div>

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
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Unique Cattle</span>
                <span class="stat-value"><?php echo $stats['unique_cattle']; ?></span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Filter Records</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Shift</label>
                        <select name="shift" class="form-control">
                            <option value="">All Shifts</option>
                            <option value="Morning" <?php echo $shift_filter === 'Morning' ? 'selected' : ''; ?>>🌅 Morning</option>
                            <option value="Evening" <?php echo $shift_filter === 'Evening' ? 'selected' : ''; ?>>🌆 Evening</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Cattle Tag</label>
                        <input type="text" name="cattle" class="form-control" 
                               placeholder="Search cattle..." 
                               value="<?php echo htmlspecialchars($cattle_filter); ?>">
                    </div>
                    
                    <div class="form-actions" style="align-self: end;">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="milk-list.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Milk Records Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Milk Collection Records</h3>
        </div>
        <div class="card-body">
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
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = $offset + 1;
                            $current_date = '';
                            while ($row = $result->fetch_assoc()): 
                                $date_display = date('d M Y', strtotime($row['collection_date']));
                                $show_date_header = ($current_date !== $date_display);
                                $current_date = $date_display;
                            ?>
                                <?php if ($show_date_header): ?>
                                    <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                        <td colspan="7" style="padding: 0.75rem;">
                                            📅 <?php echo $date_display; ?>
                                            <?php
                                            // Calculate day total
                                            $day_sql = "SELECT COALESCE(SUM(quantity), 0) as day_total 
                                                       FROM milk_collection 
                                                       WHERE user_id = ? AND collection_date = ?";
                                            $day_stmt = $conn->prepare($day_sql);
                                            $day_stmt->bind_param("is", $user_id, $row['collection_date']);
                                            $day_stmt->execute();
                                            $day_total = $day_stmt->get_result()->fetch_assoc()['day_total'];
                                            $day_stmt->close();
                                            ?>
                                            <span style="float: right; color: var(--success);">
                                                Day Total: <?php echo number_format($day_total, 2); ?> L
                                            </span>
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
                                    <td>
                                        <small><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="5" style="text-align: right; padding: 1rem;">
                                    Period Total (<?php echo date('d M', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>):
                                </td>
                                <td style="padding: 1rem;">
                                    <strong style="color: var(--success); font-size: 1.1rem;">
                                        <?php echo number_format($stats['total_quantity'], 2); ?> L
                                    </strong>
                                </td>
                                <td></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="empty-icon">🥛</span>
                                        <p>No milk collections found for the selected period</p>
                                        <a href="milk-add.php" class="btn btn-primary" style="margin-top: 1rem;">
                                            ➕ Add Your First Collection
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&shift=<?php echo $shift_filter; ?>&cattle=<?php echo urlencode($cattle_filter); ?>" 
                           class="page-link">« Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&shift=<?php echo $shift_filter; ?>&cattle=<?php echo urlencode($cattle_filter); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&shift=<?php echo $shift_filter; ?>&cattle=<?php echo urlencode($cattle_filter); ?>" 
                           class="page-link">Next »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>⚡ Quick Actions</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="milk-add.php" class="btn btn-success" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">➕</div>
                    <strong>Add New Collection</strong>
                </a>
                
                <a href="my-collections.php" class="btn btn-info" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                    <strong>My Collections History</strong>
                </a>
                
                <a href="../reports/my-milk-records.php" class="btn btn-primary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                    <strong>View Full Report</strong>
                </a>
                
                <a href="../cattle/cattle-list.php" class="btn btn-secondary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🐄</div>
                    <strong>View Cattle List</strong>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>