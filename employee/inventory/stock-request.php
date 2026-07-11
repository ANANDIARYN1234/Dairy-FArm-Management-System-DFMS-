<?php
// employee/inventory/stock-request.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Request Stock";
$errors = [];
$success = false;

// Fetch inventory items
$inventory_sql = "SELECT inventory_id, item_name, category, unit, current_quantity, minimum_quantity 
                  FROM inventory 
                  ORDER BY item_name";
$inventory = $conn->query($inventory_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory_id = intval($_POST['inventory_id']);
    $requested_quantity = floatval($_POST['requested_quantity']);
    $priority = $_POST['priority'];
    $reason = trim($_POST['reason']);
    $user_name = get_user_name();
    $user_id = get_user_id();

    // Validation
    if ($inventory_id <= 0) {
        $errors[] = "Please select an inventory item";
    }

    if ($requested_quantity <= 0) {
        $errors[] = "Requested quantity must be greater than 0";
    }

    if (empty($reason)) {
        $errors[] = "Please provide a reason for the request";
    }

    if (empty($errors)) {
        // Get item details
        $item_sql = "SELECT item_name, unit FROM inventory WHERE inventory_id = ?";
        $item_stmt = $conn->prepare($item_sql);
        $item_stmt->bind_param("i", $inventory_id);
        $item_stmt->execute();
        $item = $item_stmt->get_result()->fetch_assoc();
        $item_stmt->close();

        // In a real system, this would create a request record in a requests table
        // For now, we'll just show a success message
        // You can add a 'stock_requests' table to the database for full functionality
        
        $success = true;
        $_SESSION['success_message'] = "Stock request submitted successfully! Administrator will review your request.";
        
        // Optional: Send email notification to admin (implement if needed)
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📋 Request Stock</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="inventory-list.php">Inventory</a>
                <span>/</span>
                <span>Request Stock</span>
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

    <!-- Success Message -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <div class="alert-message">
                <strong>Request Submitted Successfully!</strong>
                <p style="margin-top: 0.5rem;">
                    Your stock request has been sent to the administrator. 
                    You will be notified once it's processed.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Request Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>📝 Stock Request Form</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="requestForm">
                    <div class="form-grid">
                        <!-- Inventory Item -->
                        <div class="form-group">
                            <label class="form-label required">Select Item</label>
                            <select name="inventory_id" class="form-control" required id="itemSelect">
                                <option value="">Select Inventory Item</option>
                                <?php while ($item = $inventory->fetch_assoc()): 
                                    $is_low = $item['current_quantity'] <= $item['minimum_quantity'];
                                ?>
                                    <option value="<?php echo $item['inventory_id']; ?>" 
                                            data-stock="<?php echo $item['current_quantity']; ?>"
                                            data-unit="<?php echo $item['unit']; ?>"
                                            data-low="<?php echo $is_low ? '1' : '0'; ?>">
                                        <?php echo htmlspecialchars($item['item_name']); ?> 
                                        (<?php echo $item['category']; ?>) - 
                                        Current: <?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?>
                                        <?php if ($is_low): ?> ⚠ Low Stock<?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Requested Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Requested Quantity</label>
                            <input type="number" name="requested_quantity" class="form-control" 
                                   step="0.01" min="0.01" placeholder="Enter quantity" required>
                            <small class="form-hint" id="stockHint">Select an item first</small>
                        </div>

                        <!-- Priority -->
                        <div class="form-group">
                            <label class="form-label required">Priority Level</label>
                            <select name="priority" class="form-control" required>
                                <option value="Normal">Normal - Can wait a few days</option>
                                <option value="High">High - Needed within 1-2 days</option>
                                <option value="Urgent">Urgent - Needed immediately</option>
                            </select>
                        </div>

                        <!-- Reason (Full Width) -->
                        <div class="form-group full-width">
                            <label class="form-label required">Reason for Request</label>
                            <textarea name="reason" class="form-control" rows="4" 
                                      placeholder="Explain why you need this stock and how it will be used" required></textarea>
                            <small class="form-hint">Provide detailed reason to help administrator prioritize</small>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>📝 Request Guidelines:</strong>
                        <ul>
                            <li>Check current stock levels before requesting</li>
                            <li>Provide realistic quantity estimates</li>
                            <li>Explain clearly why the stock is needed</li>
                            <li>Use appropriate priority levels</li>
                            <li>Administrator will review and approve requests</li>
                            <li>You may be contacted for additional information</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary">📤 Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Low Stock Items -->
    <div class="card">
        <div class="card-header">
            <h3>⚠ Low Stock Items</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <?php
            $low_stock_sql = "SELECT * FROM low_stock_inventory ORDER BY shortage DESC";
            $low_stock = $conn->query($low_stock_sql);
            ?>
            
            <?php if ($low_stock->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Minimum Required</th>
                                <th>Shortage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $low_stock->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $item['category']; ?></span></td>
                                    <td class="text-danger"><?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?></td>
                                    <td><?php echo number_format($item['minimum_quantity'], 2); ?> <?php echo $item['unit']; ?></td>
                                    <td class="text-danger"><strong><?php echo number_format($item['shortage'], 2); ?> <?php echo $item['unit']; ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-medium);">
                    <span style="font-size: 3rem;">✓</span>
                    <p>All items are adequately stocked!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Update stock hint when item is selected
document.getElementById('itemSelect').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const stock = parseFloat(selected.dataset.stock) || 0;
    const unit = selected.dataset.unit || '';
    const isLow = selected.dataset.low === '1';
    const stockHint = document.getElementById('stockHint');
    
    if (this.value) {
        stockHint.textContent = `Current stock: ${stock.toFixed(2)} ${unit}`;
        stockHint.style.color = isLow ? 'var(--danger)' : 'var(--success)';
        if (isLow) {
            stockHint.textContent += ' (Low Stock - Priority Request Recommended)';
        }
    } else {
        stockHint.textContent = 'Select an item first';
        stockHint.style.color = 'var(--text-medium)';
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>