<?php
// admin/reports/milk/available-milk.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Available Milk Report";

// Fetch available milk using the view
$sql = "SELECT * FROM available_milk ORDER BY collection_date DESC, shift";
$result = $conn->query($sql);

// Calculate totals
$totals_sql = "SELECT 
                COUNT(*) as total_records,
                COALESCE(SUM(available_quantity), 0) as total_available
               FROM available_milk";
$totals = $conn->query($totals_sql)->fetch_assoc();

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>✓ Available Milk for Sale</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Available Milk</span>
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
        <div class="stat-card stat-success">
            <div class="stat-icon">✓</div>
            <div class="stat-details">
                <span class="stat-label">Total Records</span>
                <span class="stat-value"><?php echo $totals['total_records']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-primary">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Available for Sale</span>
                <span class="stat-value"><?php echo number_format($totals['total_available'], 2); ?> L</span>
            </div>
        </div>
    </div>

    <!-- Available Milk Table -->
    <div class="card" id="reportContent">
        <div class="card-header">
            <h3>📋 Available Milk Records</h3>
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
                            <th>Type</th>
                            <th>Breed</th>
                            <th>Total Collected</th>
                            <th>Sold</th>
                            <th>Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = 1;
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['collection_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['shift'] === 'Morning' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo $row['shift']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['tag_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['breed_name']); ?></td>
                                    <td><?php echo number_format($row['total_quantity'], 2); ?> L</td>
                                    <td><?php echo number_format($row['sold_quantity'], 2); ?> L</td>
                                    <td class="text-success"><strong><?php echo number_format($row['available_quantity'], 2); ?> L</strong></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="8" style="text-align: right;">Total Available:</td>
                                <td class="text-success"><?php echo number_format($totals['total_available'], 2); ?> L</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <span class="empty-icon">🥛</span>
                                        <p>No available milk for sale</p>
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