<?php
// admin/sales/sales-edit.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Edit Sale";
$errors = [];
$sales_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sales_id <= 0) {
    $_SESSION['error_message'] = "Invalid sale ID";
    header("Location: sales-list.php");
    exit();
}

// Fetch sale details
$sale_sql = "SELECT * FROM sales WHERE sales_id = ?";
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

// Fetch customers
$customers_sql = "SELECT customer_id, customer_name, phone FROM customer WHERE status = 'Active' ORDER BY customer_name";
$customers = $conn->query($customers_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sales_date = trim($_POST['sales_date']);
    $customer_id = intval($_POST['customer_id']);
    $sales_type = $_POST['sales_type'];
    $sales_status = $_POST['sales_status'];
    $remarks = trim($_POST['remarks']);
    
    // Validation
    if (empty($sales_date)) {
        $errors[] = "Sales date is required";
    }
    
    if ($customer_id <= 0) {
        $errors[] = "Please select a customer";
    }
    
    // Update database
    if (empty($errors)) {
        $update_sql = "UPDATE sales 
                       SET sales_date = ?, customer_id = ?, sales_type = ?, 
                           sales_status = ?, remarks = ?
                       WHERE sales_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sisssi", $sales_date, $customer_id, $sales_type, 
                                   $sales_status, $remarks, $sales_id);

        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Sale updated successfully!";
            header("Location: sales-view.php?id=" . $sales_id);
            exit();
        } else {
            $errors[] = "Failed to update sale: " . $conn->error;
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
            <h1>✏️ Edit Sale #<?php echo $sales_id; ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="sales-list.php">Sales</a>
                <span>/</span>
                <span>Edit Sale</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="sales-view.php?id=<?php echo $sales_id; ?>" class="btn btn-info">
                👁 View Details
            </a>
            <a href="sales-list.php" class="btn btn-secondary">
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

    <!-- Edit Sale Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>📋 Sale Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="salesForm">
                    <div class="form-grid">
                        <!-- Sales Date -->
                        <div class="form-group">
                            <label class="form-label required">Sales Date</label>
                            <input type="date" name="sales_date" class="form-control" 
                                   value="<?php echo $sale['sales_date']; ?>" required>
                        </div>

                        <!-- Customer -->
                        <div class="form-group">
                            <label class="form-label required">Customer</label>
                            <select name="customer_id" class="form-control" required>
                                <option value="">Select Customer</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>"
                                            <?php echo $customer['customer_id'] == $sale['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                        <?php echo $customer['phone'] ? ' - ' . $customer['phone'] : ''; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Sales Type -->
                        <div class="form-group">
                            <label class="form-label required">Sales Type</label>
                            <select name="sales_type" class="form-control" required>
                                <option value="Retail" <?php echo $sale['sales_type'] === 'Retail' ? 'selected' : ''; ?>>Retail</option>
                                <option value="Wholesale" <?php echo $sale['sales_type'] === 'Wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                <option value="Dairy" <?php echo $sale['sales_type'] === 'Dairy' ? 'selected' : ''; ?>>Dairy</option>
                            </select>
                        </div>

                        <!-- Sales Status -->
                        <div class="form-group">
                            <label class="form-label required">Sales Status</label>
                            <select name="sales_status" class="form-control" required>
                                <option value="Paid" <?php echo $sale['sales_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="Partial" <?php echo $sale['sales_status'] === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                                <option value="Due" <?php echo $sale['sales_status'] === 'Due' ? 'selected' : ''; ?>>Due</option>
                            </select>
                        </div>

                        <!-- Total Quantity (Read-only) -->
                        <div class="form-group">
                            <label class="form-label">Total Quantity</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo number_format($sale['total_quantity'], 2); ?> L" 
                                   readonly style="background: var(--bg-tertiary);">
                            <small class="form-hint">Cannot be modified</small>
                        </div>

                        <!-- Total Amount (Read-only) -->
                        <div class="form-group">
                            <label class="form-label">Total Amount</label>
                            <input type="text" class="form-control" 
                                   value="रू <?php echo number_format($sale['total_amount'], 2); ?>" 
                                   readonly style="background: var(--bg-tertiary);">
                            <small class="form-hint">Cannot be modified</small>
                        </div>

                        <!-- Remarks (Full Width) -->
                        <div class="form-group full-width">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" 
                                      placeholder="Optional notes"><?php echo htmlspecialchars($sale['remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Warning Box -->
                    <div class="alert alert-warning" style="margin-top: 1.5rem;">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Important:</strong>
                            <ul style="margin: 0.5rem 0 0 1.5rem;">
                                <li>You can only edit basic sale information</li>
                                <li>To modify milk records, delete this sale and create a new one</li>
                                <li>Quantity and amount are calculated from milk records</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <a href="sales-list.php" class="btn btn-secondary">❌ Cancel</a>
                        <button type="submit" class="btn btn-primary">💾 Update Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('salesForm').addEventListener('submit', function(e) {
    const salesDate = document.querySelector('input[name="sales_date"]').value;
    const customerId = document.querySelector('select[name="customer_id"]').value;
    
    if (!salesDate) {
        e.preventDefault();
        alert('Sales date is required');
        return false;
    }
    
    if (!customerId || customerId == '0') {
        e.preventDefault();
        alert('Please select a customer');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>