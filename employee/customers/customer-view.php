<?php
// employee/customers/customer-view.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Customer Details";
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

// Get customer's sales summary
$sales_sql = "SELECT 
                COUNT(sales_id) as total_sales,
                COALESCE(SUM(total_quantity), 0) as total_quantity,
                COALESCE(SUM(total_amount), 0) as total_amount,
                MAX(sales_date) as last_sale_date
              FROM sales 
              WHERE customer_id = ?";
$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param("i", $customer_id);
$sales_stmt->execute();
$sales_summary = $sales_stmt->get_result()->fetch_assoc();
$sales_stmt->close();

// Get customer's payment summary
$payment_sql = "SELECT 
                  COALESCE(SUM(p.amount_paid), 0) as total_paid
                FROM payment p
                JOIN sales s ON p.sales_id = s.sales_id
                WHERE s.customer_id = ?";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $customer_id);
$payment_stmt->execute();
$payment_summary = $payment_stmt->get_result()->fetch_assoc();
$payment_stmt->close();

// Calculate outstanding balance
$outstanding_balance = $sales_summary['total_amount'] - $payment_summary['total_paid'];

// Get recent sales (last 5)
$recent_sales_sql = "SELECT * FROM sales WHERE customer_id = ? ORDER BY sales_date DESC LIMIT 5";
$recent_sales_stmt = $conn->prepare($recent_sales_sql);
$recent_sales_stmt->bind_param("i", $customer_id);
$recent_sales_stmt->execute();
$recent_sales = $recent_sales_stmt->get_result();
$recent_sales_stmt->close();

// Get price for this customer type
$unit_price = get_price_by_customer_type($customer['customer_type']);

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👤 Customer Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="customer-list.php">Customers</a>
                <span>/</span>
                <span><?php echo htmlspecialchars($customer['customer_name']); ?></span>
            </div>
        </div>
        <div class="header-actions">
            <a href="customer-list.php" class="btn btn-secondary">← Back to List</a>
            <a href="../sales/sales-add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success">
                🛒 Create Sale
            </a>
        </div>
    </div>

    <!-- Customer Information Cards -->
    <div class="customer-details">
        <!-- Basic Information -->
        <div class="card">
            <div class="card-header" style="background: var(--accent-blue); color: white;">
                <h3>📋 Basic Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Customer ID</div>
                    <div class="detail-value"><strong>#<?php echo $customer['customer_id']; ?></strong></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Customer Name</div>
                    <div class="detail-value"><strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Customer Type</div>
                    <div class="detail-value"><?php echo get_customer_type_badge($customer['customer_type']); ?></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Unit Price</div>
                    <div class="detail-value" style="color: var(--success); font-size: 1.5rem;">
                        <strong>रू <?php echo number_format($unit_price, 2); ?> / Liter</strong>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($customer['address'] ?? 'N/A')); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="badge badge-<?php echo $customer['status'] === 'Active' ? 'success' : 'danger'; ?>">
                            <?php echo $customer['status']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="card">
            <div class="card-header" style="background: var(--success); color: white;">
                <h3>💰 Financial Summary</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Sales</div>
                    <div class="detail-value">
                        <strong><?php echo $sales_summary['total_sales']; ?></strong> transactions
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Quantity Sold</div>
                    <div class="detail-value">
                        <strong><?php echo number_format($sales_summary['total_quantity'], 2); ?></strong> Liters
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Sales Amount</div>
                    <div class="detail-value">
                        रू <?php echo number_format($sales_summary['total_amount'], 2); ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Paid</div>
                    <div class="detail-value" style="color: var(--success);">
                        रू <?php echo number_format($payment_summary['total_paid'], 2); ?>
                    </div>
                </div>

                <hr style="border: none; border-top: 2px solid var(--border-color); margin: 1rem 0;">

                <div class="detail-item">
                    <div class="detail-label">Outstanding Balance</div>
                    <div class="detail-value" style="color: <?php echo $outstanding_balance > 0 ? 'var(--danger)' : 'var(--success)'; ?>; font-size: 1.5rem;">
                        <strong>रू <?php echo number_format($outstanding_balance, 2); ?></strong>
                    </div>
                </div>

                <?php if ($sales_summary['last_sale_date']): ?>
                    <div class="detail-item" style="margin-top: 1rem;">
                        <div class="detail-label">Last Sale Date</div>
                        <div class="detail-value">
                            <?php echo date('d M Y', strtotime($sales_summary['last_sale_date'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <h3>🛒 Recent Sales</h3>
            <a href="../sales/sales-add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary">
                ➕ New Sale
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
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $sale['sales_id']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($sale['sales_date'])); ?></td>
                                    <td><strong><?php echo number_format($sale['total_quantity'], 2); ?> L</strong></td>
                                    <td>रू <?php echo number_format($sale['total_amount'], 2); ?></td>
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
                                        <?php if ($sale['sales_status'] !== 'Paid'): ?>
                                            <a href="../sales/payment-add.php?sale_id=<?php echo $sale['sales_id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                💰 Pay
                                            </a>
                                        <?php else: ?>
                                            <span class="text-success">✓ Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🛒</span>
                    <p>No sales recorded yet</p>
                    <a href="../sales/sales-add.php?customer_id=<?php echo $customer_id; ?>" 
                       class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Create First Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Employee Note -->
    <!-- <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
        <strong>ℹ Note:</strong>
        <ul>
            <li>You can view customer details and sales history</li>
            <li>You can create new sales for this customer</li>
            <li>You can record payments for unpaid sales</li>
            <li>Price is automatically set based on customer type: <strong>रू <?php echo number_format($unit_price, 2); ?>/L</strong></li>
        </ul>
    </div> -->
</div>

<?php
$conn->close();
include '../../includes/footer.php';
?>