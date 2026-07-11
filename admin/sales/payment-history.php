<?php
// admin/sales/payment-history.php - FIXED TO SHOW ADVANCE PAYMENTS
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Payment History";
$sales_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

if ($sales_id <= 0) {
    $_SESSION['error_message'] = "Invalid sale ID";
    header("Location: sales-list.php");
    exit();
}

// Fetch sale details
$sale_sql = "SELECT s.*, c.customer_name, c.phone
             FROM sales s
             JOIN customer c ON s.customer_id = c.customer_id
             WHERE s.sales_id = ?";
$sale_stmt = $conn->prepare($sale_sql);
$sale_stmt->bind_param("i", $sales_id);
$sale_stmt->execute();
$sale_result = $sale_stmt->get_result();

if ($sale_result->num_rows === 0) {
    $_SESSION['error_message'] = "Sale not found";
    header("Location: sales-list.php");
    exit();
}

$sale = $sale_result->fetch_assoc();
$sale_stmt->close();

// Fetch payment history
$payment_sql = "SELECT p.*, u.full_name as received_by
                FROM payment p
                JOIN user u ON p.user_id = u.user_id
                WHERE p.sales_id = ?
                ORDER BY p.payment_date DESC, p.payment_id DESC";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $sales_id);
$payment_stmt->execute();
$payments = $payment_stmt->get_result();
$payment_stmt->close();

// Calculate totals
$total_paid = 0;
$payments_temp = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payment WHERE sales_id = $sales_id");
$total_paid = $payments_temp->fetch_assoc()['total'];
$balance = $sale['total_amount'] - $total_paid;

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>Payment History - Sale <?php echo $sales_id; ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="sales-list.php">Sales</a>
                <span>/</span>
                <a href="sales-view.php?id=<?php echo $sales_id; ?>">Sale <?php echo $sales_id; ?></a>
                <span>/</span>
                <span>Payment History</span>
            </div>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn btn-secondary no-print">Print</button>
            <?php if ($balance > 0): ?>
                <a href="payment-add.php?sale_id=<?php echo $sales_id; ?>" class="btn btn-success no-print">
                    Add Payment
                </a>
            <?php endif; ?>
            <a href="sales-view.php?id=<?php echo $sales_id; ?>" class="btn btn-primary no-print">
                Back to Sale
            </a>
        </div>
    </div>

    <?php echo get_flash_message(); ?>

    <div class="card">
        <div class="card-header" style="background: var(--accent-blue); color: white;">
            <h3>Sale Information</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?>
                </div>
                <div>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($sale['phone'] ?? 'N/A'); ?>
                </div>
                <div>
                    <strong>Sale Date:</strong> <?php echo date('d M Y', strtotime($sale['sales_date'])); ?>
                </div>
                <div>
                    <strong>Sale Type:</strong> 
                    <span class="badge badge-info"><?php echo $sale['sales_type']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">💵</div>
            <div class="stat-details">
                <span class="stat-label">Total Amount</span>
                <span class="stat-value">रू <?php echo number_format($sale['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Amount Paid</span>
                <span class="stat-value">रू <?php echo number_format($total_paid, 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card <?php echo $balance > 0 ? 'stat-danger' : 'stat-success'; ?>">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Balance</span>
                <span class="stat-value">रू <?php echo number_format($balance, 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🧾</div>
            <div class="stat-details">
                <span class="stat-label">Payment Count</span>
                <span class="stat-value"><?php echo $payments->num_rows; ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Payment History</h3>
        </div>
        <div class="card-body">
            <?php if ($payments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Payment Date</th>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                                <th>Received By</th>
                                <th>Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            $running_balance = $sale['total_amount'];
                            
                            // Store payments in array to reverse order for running balance
                            $payment_array = [];
                            while ($payment = $payments->fetch_assoc()) {
                                $payment_array[] = $payment;
                            }
                            $payment_array = array_reverse($payment_array);
                            
                            foreach ($payment_array as $payment):
                                $running_balance -= $payment['amount_paid'];
                                
                                // Handle NULL or empty payment_method (old advance payments)
                                $payment_method = $payment['payment_method'];
                                if (empty($payment_method) || $payment_method === null) {
                                    $payment_method = 'Advance';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                    <td class="text-success">
                                        <strong>रू <?php echo number_format($payment['amount_paid'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $payment_method === 'Advance' ? 'warning' : 'info'; ?>">
                                            <?php echo htmlspecialchars($payment_method); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['received_by']); ?></td>
                                    <td class="<?php echo $running_balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                        रू <?php echo number_format($running_balance, 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="2" style="text-align: right;">Total Paid:</td>
                                <td class="text-success">रू <?php echo number_format($total_paid, 2); ?></td>
                                <td colspan="3"></td>
                            </tr>
                            
                            <tr style="background: <?php echo $balance > 0 ? '#fee' : '#efe'; ?>; font-weight: bold;">
                                <td colspan="2" style="text-align: right;">Outstanding Balance:</td>
                                <td colspan="2" class="<?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                    रू <?php echo number_format($balance, 2); ?>
                                </td>
                                <td colspan="2">
                                    <?php if ($balance > 0): ?>
                                        <span class="badge badge-danger">Due</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Fully Paid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 2rem; padding: 1.5rem; background: var(--bg-tertiary); border-radius: 8px;">
                    <h5 style="margin-bottom: 1rem;">Payment Progress</h5>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <div style="flex: 1; height: 30px; background: var(--border-color); border-radius: 15px; overflow: hidden;">
                            <div style="height: 100%; background: var(--success); width: <?php echo ($total_paid / $sale['total_amount']) * 100; ?>%; 
                                        display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.85rem;">
                                <?php echo number_format(($total_paid / $sale['total_amount']) * 100, 1); ?>%
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--text-medium);">
                        <span>रू 0</span>
                        <span>रू <?php echo number_format($sale['total_amount'], 2); ?></span>
                    </div>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">💰</span>
                    <p>No payments received yet</p>
                    <?php if ($balance > 0): ?>
                        <a href="payment-add.php?sale_id=<?php echo $sales_id; ?>" 
                           class="btn btn-success no-print">
                            Add First Payment
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../../includes/footer.php';
?>