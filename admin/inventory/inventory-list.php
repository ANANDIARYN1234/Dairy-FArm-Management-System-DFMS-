<?php
// admin/inventory/inventory-list.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Inventory Management";

// Pagination
$records_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : ''; // all, low, out

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

if ($stock_filter === 'low') {
    $where_conditions[] = "current_quantity <= minimum_quantity AND current_quantity > 0";
} elseif ($stock_filter === 'out') {
    $where_conditions[] = "current_quantity = 0";
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

// Fetch inventory items
$sql = "SELECT * FROM inventory 
        $where_clause
        ORDER BY 
            CASE 
                WHEN current_quantity = 0 THEN 1
                WHEN current_quantity <= minimum_quantity THEN 2
                ELSE 3
            END,
            item_name ASC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN current_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN current_quantity <= minimum_quantity AND current_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN current_quantity > minimum_quantity THEN 1 ELSE 0 END) as in_stock
              FROM inventory";
$stats = $conn->query($stats_sql)->fetch_assoc();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📦 Inventory Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Inventory</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="transaction-history.php" class="btn btn-secondary">📜 Transaction History</a>
            <a href="inventory-add.php" class="btn btn-primary">➕ Add Item</a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📦</div>
            <div class="stat-details">
                <span class="stat-label">Total Items</span>
                <span class="stat-value"><?php echo $stats['total_items']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">✓</div>
            <div class="stat-details">
                <span class="stat-label">In Stock</span>
                <span class="stat-value"><?php echo $stats['in_stock']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⚠</div>
            <div class="stat-details">
                <span class="stat-label">Low Stock</span>
                <span class="stat-value"><?php echo $stats['low_stock']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">✕</div>
            <div class="stat-details">
                <span class="stat-label">Out of Stock</span>
                <span class="stat-value"><?php echo $stats['out_of_stock']; ?></span>
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
                               placeholder="Search item name..." 
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
                    
                    <div class="form-group">
                        <select name="stock" class="form-control">
                            <option value="">All Stock Levels</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
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
        <div class="card-header">
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
                            <th>Current Stock</th>
                            <th>Minimum Required</th>
                            <th>Shortage</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = $offset + 1;
                            while ($row = $result->fetch_assoc()): 
                                $shortage = max(0, $row['minimum_quantity'] - $row['current_quantity']);
                                
                                // Determine status
                                if ($row['current_quantity'] == 0) {
                                    $status = 'Out of Stock';
                                    $status_class = 'danger';
                                    $status_icon = '✕';
                                } elseif ($row['current_quantity'] <= $row['minimum_quantity']) {
                                    $status = 'Low Stock';
                                    $status_class = 'warning';
                                    $status_icon = '⚠';
                                } else {
                                    $status = 'In Stock';
                                    $status_class = 'success';
                                    $status_icon = '✓';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $row['category']; ?></span></td>
                                    <td>
                                        <strong><?php echo number_format($row['current_quantity'], 2); ?></strong> 
                                        <?php echo $row['unit']; ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($row['minimum_quantity'], 2); ?> 
                                        <?php echo $row['unit']; ?>
                                    </td>
                                    <td class="<?php echo $shortage > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong><?php echo number_format($shortage, 2); ?> <?php echo $row['unit']; ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo $status_icon; ?> <?php echo $status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="stock-in.php?id=<?php echo $row['inventory_id']; ?>" 
                                               class="btn-action btn-success" title="Stock In">📥</a>
                                            <a href="stock-out.php?id=<?php echo $row['inventory_id']; ?>" 
                                               class="btn-action btn-warning" title="Stock Out">📤</a>
                                            <a href="inventory-edit.php?id=<?php echo $row['inventory_id']; ?>" 
                                               class="btn-action btn-info" title="Edit">✏️</a>
                                            <a href="inventory-delete.php?id=<?php echo $row['inventory_id']; ?>" 
                                               class="btn-action btn-danger" title="Delete"
                                               onclick="return confirm('Delete this item?');">🗑</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>" 
                           class="page-link">« Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>" 
                           class="page-link">Next »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <!-- <?php if ($stats['low_stock'] > 0 || $stats['out_of_stock'] > 0): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠</span>
            <div class="alert-message">
                <strong>Stock Alert!</strong>
                <p style="margin-top: 0.5rem;">
                    <?php if ($stats['out_of_stock'] > 0): ?>
                        <strong><?php echo $stats['out_of_stock']; ?></strong> item(s) are out of stock. 
                    <?php endif; ?>
                    <?php if ($stats['low_stock'] > 0): ?>
                        <strong><?php echo $stats['low_stock']; ?></strong> item(s) are running low. 
                    <?php endif; ?>
                    Please restock soon!
                </p>
            </div>
        </div>
    <?php endif; ?> -->
</div>

<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>