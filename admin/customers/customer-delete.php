<?php
// admin/customers/customer-delete.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID";
    header("Location: customer-list.php");
    exit();
}

// Fetch customer details
$sql = "SELECT customer_name FROM customer WHERE customer_id = ?";
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

// Check if customer has any sales records
$check_sales_sql = "SELECT COUNT(*) as count FROM sales WHERE customer_id = ?";
$check_stmt = $conn->prepare($check_sales_sql);
$check_stmt->bind_param("i", $customer_id);
$check_stmt->execute();
$sales_count = $check_stmt->get_result()->fetch_assoc()['count'];
$check_stmt->close();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if ($sales_count > 0) {
        $_SESSION['error_message'] = "Cannot delete customer with existing sales records.";
        header("Location: customer-list.php");
        exit();
    }

    $delete_sql = "DELETE FROM customer WHERE customer_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $customer_id);

    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Customer deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete customer: " . $conn->error;
    }
    $delete_stmt->close();
    
    header("Location: customer-list.php");
    exit();
}

$page_title = "Delete Customer";
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🗑 Delete Customer</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="customer-list.php">Customers</a>
                <span>/</span>
                <span>Delete Customer</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="customer-list.php" class="btn btn-secondary">
                ← Back to List
            </a>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="form-container">
        <div class="card" style="border: 2px solid var(--danger);">
            <div class="card-header" style="background: var(--danger); color: white;">
                <h3>⚠ Confirm Deletion</h3>
            </div>
            <div class="card-body" style="padding: 2rem;">
                <?php if ($sales_count > 0): ?>
                    <!-- Cannot Delete - Has Sales Records -->
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <div class="alert-message">
                            <strong>Cannot Delete Customer!</strong>
                            <p style="margin-top: 0.5rem;">
                                This customer has <strong><?php echo $sales_count; ?></strong> sales record(s) 
                                and cannot be deleted. You must remove all associated sales records first.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Customer Details:</h6>
                        <p>
                            <strong>Name:</strong> <?php echo htmlspecialchars($customer['customer_name']); ?>
                        </p>
                        <p>
                            <strong>Total Sales:</strong> 
                            <span class="badge badge-warning"><?php echo $sales_count; ?> records</span>
                        </p>
                    </div>

                    <div class="info-box">
                        <strong>ℹ Options:</strong>
                        <ul>
                            <li>View customer sales records in the <a href="customer-ledger.php?id=<?php echo $customer_id; ?>">ledger</a></li>
                            <li>Set customer status to "Inactive" instead of deleting</li>
                            <li>Delete all associated sales records first (not recommended)</li>
                        </ul>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="customer-edit.php?id=<?php echo $customer_id; ?>" class="btn btn-warning">
                            ✏️ Edit Customer
                        </a>
                        <a href="customer-list.php" class="btn btn-secondary">
                            ← Go Back
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Can Delete - No Sales Records -->
                    <div class="alert alert-warning">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Warning!</strong>
                            <p style="margin-top: 0.5rem;">
                                You are about to permanently delete this customer. This action cannot be undone.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Customer Details:</h6>
                        <p>
                            <strong>Name:</strong> <?php echo htmlspecialchars($customer['customer_name']); ?>
                        </p>
                    </div>

                    <form method="POST" action="" id="deleteForm">
                        <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; margin: 1.5rem 0;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="confirmCheck" required style="width: 18px; height: 18px;">
                                <span>I understand this action is permanent and cannot be undone</span>
                            </label>
                        </div>

                        <div style="display: flex; justify-content: center; gap: 1rem;">
                            <a href="customer-list.php" class="btn btn-secondary">
                                ❌ Cancel
                            </a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                🗑 Delete Customer
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Enable delete button only when checkbox is checked
const confirmCheck = document.getElementById('confirmCheck');
const deleteBtn = document.getElementById('deleteBtn');

if (confirmCheck && deleteBtn) {
    confirmCheck.addEventListener('change', function() {
        deleteBtn.disabled = !this.checked;
        if (this.checked) {
            deleteBtn.style.opacity = '1';
            deleteBtn.style.cursor = 'pointer';
        } else {
            deleteBtn.style.opacity = '0.5';
            deleteBtn.style.cursor = 'not-allowed';
        }
    });

    // Final confirmation before deletion
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        if (!confirm('Are you absolutely sure you want to delete this customer? This action cannot be undone!')) {
            e.preventDefault();
            return false;
        }
    });
}
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>