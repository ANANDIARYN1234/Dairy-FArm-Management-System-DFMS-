<?php
// admin/customers/customer-ledger.php 
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Customer Ledger";
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID";
    header("Location: customer-list.php");
    exit();
}

// Fetch customer details
$customer_sql = "SELECT * FROM customer WHERE customer_id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();

if ($customer_result->num_rows === 0) {
    $_SESSION['error_message'] = "Customer not found";
    header("Location: customer-list.php");
    exit();
}

$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Date filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch all transactions (sales and payments combined)
$transactions_sql = "
    SELECT 
        'Sale' as type,
        s.sales_id as transaction_id,
        s.sales_date as transaction_date,
        s.total_quantity as quantity,
        s.total_amount as amount,
        0 as payment,
        s.sales_type as description,
        s.sales_status as status,
        u.full_name as created_by
    FROM sales s
    JOIN user u ON s.user_id = u.user_id
    WHERE s.customer_id = ? 
    AND s.sales_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Payment' as type,
        p.payment_id as transaction_id,
        p.payment_date as transaction_date,
        NULL as quantity,
        0 as amount,
        p.amount_paid as payment,
        p.payment_method as description,
        NULL as status,
        u.full_name as created_by
    FROM payment p
    JOIN sales s ON p.sales_id = s.sales_id
    JOIN user u ON p.user_id = u.user_id
    WHERE s.customer_id = ?
    AND p.payment_date BETWEEN ? AND ?
    
    ORDER BY transaction_date DESC, type DESC
";

$trans_stmt = $conn->prepare($transactions_sql);
$trans_stmt->bind_param("ississ", $customer_id, $start_date, $end_date, $customer_id, $start_date, $end_date);
$trans_stmt->execute();
$transactions = $trans_stmt->get_result();
$trans_stmt->close();

// FIXED: Calculate totals using separate queries to avoid duplicate counting
$total_sales_sql = "SELECT 
                        COALESCE(SUM(total_amount), 0) as total_sales,
                        COALESCE(SUM(total_quantity), 0) as total_quantity
                    FROM sales 
                    WHERE customer_id = ? 
                    AND sales_date BETWEEN ? AND ?";
$sales_stmt = $conn->prepare($total_sales_sql);
$sales_stmt->bind_param("iss", $customer_id, $start_date, $end_date);
$sales_stmt->execute();
$sales_totals = $sales_stmt->get_result()->fetch_assoc();
$sales_stmt->close();

$total_payments_sql = "SELECT COALESCE(SUM(p.amount_paid), 0) as total_payments
                       FROM payment p
                       JOIN sales s ON p.sales_id = s.sales_id
                       WHERE s.customer_id = ?
                       AND p.payment_date BETWEEN ? AND ?";
$payment_stmt = $conn->prepare($total_payments_sql);
$payment_stmt->bind_param("iss", $customer_id, $start_date, $end_date);
$payment_stmt->execute();
$payment_totals = $payment_stmt->get_result()->fetch_assoc();
$payment_stmt->close();

// Combine totals
$totals = [
    'total_sales' => $sales_totals['total_sales'],
    'total_quantity' => $sales_totals['total_quantity'],
    'total_payments' => $payment_totals['total_payments']
];

$balance = $totals['total_sales'] - $totals['total_payments'];

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📖 Customer Ledger</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="customer-list.php">Customers</a>
                <span>/</span>
                <span>Ledger</span>
            </div>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn btn-secondary no-print">
                🖨 Print
            </button>
            <a href="customer-view.php?id=<?php echo $customer_id; ?>" class="btn btn-info no-print">
                👁 View Details
            </a>
            <a href="customer-list.php" class="btn btn-primary no-print">
                ← Back to List
            </a>
        </div>
    </div>

    <!-- Customer Info Card -->
    <div class="card">
        <div class="card-header" style="background: var(--accent-blue); color: white;">
            <h3>
                👤 <?php echo htmlspecialchars($customer['customer_name']); ?>
                <?php if ($customer['status'] === 'Active'): ?>
                    <span class="badge badge-success" style="float: right;">Active</span>
                <?php else: ?>
                    <span class="badge badge-secondary" style="float: right;">Inactive</span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?>
                </div>
                <div>
                    <strong>Advance Balance:</strong> 
                    <span style="color: var(--success);">रू <?php echo number_format($customer['advance_balance'], 2); ?></span>
                </div>
                <div>
                    <strong>Due Balance:</strong> 
                    <span style="color: var(--danger);">रू <?php echo number_format($customer['due_balance'], 2); ?></span>
                </div>
                <div>
                    <strong>Address:</strong> <?php echo htmlspecialchars(substr($customer['address'] ?? 'N/A', 0, 30)); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card no-print">
        <div class="card-body" style="padding: 1.5rem;">
            <form method="GET" class="filter-form">
                <input type="hidden" name="id" value="<?php echo $customer_id; ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="customer-ledger.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Sales</span>
                <span class="stat-value">रू <?php echo number_format($totals['total_sales'], 2); ?></span>
                <small style="color: var(--text-medium);"><?php echo number_format($totals['total_quantity'], 2); ?> Liters</small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Total Payments</span>
                <span class="stat-value">रू <?php echo number_format($totals['total_payments'], 2); ?></span>
                <small style="color: var(--text-medium);">Received</small>
            </div>
        </div>
        
        <div class="stat-card <?php echo $balance > 0 ? 'stat-danger' : 'stat-success'; ?>">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">Balance</span>
                <span class="stat-value">रू <?php echo number_format($balance, 2); ?></span>
                <small style="color: var(--text-medium);"><?php echo $balance > 0 ? 'Outstanding' : 'Paid'; ?></small>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📅</div>
            <div class="stat-details">
                <span class="stat-label">Period</span>
                <span class="stat-value" style="font-size: 1.2rem;"><?php echo date('d M', strtotime($start_date)); ?></span>
                <small style="color: var(--text-medium);">to <?php echo date('d M Y', strtotime($end_date)); ?></small>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Transaction History</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Quantity (L)</th>
                            <th>Debit (रू)</th>
                            <th>Credit (रू)</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transactions->num_rows > 0): ?>
                            <?php while ($trans = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($trans['transaction_date'])); ?></td>
                                    <td>
                                        <?php if ($trans['type'] === 'Sale'): ?>
                                            <span class="badge badge-warning">Sale</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['description']); ?></td>
                                    <td><?php echo $trans['quantity'] ? number_format($trans['quantity'], 2) : '-'; ?></td>
                                    <td class="text-danger">
                                        <?php echo $trans['amount'] > 0 ? number_format($trans['amount'], 2) : '-'; ?>
                                    </td>
                                    <td class="text-success">
                                        <?php echo $trans['payment'] > 0 ? number_format($trans['payment'], 2) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($trans['status']): ?>
                                            <?php
                                            $status_class = ['Paid' => 'success', 'Partial' => 'warning', 'Due' => 'danger'];
                                            ?>
                                            <span class="badge badge-<?php echo $status_class[$trans['status']]; ?>">
                                                <?php echo $trans['status']; ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['created_by']); ?></td>
                                    <td class="no-print">
                                        <?php if ($trans['type'] === 'Sale'): ?>
                                            <a href="../sales/sales-view.php?id=<?php echo $trans['transaction_id']; ?>" 
                                               class="btn-action btn-info">👁</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <tr class="total-row">
                                <td colspan="4" style="text-align: right;"><strong>Total:</strong></td>
                                <td class="text-danger"><strong>रू <?php echo number_format($totals['total_sales'], 2); ?></strong></td>
                                <td class="text-success"><strong>रू <?php echo number_format($totals['total_payments'], 2); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                            <tr class="balance-row">
                                <td colspan="4" style="text-align: right;"><strong>Net Balance:</strong></td>
                                <td colspan="2" style="color: <?php echo $balance > 0 ? 'var(--danger)' : 'var(--success)'; ?>; font-weight: bold;">
                                    रू <?php echo number_format($balance, 2); ?>
                                    <?php echo $balance > 0 ? '(Outstanding)' : '(Paid)'; ?>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <span class="empty-icon">📖</span>
                                        <p>No transactions found for the selected period</p>
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

<?php
$conn->close();
include '../../includes/footer.php';
?>