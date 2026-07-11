<?php
// admin/customers/customer-view.php 
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Customer Details";
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID";
    header("Location: customer-list.php");
    exit();
}

// FIXED: Fetch customer details with summary using subqueries to avoid duplicate counting
$sql = "SELECT 
            c.*,
            (SELECT COUNT(DISTINCT s.sales_id) 
             FROM sales s 
             WHERE s.customer_id = c.customer_id) as total_sales,
            COALESCE(
                (SELECT SUM(s.total_amount) 
                 FROM sales s 
                 WHERE s.customer_id = c.customer_id), 0
            ) as total_sales_amount,
            COALESCE(
                (SELECT SUM(s.total_quantity) 
                 FROM sales s 
                 WHERE s.customer_id = c.customer_id), 0
            ) as total_quantity_sold,
            COALESCE(
                (SELECT SUM(p.amount_paid) 
                 FROM payment p 
                 JOIN sales s ON p.sales_id = s.sales_id 
                 WHERE s.customer_id = c.customer_id), 0
            ) as total_paid,
            (
                COALESCE(
                    (SELECT SUM(s.total_amount) 
                     FROM sales s 
                     WHERE s.customer_id = c.customer_id), 0
                ) - 
                COALESCE(
                    (SELECT SUM(p.amount_paid) 
                     FROM payment p 
                     JOIN sales s ON p.sales_id = s.sales_id 
                     WHERE s.customer_id = c.customer_id), 0
                )
            ) as outstanding_balance,
            (SELECT COUNT(*) 
             FROM sales s 
             WHERE s.customer_id = c.customer_id 
             AND s.sales_status IN ('Due', 'Partial')) as pending_sales_count
        FROM customer c
        WHERE c.customer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Customer not found";
    header("Location: customer-list.php");
    exit();
}

$customer = $result->fetch_assoc();
$stmt->close();

// Fetch recent sales with correct balance calculation
$recent_sales_sql = "SELECT 
                        s.sales_id,
                        s.sales_date,
                        s.total_quantity,
                        s.total_amount,
                        s.sales_type,
                        s.sales_status,
                        COALESCE(
                            (SELECT SUM(p.amount_paid) 
                             FROM payment p 
                             WHERE p.sales_id = s.sales_id), 0
                        ) as paid_amount,
                        (s.total_amount - COALESCE(
                            (SELECT SUM(p.amount_paid) 
                             FROM payment p 
                             WHERE p.sales_id = s.sales_id), 0
                        )) as balance
                     FROM sales s
                     WHERE s.customer_id = ?
                     ORDER BY s.sales_date DESC
                     LIMIT 5";
$sales_stmt = $conn->prepare($recent_sales_sql);
$sales_stmt->bind_param("i", $customer_id);
$sales_stmt->execute();
$recent_sales = $sales_stmt->get_result();
$sales_stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👤 <?php echo htmlspecialchars($customer['customer_name']); ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="customer-list.php">Customers</a>
                <span>/</span>
                <span>View Customer</span>
            </div>
        </div>
        <div class="header-actions">
            <?php if ($customer['pending_sales_count'] > 1): ?>
                <a href="../sales/bulk-payment.php?customer_id=<?php echo $customer_id; ?>" 
                   class="btn btn-success">
                    💰 Bulk Payment (<?php echo $customer['pending_sales_count']; ?> pending)
                </a>
            <?php endif; ?>
            <a href="customer-ledger.php?id=<?php echo $customer_id; ?>" class="btn btn-info">
                📖 View Ledger
            </a>
            <a href="customer-edit.php?id=<?php echo $customer_id; ?>" class="btn btn-warning">
                ✏️ Edit
            </a>
            <a href="customer-list.php" class="btn btn-secondary">
                ← Back
            </a>
        </div>
    </div>

    <!-- Alert for Outstanding Balance -->
    <?php if ($customer['outstanding_balance'] > 0): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠️</span>
            <div class="alert-message">
                <strong>Outstanding Balance:</strong> 
                This customer has रू <?php echo number_format($customer['outstanding_balance'], 2); ?> pending across 
                <?php echo $customer['pending_sales_count']; ?> sale(s).
                <?php if ($customer['pending_sales_count'] > 1): ?>
                    <a href="../sales/bulk-payment.php?customer_id=<?php echo $customer_id; ?>" 
                       style="color: var(--warning); text-decoration: underline; margin-left: 1rem;">
                        Pay All Together →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="customer-details">
        <!-- Customer Information Card -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Customer Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Customer Name</div>
                    <div class="detail-value">
                        <strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong>
                        <?php echo get_customer_type_badge($customer['customer_type']); ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Customer Type & Pricing</div>
                    <div class="detail-value">
                        <?php 
                        $price = get_price_by_customer_type($customer['customer_type']);
                        echo get_customer_type_badge($customer['customer_type']); 
                        ?>
                        <strong style="color: var(--success); margin-left: 1rem;">
                            रू <?php echo number_format($price, 2); ?> / Liter
                        </strong>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Phone Number</div>
                    <div class="detail-value">
                        <?php if (!empty($customer['phone'])): ?>
                            📞 <?php echo htmlspecialchars($customer['phone']); ?>
                        <?php else: ?>
                            <span style="color: var(--text-medium);">Not provided</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Address</div>
                    <div class="detail-value">
                        <?php if (!empty($customer['address'])): ?>
                            📍 <?php echo nl2br(htmlspecialchars($customer['address'])); ?>
                        <?php else: ?>
                            <span style="color: var(--text-medium);">Not provided</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php if ($customer['status'] === 'Active'): ?>
                            <span class="badge badge-success">✓ Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">✕ Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary Card -->
        <div class="card">
            <div class="card-header" style="background: var(--success); color: white;">
                <h3>💰 Financial Summary</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Advance Balance</div>
                    <div class="detail-value" style="color: var(--info); font-size: 1.2rem;">
                        <strong>रू <?php echo number_format($customer['advance_balance'], 2); ?></strong>
                    </div>
                    <small style="color: var(--text-medium);">Available for future payments</small>
                </div>

                <hr style="border: none; border-top: 2px solid var(--border-color); margin: 1rem 0;">

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Sales Amount</div>
                    <div class="detail-value">रू <?php echo number_format($customer['total_sales_amount'], 2); ?></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Paid</div>
                    <div class="detail-value" style="color: var(--success);">
                        रू <?php echo number_format($customer['total_paid'], 2); ?>
                    </div>
                </div>

                <hr style="border: none; border-top: 2px solid var(--border-color); margin: 1rem 0;">

                <div class="detail-item">
                    <div class="detail-label">Outstanding Balance</div>
                    <div class="detail-value" style="color: <?php echo $customer['outstanding_balance'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>; font-size: 1.5rem;">
                        <strong>रू <?php echo number_format($customer['outstanding_balance'], 2); ?></strong>
                    </div>
                    <small style="color: var(--text-medium);">
                        Across <?php echo $customer['pending_sales_count']; ?> pending sale(s)
                    </small>
                </div>

                <?php if ($customer['outstanding_balance'] > 0 && $customer['pending_sales_count'] > 1): ?>
                    <div style="margin-top: 1.5rem;">
                        <a href="../sales/bulk-payment.php?customer_id=<?php echo $customer_id; ?>" 
                           class="btn btn-success btn-block">
                            💰 Pay All Together (Bulk Payment)
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="margin-top: 2rem;">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Sales</span>
                <span class="stat-value"><?php echo $customer['total_sales']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Quantity</span>
                <span class="stat-value"><?php echo number_format($customer['total_quantity_sold'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">Total Revenue</span>
                <span class="stat-value">रू <?php echo number_format($customer['total_sales_amount'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Pending Sales</span>
                <span class="stat-value"><?php echo $customer['pending_sales_count']; ?></span>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3>🛒 Recent Sales</h3>
            <a href="customer-ledger.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary">
                View All →
            </a>
        </div>
        <div class="card-body">
            <?php if ($recent_sales->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sale ID</th>
                                <th>Date</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $sale['sales_id']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($sale['sales_date'])); ?></td>
                                    <td><?php echo number_format($sale['total_quantity'], 2); ?> L</td>
                                    <td>रू <?php echo number_format($sale['total_amount'], 2); ?></td>
                                    <td class="text-success">रू <?php echo number_format($sale['paid_amount'], 2); ?></td>
                                    <td class="<?php echo $sale['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        रू <?php echo number_format($sale['balance'], 2); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'Paid' => 'success',
                                            'Partial' => 'warning',
                                            'Due' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $status_class[$sale['sales_status']]; ?>">
                                            <?php echo $sale['sales_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="../sales/sales-view.php?id=<?php echo $sale['sales_id']; ?>" 
                                               class="btn-action btn-info" title="View">👁</a>
                                            <?php if ($sale['balance'] > 0): ?>
                                                <a href="../sales/payment-add.php?sale_id=<?php echo $sale['sales_id']; ?>" 
                                                   class="btn-action btn-success" title="Add Payment">💰</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🛒</span>
                    <p>No sales records found</p>
                    <a href="../sales/sales-add.php?customer_id=<?php echo $customer_id; ?>" 
                       class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Create First Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../../includes/footer.php';
?>