<?php
// admin/inventory/stock-adjustment.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Stock Adjustment";
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
    $new_quantity = floatval($_POST['new_quantity']);
    $remarks = trim($_POST['remarks']);
    
    // Validation
    if (empty($transaction_date)) {
        $errors[] = "Transaction date is required";
    }
    
    if ($new_quantity < 0) {
        $errors[] = "Quantity cannot be negative";
    }
    
    if (empty($remarks)) {
        $errors[] = "Remarks are required for stock adjustment";
    }
    
    // Insert adjustment transaction (trigger will set quantity to exact value)
    if (empty($errors)) {
        $trans_sql = "INSERT INTO inventory_transaction (inventory_id, user_id, transaction_type, quantity, transaction_date, remarks) 
                      VALUES (?, ?, 'ADJUSTMENT', ?, ?, ?)";
        $trans_stmt = $conn->prepare($trans_sql);
        $user_id = get_user_id();
        $trans_stmt->bind_param("iidss", $inventory_id, $user_id, $new_quantity, $transaction_date, $remarks);

        if ($trans_stmt->execute()) {
            $_SESSION['success_message'] = "Stock adjusted successfully!";
            header("Location: inventory-list.php");
            exit();
        } else {
            $errors[] = "Failed to adjust stock: " . $conn->error;
        }
        $trans_stmt->close();
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>⚖️ Stock Adjustment</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Stock Adjustment</span>
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
            <div class="card-header" style="background: var(--info); color: white;">
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
                <h3>⚖️ Adjust Stock</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" id="adjustmentForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Adjustment Date</label>
                            <input type="date" name="transaction_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">New Quantity (<?php echo $item['unit']; ?>)</label>
                            <input type="number" name="new_quantity" class="form-control" 
                                   step="0.01" min="0" 
                                   value="<?php echo $item['current_quantity']; ?>"
                                   placeholder="Enter exact quantity" required>
                            <small class="form-hint">
                                This will SET the stock to this exact value<br>
                                Current: <?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?>
                            </small>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label required">Remarks (Reason for Adjustment)</label>
                            <textarea name="remarks" class="form-control" rows="3" 
                                      placeholder="Explain the reason for this adjustment (e.g., Physical count, Damaged goods, Correction)" required></textarea>
                            <small class="form-hint">Required: Explain why you're adjusting the stock</small>
                        </div>
                    </div>

                    <div class="alert alert-warning" style="margin-top: 1.5rem;">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Important:</strong>
                            <ul style="margin: 0.5rem 0 0 1.5rem;">
                                <li>This will <strong>SET</strong> the stock to the exact quantity you enter</li>
                                <li>Use this for physical stock counts or corrections</li>
                                <li>For regular stock in/out, use the Stock In or Stock Out buttons</li>
                                <li>All adjustments are logged and tracked</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <a href="inventory-list.php" class="btn btn-secondary">❌ Cancel</a>
                        <button type="submit" class="btn btn-primary">⚖️ Adjust Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('adjustmentForm').addEventListener('submit', function(e) {
    const newQty = parseFloat(document.querySelector('input[name="new_quantity"]').value);
    const currentQty = <?php echo $item['current_quantity']; ?>;
    const remarks = document.querySelector('textarea[name="remarks"]').value.trim();
    
    if (newQty < 0) {
        e.preventDefault();
        alert('Quantity cannot be negative');
        return false;
    }
    
    if (remarks === '') {
        e.preventDefault();
        alert('Please provide a reason for the stock adjustment');
        return false;
    }
    
    const difference = newQty - currentQty;
    const changeText = difference > 0 ? '+' + difference.toFixed(2) : difference.toFixed(2);
    
    if (!confirm('Are you sure you want to adjust stock?\n\nCurrent: ' + currentQty + ' <?php echo $item['unit']; ?>\nNew: ' + newQty + ' <?php echo $item['unit']; ?>\nChange: ' + changeText + ' <?php echo $item['unit']; ?>')) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php $conn->close(); include '../../includes/footer.php'; ?>