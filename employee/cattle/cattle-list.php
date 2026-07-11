<?php
// employee/cattle/cattle-list.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "View Cattle";

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "c.tag_id LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
}

if (!empty($type_filter)) {
    $where_conditions[] = "ct.type_name = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "c.life_status = ?";// FIXED: Changed 'status' to 'life_status'
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM cattle c
              JOIN cattle_type ct ON c.type_id = ct.type_id
              JOIN breed b ON c.breed_id = b.breed_id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch cattle
$sql = "SELECT c.*, ct.type_name, b.breed_name
        FROM cattle c
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        $where_clause
        ORDER BY c.tag_id ASC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics - FIXED: Changed 'status' to 'life_status'
$stats_sql = "SELECT 
                COUNT(*) as total_cattle,
                SUM(CASE WHEN life_status = 'Alive' THEN 1 ELSE 0 END) as alive_count,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count";
$stats = $conn->query($stats_sql . " FROM cattle")->fetch_assoc();

// Get cattle types for filter
$types_sql = "SELECT DISTINCT type_name FROM cattle_type ORDER BY type_name";
$cattle_types = $conn->query($types_sql);

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🐄 View Cattle</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Cattle</span>
            </div>
        </div>
    </div>

    <!-- Info Alert -->
    <!-- <div class="alert alert-info">
        <span class="alert-icon">ℹ</span>
        <div class="alert-message">
            <strong>Read-Only Access:</strong>
            You can view cattle information but cannot add, edit, or delete records. 
            Contact your administrator for any changes needed.
        </div>
    </div> -->

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Total Cattle</span>
                <span class="stat-value"><?php echo $stats['total_cattle']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">✓</div>
            <div class="stat-details">
                <span class="stat-label">Alive</span>
                <span class="stat-value"><?php echo $stats['alive_count']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🐮</div>
            <div class="stat-details">
                <span class="stat-label">Female Cattle</span>
                <span class="stat-value"><?php echo $stats['female_count']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Milk Producers</span>
                <span class="stat-value"><?php echo $stats['female_count']; ?></span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Search & Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by tag ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            <?php while ($type = $cattle_types->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($type['type_name']); ?>"
                                        <?php echo $type_filter === $type['type_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Alive" <?php echo $status_filter === 'Alive' ? 'selected' : ''; ?>>Alive</option>
                            <option value="Pregnant" <?php echo $status_filter === 'Pregnant' ? 'selected' : ''; ?>>Pregnant</option>
                            <option value="Sold" <?php echo $status_filter === 'Sold' ? 'selected' : ''; ?>>Sold</option>
                            <option value="Dead" <?php echo $status_filter === 'Dead' ? 'selected' : ''; ?>>Dead</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="cattle-list.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Cattle Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Cattle List</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tag ID</th>
                            <th>Type</th>
                            <th>Breed</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = $offset + 1;
                            while ($row = $result->fetch_assoc()): 
                                $age_years = floor((strtotime('now') - strtotime($row['dob'])) / (365 * 24 * 60 * 60));
                                $age_months = floor(((strtotime('now') - strtotime($row['dob'])) % (365 * 24 * 60 * 60)) / (30 * 24 * 60 * 60));
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['tag_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['breed_name']); ?></td>
                                    <td>
                                        <?php if ($row['gender'] === 'Male'): ?>
                                            <span class="badge badge-info">♂ Male</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">♀ Female</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($age_years > 0): ?>
                                            <?php echo $age_years; ?>y <?php echo $age_months; ?>m
                                        <?php else: ?>
                                            <?php echo $age_months; ?> months
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'Alive' => 'success',
                                            'Pregnant' => 'warning',
                                            'Sold' => 'info',
                                            'Dead' => 'secondary'
                                        ];
                                        $life_status = $row['life_status']; // FIXED: Changed from 'status' to 'life_status'
                                        ?>
                                        <span class="badge badge-<?php echo $status_class[$life_status] ?? 'secondary'; ?>">
                                            <?php echo htmlspecialchars($life_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="cattle-view.php?id=<?php echo $row['cattle_id']; ?>" 
                                           class="btn-action btn-info" title="View Details">👁</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <span class="empty-icon">🐄</span>
                                        <p>No cattle found</p>
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="page-link">« Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="page-link">Next »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>