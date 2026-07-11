<?php
// admin/sales/payment-list.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin', 'Employee']);

$page_title = "Payment Records";
$errors = [];

// Pagination
$records_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$method_filter = isset($_GET['method']) ? $_GET['method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Validate dates
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
$where_conditions = ["1=1"];  
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "c.customer_name LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
}

if (!empty($method_filter)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $method_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "p.payment_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "p.payment_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM payment p
              JOIN sales s ON p.sales_id = s.sales_id
              JOIN customer c ON s.customer_id = c.customer_id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch payments
$sql = "SELECT 
            p.*,
            s.sales_id,
            s.sales_date,
            s.total_amount as sale_amount,
            c.customer_name,
            c.phone,
            u.full_name as received_by
        FROM payment p
        JOIN sales s ON p.sales_id = s.sales_id
        JOIN customer c ON s.customer_id = c.customer_id
        JOIN user u ON p.user_id = u.user_id
        $where_clause
        ORDER BY p.payment_date DESC, p.payment_id DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params_with_limit = $params; // Copy params for binding
$params_with_limit[] = $records_per_page;
$params_with_limit[] = $offset;
$types_with_limit = $types . 'ii';
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$result = $stmt->get_result();

// Get FILTERED statistics (based on current search criteria)
$stats_sql = "SELECT 
                COUNT(*) as total_payments,
                COALESCE(SUM(p.amount_paid), 0) as total_amount,
                COALESCE(SUM(CASE WHEN p.payment_method = 'Cash' THEN p.amount_paid ELSE 0 END), 0) as cash_amount,
                COALESCE(SUM(CASE WHEN p.payment_method = 'Bank' THEN p.amount_paid ELSE 0 END), 0) as bank_amount,
                COALESCE(SUM(CASE WHEN p.payment_method = 'Advance' THEN p.amount_paid ELSE 0 END), 0) as advance_amount
              FROM payment p
              JOIN sales s ON p.sales_id = s.sales_id
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
            <h1>Payment Records</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="sales-list.php">Sales</a>
                <span>/</span>
                <span>Payments</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="sales-list.php" class="btn btn-primary">Back to Sales</a>
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
    <?php if (!empty($search) || !empty($method_filter) || !empty($date_from) || !empty($date_to)): ?>
        <div class="alert alert-info" style="margin-bottom: 1rem;">
            <span class="alert-icon">ℹ</span>
            <div class="alert-message">
                <strong>Filtered Results:</strong> Statistics below show filtered data only. 
                <a href="payment-list.php" style="color: var(--info); text-decoration: underline;">Clear filters</a> to see all-time totals.
            </div>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">
                    <?php echo (!empty($search) || !empty($method_filter) || !empty($date_from) || !empty($date_to)) ? 'Filtered ' : 'Total '; ?>Payments
                </span>
                <span class="stat-value"><?php echo $stats['total_payments']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">
                    <?php echo (!empty($search) || !empty($method_filter) || !empty($date_from) || !empty($date_to)) ? 'Filtered ' : 'Total '; ?>Amount
                </span>
                <span class="stat-value">रू <?php echo number_format($stats['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">Cash/Bank Payments</span>
                <span class="stat-value">रू <?php echo number_format($stats['cash_amount'] + $stats['bank_amount'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🏦</div>
            <div class="stat-details">
                <span class="stat-label">Advance Payments</span>
                <span class="stat-value">रू <?php echo number_format($stats['advance_amount'], 2); ?></span>
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
                        <select name="method" class="form-control">
                            <option value="">All Methods</option>
                            <option value="Cash" <?php echo $method_filter === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Bank" <?php echo $method_filter === 'Bank' ? 'selected' : ''; ?>>Bank</option>
                            <option value="Cheque" <?php echo $method_filter === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            <option value="Digital" <?php echo $method_filter === 'Digital' ? 'selected' : ''; ?>>Digital</option>
                            <option value="Advance" <?php echo $method_filter === 'Advance' ? 'selected' : ''; ?>>Advance</option>
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
                        <a href="payment-list.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Payment Records (Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Payment Date</th>
                            <th>Customer</th>
                            <th>Sale ID</th>
                            <th>Sale Date</th>
                            <th>Sale Amount</th>
                            <th>Amount Paid</th>
                            <th>Method</th>
                            <th>Received By</th>
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
                                    <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['phone'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <a href="sales-view.php?id=<?php echo $row['sales_id']; ?>" 
                                           class="badge badge-primary">
                                            <?php echo $row['sales_id']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($row['sales_date'])); ?></td>
                                    <td>रू <?php echo number_format($row['sale_amount'], 2); ?></td>
                                    <td class="text-success">
                                        <strong>रू <?php echo number_format($row['amount_paid'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['payment_method'] === 'Advance' ? 'warning' : 'info'; ?>">
                                            <?php echo $row['payment_method']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['received_by']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="sales-view.php?id=<?php echo $row['sales_id']; ?>" 
                                               class="btn-action btn-info" title="View Sale">👁️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <span class="empty-icon">💳</span>
                                        <p>No payment records found</p>
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
                        if ($method_filter) $query_params['method'] = $method_filter;
                        if ($date_from) $query_params['date_from'] = $date_from;
                        if ($date_to) $query_params['date_to'] = $date_to;
                        
                        function build_payment_url($page_num, $params) {
                            $params['page'] = $page_num;
                            return 'payment-list.php?' . http_build_query($params);
                        }
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?php echo build_payment_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" title="First Page">⏮️</a>
                            <a href="<?php echo build_payment_url($page - 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Previous">⬅️</a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>⏮️</button>
                            <button class="btn btn-secondary btn-sm" disabled>⬅️</button>
                        <?php endif; ?>
                        
                        <?php
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);
                        
                        if ($start > 1): ?>
                            <a href="<?php echo build_payment_url(1, $query_params); ?>" class="btn btn-secondary btn-sm">1</a>
                            <?php if ($start > 2): ?>
                                <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <button class="btn btn-primary btn-sm" style="min-width: 40px;"><?php echo $i; ?></button>
                            <?php else: ?>
                                <a href="<?php echo build_payment_url($i, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                            <?php endif; ?>
                            <a href="<?php echo build_payment_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo build_payment_url($page + 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Next">➡️</a>
                            <a href="<?php echo build_payment_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm" title="Last Page">⏭️</a>
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
</script>

<?php
$stmt->close();
$conn->close();
include '../../includes/footer.php';
?>