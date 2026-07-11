<?php
// admin/inventory/inventory-edit.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Edit Inventory Item";
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
    $item_name = trim($_POST['item_name']);
    $category = $_POST['category'];
    $unit = $_POST['unit'];
    $minimum_quantity = floatval($_POST['minimum_quantity']);
    
    // Validation
    if (empty($item_name)) {
        $errors[] = "Item name is required";
    }
    
    if ($minimum_quantity < 0) {
        $errors[] = "Minimum quantity cannot be negative";
    }
    
    // Check if item name already exists (excluding current item)
    if (empty($errors)) {
        $check_sql = "SELECT inventory_id FROM inventory WHERE item_name = ? AND inventory_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $item_name, $inventory_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Item name already exists";
        }
        $check_stmt->close();
    }
    
    // Update database
    if (empty($errors)) {
        $update_sql = "UPDATE inventory 
                       SET item_name = ?, category = ?, unit = ?, minimum_quantity = ?
                       WHERE inventory_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssdi", $item_name, $category, $unit, $minimum_quantity, $inventory_id);

        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Item updated successfully!";
            header("Location: inventory-list.php");
            exit();
        } else {
            $errors[] = "Failed to update item: " . $conn->error;
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
            <h1>✏️ Edit Inventory Item</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Edit Item</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="inventory-list.php" class="btn btn-secondary">← Back to List</a>
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

    <!-- Edit Item Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>📦 Item Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="inventoryForm">
                    <div class="form-grid">
                        <!-- Item Name -->
                        <div class="form-group">
                            <label class="form-label required">Item Name</label>
                            <input type="text" name="item_name" class="form-control" 
                                   placeholder="e.g., Cattle Feed, Medicine" required
                                   value="<?php echo htmlspecialchars($item['item_name']); ?>">
                        </div>

                        <!-- Category -->
                        <div class="form-group">
                            <label class="form-label required">Category</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="Feed" <?php echo $item['category'] === 'Feed' ? 'selected' : ''; ?>>Feed</option>
                                <option value="Medicine" <?php echo $item['category'] === 'Medicine' ? 'selected' : ''; ?>>Medicine</option>
                                <option value="Fertilizer" <?php echo $item['category'] === 'Fertilizer' ? 'selected' : ''; ?>>Fertilizer</option>
                                <option value="Supplement" <?php echo $item['category'] === 'Supplement' ? 'selected' : ''; ?>>Supplement</option>
                                <option value="Equipment" <?php echo $item['category'] === 'Equipment' ? 'selected' : ''; ?>>Equipment</option>
                                <option value="Other" <?php echo $item['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <!-- Unit -->
                        <div class="form-group">
                            <label class="form-label required">Unit of Measurement</label>
                            <select name="unit" class="form-control" required>
                                <option value="">Select Unit</option>
                                <option value="Kg" <?php echo $item['unit'] === 'Kg' ? 'selected' : ''; ?>>Kg</option>
                                <option value="Bag" <?php echo $item['unit'] === 'Bag' ? 'selected' : ''; ?>>Bag</option>
                                <option value="Litre" <?php echo $item['unit'] === 'Litre' ? 'selected' : ''; ?>>Litre</option>
                                <option value="Piece" <?php echo $item['unit'] === 'Piece' ? 'selected' : ''; ?>>Piece</option>
                                <option value="Box" <?php echo $item['unit'] === 'Box' ? 'selected' : ''; ?>>Box</option>
                            </select>
                        </div>

                        <!-- Current Quantity (Read-only) -->
                        <div class="form-group">
                            <label class="form-label">Current Quantity</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?>" 
                                   readonly style="background: var(--bg-tertiary);">
                            <small class="form-hint">Use Stock In/Out to change quantity</small>
                        </div>

                        <!-- Minimum Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Minimum Required Quantity</label>
                            <input type="number" name="minimum_quantity" class="form-control" 
                                   step="0.01" min="0" required
                                   value="<?php echo $item['minimum_quantity']; ?>"
                                   placeholder="Enter minimum threshold">
                            <small class="form-hint">Alert will be shown when stock falls below this</small>
                        </div>
                    </div>

                    <!-- Warning Box -->
                    <div class="alert alert-warning" style="margin-top: 1.5rem;">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Important:</strong>
                            <ul style="margin: 0.5rem 0 0 1.5rem;">
                                <li>Current quantity cannot be edited directly</li>
                                <li>Use "Stock In" to add stock or "Stock Out" to remove stock</li>
                                <li>All stock changes are tracked in transaction history</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <a href="inventory-list.php" class="btn btn-secondary">❌ Cancel</a>
                        <button type="submit" class="btn btn-primary">💾 Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('inventoryForm').addEventListener('submit', function(e) {
    const itemName = document.querySelector('input[name="item_name"]').value.trim();
    const minQty = parseFloat(document.querySelector('input[name="minimum_quantity"]').value);
    
    if (itemName === '') {
        e.preventDefault();
        alert('Item name is required');
        return false;
    }
    
    if (minQty < 0) {
        e.preventDefault();
        alert('Minimum quantity cannot be negative');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>