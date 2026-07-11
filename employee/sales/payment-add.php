<?php
// employee/sales/payment-add.php - WITH ADVANCE PAYMENT
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Add Payment";
$errors = [];
$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

if ($sale_id <= 0) {
    $_SESSION['error_message'] = "Invalid sale ID";
    header("Location: sales-list.php");
    exit();
}

// Fetch sale details with customer advance balance
$sale_sql = "SELECT s.*, c.customer_name, c.customer_type, c.phone, c.advance_balance,
             COALESCE(SUM(p.amount_paid), 0) as total_paid,
             (s.total_amount - COALESCE(SUM(p.amount_paid), 0)) as balance
             FROM sales s
             JOIN customer c ON s.customer_id = c.customer_id
             LEFT JOIN payment p ON s.sales_id = p.sales_id
             WHERE s.sales_id = ?
             GROUP BY s.sales_id";
$sale_stmt = $conn->prepare($sale_sql);
$sale_stmt->bind_param("i", $sale_id);
$sale_stmt->execute();
$sale_result = $sale_stmt->get_result();

if ($sale_result->num_rows === 0) {
    $_SESSION['error_message'] = "Sale not found";
    header("Location: sales-list.php");
    exit();
}

$sale = $sale_result->fetch_assoc();
$sale_stmt->close();

// Check if already paid
if ($sale['balance'] <= 0) {
    $_SESSION['error_message'] = "This sale is already fully paid";
    header("Location: sales-list.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_date = trim($_POST['payment_date']);
    $payment_type = $_POST['payment_type']; // 'cash' or 'advance'
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_method = $_POST['payment_method'];
    
    // Validation
    if (empty($payment_date)) {
        $errors[] = "Payment date is required";
    } else {
        // Validate date is not in future
        $today = date('Y-m-d');
        if ($payment_date > $today) {
            $errors[] = "Payment date cannot be in the future";
        }
    }
    
    if ($amount_paid <= 0) {
        $errors[] = "Payment amount must be greater than 0";
    }
    
    // Validate based on payment type
    if ($payment_type === 'advance') {
        if ($amount_paid > $sale['advance_balance']) {
            $errors[] = "Advance amount (रू " . number_format($amount_paid, 2) . ") cannot exceed available advance balance (रू " . number_format($sale['advance_balance'], 2) . ")";
        }
        // If advance > outstanding, limit to outstanding amount
        if ($amount_paid > $sale['balance']) {
            $amount_paid = $sale['balance'];
        }
    } else {
        // Cash payment
        if ($amount_paid > $sale['balance']) {
            $errors[] = "Cash payment (रू " . number_format($amount_paid, 2) . ") cannot exceed outstanding balance (रू " . number_format($sale['balance'], 2) . ")";
        }
    }
    
    // Insert payment
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            $customer_id = $sale['customer_id'];
            $user_id = get_user_id();
            
            if ($payment_type === 'advance') {
                // Deduct from customer's advance balance
                $update_advance_sql = "UPDATE customer SET advance_balance = advance_balance - ? WHERE customer_id = ?";
                $update_advance_stmt = $conn->prepare($update_advance_sql);
                $update_advance_stmt->bind_param("di", $amount_paid, $customer_id);
                $update_advance_stmt->execute();
                $update_advance_stmt->close();
                
                // Record advance payment with method 'Advance'
                $payment_sql = "INSERT INTO payment (payment_date, amount_paid, payment_method, sales_id, user_id) 
                               VALUES (?, ?, 'Advance', ?, ?)";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->bind_param("sdii", $payment_date, $amount_paid, $sale_id, $user_id);
                $payment_stmt->execute();
                $payment_stmt->close();
            } else {
                // Cash payment
                $payment_sql = "INSERT INTO payment (payment_date, amount_paid, payment_method, sales_id, user_id) 
                               VALUES (?, ?, ?, ?, ?)";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->bind_param("sdsii", $payment_date, $amount_paid, $payment_method, $sale_id, $user_id);
                $payment_stmt->execute();
                $payment_stmt->close();
            }
            
            // Update sales status
            $new_total_paid = $sale['total_paid'] + $amount_paid;
            $new_balance = $sale['total_amount'] - $new_total_paid;
            
            if ($new_balance <= 0.01) { // Allow for small rounding errors
                $new_status = 'Paid';
            } elseif ($new_total_paid > 0) {
                $new_status = 'Partial';
            } else {
                $new_status = 'Due';
            }
            
            $update_sql = "UPDATE sales SET sales_status = ? WHERE sales_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_status, $sale_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $conn->commit();
            $_SESSION['success_message'] = "Payment recorded successfully!";
            header("Location: sales-list.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to record payment: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>💰 Add Payment</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="sales-list.php">My Sales</a>
                <span>/</span>
                <span>Sale #<?php echo $sale_id; ?></span>
                <span>/</span>
                <span>Add Payment</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="sales-list.php" class="btn btn-secondary">← Back to Sales</a>
        </div>
    </div>

    <!-- Error Messages -->
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

    <div class="customer-details">
        <!-- Sale Information -->
        <div class="card">
            <div class="card-header" style="background: var(--accent-blue); color: white;">
                <h3>📋 Sale Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Sale ID</div>
                    <div class="detail-value"><strong>#<?php echo $sale['sales_id']; ?></strong></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Customer</div>
                    <div class="detail-value">
                        <strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong>
                        <?php echo get_customer_type_badge($sale['customer_type']); ?>
                        <br>
                        <small><?php echo htmlspecialchars($sale['phone'] ?? ''); ?></small>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Sale Date</div>
                    <div class="detail-value"><?php echo date('d M Y', strtotime($sale['sales_date'])); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Sale Type</div>
                    <div class="detail-value">
                        <?php echo get_customer_type_badge($sale['sales_type']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="card">
            <div class="card-header" style="background: var(--success); color: white;">
                <h3>💵 Payment Summary</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Total Amount</div>
                    <div class="detail-value">रू <?php echo number_format($sale['total_amount'], 2); ?></div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Amount Paid</div>
                    <div class="detail-value" style="color: var(--success);">
                        रू <?php echo number_format($sale['total_paid'], 2); ?>
                    </div>
                </div>

                <hr style="border: none; border-top: 2px solid var(--border-color); margin: 1rem 0;">

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Outstanding Balance</div>
                    <div class="detail-value" style="color: var(--danger); font-size: 1.5rem;">
                        <strong>रू <?php echo number_format($sale['balance'], 2); ?></strong>
                    </div>
                </div>

                <?php if ($sale['advance_balance'] > 0): ?>
                    <div class="detail-item" style="padding: 1rem; background: #d1ecf1; border-radius: 8px; border-left: 4px solid var(--info);">
                        <div class="detail-label">Available Advance Balance</div>
                        <div class="detail-value" style="color: var(--info); font-size: 1.3rem;">
                            <strong>रू <?php echo number_format($sale['advance_balance'], 2); ?></strong>
                        </div>
                        <small style="color: var(--text-medium);">Can be used to pay this invoice</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>💰 Payment Details</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="paymentForm">
                    <div class="form-grid">
                        <!-- Payment Date -->
                        <div class="form-group">
                            <label class="form-label required">Payment Date</label>
                            <input type="date" name="payment_date" id="paymentDate" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Payment Type -->
                        <div class="form-group full-width">
                            <label class="form-label required">Payment Type</label>
                            <div style="display: flex; gap: 2rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 8px;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="radio" name="payment_type" value="cash" checked 
                                           style="width: 18px; height: 18px;">
                                    <span style="font-weight: 600;">Cash/Bank Payment</span>
                                </label>
                                <?php if ($sale['advance_balance'] > 0): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="radio" name="payment_type" value="advance" 
                                           style="width: 18px; height: 18px;">
                                    <span style="font-weight: 600;">Use Advance Balance (रू <?php echo number_format($sale['advance_balance'], 2); ?>)</span>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Payment Method (Only for Cash) -->
                        <div class="form-group" id="cashMethodGroup">
                            <label class="form-label required">Payment Method</label>
                            <select name="payment_method" id="paymentMethod" class="form-control" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Digital">Digital Payment</option>
                            </select>
                        </div>

                        <!-- Amount Paid -->
                        <div class="form-group">
                            <label class="form-label required">Payment Amount (रू)</label>
                            <input type="number" name="amount_paid" id="amountPaid" class="form-control" 
                                   step="0.01" min="0.01" 
                                   max="<?php echo $sale['balance']; ?>"
                                   value="<?php echo $sale['balance']; ?>"
                                   placeholder="Enter amount" required>
                            <small class="form-hint" id="maxAmountHint">Max: रू <?php echo number_format($sale['balance'], 2); ?></small>
                        </div>
                    </div>

                    <!-- Payment Summary Preview -->
                    <div style="margin-top: 1.5rem; padding: 1.5rem; background: var(--bg-tertiary); border-radius: 8px;">
                        <h4 style="margin-bottom: 1rem;">Payment Summary</h4>
                        <div style="display: grid; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Payment Amount:</span>
                                <strong id="displayPayment" style="font-size: 1.2rem; color: var(--success);">
                                    रू <?php echo number_format($sale['balance'], 2); ?>
                                </strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Outstanding Balance:</span>
                                <strong style="color: var(--danger);">रू <?php echo number_format($sale['balance'], 2); ?></strong>
                            </div>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 2px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600;">Remaining Balance:</span>
                                <strong id="displayRemaining" style="font-size: 1.3rem; color: var(--danger);">
                                    रू 0.00
                                </strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.5rem; border-top: 1px solid var(--border-color);">
                                <span>Status After Payment:</span>
                                <strong id="displayStatus">
                                    <span class="badge badge-success">Paid</span>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <!-- <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>ℹ Note:</strong>
                        <ul>
                            <li>Choose either Cash/Bank payment OR Advance payment (not both)</li>
                            <li>Payment date cannot be in the future</li>
                            <?php if ($sale['advance_balance'] > 0): ?>
                                <li><strong>Available Advance:</strong> रू <?php echo number_format($sale['advance_balance'], 2); ?></li>
                                <li>Using advance will automatically deduct from customer's advance balance</li>
                            <?php endif; ?>
                            <li>Payment cannot exceed the outstanding balance</li>
                            <li>Sale status will be updated automatically</li>
                        </ul>
                    </div> -->

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <a href="sales-list.php" class="btn btn-secondary">❌ Cancel</a>
                        <button type="submit" class="btn btn-success">💰 Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const maxBalance = <?php echo $sale['balance']; ?>;
const maxAdvance = <?php echo $sale['advance_balance']; ?>;

// Handle payment type change
document.querySelectorAll('input[name="payment_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const amountInput = document.getElementById('amountPaid');
        const methodGroup = document.getElementById('cashMethodGroup');
        const methodSelect = document.getElementById('paymentMethod');
        const maxHint = document.getElementById('maxAmountHint');
        
        if (this.value === 'advance') {
            methodGroup.style.display = 'none';
            methodSelect.required = false;
            const maxAmount = Math.min(maxAdvance, maxBalance);
            amountInput.max = maxAmount;
            amountInput.value = maxAmount.toFixed(2);
            maxHint.textContent = 'Max: रू ' + maxAmount.toFixed(2);
        } else {
            methodGroup.style.display = 'block';
            methodSelect.required = true;
            amountInput.max = maxBalance;
            amountInput.value = maxBalance.toFixed(2);
            maxHint.textContent = 'Max: रू ' + maxBalance.toFixed(2);
        }
        
        calculateRemaining();
    });
});

// Calculate remaining balance
function calculateRemaining() {
    const amount = parseFloat(document.getElementById('amountPaid').value) || 0;
    const remaining = Math.max(0, maxBalance - amount);
    
    document.getElementById('displayPayment').textContent = 'रू ' + amount.toFixed(2);
    document.getElementById('displayRemaining').textContent = 'रू ' + remaining.toFixed(2);
    
    // Update status badge
    const statusEl = document.getElementById('displayStatus');
    if (remaining <= 0.01) {
        statusEl.innerHTML = '<span class="badge badge-success">Paid</span>';
        document.getElementById('displayRemaining').style.color = 'var(--success)';
    } else {
        statusEl.innerHTML = '<span class="badge badge-warning">Partial</span>';
        document.getElementById('displayRemaining').style.color = 'var(--danger)';
    }
}

document.getElementById('amountPaid').addEventListener('input', calculateRemaining);

// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const paymentDate = document.getElementById('paymentDate').value;
    const today = new Date().toISOString().split('T')[0];
    
    if (paymentDate > today) {
        e.preventDefault();
        alert('Payment date cannot be in the future');
        return false;
    }
    
    const amount = parseFloat(document.getElementById('amountPaid').value) || 0;
    const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
    
    if (amount <= 0) {
        e.preventDefault();
        alert('Payment amount must be greater than 0');
        return false;
    }
    
    if (paymentType === 'advance') {
        if (amount > maxAdvance) {
            e.preventDefault();
            alert('Advance payment cannot exceed available advance balance');
            return false;
        }
    }
    
    if (amount > maxBalance) {
        e.preventDefault();
        alert('Payment cannot exceed outstanding balance');
        return false;
    }
});

// Initialize on load
calculateRemaining();
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>