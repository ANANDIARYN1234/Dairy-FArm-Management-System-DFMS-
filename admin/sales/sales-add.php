<?php
// admin/sales/sales-add.php - STANDALONE VERSION
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Add New Sale";
$errors = [];

// IMPORTANT: Only get customer_id if it exists (optional pre-selection)
$preselected_customer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Fetch customers
$customers_sql = "SELECT customer_id, customer_name, customer_type, phone FROM customer WHERE status = 'Active' ORDER BY customer_name";
$customers = $conn->query($customers_sql);

// Fetch available milk
$available_milk_sql = "SELECT * FROM available_milk ORDER BY hours_since_collection ASC, collection_date DESC, shift";
$available_milk = $conn->query($available_milk_sql);

// Get wastage count
$wastage_sql = "SELECT COUNT(*) as wastage_count, COALESCE(SUM(wasted_quantity), 0) as total_wasted FROM milk_wastage";
$wastage_result = $conn->query($wastage_sql);
$wastage_data = $wastage_result ? $wastage_result->fetch_assoc() : ['wastage_count' => 0, 'total_wasted' => 0];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sales_date = trim($_POST['sales_date']);
    $customer_id = intval($_POST['customer_id']);
    $remarks = trim($_POST['remarks']);
    
    $milk_ids = $_POST['milk_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    
    // Validation
    if (empty($sales_date)) {
        $errors[] = "Sales date is required";
    } else {
        $today = date('Y-m-d');
        if ($sales_date > $today) {
            $errors[] = "Sales date cannot be in the future";
        }
    }
    
    if ($customer_id <= 0) {
        $errors[] = "Please select a customer";
    }
    
    if (empty($milk_ids)) {
        $errors[] = "Please select at least one milk record";
    }
    
    // Get customer type and price
    if ($customer_id > 0) {
        $customer_sql = "SELECT customer_type FROM customer WHERE customer_id = ?";
        $customer_stmt = $conn->prepare($customer_sql);
        $customer_stmt->bind_param("i", $customer_id);
        $customer_stmt->execute();
        $customer_result = $customer_stmt->get_result();
        
        if ($customer_result->num_rows === 0) {
            $errors[] = "Invalid customer selected";
        } else {
            $customer_data = $customer_result->fetch_assoc();
            $sales_type = $customer_data['customer_type'];
            $unit_price = get_price_by_customer_type($sales_type);
        }
        $customer_stmt->close();
    }
    
    // Calculate totals
    $total_quantity = 0;
    $total_amount = 0;
    $milk_data = [];
    
    if (empty($errors)) {
        foreach ($milk_ids as $index => $milk_id) {
            $quantity = floatval($quantities[$index]);
            
            if ($quantity <= 0) {
                $errors[] = "Quantity must be greater than 0";
                break;
            }
            
            // Check available quantity
            $check_sql = "SELECT available_quantity FROM available_milk WHERE milk_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $milk_id);
            $check_stmt->execute();
            $available = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if (!$available || $quantity > $available['available_quantity']) {
                $errors[] = "Insufficient milk quantity for selected record";
                break;
            }
            
            $milk_data[] = [
                'milk_id' => $milk_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'amount' => $quantity * $unit_price
            ];
            
            $total_quantity += $quantity;
            $total_amount += ($quantity * $unit_price);
        }
    }
    
    // Insert into database
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            $sales_sql = "INSERT INTO sales (sales_date, total_quantity, total_amount, sales_type, sales_status, remarks, customer_id, user_id) 
                         VALUES (?, ?, ?, ?, 'Due', ?, ?, ?)";
            $sales_stmt = $conn->prepare($sales_sql);
            $user_id = get_user_id();
            $sales_stmt->bind_param("sddssii", $sales_date, $total_quantity, $total_amount, $sales_type, $remarks, $customer_id, $user_id);
            $sales_stmt->execute();
            $sales_id = $conn->insert_id;
            $sales_stmt->close();
            
            // Insert sale_milk records
            $sale_milk_sql = "INSERT INTO sale_milk (sales_id, milk_id, quantity_sold, unit_price) VALUES (?, ?, ?, ?)";
            $sale_milk_stmt = $conn->prepare($sale_milk_sql);
            
            foreach ($milk_data as $milk) {
                $sale_milk_stmt->bind_param("iidd", $sales_id, $milk['milk_id'], $milk['quantity'], $milk['unit_price']);
                $sale_milk_stmt->execute();
            }
            $sale_milk_stmt->close();
            
            $conn->commit();
            $_SESSION['success_message'] = "Sale recorded successfully!";
            header("Location: sales-list.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to record sale: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>Add New Sale</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="sales-list.php">Sales</a>
                <span>/</span>
                <span>Add Sale</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="sales-list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <?php echo get_flash_message(); ?>

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

    <form method="POST" action="" id="salesForm">
        <div class="form-container">
            
            <!-- <?php if ($wastage_data['wastage_count'] > 0): ?>
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠</span>
                    <div class="alert-message">
                        <strong>Wastage Alert!</strong> 
                        <?php echo $wastage_data['wastage_count']; ?> milk record(s) expired. 
                        Total wasted: <?php echo number_format($wastage_data['total_wasted'], 2); ?> Liters.
                    </div>
                </div>
            <?php endif; ?> -->
            
            <div class="card">
                <div class="card-header">
                    <h3>Sale Information</h3>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Sales Date</label>
                            <input type="date" name="sales_date" id="salesDate" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                            <!-- <small class="form-hint">Cannot be a future date</small> -->
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Customer</label>
                            <select name="customer_id" id="customerSelect" class="form-control" required>
                                <option value="">Select Customer</option>
                                <?php 
                                $customers->data_seek(0);
                                while ($customer = $customers->fetch_assoc()): 
                                    $price = get_price_by_customer_type($customer['customer_type']);
                                ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" 
                                            data-type="<?php echo $customer['customer_type']; ?>"
                                            data-price="<?php echo $price; ?>"
                                            <?php echo ($preselected_customer == $customer['customer_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                        - <?php echo $customer['customer_type']; ?>
                                        <?php echo $customer['phone'] ? ' (' . $customer['phone'] . ')' : ''; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Customer Type (Auto)</label>
                            <input type="text" id="customerTypeDisplay" class="form-control" readonly 
                                   style="background: #e8f4f8; cursor: not-allowed;" 
                                   placeholder="Select customer first">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Unit Price (Auto)</label>
                            <input type="text" id="unitPriceDisplay" class="form-control" readonly 
                                   style="background: #e8f4f8; cursor: not-allowed; color: var(--success); font-weight: bold;" 
                                   placeholder="Select customer first">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" 
                                      placeholder="Optional notes"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Select Fresh Milk Records</h3>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table class="data-table" id="milkTable">
                            <thead>
                                <tr>
                                    <th width="50">Select</th>
                                    <th>Date & Freshness</th>
                                    <th>Shift</th>
                                    <th>Cattle Tag</th>
                                    <th>Type/Breed</th>
                                    <th>Available (L)</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($available_milk->num_rows > 0): ?>
                                    <?php 
                                    $available_milk->data_seek(0);
                                    while ($milk = $available_milk->fetch_assoc()): 
                                    ?>
                                        <tr class="milk-row">
                                            <td>
                                                <input type="checkbox" name="milk_id[]" 
                                                       value="<?php echo $milk['milk_id']; ?>"
                                                       class="milk-checkbox"
                                                       data-available="<?php echo $milk['available_quantity']; ?>">
                                            </td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($milk['collection_date'])); ?>
                                                <br>
                                                <small><?php echo $milk['hours_since_collection']; ?>h old</small>
                                            </td>
                                            <td><span class="badge badge-info"><?php echo $milk['shift']; ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($milk['tag_id']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($milk['type_name']); ?> / 
                                                <?php echo htmlspecialchars($milk['breed_name']); ?>
                                            </td>
                                            <td><strong><?php echo number_format($milk['available_quantity'], 2); ?></strong></td>
                                            <td>
                                                <input type="number" name="quantity[]" 
                                                       class="form-control quantity-input" 
                                                       step="0.01" min="0" 
                                                       max="<?php echo $milk['available_quantity']; ?>"
                                                       placeholder="0.00" disabled>
                                            </td>
                                            <td class="amount-cell">रू 0.00</td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <p>No fresh milk available</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                    <td colspan="6" style="text-align: right;">Total:</td>
                                    <td id="totalQuantity">0.00 L</td>
                                    <td id="totalAmount">रू 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="form-actions" style="justify-content: flex-end;">
                        <a href="sales-list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Record Sale</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let currentUnitPrice = 0;

document.getElementById('customerSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const customerType = selectedOption.getAttribute('data-type');
    const unitPrice = selectedOption.getAttribute('data-price');
    
    if (customerType && unitPrice) {
        document.getElementById('customerTypeDisplay').value = customerType;
        document.getElementById('unitPriceDisplay').value = 'रू ' + parseFloat(unitPrice).toFixed(2) + ' / Liter';
        currentUnitPrice = parseFloat(unitPrice);
        calculateTotals();
    } else {
        document.getElementById('customerTypeDisplay').value = '';
        document.getElementById('unitPriceDisplay').value = '';
        currentUnitPrice = 0;
    }
});

window.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customerSelect');
    if (customerSelect.value) {
        customerSelect.dispatchEvent(new Event('change'));
    }
});

document.querySelectorAll('.milk-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const row = this.closest('tr');
        const qtyInput = row.querySelector('.quantity-input');
        
        if (this.checked) {
            qtyInput.disabled = false;
            qtyInput.value = this.dataset.available;
            qtyInput.focus();
        } else {
            qtyInput.disabled = true;
            qtyInput.value = '';
            row.querySelector('.amount-cell').textContent = 'रू 0.00';
        }
        calculateTotals();
    });
});

document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('input', calculateTotals);
});

function calculateTotals() {
    if (currentUnitPrice <= 0) return;
    
    let totalQty = 0;
    let totalAmount = 0;
    
    document.querySelectorAll('.milk-row').forEach(row => {
        const checkbox = row.querySelector('.milk-checkbox');
        if (checkbox.checked) {
            const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const amount = qty * currentUnitPrice;
            
            row.querySelector('.amount-cell').textContent = 'रू ' + amount.toFixed(2);
            totalQty += qty;
            totalAmount += amount;
        }
    });
    
    document.getElementById('totalQuantity').textContent = totalQty.toFixed(2) + ' L';
    document.getElementById('totalAmount').textContent = 'रू ' + totalAmount.toFixed(2);
}

document.getElementById('salesForm').addEventListener('submit', function(e) {
    const salesDate = document.getElementById('salesDate').value;
    const today = new Date().toISOString().split('T')[0];
    
    if (salesDate > today) {
        e.preventDefault();
        alert('Sales date cannot be in the future');
        return false;
    }
    
    const customerId = document.getElementById('customerSelect').value;
    const checkedBoxes = document.querySelectorAll('.milk-checkbox:checked');
    
    if (!customerId) {
        e.preventDefault();
        alert('Please select a customer');
        return false;
    }
    
    if (checkedBoxes.length === 0) {
        e.preventDefault();
        alert('Please select at least one milk record');
        return false;
    }
    
    if (currentUnitPrice <= 0) {
        e.preventDefault();
        alert('Invalid unit price. Please select a customer.');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>