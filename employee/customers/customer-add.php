<?php
// employee/customers/customer-add.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Add Customer";
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $customer_type = $_POST['customer_type'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

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
        $sql = "INSERT INTO customer (customer_name, customer_type, phone, address, status) 
                VALUES (?, ?, ?, ?, 'Active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $customer_name, $customer_type, $phone, $address);

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
                            <select name="customer_type" class="form-control" id="customerType" required>
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
                    </div>

                    <!-- Pricing Information Box -->
                    <div class="info-box">
                        <strong> Pricing Information:</strong>
                        <ul>
                            <li><strong>Retail:</strong> रू 80 per liter</li>
                            <li><strong>Wholesale:</strong> रू 75 per liter</li>
                            <li><strong>Dairy:</strong> रू 70 per liter</li>
                        </ul>
                        <!-- <p style="margin-top: 0.5rem; margin-bottom: 0;">
                            <strong>Note:</strong> When you create a sale for this customer, the price will be automatically set based on their customer type.
                        </p> -->
                    </div>

                    <!-- Employee Information Box -->
                    <!-- <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                        <strong>ℹ Employee Permissions:</strong>
                        <ul>
                            <li>✅ You can add new walk-in customers</li>
                            <li>✅ Customer will be set as Active by default</li>
                            <li>❌ You cannot edit or delete customers</li>
                            <li>✅ You can create sales for this customer</li>
                        </ul>
                    </div> -->

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
});

// Show pricing hint based on selected type
document.getElementById('customerType').addEventListener('change', function() {
    const prices = {
        'Retail': 'रू 80 per liter',
        'Wholesale': 'रू 75 per liter',
        'Dairy': 'रू 70 per liter'
    };
    
    if (this.value && prices[this.value]) {
        console.log('Selected: ' + this.value + ' - Price: ' + prices[this.value]);
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>