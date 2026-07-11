<?php
// employee/customers/customer-balance-add.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Add Customer Balance";
$errors = [];
$success = false;

// Fetch all active customers for dropdown
$customers_sql = "SELECT customer_id, customer_name, customer_type, advance_balance 
                  FROM customer 
                  WHERE status = 'Active' 
                  ORDER BY customer_name";
$customers_result = $conn->query($customers_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id']);
    $amount = floatval($_POST['amount']);
    $transaction_type = $_POST['transaction_type'];
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes']);
    $transaction_date = $_POST['transaction_date'];

    // Validation
    if ($customer_id <= 0) {
        $errors[] = "Please select a customer";
    }

    if ($amount <= 0) {
        $errors[] = "Amount must be greater than 0";
    }

    if (empty($transaction_date)) {
        $errors[] = "Transaction date is required";
    }

    // Process transaction if no errors
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Get current balance
            $balance_sql = "SELECT advance_balance, customer_name FROM customer WHERE customer_id = ?";
            $balance_stmt = $conn->prepare($balance_sql);
            $balance_stmt->bind_param("i", $customer_id);
            $balance_stmt->execute();
            $customer_data = $balance_stmt->get_result()->fetch_assoc();
            $balance_stmt->close();
            
            if (!$customer_data) {
                throw new Exception("Customer not found");
            }
            
            $current_balance = $customer_data['advance_balance'];
            $customer_name = $customer_data['customer_name'];
            
            // Calculate new balance based on transaction type
            if ($transaction_type === 'Credit') {
                $new_balance = $current_balance + $amount;
                $transaction_description = "Balance added";
            } else {
                // Debit
                if ($amount > $current_balance) {
                    throw new Exception("Insufficient balance. Current balance: रू " . number_format($current_balance, 2));
                }
                $new_balance = $current_balance - $amount;
                $transaction_description = "Balance deducted";
            }
            
            // Update customer balance
            $update_sql = "UPDATE customer SET advance_balance = ? WHERE customer_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("di", $new_balance, $customer_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update customer balance");
            }
            $update_stmt->close();
            
            // Optional: Record transaction history (skipped if table doesn't exist)
            // You can enable this later by creating the customer_balance_history table
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Balance updated successfully! " . 
                                          $customer_name . "'s new balance: रू " . 
                                          number_format($new_balance, 2);
            header("Location: customer-list.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📥 Add Customer Balance</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="customer-list.php">Customers</a>
                <span>/</span>
                <span>Add Balance</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="customer-list.php" class="btn btn-secondary">← Back to List</a>
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

    <!-- Add Balance Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>💰 Balance Transaction</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="balanceForm">
                    <div class="form-grid">
                        <!-- Customer Selection -->
                        <div class="form-group full-width">
                            <label class="form-label required">Select Customer</label>
                            <select name="customer_id" id="customer_id" class="form-control" required>
                                <option value="">-- Select Customer --</option>
                                <?php while ($customer = $customers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" 
                                            data-balance="<?php echo $customer['advance_balance']; ?>"
                                            data-type="<?php echo $customer['customer_type']; ?>"
                                            <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['customer_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_name']); ?> 
                                        (<?php echo $customer['customer_type']; ?>) - 
                                        Current Balance: रू <?php echo number_format($customer['advance_balance'], 2); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Current Balance Display -->
                        <div class="form-group full-width" id="currentBalanceDisplay" style="display: none;">
                            <div class="info-box" style="background: #d1ecf1; border-color: #bee5eb;">
                                <strong>Current Advance Balance:</strong>
                                <span id="currentBalanceAmount" style="font-size: 1.5rem; color: var(--info); margin-left: 1rem;">
                                    रू 0.00
                                </span>
                            </div>
                        </div>

                        <!-- Transaction Type -->
                        <div class="form-group">
                            <label class="form-label required">Transaction Type</label>
                            <select name="transaction_type" id="transaction_type" class="form-control" required>
                                <option value="Credit" selected>💵 Credit (Add Balance)</option>
                                <option value="Debit">💸 Debit (Deduct Balance)</option>
                            </select>
                        </div>

                        <!-- Amount -->
                        <div class="form-group">
                            <label class="form-label required">Amount (रू)</label>
                            <input type="number" name="amount" id="amount" class="form-control" 
                                   step="0.01" min="0.01" required
                                   placeholder="Enter amount"
                                   value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                        </div>

                        <!-- Payment Method -->
                        <div class="form-group">
                            <label class="form-label required">Payment Method</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="Cash" selected>💵 Cash</option>
                                <option value="Online">💳 Online Transfer</option>
                                <option value="Cheque">📝 Cheque</option>
                                <option value="Other">🔄 Other</option>
                            </select>
                        </div>

                        <!-- Transaction Date -->
                        <div class="form-group">
                            <label class="form-label required">Transaction Date</label>
                            <input type="date" name="transaction_date" class="form-control" 
                                   value="<?php echo isset($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Notes -->
                        <div class="form-group full-width">
                            <label class="form-label">Notes / Remarks</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Enter any additional notes (optional)"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                    </div>

                    <!-- New Balance Preview -->
                    <div id="balancePreview" style="display: none; margin-top: 1.5rem;">
                        <div class="info-box" style="background: #d4edda; border-color: #c3e6cb;">
                            <strong>New Balance After Transaction:</strong>
                            <span id="newBalanceAmount" style="font-size: 1.5rem; color: var(--success); margin-left: 1rem;">
                                रू 0.00
                            </span>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <!-- <div class="info-box">
                        <strong>ℹ Important Notes:</strong>
                        <ul>
                            <li><strong>Credit:</strong> Adds money to customer's advance balance (customer pays you)</li>
                            <li><strong>Debit:</strong> Deducts money from customer's advance balance (refund or adjustment)</li>
                            <li>Advance balance can be used for future sales payments</li>
                            <li>This transaction will be recorded in customer's ledger</li>
                            <li>You cannot deduct more than the current advance balance</li>
                        </ul>
                    </div> -->

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary">💾 Update Balance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation and balance preview
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customer_id');
    const transactionType = document.getElementById('transaction_type');
    const amountInput = document.getElementById('amount');
    const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
    const currentBalanceAmount = document.getElementById('currentBalanceAmount');
    const balancePreview = document.getElementById('balancePreview');
    const newBalanceAmount = document.getElementById('newBalanceAmount');

    function updateBalanceDisplay() {
        const selectedOption = customerSelect.options[customerSelect.selectedIndex];
        
        if (customerSelect.value) {
            const currentBalance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
            currentBalanceAmount.textContent = 'रू ' + currentBalance.toFixed(2);
            currentBalanceDisplay.style.display = 'block';
            
            // Update preview if amount is entered
            updateBalancePreview(currentBalance);
        } else {
            currentBalanceDisplay.style.display = 'none';
            balancePreview.style.display = 'none';
        }
    }

    function updateBalancePreview(currentBalance) {
        const amount = parseFloat(amountInput.value) || 0;
        const type = transactionType.value;
        
        if (amount > 0 && customerSelect.value) {
            let newBalance;
            if (type === 'Credit') {
                newBalance = currentBalance + amount;
            } else {
                newBalance = currentBalance - amount;
            }
            
            newBalanceAmount.textContent = 'रू ' + newBalance.toFixed(2);
            newBalanceAmount.style.color = newBalance >= 0 ? 'var(--success)' : 'var(--danger)';
            balancePreview.style.display = 'block';
        } else {
            balancePreview.style.display = 'none';
        }
    }

    customerSelect.addEventListener('change', updateBalanceDisplay);
    transactionType.addEventListener('change', function() {
        if (customerSelect.value) {
            const currentBalance = parseFloat(customerSelect.options[customerSelect.selectedIndex].getAttribute('data-balance')) || 0;
            updateBalancePreview(currentBalance);
        }
    });
    amountInput.addEventListener('input', function() {
        if (customerSelect.value) {
            const currentBalance = parseFloat(customerSelect.options[customerSelect.selectedIndex].getAttribute('data-balance')) || 0;
            updateBalancePreview(currentBalance);
        }
    });

    // Form submission validation
    document.getElementById('balanceForm').addEventListener('submit', function(e) {
        const selectedOption = customerSelect.options[customerSelect.selectedIndex];
        const currentBalance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
        const amount = parseFloat(amountInput.value) || 0;
        const type = transactionType.value;

        if (!customerSelect.value) {
            e.preventDefault();
            alert('Please select a customer');
            return false;
        }

        if (amount <= 0) {
            e.preventDefault();
            alert('Amount must be greater than 0');
            return false;
        }

        if (type === 'Debit' && amount > currentBalance) {
            e.preventDefault();
            alert('Cannot deduct रू ' + amount.toFixed(2) + '. Current balance is only रू ' + currentBalance.toFixed(2));
            return false;
        }

        // Confirm transaction
        const customerName = selectedOption.text.split('(')[0].trim();
        const newBalance = type === 'Credit' ? (currentBalance + amount) : (currentBalance - amount);
        const message = `Confirm ${type} Transaction:\n\n` +
                       `Customer: ${customerName}\n` +
                       `Transaction: ${type}\n` +
                       `Amount: रू ${amount.toFixed(2)}\n` +
                       `Current Balance: रू ${currentBalance.toFixed(2)}\n` +
                       `New Balance: रू ${newBalance.toFixed(2)}\n\n` +
                       `Proceed with this transaction?`;

        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>