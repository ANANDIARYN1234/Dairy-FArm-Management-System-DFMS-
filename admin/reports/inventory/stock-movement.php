<?php
// admin/reports/inventory/stock-movement.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Stock Movement Report";

// Date filters with validation
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Validation: Check if dates are swapped
$date_error = '';
if (strtotime($date_from) > strtotime($date_to)) {
    $date_error = 'Error: "From Date" cannot be later than "To Date". Please correct the date range.';
    // Swap dates automatically
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_error = 'Invalid date format. Please use a valid date.';
    $date_from = date('Y-m-d');
    $date_to = date('Y-m-d');
}

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM inventory_transaction it
              WHERE it.transaction_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("ss", $date_from, $date_to);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch stock movements with pagination
$sql = "SELECT 
        it.*,
        i.item_name,
        i.category,
        i.unit,
        u.full_name as created_by
        FROM inventory_transaction it
        JOIN inventory i ON it.inventory_id = i.inventory_id
        JOIN user u ON it.user_id = u.user_id
        WHERE it.transaction_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY it.transaction_date DESC, it.transaction_id DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $date_from, $date_to, $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Calculate totals
$totals_sql = "SELECT 
               COUNT(*) as total_transactions,
               SUM(CASE WHEN transaction_type = 'IN' THEN quantity ELSE 0 END) as total_in,
               SUM(CASE WHEN transaction_type = 'OUT' THEN quantity ELSE 0 END) as total_out,
               SUM(CASE WHEN transaction_type = 'ADJUSTMENT' THEN 1 ELSE 0 END) as total_adjustments
               FROM inventory_transaction
               WHERE transaction_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
$totals_stmt = $conn->prepare($totals_sql);
$totals_stmt->bind_param("ss", $date_from, $date_to);
$totals_stmt->execute();
$totals = $totals_stmt->get_result()->fetch_assoc();

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📊 Stock Movement Report</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Stock Movement</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card no-print">
        <div class="card-body">
            <form method="GET" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="stock-movement.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
            
            <?php if ($date_error): ?>
                <div class="alert alert-error" style="margin-top: 1rem;">
                    <span class="alert-icon">⚠️</span>
                    <span><?php echo htmlspecialchars($date_error); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Transactions</span>
                <span class="stat-value"><?php echo number_format($totals['total_transactions'] ?? 0); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">📥</div>
            <div class="stat-details">
                <span class="stat-label">Stock IN</span>
                <span class="stat-value"><?php echo number_format($totals['total_in'] ?? 0, 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">📤</div>
            <div class="stat-details">
                <span class="stat-label">Stock OUT</span>
                <span class="stat-value"><?php echo number_format($totals['total_out'] ?? 0, 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">⚖️</div>
            <div class="stat-details">
                <span class="stat-label">Adjustments</span>
                <span class="stat-value"><?php echo number_format($totals['total_adjustments'] ?? 0); ?></span>
            </div>
        </div>
    </div>

    <!-- Stock Movement Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Stock Movement Details (<?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>)</h3>
            <p style="margin: 0.5rem 0 0 0; color: var(--text-medium); font-size: 0.9rem;">
                Showing <?php echo min($offset + 1, $total_records); ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo number_format($total_records); ?> records
            </p>
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
                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                    <td>
                                        <span class="badge badge-<?php echo $type_badge; ?>">
                                            <?php echo $type_icon; ?> <?php echo htmlspecialchars($row['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($row['quantity'], 2); ?></strong> <?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td><?php echo htmlspecialchars($row['remarks'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <span class="empty-icon">📊</span>
                                        <p>No stock movements for selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div style="padding: 1rem; background: var(--bg-secondary); border-top: 1px solid var(--border-color);">
                <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <?php
                    // Build query string for pagination links
                    $query_params = [];
                    if (isset($_GET['date_from'])) $query_params['date_from'] = $date_from;
                    if (isset($_GET['date_to'])) $query_params['date_to'] = $date_to;
                    
                    function build_pagination_url($page_num, $params) {
                        $params['page'] = $page_num;
                        return 'stock-movement.php?' . http_build_query($params);
                    }
                    ?>
                    
                    <!-- First Page -->
                    <?php if ($page > 1): ?>
                        <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" title="First Page">⏮️</a>
                        <a href="<?php echo build_pagination_url($page - 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Previous">◀️</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled>⏮️</button>
                        <button class="btn btn-secondary btn-sm" disabled>◀️</button>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $range = 2; // Show 2 pages before and after current page
                    $start = max(1, $page - $range);
                    $end = min($total_pages, $page + $range);
                    
                    if ($start > 1): ?>
                        <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm">1</a>
                        <?php if ($start > 2): ?>
                            <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <button class="btn btn-primary btn-sm" style="min-width: 40px;"><?php echo $i; ?></button>
                        <?php else: ?>
                            <a href="<?php echo build_pagination_url($i, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                        <?php endif; ?>
                        <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                    
                    <!-- Next & Last Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo build_pagination_url($page + 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Next">▶️</a>
                        <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm" title="Last Page">⏭️</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled>▶️</button>
                        <button class="btn btn-secondary btn-sm" disabled>⏭️</button>
                    <?php endif; ?>
                </div>
                
                <!-- Page info text -->
                <div style="text-align: center; margin-top: 1rem; color: var(--text-medium); font-size: 0.9rem;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Client-side date validation
document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
    const dateFrom = new Date(document.getElementById('date_from').value);
    const dateTo = new Date(document.getElementById('date_to').value);
    
    if (dateFrom > dateTo) {
        e.preventDefault();
        alert('Error: "From Date" cannot be later than "To Date". Please correct the date range.');
        return false;
    }
});

function exportPDF() {
    window.print();
}
</script>

<?php
$stmt->close();
$count_stmt->close();
$totals_stmt->close();
$conn->close();
include '../../../includes/footer.php';
?>