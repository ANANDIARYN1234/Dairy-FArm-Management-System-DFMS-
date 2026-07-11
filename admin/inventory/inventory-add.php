<?php
// admin/inventory/inventory-add.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Add Inventory Item";
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = $_POST['category'];
    $unit = $_POST['unit'];
    $current_quantity = floatval($_POST['current_quantity']);
    $minimum_quantity = floatval($_POST['minimum_quantity']);
    
    // Validation
    if (empty($item_name)) {
        $errors[] = "Item name is required";
    }
    
    if ($current_quantity < 0) {
        $errors[] = "Current quantity cannot be negative";
    }
    
    if ($minimum_quantity < 0) {
        $errors[] = "Minimum quantity cannot be negative";
    }
    
    // Check if item already exists
    if (empty($errors)) {
        $check_sql = "SELECT inventory_id FROM inventory WHERE item_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $item_name);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Item name already exists";
        }
        $check_stmt->close();
    }
    
    // Insert into database
    if (empty($errors)) {
        $sql = "INSERT INTO inventory (item_name, category, unit, current_quantity, minimum_quantity) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdd", $item_name, $category, $unit, $current_quantity, $minimum_quantity);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Inventory item added successfully!";
            header("Location: inventory-list.php");
            exit();
        } else {
            $errors[] = "Failed to add item: " . $conn->error;
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
            <h1>➕ Add Inventory Item</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Add Item</span>
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

    <!-- Add Item Form -->
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
                                   value="<?php echo isset($_POST['item_name']) ? htmlspecialchars($_POST['item_name']) : ''; ?>">
                        </div>

                        <!-- Category -->
                        <div class="form-group">
                            <label class="form-label required">Category</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="Feed" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Feed') ? 'selected' : ''; ?>>Feed</option>
                                <option value="Medicine" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                                <option value="Fertilizer" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Fertilizer') ? 'selected' : ''; ?>>Fertilizer</option>
                                <option value="Supplement" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Supplement') ? 'selected' : ''; ?>>Supplement</option>
                                <option value="Equipment" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                                <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <!-- Unit -->
                        <div class="form-group">
                            <label class="form-label required">Unit of Measurement</label>
                            <select name="unit" class="form-control" required>
                                <option value="">Select Unit</option>
                                <option value="Kg" <?php echo (isset($_POST['unit']) && $_POST['unit'] === 'Kg') ? 'selected' : ''; ?>>Kg</option>
                                <option value="Bag" <?php echo (isset($_POST['unit']) && $_POST['unit'] === 'Bag') ? 'selected' : ''; ?>>Bag</option>
                                <option value="Litre" <?php echo (isset($_POST['unit']) && $_POST['unit'] === 'Litre') ? 'selected' : ''; ?>>Litre</option>
                                <option value="Piece" <?php echo (isset($_POST['unit']) && $_POST['unit'] === 'Piece') ? 'selected' : ''; ?>>Piece</option>
                                <option value="Box" <?php echo (isset($_POST['unit']) && $_POST['unit'] === 'Box') ? 'selected' : ''; ?>>Box</option>
                            </select>
                        </div>

                        <!-- Current Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Current Quantity</label>
                            <input type="number" name="current_quantity" class="form-control" 
                                   step="0.01" min="0" value="0" required
                                   placeholder="Enter current stock">
                            <small class="form-hint">Initial stock quantity</small>
                        </div>

                        <!-- Minimum Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Minimum Required Quantity</label>
                            <input type="number" name="minimum_quantity" class="form-control" 
                                   step="0.01" min="0" value="0" required
                                   placeholder="Enter minimum threshold">
                            <small class="form-hint">Alert will be shown when stock falls below this</small>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>ℹ Note:</strong>
                        <ul>
                            <li>Item name must be unique</li>
                            <li>Choose appropriate unit of measurement</li>
                            <li>Set minimum quantity for low stock alerts</li>
                            <li>Current quantity can be updated later using Stock In/Out</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary">💾 Add Item</button>
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
    const currentQty = parseFloat(document.querySelector('input[name="current_quantity"]').value);
    const minQty = parseFloat(document.querySelector('input[name="minimum_quantity"]').value);
    
    if (itemName === '') {
        e.preventDefault();
        alert('Item name is required');
        return false;
    }
    
    if (currentQty < 0 || minQty < 0) {
        e.preventDefault();
        alert('Quantities cannot be negative');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>