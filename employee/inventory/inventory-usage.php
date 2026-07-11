<?php
// employee/inventory/inventory-usage.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Record Inventory Usage";
$errors = [];
$user_id = get_user_id();

// Fetch inventory items
$inventory_sql = "SELECT inventory_id, item_name, category, unit, current_quantity 
                  FROM inventory 
                  WHERE current_quantity > 0
                  ORDER BY item_name";
$inventory = $conn->query($inventory_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory_id = intval($_POST['inventory_id']);
    $quantity = floatval($_POST['quantity']);
    $transaction_date = trim($_POST['transaction_date']);
    $remarks = trim($_POST['remarks']);

    // Validation
    if ($inventory_id <= 0) {
        $errors[] = "Please select an inventory item";
    }

    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0";
    }

    if (empty($transaction_date)) {
        $errors[] = "Transaction date is required";
    }

    // Check available stock
    if (empty($errors)) {
        $check_sql = "SELECT current_quantity, item_name FROM inventory WHERE inventory_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $inventory_id);
        $check_stmt->execute();
        $stock = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if (!$stock) {
            $errors[] = "Invalid inventory item";
        } elseif ($quantity > $stock['current_quantity']) {
            $errors[] = "Insufficient stock. Available: " . number_format($stock['current_quantity'], 2);
        }
    }

    // Insert transaction (trigger will auto-update inventory)
    if (empty($errors)) {
        $sql = "INSERT INTO inventory_transaction (inventory_id, user_id, transaction_type, quantity, transaction_date, remarks) 
                VALUES (?, ?, 'OUT', ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidss", $inventory_id, $user_id, $quantity, $transaction_date, $remarks);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Inventory usage recorded successfully!";
            header("Location: inventory-list.php");
            exit();
        } else {
            $errors[] = "Failed to record usage: " . $conn->error;
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
            <h1>📝 Record Inventory Usage</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Record Usage</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="inventory-list.php" class="btn btn-secondary">← Back to Inventory</a>
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

    <!-- Usage Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>📋 Usage Details</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="usageForm">
                    <div class="form-grid">
                        <!-- Transaction Date -->
                        <div class="form-group">
                            <label class="form-label required">Transaction Date</label>
                            <input type="date" name="transaction_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Inventory Item -->
                        <div class="form-group">
                            <label class="form-label required">Select Item</label>
                            <select name="inventory_id" class="form-control" required id="itemSelect">
                                <option value="">Select Inventory Item</option>
                                <?php while ($item = $inventory->fetch_assoc()): ?>
                                    <option value="<?php echo $item['inventory_id']; ?>" 
                                            data-stock="<?php echo $item['current_quantity']; ?>"
                                            data-unit="<?php echo $item['unit']; ?>">
                                        <?php echo htmlspecialchars($item['item_name']); ?> 
                                        (<?php echo $item['category']; ?>) - 
                                        Available: <?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Quantity Used</label>
                            <input type="number" name="quantity" class="form-control" 
                                   step="0.01" min="0.01" placeholder="Enter quantity" required id="quantityInput">
                            <small class="form-hint" id="stockHint">Select an item first</small>
                        </div>

                        <!-- Remarks -->
                        <div class="form-group full-width">
                            <label class="form-label">Remarks / Purpose</label>
                            <textarea name="remarks" class="form-control" rows="3" 
                                      placeholder="Enter purpose of usage (optional)"></textarea>
                            <small class="form-hint">Describe how/where the item was used</small>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <!-- <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>📝 Important Notes:</strong>
                        <ul>
                            <li>Record usage immediately after using inventory items</li>
                            <li>Verify the item name and quantity before submitting</li>
                            <li>Quantity cannot exceed available stock</li>
                            <li>Provide clear remarks for tracking purposes</li>
                            <li>This action will reduce the stock automatically</li>
                        </ul>
                    </div> -->

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary">💾 Record Usage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Update stock hint when item is selected
document.getElementById('itemSelect').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const stock = parseFloat(selected.dataset.stock) || 0;
    const unit = selected.dataset.unit || '';
    const quantityInput = document.getElementById('quantityInput');
    const stockHint = document.getElementById('stockHint');
    
    if (this.value) {
        quantityInput.max = stock;
        stockHint.textContent = `Available stock: ${stock.toFixed(2)} ${unit}`;
        stockHint.style.color = stock > 0 ? 'var(--success)' : 'var(--danger)';
    } else {
        quantityInput.max = '';
        stockHint.textContent = 'Select an item first';
        stockHint.style.color = 'var(--text-medium)';
    }
});

// Form validation
document.getElementById('usageForm').addEventListener('submit', function(e) {
    const select = document.getElementById('itemSelect');
    const quantity = parseFloat(document.getElementById('quantityInput').value);
    const selected = select.options[select.selectedIndex];
    const stock = parseFloat(selected.dataset.stock) || 0;
    
    if (quantity > stock) {
        e.preventDefault();
        alert('Quantity exceeds available stock (' + stock.toFixed(2) + ')');
        return false;
    }
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>