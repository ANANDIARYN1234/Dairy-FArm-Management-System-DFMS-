<?php
// admin/customers/customer-edit.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Edit Customer";
$errors = [];
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch customer details
if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID";
    header("Location: customer-list.php");
    exit();
}

$sql = "SELECT * FROM customer WHERE customer_id = ?";
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $advance_balance = floatval($_POST['advance_balance']);
    $due_balance = floatval($_POST['due_balance']);
    $status = $_POST['status'];

    // Validation
    if (empty($customer_name)) {
        $errors[] = "Customer name is required";
    }

    if (!empty($phone) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    if ($advance_balance < 0) {
        $errors[] = "Advance balance cannot be negative";
    }

    if ($due_balance < 0) {
        $errors[] = "Due balance cannot be negative";
    }

    // Check if customer name already exists (excluding current customer)
    if (empty($errors)) {
        $check_sql = "SELECT customer_id FROM customer WHERE customer_name = ? AND customer_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $customer_name, $customer_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Customer name already exists";
        }
        $check_stmt->close();
    }

    // Update database
    if (empty($errors)) {
        $update_sql = "UPDATE customer 
                       SET customer_name = ?, phone = ?, address = ?, 
                           advance_balance = ?, due_balance = ?, status = ? 
                       WHERE customer_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssddsi", $customer_name, $phone, $address, 
                                   $advance_balance, $due_balance, $status, $customer_id);

        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Customer updated successfully!";
            header("Location: customer-list.php");
            exit();
        } else {
            $errors[] = "Failed to update customer: " . $conn->error;
        }
        $update_stmt->close();
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>✏️ Edit Customer</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="customer-list.php">Customers</a>
                <span>/</span>
                <span>Edit Customer</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="customer-view.php?id=<?php echo $customer_id; ?>" class="btn btn-info">
                👁 View Details
            </a>
            <a href="customer-list.php" class="btn btn-secondary">
                ← Back to List
            </a>
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

    <!-- Edit Customer Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>👤 Customer Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="customerForm" style="padding: 1.5rem;">
                    <div class="form-grid">
                        <!-- Customer Name -->
                        <div class="form-group">
                            <label class="form-label required">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" 
                                   placeholder="Enter customer name" required
                                   value="<?php echo htmlspecialchars($customer['customer_name']); ?>">
                        </div>

                        <!-- Phone -->
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="Enter phone number"
                                   value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                        </div>

                        <!-- Address (Full Width) -->
                        <div class="form-group full-width">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3" 
                                      placeholder="Enter customer address"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                        </div>

                        <!-- Advance Balance -->
                        <div class="form-group">
                            <label class="form-label">Advance Balance (रू)</label>
                            <input type="number" name="advance_balance" class="form-control" 
                                   step="0.01" min="0"
                                   value="<?php echo $customer['advance_balance']; ?>">
                            <small class="form-hint">Customer prepayment</small>
                        </div>

                        <!-- Due Balance -->
                        <div class="form-group">
                            <label class="form-label">Due Balance (रू)</label>
                            <input type="number" name="due_balance" class="form-control" 
                                   step="0.01" min="0"
                                   value="<?php echo $customer['due_balance']; ?>">
                            <small class="form-hint">Outstanding amount</small>
                        </div>

                        <!-- Status -->
                        <div class="form-group">
                            <label class="form-label required">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Active" <?php echo $customer['status'] === 'Active' ? 'selected' : ''; ?>>
                                    Active
                                </option>
                                <option value="Inactive" <?php echo $customer['status'] === 'Inactive' ? 'selected' : ''; ?>>
                                    Inactive
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Warning Box -->
                    <div class="alert alert-warning" style="margin-top: 1.5rem;">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Important:</strong>
                            <ul style="margin: 0.5rem 0 0 1.5rem;">
                                <li>Changing balances manually may affect financial reports</li>
                                <li>Balance adjustments should be documented properly</li>
                                <li>Use the ledger to track all customer transactions</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <a href="customer-list.php" class="btn btn-secondary">
                            ❌ Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            💾 Update Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('customerForm').addEventListener('submit', function(e) {
    const customerName = document.querySelector('input[name="customer_name"]').value.trim();
    const advanceBalance = parseFloat(document.querySelector('input[name="advance_balance"]').value);
    const dueBalance = parseFloat(document.querySelector('input[name="due_balance"]').value);
    
    if (customerName === '') {
        e.preventDefault();
        alert('Customer name is required');
        return false;
    }
    
    if (advanceBalance < 0 || dueBalance < 0) {
        e.preventDefault();
        alert('Balances cannot be negative');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>