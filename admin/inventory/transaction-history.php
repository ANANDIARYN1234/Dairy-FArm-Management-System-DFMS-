<?php
// admin/inventory/transaction-history.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Transaction History";

// Pagination
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$item_filter = isset($_GET['item']) ? intval($_GET['item']) : 0;
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($item_filter > 0) {
    $where_conditions[] = "it.inventory_id = ?";
    $params[] = $item_filter;
    $types .= 'i';
}

if (!empty($type_filter)) {
    $where_conditions[] = "it.transaction_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "it.transaction_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "it.transaction_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM inventory_transaction it
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch transactions
$sql = "SELECT 
            it.*,
            i.item_name,
            i.category,
            i.unit,
            u.full_name as created_by
        FROM inventory_transaction it
        JOIN inventory i ON it.inventory_id = i.inventory_id
        JOIN user u ON it.user_id = u.user_id
        $where_clause
        ORDER BY it.transaction_date DESC, it.transaction_id DESC
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
                COUNT(*) as total_transactions,
                SUM(CASE WHEN transaction_type = 'IN' THEN 1 ELSE 0 END) as in_transactions,
                SUM(CASE WHEN transaction_type = 'OUT' THEN 1 ELSE 0 END) as out_transactions,
                SUM(CASE WHEN transaction_type = 'ADJUSTMENT' THEN 1 ELSE 0 END) as adjustment_transactions
              FROM inventory_transaction";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get inventory items for filter
$items_sql = "SELECT inventory_id, item_name FROM inventory ORDER BY item_name";
$items = $conn->query($items_sql);

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📜 Transaction History</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Transaction History</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="inventory-list.php" class="btn btn-primary">← Back to Inventory</a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Transactions</span>
                <span class="stat-value"><?php echo $stats['total_transactions']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">📥</div>
            <div class="stat-details">
                <span class="stat-label">Stock In</span>
                <span class="stat-value"><?php echo $stats['in_transactions']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📤</div>
            <div class="stat-details">
                <span class="stat-label">Stock Out</span>
                <span class="stat-value"><?php echo $stats['out_transactions']; ?></span>
            </div>
        </div>
        
        <!-- <div class="stat-card stat-info">
            <div class="stat-icon">⚖️</div>
            <div class="stat-details">
                <span class="stat-label">Adjustments</span>
                <span class="stat-value"><?php echo $stats['adjustment_transactions']; ?></span>
            </div>
        </div> -->
    </div>

    <!-- Filters -->
    <div class="card no-print">
        <div class="card-header">
            <h3>🔍 Search & Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="form-group">
                        <select name="item" class="form-control">
                            <option value="">All Items</option>
                            <?php while ($item = $items->fetch_assoc()): ?>
                                <option value="<?php echo $item['inventory_id']; ?>"
                                        <?php echo $item_filter == $item['inventory_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            <option value="IN" <?php echo $type_filter === 'IN' ? 'selected' : ''; ?>>Stock In</option>
                            <option value="OUT" <?php echo $type_filter === 'OUT' ? 'selected' : ''; ?>>Stock Out</option>
                            <option value="ADJUSTMENT" <?php echo $type_filter === 'ADJUSTMENT' ? 'selected' : ''; ?>>Adjustment</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <input type="date" name="date_from" class="form-control" 
                               placeholder="From Date" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <input type="date" name="date_to" class="form-control" 
                               placeholder="To Date" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="transaction-history.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Transaction Records</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Remarks</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = $offset + 1;
                            while ($row = $result->fetch_assoc()): 
                                // Determine type badge
                                if ($row['transaction_type'] === 'IN') {
                                    $type_badge = 'success';
                                    $type_icon = '📥';
                                } elseif ($row['transaction_type'] === 'OUT') {
                                    $type_badge = 'warning';
                                    $type_icon = '📤';
                                } else {
                                    $type_badge = 'info';
                                    $type_icon = '⚖️';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['transaction_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-secondary"><?php echo $row['category']; ?></span></td>
                                    <td>
                                        <span class="badge badge-<?php echo $type_badge; ?>">
                                            <?php echo $type_icon; ?> <?php echo $row['transaction_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($row['quantity'], 2); ?></strong> 
                                        <?php echo $row['unit']; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['remarks'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <span class="empty-icon">📜</span>
                                        <p>No transactions found</p>
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
                        <a href="?page=<?php echo $page - 1; ?>&item=<?php echo $item_filter; ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                           class="page-link">« Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&item=<?php echo $item_filter; ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&item=<?php echo $item_filter; ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
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