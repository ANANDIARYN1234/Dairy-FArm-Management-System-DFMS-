<?php
// admin/sales/bulk-payment.php - FIXED WITH OLDEST FIRST PRIORITY
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Bulk Payment";
$errors = [];
$success_count = 0;

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID";
    header("Location: ../customers/customer-list.php");
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
    header("Location: ../customers/customer-list.php");
    exit();
}

$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Fetch all due/partial sales - OLDEST FIRST for smart allocation
$sales_sql = "SELECT s.*, 
              COALESCE(SUM(p.amount_paid), 0) as total_paid,
              (s.total_amount - COALESCE(SUM(p.amount_paid), 0)) as balance
              FROM sales s
              LEFT JOIN payment p ON s.sales_id = p.sales_id
              WHERE s.customer_id = ? AND s.sales_status IN ('Due', 'Partial')
              GROUP BY s.sales_id
              HAVING balance > 0
              ORDER BY s.sales_date ASC";
$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param("i", $customer_id);
$sales_stmt->execute();
$due_sales = $sales_stmt->get_result();
$sales_stmt->close();

// Calculate total outstanding
$total_outstanding = 0;
$due_sales->data_seek(0);
while ($sale = $due_sales->fetch_assoc()) {
    $total_outstanding += $sale['balance'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_date = trim($_POST['payment_date']);
    $payment_type = $_POST['payment_type'];
    $total_payment_amount = floatval($_POST['total_payment_amount']);
    $payment_method = $_POST['payment_method'];
    
    // Validation
    if (empty($payment_date)) {
        $errors[] = "Payment date is required";
    } else {
        $today = date('Y-m-d');
        if ($payment_date > $today) {
            $errors[] = "Payment date cannot be in the future";
        }
    }
    
    if ($total_payment_amount <= 0) {
        $errors[] = "Payment amount must be greater than 0";
    }
    
    if ($total_payment_amount > $total_outstanding) {
        $errors[] = "Payment amount (रू " . number_format($total_payment_amount, 2) . ") exceeds total outstanding (रू " . number_format($total_outstanding, 2) . ")";
    }
    
    // Validate based on payment type
    if ($payment_type === 'advance') {
        if ($total_payment_amount > $customer['advance_balance']) {
            $errors[] = "Advance amount (रू " . number_format($total_payment_amount, 2) . ") exceeds available advance balance (रू " . number_format($customer['advance_balance'], 2) . ")";
        }
    }
    
    // Process payments - Smart allocation (oldest first, partial allowed)
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            $user_id = get_user_id();
            $remaining_payment = $total_payment_amount;
            
            // Deduct advance if used
            if ($payment_type === 'advance') {
                $update_advance_sql = "UPDATE customer SET advance_balance = advance_balance - ? WHERE customer_id = ?";
                $update_advance_stmt = $conn->prepare($update_advance_sql);
                $update_advance_stmt->bind_param("di", $total_payment_amount, $customer_id);
                $update_advance_stmt->execute();
                $update_advance_stmt->close();
            }
            
            // Get all due sales in oldest-first order
            $due_sales->data_seek(0);
            $sales_to_pay = [];
            while ($sale = $due_sales->fetch_assoc()) {
                $sales_to_pay[] = $sale;
            }
            
            // Allocate payment to sales (oldest first, allow partial)
            foreach ($sales_to_pay as $sale) {
                if ($remaining_payment <= 0) break;
                
                $sale_id = $sale['sales_id'];
                $balance = $sale['balance'];
                
                // Pay what we can (full or partial)
                $payment_amount = min($remaining_payment, $balance);
                
                // Record payment
                if ($payment_type === 'advance') {
                    $payment_sql = "INSERT INTO payment (payment_date, amount_paid, payment_method, sales_id, user_id) 
                                   VALUES (?, ?, 'Advance', ?, ?)";
                    $payment_stmt = $conn->prepare($payment_sql);
                    $payment_stmt->bind_param("sdii", $payment_date, $payment_amount, $sale_id, $user_id);
                } else {
                    $payment_sql = "INSERT INTO payment (payment_date, amount_paid, payment_method, sales_id, user_id) 
                                   VALUES (?, ?, ?, ?, ?)";
                    $payment_stmt = $conn->prepare($payment_sql);
                    $payment_stmt->bind_param("sdsii", $payment_date, $payment_amount, $payment_method, $sale_id, $user_id);
                }
                
                $payment_stmt->execute();
                $payment_stmt->close();
                
                // Update sale status
                $check_sql = "SELECT 
                              s.total_amount,
                              COALESCE(SUM(p.amount_paid), 0) as total_paid
                              FROM sales s
                              LEFT JOIN payment p ON s.sales_id = p.sales_id
                              WHERE s.sales_id = ?
                              GROUP BY s.sales_id";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $sale_id);
                $check_stmt->execute();
                $sale_info = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();
                
                $new_balance = $sale_info['total_amount'] - $sale_info['total_paid'];
                
                if ($new_balance <= 0.01) {
                    $new_status = 'Paid';
                } elseif ($sale_info['total_paid'] > 0) {
                    $new_status = 'Partial';
                } else {
                    $new_status = 'Due';
                }
                
                $update_sql = "UPDATE sales SET sales_status = ? WHERE sales_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_status, $sale_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $remaining_payment -= $payment_amount;
                $success_count++;
            }
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Successfully processed payment for {$success_count} invoice(s)!";
            header("Location: ../customers/customer-view.php?id=" . $customer_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to process payments: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>Bulk Payment</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../customers/customer-list.php">Customers</a>
                <span>/</span>
                <a href="../customers/customer-view.php?id=<?php echo $customer_id; ?>">
                    <?php echo htmlspecialchars($customer['customer_name']); ?>
                </a>
                <span>/</span>
                <span>Bulk Payment</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="../customers/customer-view.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                Back to Customer
            </a>
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

    <div class="card">
        <div class="card-header" style="background: var(--accent-blue); color: white;">
            <h3>Customer Information</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div>
                    <strong>Customer:</strong> <?php echo htmlspecialchars($customer['customer_name']); ?>
                    <br><?php echo get_customer_type_badge($customer['customer_type']); ?>
                </div>
                <div>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?>
                </div>
                <div>
                    <strong>Total Outstanding:</strong> 
                    <span style="color: var(--danger); font-size: 1.2rem; font-weight: bold;">
                        रू <?php echo number_format($total_outstanding, 2); ?>
                    </span>
                </div>
                <div>
                    <strong>Advance Balance:</strong> 
                    <span style="color: var(--info); font-size: 1.2rem; font-weight: bold;">
                        रू <?php echo number_format($customer['advance_balance'], 2); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    
    <form method="POST" action="" id="bulkPaymentForm">
        <div class="card">
            <div class="card-header">
                <h3>Outstanding Invoices (Oldest First - Payment Priority)</h3>
            </div>
            <div class="card-body">
                <?php if ($due_sales->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Sale ID</th>
                                    <th>Date</th>
                                    <th>Total Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $due_sales->data_seek(0);
                                $priority = 1;
                                while ($sale = $due_sales->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><span class="badge badge-info">#<?php echo $priority++; ?></span></td>
                                        <td><strong>#<?php echo $sale['sales_id']; ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($sale['sales_date'])); ?></td>
                                        <td>रू <?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td class="text-success">रू <?php echo number_format($sale['total_paid'], 2); ?></td>
                                        <td class="text-danger">
                                            <strong>रू <?php echo number_format($sale['balance'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $sale['sales_status'] === 'Partial' ? 'warning' : 'danger'; ?>">
                                                <?php echo $sale['sales_status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info" style="margin-top: 1rem;">
                        <span class="alert-icon">ℹ️</span>
                        <div class="alert-message">
                            <strong>Payment Priority:</strong> Oldest invoices will be paid first. Partial payments will be applied if the payment amount doesn't cover all invoices.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">✅</span>
                        <p>No outstanding sales for this customer</p>
                        <small>All sales are fully paid!</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($due_sales->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Payment Information</h3>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Payment Date</label>
                            <input type="date" name="payment_date" id="paymentDate" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label required">Payment Type</label>
                            <div style="display: flex; gap: 2rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 8px;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="radio" name="payment_type" value="cash" checked 
                                           style="width: 18px; height: 18px;">
                                    <span style="font-weight: 600;">Cash/Bank Payment</span>
                                </label>
                                <?php if ($customer['advance_balance'] > 0): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="radio" name="payment_type" value="advance" 
                                           style="width: 18px; height: 18px;">
                                    <span style="font-weight: 600;">Use Advance Balance (रू <?php echo number_format($customer['advance_balance'], 2); ?>)</span>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group" id="cashMethodGroup">
                            <label class="form-label required">Payment Method</label>
                            <select name="payment_method" id="paymentMethod" class="form-control" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Digital">Digital Payment</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Total Payment Amount (रू)</label>
                            <input type="number" name="total_payment_amount" id="totalPaymentAmount" 
                                   class="form-control" step="0.01" min="0.01" 
                                   max="<?php echo $total_outstanding; ?>"
                                   placeholder="Enter total payment" required>
                            <small class="form-hint" id="maxPaymentHint">
                                Max outstanding: रू <?php echo number_format($total_outstanding, 2); ?>
                            </small>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 1.5rem;">
                        <a href="../customers/customer-view.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            Process Payment
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
const maxOutstanding = <?php echo $total_outstanding; ?>;
const maxAdvance = <?php echo $customer['advance_balance']; ?>;

// Handle payment type change
document.querySelectorAll('input[name="payment_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const methodGroup = document.getElementById('cashMethodGroup');
        const methodSelect = document.getElementById('paymentMethod');
        const paymentInput = document.getElementById('totalPaymentAmount');
        const maxHint = document.getElementById('maxPaymentHint');
        
        if (this.value === 'advance') {
            methodGroup.style.display = 'none';
            methodSelect.required = false;
            const maxAmount = Math.min(maxAdvance, maxOutstanding);
            paymentInput.max = maxAmount;
            maxHint.textContent = 'Max advance: रू ' + maxAdvance.toFixed(2);
        } else {
            methodGroup.style.display = 'block';
            methodSelect.required = true;
            paymentInput.max = maxOutstanding;
            maxHint.textContent = 'Max outstanding: रू ' + maxOutstanding.toFixed(2);
        }
    });
});

// Form validation
document.getElementById('bulkPaymentForm').addEventListener('submit', function(e) {
    const paymentDate = document.getElementById('paymentDate').value;
    const today = new Date().toISOString().split('T')[0];
    
    if (paymentDate > today) {
        e.preventDefault();
        alert('❌ Payment date cannot be in the future');
        return false;
    }
    
    const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
    const totalPayment = parseFloat(document.getElementById('totalPaymentAmount').value) || 0;
    
    if (totalPayment <= 0) {
        e.preventDefault();
        alert('❌ Payment amount must be greater than 0');
        return false;
    }
    
    if (totalPayment > maxOutstanding) {
        e.preventDefault();
        alert('❌ Payment amount (रू ' + totalPayment.toFixed(2) + ') exceeds total outstanding (रू ' + maxOutstanding.toFixed(2) + ')');
        return false;
    }
    
    if (paymentType === 'advance' && totalPayment > maxAdvance) {
        e.preventDefault();
        alert('❌ Advance payment (रू ' + totalPayment.toFixed(2) + ') exceeds available advance balance (रू ' + maxAdvance.toFixed(2) + ')');
        return false;
    }
    
    return true;
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>