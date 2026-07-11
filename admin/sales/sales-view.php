<?php
// admin/sales/sales-view.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Sale Details";
$sales_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sales_id <= 0) {
    $_SESSION['error_message'] = "Invalid sale ID";
    header("Location: sales-list.php");
    exit();
}

// Fetch sale details
$sale_sql = "SELECT s.*, c.customer_name, c.phone, c.address, u.full_name as created_by
             FROM sales s
             JOIN customer c ON s.customer_id = c.customer_id
             JOIN user u ON s.user_id = u.user_id
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

// Fetch sale_milk details
$milk_sql = "SELECT sm.*, mc.collection_date, mc.shift, c.tag_id, ct.type_name, b.breed_name
             FROM sale_milk sm
             JOIN milk_collection mc ON sm.milk_id = mc.milk_id
             JOIN cattle c ON mc.cattle_id = c.cattle_id
             JOIN cattle_type ct ON c.type_id = ct.type_id
             JOIN breed b ON c.breed_id = b.breed_id
             WHERE sm.sales_id = ?";
$milk_stmt = $conn->prepare($milk_sql);
$milk_stmt->bind_param("i", $sales_id);
$milk_stmt->execute();
$milk_result = $milk_stmt->get_result();
$milk_stmt->close();

// Fetch payments
$payment_sql = "SELECT p.*, u.full_name as created_by
                FROM payment p
                JOIN user u ON p.user_id = u.user_id
                WHERE p.sales_id = ?
                ORDER BY p.payment_date DESC";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $sales_id);
$payment_stmt->execute();
$payments = $payment_stmt->get_result();
$payment_stmt->close();

// Calculate payment totals
$total_paid = 0;
$payments_temp = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payment WHERE sales_id = $sales_id");
$total_paid = $payments_temp->fetch_assoc()['total'];
$balance = $sale['total_amount'] - $total_paid;

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👁 Sale Details #<?php echo $sales_id; ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="sales-list.php">Sales</a>
                <span>/</span>
                <span>View Sale</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <?php if ($balance > 0): ?>
                <a href="payment-add.php?sale_id=<?php echo $sales_id; ?>" class="btn btn-success no-print">
                    💰 Add Payment
                </a>
            <?php endif; ?>
            <a href="sales-edit.php?id=<?php echo $sales_id; ?>" class="btn btn-warning no-print">✏️ Edit</a>
            <a href="sales-list.php" class="btn btn-primary no-print">← Back to List</a>
        </div>
    </div>

    <div class="customer-details">
        <!-- Sale Information -->
        <div class="card">
            <div class="card-header" style="background: var(--accent-blue); color: white;">
                <h3>📋 Sale Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Sales Date</div>
                    <div class="detail-value"><?php echo date('d M Y', strtotime($sale['sales_date'])); ?></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Sales Type</div>
                    <div class="detail-value">
                        <span class="badge badge-info"><?php echo $sale['sales_type']; ?></span>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php
                        $status_class = ['Paid' => 'success', 'Partial' => 'warning', 'Due' => 'danger'];
                        ?>
                        <span class="badge badge-<?php echo $status_class[$sale['sales_status']]; ?>">
                            <?php echo $sale['sales_status']; ?>
                        </span>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo htmlspecialchars($sale['created_by']); ?></div>
                </div>

                <?php if (!empty($sale['remarks'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Remarks</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($sale['remarks'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="card">
            <div class="card-header">
                <h3>👤 Customer Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Customer Name</div>
                    <div class="detail-value">
                        <strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($sale['phone'] ?? 'N/A'); ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Address</div>
                    <div class="detail-value">
                        <?php echo nl2br(htmlspecialchars($sale['address'] ?? 'N/A')); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Quantity</span>
                <span class="stat-value"><?php echo number_format($sale['total_quantity'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
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
    </div>

    <!-- Milk Records -->
    <div class="card">
        <div class="card-header">
            <h3>🥛 Milk Records</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Collection Date</th>
                            <th>Shift</th>
                            <th>Cattle Tag</th>
                            <th>Type / Breed</th>
                            <th>Quantity Sold</th>
                            <th>Unit Price</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $serial = 1;
                        while ($milk = $milk_result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $serial++; ?></td>
                                <td><?php echo date('d M Y', strtotime($milk['collection_date'])); ?></td>
                                <td><span class="badge badge-info"><?php echo $milk['shift']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($milk['tag_id']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($milk['type_name']); ?> / 
                                    <?php echo htmlspecialchars($milk['breed_name']); ?>
                                </td>
                                <td><?php echo number_format($milk['quantity_sold'], 2); ?> L</td>
                                <td>रू <?php echo number_format($milk['unit_price'], 2); ?></td>
                                <td><strong>रू <?php echo number_format($milk['quantity_sold'] * $milk['unit_price'], 2); ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                        <tr style="background: var(--bg-tertiary); font-weight: bold;">
                            <td colspan="5" style="text-align: right;">Total:</td>
                            <td><?php echo number_format($sale['total_quantity'], 2); ?> L</td>
                            <td></td>
                            <td>रू <?php echo number_format($sale['total_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="card">
        <div class="card-header">
            <h3>💰 Payment History</h3>
            <?php if ($balance > 0): ?>
                <a href="payment-add.php?sale_id=<?php echo $sales_id; ?>" class="btn btn-sm btn-success no-print">
                    ➕ Add Payment
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($payments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Payment Date</th>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                                <th>Received By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            while ($payment = $payments->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $serial++; ?></td>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                    <td class="text-success">
                                        <strong>रू <?php echo number_format($payment['amount_paid'], 2); ?></strong>
                                    </td>
                                    <td><span class="badge badge-info"><?php echo $payment['payment_method']; ?></span></td>
                                    <td><?php echo htmlspecialchars($payment['created_by']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="2" style="text-align: right;">Total Paid:</td>
                                <td class="text-success">रू <?php echo number_format($total_paid, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr style="background: <?php echo $balance > 0 ? '#fee' : '#efe'; ?>; font-weight: bold;">
                                <td colspan="2" style="text-align: right;">Balance:</td>
                                <td class="<?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                    रू <?php echo number_format($balance, 2); ?>
                                </td>
                                <td colspan="2">
                                    <?php echo $balance > 0 ? '(Outstanding)' : '(Fully Paid)'; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">💰</span>
                    <p>No payments received yet</p>
                    <?php if ($balance > 0): ?>
                        <a href="payment-add.php?sale_id=<?php echo $sales_id; ?>" class="btn btn-success no-print">
                            ➕ Add First Payment
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