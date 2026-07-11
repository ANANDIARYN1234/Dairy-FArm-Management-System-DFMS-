<?php
// admin/reports/inventory/inventory-valuation.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Inventory Valuation Report";

// Note: This is a basic valuation. In real scenarios, you'd have purchase prices stored.
// For this demo, we'll show current quantities and allow estimated values

// Fetch all inventory items
$sql = "SELECT * FROM inventory ORDER BY category, item_name";
$result = $conn->query($sql);

// Calculate summary by category
$by_category_sql = "SELECT 
                    category,
                    COUNT(*) as item_count,
                    SUM(current_quantity) as total_quantity
                    FROM inventory
                    GROUP BY category
                    ORDER BY category";
$by_category = $conn->query($by_category_sql);

// Overall stats
$stats_sql = "SELECT 
              COUNT(*) as total_items,
              SUM(current_quantity) as total_stock_quantity,
              SUM(CASE WHEN current_quantity > minimum_quantity THEN 1 ELSE 0 END) as well_stocked
              FROM inventory";
$stats = $conn->query($stats_sql)->fetch_assoc();

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>💰 Inventory Status</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Inventory Valuation</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
        </div>
    </div>

    <!-- Summary Stats -->
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
                <span class="stat-label">Well Stocked</span>
                <span class="stat-value"><?php echo $stats['well_stocked']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Report Date</span>
                <span class="stat-value" style="font-size: 1.2rem;"><?php echo date('d M Y'); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⚖️</div>
            <div class="stat-details">
                <span class="stat-label">Categories</span>
                <span class="stat-value"><?php echo $by_category->num_rows; ?></span>
            </div>
        </div>
    </div>

    <!-- Detailed Inventory Valuation -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Current Inventory Status</h3>
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
                            <th>Stock Status</th>
                            <th>Health</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = 1;
                            while ($row = $result->fetch_assoc()): 
                                // Calculate stock health percentage
                                $stock_health = $row['minimum_quantity'] > 0 
                                    ? min(100, ($row['current_quantity'] / $row['minimum_quantity']) * 100) 
                                    : 100;
                                
                                if ($row['current_quantity'] == 0) {
                                    $status = 'Out of Stock';
                                    $status_class = 'danger';
                                    $health_class = 'danger';
                                } elseif ($row['current_quantity'] <= $row['minimum_quantity']) {
                                    $status = 'Low Stock';
                                    $status_class = 'warning';
                                    $health_class = 'warning';
                                } else {
                                    $status = 'Good';
                                    $status_class = 'success';
                                    $health_class = 'success';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $row['category']; ?></span></td>
                                    <td><strong><?php echo number_format($row['current_quantity'], 2); ?></strong> <?php echo $row['unit']; ?></td>
                                    <td><?php echo number_format($row['minimum_quantity'], 2); ?> <?php echo $row['unit']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="flex: 1; height: 20px; background: var(--border-color); border-radius: 10px; overflow: hidden;">
                                                <div style="height: 100%; background: var(--<?php echo $health_class; ?>); width: <?php echo $stock_health; ?>%;"></div>
                                            </div>
                                            <span style="font-size: 0.85rem; min-width: 45px;"><?php echo number_format($stock_health, 0); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="empty-icon">📦</span>
                                        <p>No inventory items</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- current inventory status ended -->

    <!-- By Category Summary -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Inventory by Category</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Item Count</th>
                            <th>Total Stock Units</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $by_category->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge badge-info"><?php echo $row['category']; ?></span></td>
                                <td><?php echo $row['item_count']; ?></td>
                                <td><strong><?php echo number_format($row['total_quantity'], 2); ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- By Category Summary ended -->


    <!-- Info Box -->
    <div class="info-box">
        <strong>ℹ Note:</strong>
        <ul>
            <li>This report shows current inventory status and stock health</li>
            <li>Stock health percentage = (Current Stock / Minimum Required) × 100</li>
            <li>Items with 0% health are out of stock</li>
            <li>Items below 100% health need restocking</li>
            <li>For detailed pricing and valuation, maintain purchase records in your accounting system</li>
        </ul>
    </div>
</div>

<script>
function exportPDF() {
    window.print();
}
</script>

<?php
$conn->close();
include '../../../includes/footer.php';
?>