<?php
// employee/customers/customer-list.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Customers";

// Pagination
$records_per_page = 15;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Search & Filter
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? clean($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : '';

// Build query
$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(customer_name LIKE ? OR phone LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($type_filter)) {
    $where_conditions[] = "customer_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM customer WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// Fetch customers
$sql = "SELECT * FROM customer WHERE $where_clause ORDER BY customer_name LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👥 Customers</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Customers</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="customer-add.php" class="btn btn-primary">➕ Add New Customer</a>
            <a href="customer-balance-add.php" class="btn btn-secondary">📥 Add Balance</a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Search & Filter</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" action="" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            <option value="Retail" <?php echo $type_filter === 'Retail' ? 'selected' : ''; ?>>🛒 Retail</option>
                            <option value="Wholesale" <?php echo $type_filter === 'Wholesale' ? 'selected' : ''; ?>>📦 Wholesale</option>
                            <option value="Dairy" <?php echo $type_filter === 'Dairy' ? 'selected' : ''; ?>>🏭 Dairy</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="customer-list.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Customer List (<?php echo $total_records; ?> total)</h3>
        </div>
        <div class="card-body">
            <?php if ($customers->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Type</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $customers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['customer_id']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                    <td><?php echo get_customer_type_badge($row['customer_type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($row['address'] ?? 'N/A', 0, 30)); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="customer-view.php?id=<?php echo $row['customer_id']; ?>" 
                                               class="btn-action btn-info" title="View Details">
                                                👁️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="page-link">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                               class="page-link">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">👥</span>
                    <p>No customers found</p>
                    <a href="customer-add.php" class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Add Your First Customer
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../../includes/footer.php';
?>