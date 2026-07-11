<?php
// admin/inventory/inventory-delete.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$inventory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($inventory_id <= 0) {
    $_SESSION['error_message'] = "Invalid inventory ID";
    header("Location: inventory-list.php");
    exit();
}

// Fetch item details
$sql = "SELECT * FROM inventory WHERE inventory_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Item not found";
    header("Location: inventory-list.php");
    exit();
}

$item = $result->fetch_assoc();
$stmt->close();

// Check if item has transactions
$trans_sql = "SELECT COUNT(*) as count FROM inventory_transaction WHERE inventory_id = ?";
$trans_stmt = $conn->prepare($trans_sql);
$trans_stmt->bind_param("i", $inventory_id);
$trans_stmt->execute();
$trans_count = $trans_stmt->get_result()->fetch_assoc()['count'];
$trans_stmt->close();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if ($trans_count > 0) {
        $_SESSION['error_message'] = "Cannot delete item with transaction history.";
        header("Location: inventory-list.php");
        exit();
    }

    $delete_sql = "DELETE FROM inventory WHERE inventory_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $inventory_id);

    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Item deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete item";
    }
    $delete_stmt->close();
    
    header("Location: inventory-list.php");
    exit();
}

$page_title = "Delete Inventory Item";
include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>🗑 Delete Inventory Item</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Delete Item</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="inventory-list.php" class="btn btn-secondary">← Back</a>
        </div>
    </div>

    <div class="form-container">
        <div class="card" style="border: 2px solid var(--danger);">
            <div class="card-header" style="background: var(--danger); color: white;">
                <h3>⚠ Confirm Deletion</h3>
            </div>
            <div class="card-body" style="padding: 2rem;">
                <?php if ($trans_count > 0): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <div class="alert-message">
                            <strong>Cannot Delete Item!</strong>
                            <p style="margin-top: 0.5rem;">
                                This item has <strong><?php echo $trans_count; ?></strong> transaction(s) 
                                and cannot be deleted.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Item Details:</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($item['item_name']); ?></p>
                        <p><strong>Category:</strong> <?php echo $item['category']; ?></p>
                        <p><strong>Current Stock:</strong> <?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?></p>
                        <p><strong>Transactions:</strong> <span class="badge badge-warning"><?php echo $trans_count; ?></span></p>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="inventory-list.php" class="btn btn-secondary">← Go Back</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Warning!</strong>
                            <p style="margin-top: 0.5rem;">
                                You are about to permanently delete this item. This action cannot be undone.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Item Details:</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($item['item_name']); ?></p>
                        <p><strong>Category:</strong> <?php echo $item['category']; ?></p>
                        <p><strong>Current Stock:</strong> <?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?></p>
                    </div>

                    <form method="POST" id="deleteForm">
                        <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; margin: 1.5rem 0;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="confirmCheck" required style="width: 18px; height: 18px;">
                                <span>I understand this action is permanent</span>
                            </label>
                        </div>

                        <div style="display: flex; justify-content: center; gap: 1rem;">
                            <a href="inventory-list.php" class="btn btn-secondary">❌ Cancel</a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                🗑 Delete Item
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const confirmCheck = document.getElementById('confirmCheck');
const deleteBtn = document.getElementById('deleteBtn');

if (confirmCheck && deleteBtn) {
    confirmCheck.addEventListener('change', function() {
        deleteBtn.disabled = !this.checked;
        deleteBtn.style.opacity = this.checked ? '1' : '0.5';
    });

    document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
}
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>