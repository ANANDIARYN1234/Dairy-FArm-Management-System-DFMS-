<?php
// employee/sales/sales-list.php 
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "My Sales";
$user_id = get_user_id();

// Pagination - Default 10 records per page
$records_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Date validation errors
$date_errors = [];

// Filters
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : '';
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : '';

// Validate dates
$today = date('Y-m-d');

if (!empty($date_from)) {
    // Check if date_from is valid format
    $date_check = DateTime::createFromFormat('Y-m-d', $date_from);
    if (!$date_check || $date_check->format('Y-m-d') !== $date_from) {
        $date_errors[] = "Invalid 'From Date' format";
        $date_from = '';
    } elseif ($date_from > $today) {
        $date_errors[] = "From Date cannot be in the future";
        $date_from = '';
    }
}

if (!empty($date_to)) {
    // Check if date_to is valid format
    $date_check = DateTime::createFromFormat('Y-m-d', $date_to);
    if (!$date_check || $date_check->format('Y-m-d') !== $date_to) {
        $date_errors[] = "Invalid 'To Date' format";
        $date_to = '';
    } elseif ($date_to > $today) {
        $date_errors[] = "To Date cannot be in the future";
        $date_to = '';
    }
}

// Check if from_date is after to_date
if (!empty($date_from) && !empty($date_to) && $date_from > $date_to) {
    $date_errors[] = "From Date cannot be after To Date";
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Build query - Employee can only see their own sales
$where_conditions = ["s.user_id = ?"];
$params = [$user_id];
$types = "i";

if (!empty($date_from) && empty($date_errors)) {
    $where_conditions[] = "s.sales_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to) && empty($date_errors)) {
    $where_conditions[] = "s.sales_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "s.sales_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM sales s WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// If current page exceeds total pages, reset to page 1
if ($page > $total_pages && $total_pages > 0) {
    $page = 1;
    $offset = 0;
}

// Fetch sales
$sql = "SELECT s.*, c.customer_name, c.customer_type, c.phone,
               COALESCE(SUM(p.amount_paid), 0) as total_paid,
               (s.total_amount - COALESCE(SUM(p.amount_paid), 0)) as balance
        FROM sales s
        JOIN customer c ON s.customer_id = c.customer_id
        LEFT JOIN payment p ON s.sales_id = p.sales_id
        WHERE $where_clause
        GROUP BY s.sales_id
        ORDER BY s.sales_date DESC, s.sales_id DESC
        LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();
$stmt->close();

// Get summary statistics for employee
$summary_sql = "SELECT 
                  COUNT(*) as total_sales,
                  COALESCE(SUM(total_quantity), 0) as total_quantity,
                  COALESCE(SUM(total_amount), 0) as total_amount,
                  COALESCE(SUM(CASE WHEN sales_status = 'Paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
                  COALESCE(SUM(CASE WHEN sales_status = 'Due' THEN total_amount ELSE 0 END), 0) as due_amount
                FROM sales WHERE user_id = ?";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("i", $user_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

// Build query params for pagination
$query_params = [];
if ($date_from) $query_params['date_from'] = $date_from;
if ($date_to) $query_params['date_to'] = $date_to;
if ($status_filter) $query_params['status'] = $status_filter;

function build_pagination_url($page_num, $params) {
    $params['page'] = $page_num;
    return 'sales-list.php?' . http_build_query($params);
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🛒 My Sales</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>My Sales</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="sales-add.php" class="btn btn-primary">➕ Add New Sale</a>
            
        </div>
    </div>

    <!-- Date Validation Errors -->
    <?php if (!empty($date_errors)): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠</span>
            <div class="alert-message">
                <strong>Date Validation Error:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($date_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🛒</div>
            <div class="stat-details">
                <span class="stat-label">Total Sales</span>
                <span class="stat-value"><?php echo $summary['total_sales']; ?></span>
                <small style="color: var(--text-medium);">All time</small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Quantity Sold</span>
                <span class="stat-value"><?php echo number_format($summary['total_quantity'], 2); ?> L</span>
                <small style="color: var(--text-medium);">Milk sold</small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Total Revenue</span>
                <span class="stat-value">रू <?php echo number_format($summary['total_amount'], 2); ?></span>
                <small style="color: var(--text-medium);">Generated</small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-details">
                <span class="stat-label">Pending Amount</span>
                <span class="stat-value">रू <?php echo number_format($summary['due_amount'], 2); ?></span>
                <small style="color: var(--text-medium);">Outstanding</small>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card">
        <div class="card-header">
            <h3>🔍 Filter Sales</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               max="<?php echo $today; ?>"
                               value="<?php echo htmlspecialchars($date_from); ?>">
                        <!-- <small class="form-hint">Cannot be in the future</small> -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               max="<?php echo $today; ?>"
                               value="<?php echo htmlspecialchars($date_to); ?>">
                        <!-- <small class="form-hint">Cannot be in the future</small> -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Partial" <?php echo $status_filter === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="Due" <?php echo $status_filter === 'Due' ? 'selected' : ''; ?>>Due</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="sales-list.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Sales List</h3>
        </div>
        
        <?php if ($sales->num_rows > 0): ?>
            <!-- Pagination Info -->
            <div style="padding: 1rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div style="color: var(--text-medium); font-size: 0.9rem;">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo number_format($total_records); ?> records
                </div>
                <div style="color: var(--text-medium); font-size: 0.9rem;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sale ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $sales->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['sales_id']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($row['sales_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['phone'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo get_customer_type_badge($row['customer_type']); ?></td>
                                    <td><strong><?php echo number_format($row['total_quantity'], 2); ?> L</strong></td>
                                    <td>रू <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="text-success">रू <?php echo number_format($row['total_paid'], 2); ?></td>
                                    <td class="<?php echo $row['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        रू <?php echo number_format($row['balance'], 2); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'Paid' => 'success',
                                            'Partial' => 'warning',
                                            'Due' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $status_class[$row['sales_status']]; ?>">
                                            <?php echo $row['sales_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($row['sales_status'] !== 'Paid'): ?>
                                                <a href="payment-add.php?sale_id=<?php echo $row['sales_id']; ?>" 
                                                   class="btn-action btn-success" title="Add Payment">
                                                    💰
                                                </a>
                                            <?php else: ?>
                                                <span class="text-success" style="font-size: 1.2rem;">✓</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="4">Page Total:</td>
                                <td>
                                    <?php
                                    $sales->data_seek(0);
                                    $page_qty = 0;
                                    while ($r = $sales->fetch_assoc()) {
                                        $page_qty += $r['total_quantity'];
                                    }
                                    echo number_format($page_qty, 2) . ' L';
                                    ?>
                                </td>
                                <td colspan="5"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Enhanced Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div style="padding: 1rem; background: var(--bg-secondary); border-top: 1px solid var(--border-color);">
                    <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        
                        <!-- First Page -->
                        <?php if ($page > 1): ?>
                            <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" title="First Page">⏮️</a>
                            <a href="<?php echo build_pagination_url($page - 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Previous">◀️</a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>⏮️</button>
                            <button class="btn btn-secondary btn-sm" disabled>◀️</button>
                        <?php endif; ?>
                        
                        <!-- Page Numbers with Ellipsis -->
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
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="card-body">
                <div class="empty-state">
                    <span class="empty-icon">🛒</span>
                    <p>No sales records found</p>
                    <?php if ($date_from || $date_to || $status_filter): ?>
                        <p style="margin-bottom: 1rem;">Try adjusting your filters</p>
                        <a href="sales-list.php" class="btn btn-secondary">Clear Filters</a>
                    <?php else: ?>
                        <a href="sales-add.php" class="btn btn-primary" style="margin-top: 1rem;">
                            ➕ Create Your First Sale
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Employee Note -->
    <!-- <div class="info-box" style="background: #d1ecf1; border-color: #bee5eb;">
        <strong>ℹ Note:</strong>
        <ul>
            <li>You can only view sales that you have created</li>
            <li>Showing <?php echo $records_per_page; ?> records per page</li>
            <li>Date filters cannot be in the future</li>
            <li>You can add payments for unpaid sales</li>
            <li>Sales cannot be edited or deleted once created</li>
        </ul>
    </div> -->
</div>

<script>
// Client-side date validation
document.getElementById('filterForm').addEventListener('submit', function(e) {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const today = '<?php echo $today; ?>';
    
    // Check if dates are in the future
    if (dateFrom && dateFrom > today) {
        e.preventDefault();
        alert('From Date cannot be in the future');
        return false;
    }
    
    if (dateTo && dateTo > today) {
        e.preventDefault();
        alert('To Date cannot be in the future');
        return false;
    }
    
    // Check if from_date is after to_date
    if (dateFrom && dateTo && dateFrom > dateTo) {
        e.preventDefault();
        alert('From Date cannot be after To Date');
        return false;
    }
});

// Update To Date min value when From Date changes
document.getElementById('dateFrom').addEventListener('change', function() {
    const dateTo = document.getElementById('dateTo');
    if (this.value) {
        dateTo.min = this.value;
    } else {
        dateTo.removeAttribute('min');
    }
});

// Update From Date max value when To Date changes
document.getElementById('dateTo').addEventListener('change', function() {
    const dateFrom = document.getElementById('dateFrom');
    if (this.value) {
        dateFrom.max = this.value;
    } else {
        dateFrom.max = '<?php echo $today; ?>';
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>