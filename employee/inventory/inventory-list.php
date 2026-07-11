<?php
// employee/inventory/inventory-list.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "View Inventory";

// Pagination
$records_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "item_name LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch inventory
$sql = "SELECT * FROM inventory 
        $where_clause
        ORDER BY item_name ASC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats = $conn->query("SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN current_quantity <= minimum_quantity THEN 1 ELSE 0 END) as low_stock_count
                       FROM inventory")->fetch_assoc();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📦 View Inventory</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Inventory</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="inventory-usage.php" class="btn btn-warning">📝 Record Usage</a>
            <!-- <a href="stock-request.php" class="btn btn-primary">📋 Request Stock</a> -->
        </div>
    </div>

    <!-- Info Alert -->
    <!-- <div class="alert alert-info">
        <span class="alert-icon">ℹ</span>
        <div class="alert-message">
            <strong>Read-Only Access:</strong>
            You can view inventory levels and record usage. Contact administrator for stock adjustments or new items.
        </div>
    </div> -->

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📦</div>
            <div class="stat-details">
                <span class="stat-label">Total Items</span>
                <span class="stat-value"><?php echo $stats['total_items']; ?></span>
            </div>
        </div>
        
        <!-- <div class="stat-card stat-danger">
            <div class="stat-icon">⚠</div>
            <div class="stat-details">
                <span class="stat-label">Low Stock Alerts</span>
                <span class="stat-value"><?php echo $stats['low_stock_count']; ?></span>
                <a href="#inventoryHeader"></a>
            </div>
        </div> -->
        <div class="stat-card stat-danger" onclick="filterLowStock()" style="cursor: pointer;">
            <div class="stat-icon">⚠</div>
            <div class="stat-details">
                <span class="stat-label">Low Stock Alerts</span>
                <span class="stat-value"><?php echo $stats['low_stock_count']; ?></span>
                <small>Click to view alerts</small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📝</div>
            <div class="stat-details">
                <span class="stat-label">Quick Actions</span>
                <span class="stat-value">2</span>
                <small style="color: var(--text-medium);">Items Usage</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">👤</div>
            <div class="stat-details">
                <span class="stat-label">Your Access</span>
                <span class="stat-value">View Only</span>
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
                               placeholder="Search by item name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <option value="Feed" <?php echo $category_filter === 'Feed' ? 'selected' : ''; ?>>Feed</option>
                            <option value="Medicine" <?php echo $category_filter === 'Medicine' ? 'selected' : ''; ?>>Medicine</option>
                            <option value="Fertilizer" <?php echo $category_filter === 'Fertilizer' ? 'selected' : ''; ?>>Fertilizer</option>
                            <option value="Supplement" <?php echo $category_filter === 'Supplement' ? 'selected' : ''; ?>>Supplement</option>
                            <option value="Equipment" <?php echo $category_filter === 'Equipment' ? 'selected' : ''; ?>>Equipment</option>
                            <option value="Other" <?php echo $category_filter === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="inventory-list.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card">
        <div class="card-header" id="inventoryHeader">
            <h3>📋 Inventory Items</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Minimum Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = $offset + 1;
                            while ($row = $result->fetch_assoc()): 
                                $is_low = $row['current_quantity'] <= $row['minimum_quantity'];
                                $stock_percentage = $row['minimum_quantity'] > 0 ? 
                                    ($row['current_quantity'] / $row['minimum_quantity']) * 100 : 100;
                            ?>
                                <!-- <tr> -->
                                <tr class="inventory-row <?php echo $row_class; ?>">    
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $row['category']; ?></span></td>
                                    <td><?php echo $row['unit']; ?></td>
                                    <td>
                                        <strong style="color: <?php echo $is_low ? 'var(--danger)' : 'var(--success)'; ?>">
                                            <?php echo number_format($row['current_quantity'], 2); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo number_format($row['minimum_quantity'], 2); ?></td>
                                    <td>
                                        <?php if ($is_low): ?>
                                            <span class="badge badge-danger">⚠ Low Stock</span>
                                        <?php elseif ($stock_percentage < 150): ?>
                                            <span class="badge badge-warning">⚡ Running Low</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">✓ Adequate</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="empty-icon">📦</span>
                                        <p>No inventory items found</p>
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>" 
                           class="page-link">« Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>" 
                           class="page-link">Next »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    //js to move to low stock alert click
    function filterLowStock() {
        // 1. Find all rows
        const allRows = document.querySelectorAll('.inventory-row');
        const adequateRows = document.querySelectorAll('.row-adequate');
        
        // 2. Hide rows that are adequate
        adequateRows.forEach(row => {
            row.style.display = 'none';
        });

        // 3. Ensure low stock rows are visible
        document.querySelectorAll('.row-low-stock').forEach(row => {
            row.style.display = 'table-row';
        });

        // 4. Scroll to the table
        document.getElementById('inventoryHeader').scrollIntoView({ behavior: 'smooth' });
    }
</script>
<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>