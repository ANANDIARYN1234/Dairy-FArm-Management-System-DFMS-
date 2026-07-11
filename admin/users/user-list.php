<?php
// admin/users/user-list.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Employee Management";

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause - ONLY show employees (role_id = 2)
$where_conditions = ["r.role_name = 'Employee'"];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.contact LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM user u
              JOIN role r ON u.role_id = r.role_id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch users (employees only)
$sql = "SELECT u.*, r.role_name
        FROM user u
        JOIN role r ON u.role_id = r.role_id
        $where_clause
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics (employees only)
$stats_sql = "SELECT 
                COUNT(*) as total_employees,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_employees,
                SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive_employees
              FROM user u
              JOIN role r ON u.role_id = r.role_id
              WHERE r.role_name = 'Employee'";
$stats = $conn->query($stats_sql)->fetch_assoc();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👥 Employee Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Employees</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <a href="role-management.php" class="btn btn-info">🔐 View Roles</a> -->
            <a href="user-add.php" class="btn btn-primary">➕ Add Employee</a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">👥</div>
            <div class="stat-details">
                <span class="stat-label">Total Employees</span>
                <span class="stat-value"><?php echo $stats['total_employees']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">✓</div>
            <div class="stat-details">
                <span class="stat-label">Active Employees</span>
                <span class="stat-value"><?php echo $stats['active_employees']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⏸️</div>
            <div class="stat-details">
                <span class="stat-label">Inactive Employees</span>
                <span class="stat-value"><?php echo $stats['inactive_employees']; ?></span>
            </div>
        </div>
        
        <!-- <div class="stat-card stat-info">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Activity Rate</span>
                <span class="stat-value">
                    <?php 
                    $rate = $stats['total_employees'] > 0 
                        ? round(($stats['active_employees'] / $stats['total_employees']) * 100) 
                        : 0;
                    echo $rate . '%';
                    ?>
                </span>
            </div>
        </div> -->
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Search & Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name, email or contact..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="user-list.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Employee Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Employee List (<?php echo number_format($total_records); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = $offset + 1;
                            $current_user_id = get_user_id();
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                        <?php if ($row['user_id'] == $current_user_id): ?>
                                            <span class="badge badge-info" style="font-size: 0.7rem;">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Active'): ?>
                                            <span class="badge badge-success">✓ Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">⏸️ Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="user-view.php?id=<?php echo $row['user_id']; ?>" 
                                               class="btn-action btn-info" title="View Details">👁️</a>
                                            <a href="user-edit.php?id=<?php echo $row['user_id']; ?>" 
                                               class="btn-action btn-warning" title="Edit">✏️</a>
                                            <?php if ($row['user_id'] != $current_user_id): ?>
                                                <a href="user-delete.php?id=<?php echo $row['user_id']; ?>" 
                                                   class="btn-action btn-danger" title="Delete"
                                                   onclick="return confirm('⚠️ Are you sure you want to delete this employee?\n\nThis action cannot be undone!');">🗑️</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="empty-icon">👥</span>
                                        <p style="font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem;">No employees found</p>
                                        <?php if ($search || $status_filter): ?>
                                            <p style="color: var(--text-medium); margin-bottom: 1rem;">Try adjusting your filters</p>
                                            <a href="user-list.php" class="btn btn-secondary">Clear Filters</a>
                                        <?php else: ?>
                                            <p style="color: var(--text-medium); margin-bottom: 1rem;">Get started by adding your first employee</p>
                                            <a href="user-add.php" class="btn btn-primary">Add Employee</a>
                                        <?php endif; ?>
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
                    <?php
                    // Build query parameters
                    $query_params = [];
                    if ($search) $query_params['search'] = $search;
                    if ($status_filter) $query_params['status'] = $status_filter;
                    
                    function build_page_url($page_num, $params) {
                        $params['page'] = $page_num;
                        return 'user-list.php?' . http_build_query($params);
                    }
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo build_page_url($page - 1, $query_params); ?>" 
                           class="page-link">« Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="<?php echo build_page_url($i, $query_params); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo build_page_url($page + 1, $query_params); ?>" 
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