<?php
// admin/sales/sales-delete.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$sales_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sales_id <= 0) {
    $_SESSION['error_message'] = "Invalid sale ID";
    header("Location: sales-list.php");
    exit();
}

// Fetch sale details
$sale_sql = "SELECT s.*, c.customer_name 
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

// Check if sale has payments
$payment_sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount_paid), 0) as total_paid 
                FROM payment WHERE sales_id = ?";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $sales_id);
$payment_stmt->execute();
$payment_info = $payment_stmt->get_result()->fetch_assoc();
$payment_stmt->close();

$has_payments = $payment_info['count'] > 0;

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if ($has_payments) {
        $_SESSION['error_message'] = "Cannot delete sale with existing payments. Please remove payments first.";
        header("Location: sales-list.php");
        exit();
    }

    $conn->begin_transaction();
    
    try {
        // Delete sale_milk records first (foreign key constraint)
        $delete_milk_sql = "DELETE FROM sale_milk WHERE sales_id = ?";
        $delete_milk_stmt = $conn->prepare($delete_milk_sql);
        $delete_milk_stmt->bind_param("i", $sales_id);
        $delete_milk_stmt->execute();
        $delete_milk_stmt->close();
        
        // Delete sale record
        $delete_sale_sql = "DELETE FROM sales WHERE sales_id = ?";
        $delete_sale_stmt = $conn->prepare($delete_sale_sql);
        $delete_sale_stmt->bind_param("i", $sales_id);
        $delete_sale_stmt->execute();
        $delete_sale_stmt->close();
        
        $conn->commit();
        $_SESSION['success_message'] = "Sale deleted successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to delete sale: " . $e->getMessage();
    }
    
    header("Location: sales-list.php");
    exit();
}

$page_title = "Delete Sale";
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🗑 Delete Sale #<?php echo $sales_id; ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="sales-list.php">Sales</a>
                <span>/</span>
                <span>Delete Sale</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="sales-list.php" class="btn btn-secondary">← Back to List</a>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="form-container">
        <div class="card" style="border: 2px solid var(--danger);">
            <div class="card-header" style="background: var(--danger); color: white;">
                <h3>⚠ Confirm Deletion</h3>
            </div>
            <div class="card-body" style="padding: 2rem;">
                <?php if ($has_payments): ?>
                    <!-- Cannot Delete - Has Payments -->
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <div class="alert-message">
                            <strong>Cannot Delete Sale!</strong>
                            <p style="margin-top: 0.5rem;">
                                This sale has <strong><?php echo $payment_info['count']; ?></strong> payment(s) 
                                totaling <strong>रू <?php echo number_format($payment_info['total_paid'], 2); ?></strong> 
                                and cannot be deleted. You must remove all payments first.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Sale Details:</h6>
                        <p><strong>Sale ID:</strong> #<?php echo $sales_id; ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($sale['sales_date'])); ?></p>
                        <p><strong>Total Amount:</strong> रू <?php echo number_format($sale['total_amount'], 2); ?></p>
                        <p>
                            <strong>Payments:</strong> 
                            <span class="badge badge-warning"><?php echo $payment_info['count']; ?> payment(s)</span>
                        </p>
                    </div>

                    <div class="info-box">
                        <strong>ℹ Options:</strong>
                        <ul>
                            <li>View sale details and payments in the <a href="sales-view.php?id=<?php echo $sales_id; ?>">sale view page</a></li>
                            <li>Delete all associated payments first</li>
                            <li>Contact administrator if you need assistance</li>
                        </ul>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="sales-view.php?id=<?php echo $sales_id; ?>" class="btn btn-info">
                            👁 View Sale Details
                        </a>
                        <a href="sales-list.php" class="btn btn-secondary">
                            ← Go Back
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Can Delete - No Payments -->
                    <div class="alert alert-warning">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Warning!</strong>
                            <p style="margin-top: 0.5rem;">
                                You are about to permanently delete this sale. This action cannot be undone.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Sale Details:</h6>
                        <p><strong>Sale ID:</strong> #<?php echo $sales_id; ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($sale['sales_date'])); ?></p>
                        <p><strong>Quantity:</strong> <?php echo number_format($sale['total_quantity'], 2); ?> L</p>
                        <p><strong>Total Amount:</strong> रू <?php echo number_format($sale['total_amount'], 2); ?></p>
                        <p><strong>Type:</strong> <span class="badge badge-info"><?php echo $sale['sales_type']; ?></span></p>
                        <p>
                            <strong>Status:</strong> 
                            <?php
                            $status_class = ['Paid' => 'success', 'Partial' => 'warning', 'Due' => 'danger'];
                            ?>
                            <span class="badge badge-<?php echo $status_class[$sale['sales_status']]; ?>">
                                <?php echo $sale['sales_status']; ?>
                            </span>
                        </p>
                    </div>

                    <div class="info-box" style="background: #fee; border-color: var(--danger);">
                        <strong>⚠ Important:</strong>
                        <ul style="margin: 0.5rem 0 0 1.5rem;">
                            <li>This will delete the sale record and all associated milk records</li>
                            <li>The milk quantity will become available again for other sales</li>
                            <li>This action is permanent and cannot be undone</li>
                        </ul>
                    </div>

                    <form method="POST" action="" id="deleteForm">
                        <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; margin: 1.5rem 0;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="confirmCheck" required style="width: 18px; height: 18px;">
                                <span>I understand this action is permanent and cannot be undone</span>
                            </label>
                        </div>

                        <div style="display: flex; justify-content: center; gap: 1rem;">
                            <a href="sales-list.php" class="btn btn-secondary">❌ Cancel</a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                🗑 Delete Sale
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
        if (!confirm('Are you absolutely sure you want to delete this sale? This action cannot be undone!')) {
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