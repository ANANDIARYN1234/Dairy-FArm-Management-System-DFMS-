<?php
// admin/sales/sales-list.php - FIXED WITH DYNAMIC FILTERED STATS
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin', 'Employee']);

$page_title = "Sales Management";

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Validate dates
$errors = [];
$today = date('Y-m-d');
if (!empty($date_from) && $date_from > $today) {
    $errors[] = "From date cannot be in the future";
    $date_from = '';
}
if (!empty($date_to) && $date_to > $today) {
    $errors[] = "To date cannot be in the future";
    $date_to = '';
}
if (!empty($date_from) && !empty($date_to) && $date_from > $date_to) {
    $errors[] = "From date cannot be after To date";
    $date_from = '';
    $date_to = '';
}

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "c.customer_name LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "s.sales_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($type_filter)) {
    $where_conditions[] = "s.sales_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "s.sales_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "s.sales_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM sales s
              JOIN customer c ON s.customer_id = c.customer_id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch sales with customer and payment info
$sql = "SELECT 
            s.*,
            c.customer_name,
            c.phone,
            u.full_name as created_by,
            COALESCE(SUM(p.amount_paid), 0) as total_paid,
            (s.total_amount - COALESCE(SUM(p.amount_paid), 0)) as balance
        FROM sales s
        JOIN customer c ON s.customer_id = c.customer_id
        JOIN user u ON s.user_id = u.user_id
        LEFT JOIN payment p ON s.sales_id = p.sales_id
        $where_clause
        GROUP BY s.sales_id
        ORDER BY s.sales_date DESC, s.sales_id DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params_with_limit = $params;
$params_with_limit[] = $records_per_page;
$params_with_limit[] = $offset;
$types_with_limit = $types . 'ii';
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$result = $stmt->get_result();

// Get FILTERED statistics
$stats_sql = "SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(s.total_amount), 0) as total_revenue,
                COALESCE(SUM(s.total_quantity), 0) as total_quantity,
                COALESCE(SUM(CASE WHEN s.sales_status = 'Paid' THEN s.total_amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN s.sales_status = 'Due' THEN s.total_amount ELSE 0 END), 0) as due_amount
              FROM sales s
              JOIN customer c ON s.customer_id = c.customer_id
              $where_clause";

if (!empty($params)) {
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param($types, ...$params);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    $stats_stmt->close();
} else {
    $stats = $conn->query($stats_sql)->fetch_assoc();
}

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>Sales Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Sales</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="payment-list.php" class="btn btn-info">View Payments</a>
            <a href="sales-add.php" class="btn btn-primary">New Sale</a>
        </div>
    </div>

    <?php echo get_flash_message(); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Error!</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Stats show FILTERED results -->
    <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter) || !empty($date_from) || !empty($date_to)): ?>
        <div class="alert alert-info" style="margin-bottom: 1rem;">
            <span class="alert-icon">ℹ</span>
            <div class="alert-message">
                <strong>Filtered Results:</strong> Statistics below show filtered data only. 
                <a href="sales-list.php" style="color: var(--info); text-decoration: underline;">Clear filters</a> to see all-time totals.
            </div>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">
                    <?php echo (!empty($search) || !empty($status_filter) || !empty($type_filter) || !empty($date_from) || !empty($date_to)) ? 'Filtered ' : 'Total '; ?>Sales
                </span>
                <span class="stat-value"><?php echo $stats['total_sales']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">
                    <?php echo (!empty($search) || !empty($status_filter) || !empty($type_filter) || !empty($date_from) || !empty($date_to)) ? 'Filtered ' : 'Total '; ?>Revenue
                </span>
                <span class="stat-value">रू <?php echo number_format($stats['total_revenue'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Quantity Sold</span>
                <span class="stat-value"><?php echo number_format($stats['total_quantity'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⚠</div>
            <div class="stat-details">
                <span class="stat-label">Due Amount</span>
                <span class="stat-value">रू <?php echo number_format($stats['due_amount'], 2); ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Search & Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search customer..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Partial" <?php echo $status_filter === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="Due" <?php echo $status_filter === 'Due' ? 'selected' : ''; ?>>Due</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            <option value="Retail" <?php echo $type_filter === 'Retail' ? 'selected' : ''; ?>>Retail</option>
                            <option value="Wholesale" <?php echo $type_filter === 'Wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                            <option value="Dairy" <?php echo $type_filter === 'Dairy' ? 'selected' : ''; ?>>Dairy</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <input type="date" name="date_from" id="dateFrom" class="form-control" 
                               placeholder="From Date" value="<?php echo $date_from; ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <input type="date" name="date_to" id="dateTo" class="form-control" 
                               placeholder="To Date" value="<?php echo $date_to; ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="sales-list.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Sales Records (Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php 
                            $serial = $offset + 1;
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['sales_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['phone'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo number_format($row['total_quantity'], 2); ?> L</td>
                                    <td>रू <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="text-success">रू <?php echo number_format($row['total_paid'], 2); ?></td>
                                    <td class="<?php echo $row['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong>रू <?php echo number_format($row['balance'], 2); ?></strong>
                                    </td>
                                    <td><span class="badge badge-info"><?php echo $row['sales_type']; ?></span></td>
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
                                            <a href="sales-view.php?id=<?php echo $row['sales_id']; ?>" 
                                               class="btn-action btn-info" title="View">👁️</a>
                                            <?php if ($row['balance'] > 0): ?>
                                                <a href="payment-add.php?sale_id=<?php echo $row['sales_id']; ?>" 
                                                   class="btn-action btn-success" title="Add Payment">💳</a>
                                            <?php endif; ?>
                                            <a href="sales-edit.php?id=<?php echo $row['sales_id']; ?>" 
                                               class="btn-action btn-warning" title="Edit">✏️</a>
                                            <a href="sales-delete.php?id=<?php echo $row['sales_id']; ?>" 
                                               class="btn-action btn-danger" title="Delete"
                                               onclick="return confirm('Delete this sale?');">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <span class="empty-icon">🛒</span>
                                        <p>No sales records found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div style="padding: 1rem; background: var(--bg-secondary); border-top: 1px solid var(--border-color);">
                    <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <?php
                        $query_params = [];
                        if ($search) $query_params['search'] = $search;
                        if ($status_filter) $query_params['status'] = $status_filter;
                        if ($type_filter) $query_params['type'] = $type_filter;
                        if ($date_from) $query_params['date_from'] = $date_from;
                        if ($date_to) $query_params['date_to'] = $date_to;
                        
                        function build_sales_url($page_num, $params) {
                            $params['page'] = $page_num;
                            return 'sales-list.php?' . http_build_query($params);
                        }
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?php echo build_sales_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" title="First Page">⏮️</a>
                            <a href="<?php echo build_sales_url($page - 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Previous">⬅️</a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>⏮️</button>
                            <button class="btn btn-secondary btn-sm" disabled>⬅️</button>
                        <?php endif; ?>
                        
                        <?php
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);
                        
                        if ($start > 1): ?>
                            <a href="<?php echo build_sales_url(1, $query_params); ?>" class="btn btn-secondary btn-sm">1</a>
                            <?php if ($start > 2): ?>
                                <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <button class="btn btn-primary btn-sm" style="min-width: 40px;"><?php echo $i; ?></button>
                            <?php else: ?>
                                <a href="<?php echo build_sales_url($i, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                            <?php endif; ?>
                            <a href="<?php echo build_sales_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo build_sales_url($page + 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Next">➡️</a>
                            <a href="<?php echo build_sales_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm" title="Last Page">⏭️</a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>➡️</button>
                            <button class="btn btn-secondary btn-sm" disabled>⏭️</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('filterForm').addEventListener('submit', function(e) {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const today = new Date().toISOString().split('T')[0];
    
    if (dateFrom && dateFrom > today) {
        e.preventDefault();
        alert('From date cannot be in the future');
        return false;
    }
    
    if (dateTo && dateTo > today) {
        e.preventDefault();
        alert('To date cannot be in the future');
        return false;
    }
    
    if (dateFrom && dateTo && dateFrom > dateTo) {
        e.preventDefault();
        alert('From date cannot be after To date');
        return false;
    }
});

document.getElementById('dateFrom').addEventListener('change', function() {
    const dateTo = document.getElementById('dateTo').value;
    if (dateTo && this.value > dateTo) {
        alert('From date cannot be after To date');
        this.value = '';
    }
});

document.getElementById('dateTo').addEventListener('change', function() {
    const dateFrom = document.getElementById('dateFrom').value;
    if (dateFrom && this.value < dateFrom) {
        alert('To date cannot be before From date');
        this.value = '';
    }
});
if (!isValid) {
        e.preventDefault();
    }
return isValid;
</script>

<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>