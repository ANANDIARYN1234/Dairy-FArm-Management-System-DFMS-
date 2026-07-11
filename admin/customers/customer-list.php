<?php
// admin/customers/customer-list.php 
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

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
    $where_conditions[] = "(c.customer_name LIKE ? OR c.phone LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($type_filter)) {
    $where_conditions[] = "c.customer_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total records
$count_sql = "SELECT COUNT(DISTINCT c.customer_id) as total FROM customer c WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// FIXED: Fetch customers with calculated balances using subqueries to avoid duplicate counting
$sql = "SELECT 
            c.customer_id,
            c.customer_name,
            c.customer_type,
            c.phone,
            c.address,
            c.advance_balance,
            c.status,
            COALESCE(
                (SELECT SUM(s.total_amount) 
                 FROM sales s 
                 WHERE s.customer_id = c.customer_id), 0
            ) as total_sales_amount,
            COALESCE(
                (SELECT SUM(p.amount_paid) 
                 FROM payment p 
                 JOIN sales s ON p.sales_id = s.sales_id 
                 WHERE s.customer_id = c.customer_id), 0
            ) as total_paid,
            (
                COALESCE(
                    (SELECT SUM(s.total_amount) 
                     FROM sales s 
                     WHERE s.customer_id = c.customer_id), 0
                ) - 
                COALESCE(
                    (SELECT SUM(p.amount_paid) 
                     FROM payment p 
                     JOIN sales s ON p.sales_id = s.sales_id 
                     WHERE s.customer_id = c.customer_id), 0
                )
            ) as outstanding_balance
        FROM customer c
        WHERE $where_clause
        ORDER BY c.customer_name
        LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();

// Get summary statistics - FIXED with subqueries
$stats_sql = "SELECT 
                COUNT(DISTINCT c.customer_id) as total_customers,
                COUNT(DISTINCT CASE WHEN c.status = 'Active' THEN c.customer_id END) as active_customers,
                COALESCE(SUM(c.advance_balance), 0) as total_advance,
                COALESCE(
                    (SELECT SUM(s.total_amount) FROM sales s), 0
                ) - COALESCE(
                    (SELECT SUM(p.amount_paid) FROM payment p), 0
                ) as total_outstanding
              FROM customer c";
$stats = $conn->query($stats_sql)->fetch_assoc();

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

    <!-- Summary Statistics -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">👥</div>
            <div class="stat-details">
                <span class="stat-label">Total Customers</span>
                <span class="stat-value"><?php echo $stats['total_customers']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">✓</div>
            <div class="stat-details">
                <span class="stat-label">Active Customers</span>
                <span class="stat-value"><?php echo $stats['active_customers']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">Total Advance</span>
                <span class="stat-value">रू <?php echo number_format($stats['total_advance'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Outstanding</span>
                <span class="stat-value">रू <?php echo number_format($stats['total_outstanding'], 2); ?></span>
            </div>
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
                                <th>S.N.</th>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Type</th>
                                <th>Phone</th>
                                <th>Advance Balance</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sn = isset($offset) ? $offset + 1 : 1; // Start from offset + 1 for pagination
                            while ($row = $customers->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $sn++; ?></td>
                                    <td><strong>#<?php echo $row['customer_id']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                    <td><?php echo get_customer_type_badge($row['customer_type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                    <td class="text-info">
                                        <strong>रू <?php echo number_format($row['advance_balance'], 2); ?></strong>
                                    </td>
                                    <td class="<?php echo $row['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong>रू <?php echo number_format($row['outstanding_balance'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="customer-view.php?id=<?php echo $row['customer_id']; ?>" 
                                               class="btn-action btn-info" title="View Details">👁️</a>
                                            <a href="customer-edit.php?id=<?php echo $row['customer_id']; ?>" 
                                               class="btn-action btn-warning" title="Edit">✏️</a>
                                            <?php if ($row['outstanding_balance'] > 0): ?>
                                                <a href="../sales/bulk-payment.php?customer_id=<?php echo $row['customer_id']; ?>" 
                                                   class="btn-action btn-success" title="Pay Outstanding">💳</a>
                                            <?php endif; ?>
                                            <a href="customer-delete.php?id=<?php echo $row['customer_id']; ?>" 
                                               class="btn-action btn-danger" title="Delete" 
                                               onclick="return confirm('Are you sure you want to delete this customer?');">🗑️</a>
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