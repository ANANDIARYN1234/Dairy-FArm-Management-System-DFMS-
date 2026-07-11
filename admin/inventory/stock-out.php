<?php
// admin/inventory/stock-out.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Stock Out";
$errors = [];
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_date = trim($_POST['transaction_date']);
    $quantity = floatval($_POST['quantity']);
    $remarks = trim($_POST['remarks']);
    
    // Validation
    if (empty($transaction_date)) {
        $errors[] = "Transaction date is required";
    }
    
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0";
    }
    
    if ($quantity > $item['current_quantity']) {
        $errors[] = "Insufficient stock. Available: " . number_format($item['current_quantity'], 2) . " " . $item['unit'];
    }
    
    // Insert transaction (trigger will update inventory and validate stock)
    if (empty($errors)) {
        try {
            $trans_sql = "INSERT INTO inventory_transaction (inventory_id, user_id, transaction_type, quantity, transaction_date, remarks) 
                          VALUES (?, ?, 'OUT', ?, ?, ?)";
            $trans_stmt = $conn->prepare($trans_sql);
            $user_id = get_user_id();
            $trans_stmt->bind_param("iidss", $inventory_id, $user_id, $quantity, $transaction_date, $remarks);

            if ($trans_stmt->execute()) {
                $_SESSION['success_message'] = "Stock removed successfully!";
                header("Location: inventory-list.php");
                exit();
            } else {
                $errors[] = "Failed to remove stock: " . $conn->error;
            }
            $trans_stmt->close();
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📤 Stock Out</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Stock Out</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="inventory-list.php" class="btn btn-secondary">← Back</a>
        </div>
    </div>

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
        <div class="card">
            <div class="card-header" style="background: var(--warning); color: white;">
                <h3>📦 Item Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Item Name</div>
                    <div class="detail-value"><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></div>
                </div>
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Category</div>
                    <div class="detail-value"><span class="badge badge-info"><?php echo $item['category']; ?></span></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Current Stock</div>
                    <div class="detail-value" style="font-size: 1.5rem; color: var(--accent-blue);">
                        <strong><?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>📤 Remove Stock</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" id="stockForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Transaction Date</label>
                            <input type="date" name="transaction_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Quantity (<?php echo $item['unit']; ?>)</label>
                            <input type="number" name="quantity" class="form-control" 
                                   step="0.01" min="0.01" 
                                   max="<?php echo $item['current_quantity']; ?>"
                                   placeholder="Enter quantity to remove" required>
                            <small class="form-hint">Available: <?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?></small>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label required">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" 
                                      placeholder="Purpose (e.g., Used for cattle feed, Medicine used)" required></textarea>
                            <small class="form-hint">Explain why stock is being removed</small>
                        </div>
                    </div>

                    <div class="alert alert-warning" style="margin-top: 1.5rem;">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Warning:</strong>
                            <p style="margin-top: 0.5rem;">
                                You are removing stock from inventory. Make sure the quantity and purpose are correct.
                            </p>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <a href="inventory-list.php" class="btn btn-secondary">❌ Cancel</a>
                        <button type="submit" class="btn btn-warning">📤 Remove Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('stockForm').addEventListener('submit', function(e) {
    const qty = parseFloat(document.querySelector('input[name="quantity"]').value);
    const maxQty = <?php echo $item['current_quantity']; ?>;
    
    if (qty <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0');
        return false;
    }
    
    if (qty > maxQty) {
        e.preventDefault();
        alert('Insufficient stock. Available: ' + maxQty + ' <?php echo $item['unit']; ?>');
        return false;
    }
    
    if (!confirm('Are you sure you want to remove ' + qty + ' <?php echo $item['unit']; ?> from stock?')) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php $conn->close(); include '../../includes/footer.php'; ?>