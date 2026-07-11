<?php
// admin/reports/inventory/low-stock-alert.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Low Stock Alert";

// Fetch low stock items using the view
$sql = "SELECT * FROM low_stock_inventory ORDER BY shortage DESC";
$result = $conn->query($sql);

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>⚠ Low Stock Alert</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Low Stock Alert</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
        </div>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠</span>
            <div class="alert-message">
                <strong>Action Required!</strong>
                <p style="margin-top: 0.5rem;">
                    <strong><?php echo $result->num_rows; ?></strong> item(s) are running low or out of stock. Please restock soon!
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Low Stock Table -->
    <div class="card">
        <div class="card-header">
            <h3>📦 Items Requiring Attention</h3>
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
                            <th>Min Required</th>
                            <th>Shortage</th>
                            <th>Status</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = 1;
                            while ($row = $result->fetch_assoc()): 
                                $status = $row['current_quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
                                $status_class = $row['current_quantity'] == 0 ? 'danger' : 'warning';
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $row['category']; ?></span></td>
                                    <td><?php echo $row['unit']; ?></td>
                                    <td class="text-<?php echo $row['current_quantity'] == 0 ? 'danger' : 'warning'; ?>">
                                        <strong><?php echo number_format($row['current_quantity'], 2); ?></strong>
                                    </td>
                                    <td><?php echo number_format($row['minimum_quantity'], 2); ?></td>
                                    <td class="text-danger">
                                        <strong><?php echo number_format($row['shortage'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <a href="../../inventory/stock-in.php?id=<?php echo $row['inventory_id']; ?>" 
                                           class="btn-action btn-success" title="Add Stock">📥</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <span class="empty-icon">✓</span>
                                        <p style="color: var(--success);">All items are adequately stocked!</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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