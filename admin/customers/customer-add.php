<?php
// admin/customers/customer-add.php - UPDATED WITH CUSTOMER TYPE
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Add Customer";
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $customer_type = $_POST['customer_type'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $advance_balance = floatval($_POST['advance_balance']);
    $status = $_POST['status'];

    // Validation
    if (empty($customer_name)) {
        $errors[] = "Customer name is required";
    }

    if (empty($customer_type)) {
        $errors[] = "Customer type is required";
    }

    if (!empty($phone) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    if ($advance_balance < 0) {
        $errors[] = "Advance balance cannot be negative";
    }

    // Check if customer name already exists
    if (empty($errors)) {
        $check_sql = "SELECT customer_id FROM customer WHERE customer_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $customer_name);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Customer name already exists";
        }
        $check_stmt->close();
    }

    // Insert into database
    if (empty($errors)) {
        $sql = "INSERT INTO customer (customer_name, customer_type, phone, address, advance_balance, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssds", $customer_name, $customer_type, $phone, $address, $advance_balance, $status);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Customer added successfully!";
            header("Location: customer-list.php");
            exit();
        } else {
            $errors[] = "Failed to add customer: " . $conn->error;
        }
        $stmt->close();
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>➕ Add New Customer</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="customer-list.php">Customers</a>
                <span>/</span>
                <span>Add Customer</span>
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

    <!-- Add Customer Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>👤 Customer Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="customerForm">
                    <div class="form-grid">
                        <!-- Customer Name -->
                        <div class="form-group">
                            <label class="form-label required">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" 
                                   placeholder="Enter customer name" required
                                   value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''; ?>">
                        </div>

                        <!-- Customer Type -->
                        <div class="form-group">
                            <label class="form-label required">Customer Type</label>
                            <select name="customer_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="Retail" <?php echo (isset($_POST['customer_type']) && $_POST['customer_type'] === 'Retail') ? 'selected' : ''; ?>>
                                    🛒 Retail (रू 80/L)
                                </option>
                                <option value="Wholesale" <?php echo (isset($_POST['customer_type']) && $_POST['customer_type'] === 'Wholesale') ? 'selected' : ''; ?>>
                                    📦 Wholesale (रू 75/L)
                                </option>
                                <option value="Dairy" <?php echo (isset($_POST['customer_type']) && $_POST['customer_type'] === 'Dairy') ? 'selected' : ''; ?>>
                                    🏭 Dairy (रू 70/L)
                                </option>
                            </select>
                            <small class="form-hint">Price will be auto-applied during sales</small>
                        </div>

                        <!-- Phone -->
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="Enter phone number"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            <small class="form-hint">Optional</small>
                        </div>

                        <!-- Address (Full Width) -->
                        <div class="form-group full-width">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3" 
                                      placeholder="Enter customer address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            <small class="form-hint">Optional</small>
                        </div>

                        <!-- Advance Balance -->
                        <div class="form-group">
                            <label class="form-label">Advance Balance (रू)</label>
                            <input type="number" name="advance_balance" class="form-control" 
                                   step="0.01" min="0" value="0"
                                   placeholder="Enter advance balance">
                            <small class="form-hint">Enter any advance payment received</small>
                        </div>

                        <!-- Status -->
                        <div class="form-group">
                            <label class="form-label required">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Pricing Information Box -->
                    <div class="info-box">
                        <strong>💰 Pricing Information:</strong>
                        <ul>
                            <li><strong>Retail:</strong> रू 80 per liter</li>
                            <li><strong>Wholesale:</strong> रू 75 per liter</li>
                            <li><strong>Dairy:</strong> रू 70 per liter</li>
                        </ul>
                        <p style="margin-top: 0.5rem; margin-bottom: 0;">
                            <strong>Note:</strong> When creating a sale, the price will be automatically set based on customer type.
                        </p>
                    </div>

                    <!-- Information Box -->
                    <div class="info-box" style="background: #d1ecf1; border-color: #bee5eb;">
                        <strong>ℹ Note:</strong>
                        <ul>
                            <li>Customer name is required and must be unique</li>
                            <li>Customer type determines the unit price for sales</li>
                            <li>Phone and address are optional but recommended</li>
                            <li>Advance balance will be recorded if customer pays upfront</li>
                            <li>Due balance will be calculated automatically from sales</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary">💾 Add Customer</button>
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
    const customerType = document.querySelector('select[name="customer_type"]').value;
    const advanceBalance = parseFloat(document.querySelector('input[name="advance_balance"]').value);
    
    if (customerName === '') {
        e.preventDefault();
        alert('Customer name is required');
        return false;
    }
    
    if (customerType === '') {
        e.preventDefault();
        alert('Please select customer type');
        return false;
    }
    
    if (advanceBalance < 0) {
        e.preventDefault();
        alert('Advance balance cannot be negative');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>