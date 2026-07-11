<?php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';
require_admin();

$preselected_cattle = isset($_GET['cattle_id']) ? (int)$_GET['cattle_id'] : 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $collection_date = clean($_POST['collection_date'] ?? '');
    $shift = clean($_POST['shift'] ?? '');
    $quantity = clean($_POST['quantity'] ?? '');
    $cattle_id = (int)($_POST['cattle_id'] ?? 0);
    $user_id = get_user_id();
    
    $validator = new Validator($_POST);
    $validator->required('collection_date', 'Collection date is required')
              ->date('collection_date', 'Y-m-d', 'Invalid date format')
              ->required('shift', 'Shift is required')
              ->required('cattle_id', 'Cattle is required')
              ->required('quantity', 'Quantity is required')
              ->positive('quantity', 'Quantity must be greater than 0')
              ->max('quantity', 42, 'Quantity cannot exceed 42 liters'); 
    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        // Check duplicate BEFORE other validations
        $check = $conn->prepare("SELECT milk_id FROM milk_collection WHERE cattle_id = ? AND collection_date = ? AND shift = ?");
        $check->bind_param("iss", $cattle_id, $collection_date, $shift);
        $check->execute();
        $duplicate_result = $check->get_result();
        
        if ($duplicate_result->num_rows > 0) {
            $errors['general'] = 'Milk collection already recorded for this cattle on ' . date('d M Y', strtotime($collection_date)) . ' (' . $shift . ' shift). Please check existing records.';
        } else {
            // Backend gender validation to prevent male cattle milk recording
            $gender_check = $conn->prepare("SELECT gender FROM cattle WHERE cattle_id = ?");
            $gender_check->bind_param("i", $cattle_id);
            $gender_check->execute();
            $gender_result = $gender_check->get_result()->fetch_assoc();
            
            if (!$gender_result || $gender_result['gender'] !== 'Female') {
                $errors['cattle_id'] = 'Selected cattle is not a milk producer (male or invalid). Please choose a female cow.';
            } else {
                // Proceed with insert wrapped in try-catch
                try {
                    $stmt = $conn->prepare("INSERT INTO milk_collection (collection_date, shift, quantity, cattle_id, user_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssdii", $collection_date, $shift, $quantity, $cattle_id, $user_id);
                    
                    if ($stmt->execute()) {
                        set_flash_message("Milk collection recorded successfully!", 'success');
                        redirect('milk-list.php');
                        exit;
                    } else {
                        $errors['general'] = 'Failed to record collection. Please try again.';
                    }
                } catch (mysqli_sql_exception $e) {
                    // Catch duplicate entry error
                    if ($e->getCode() == 1062) {
                        $errors['general'] = 'This milk collection has already been recorded. Please check existing records.';
                    } else {
                        $errors['general'] = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
        
        $check->close();
    }
}

$page_title = 'Add Milk Collection';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🥛 Add Milk Collection</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="milk-list.php">Milk Collection Management</a>
                <span>/</span>
                <span>Add Milk Collection</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="milk-list.php" class="btn btn-secondary">← Back to List</a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Error!</strong>
                <p><?php echo htmlspecialchars($errors['general']); ?></p>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Add Milk Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>📋 Collection Details</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="milkForm">
                    <?php echo csrf_field(); ?>
                    
                    <div class="form-grid">
                        <!-- Collection Date -->
                        <div class="form-group">
                            <label class="form-label required">Collection Date</label>
                            <input type="date" name="collection_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['collection_date'] ?? date('Y-m-d')); ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                            <?php if (isset($errors['collection_date'])): ?>
                                <span class="error-msg"><?php echo $errors['collection_date']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Shift -->
                        <div class="form-group">
                            <label class="form-label required">Shift</label>
                            <select name="shift" class="form-control" required id="shiftSelect">
                                <option value="">Select Shift</option>
                                <option value="Morning" <?php echo (isset($_POST['shift']) && $_POST['shift'] === 'Morning') ? 'selected' : ''; ?>>🌅 Morning</option>
                                <option value="Evening" <?php echo (isset($_POST['shift']) && $_POST['shift'] === 'Evening') ? 'selected' : ''; ?>>🌆 Evening</option>
                            </select>
                            <?php if (isset($errors['shift'])): ?>
                                <span class="error-msg"><?php echo $errors['shift']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Cattle -->
                        <div class="form-group">
                            <label class="form-label required">Select Cattle</label>
                            <select name="cattle_id" class="form-control" required id="cattleSelect">
                                <option value="">Select Cattle</option>
                                <?php
                                $cattle = $conn->query("
                                    SELECT c.cattle_id, c.tag_id, ct.type_name, b.breed_name
                                    FROM cattle c 
                                    JOIN cattle_type ct ON c.type_id = ct.type_id 
                                    JOIN breed b ON c.breed_id = b.breed_id
                                    WHERE c.life_status IN ('Alive', 'Pregnant') 
                                    AND c.gender = 'Female' 
                                    ORDER BY c.tag_id
                                ");
                                
                                if ($cattle && $cattle->num_rows > 0) {
                                    while ($c = $cattle->fetch_assoc()):
                                        $selected = ($preselected_cattle == $c['cattle_id'] || (isset($_POST['cattle_id']) && $_POST['cattle_id'] == $c['cattle_id'])) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $c['cattle_id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($c['tag_id']); ?> - 
                                            <?php echo htmlspecialchars($c['type_name']); ?> 
                                            (<?php echo htmlspecialchars($c['breed_name']); ?>)
                                        </option>
                                    <?php 
                                    endwhile;
                                } else {
                                    echo '<option value="">No female cattle available</option>';
                                }
                                ?>
                            </select>
                            <?php if (isset($errors['cattle_id'])): ?>
                                <span class="error-msg"><?php echo $errors['cattle_id']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Quantity (Liters)</label>
                            <input type="number" name="quantity" class="form-control" 
                                step="0.01" min="0.01" max="42" placeholder="Enter quantity in liters"
                                value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required id="quantityInput">
                            <small class="form-hint">Enter milk quantity in liters (Maximum: 42L)</small>
                            <span class="error-msg" id="quantityError" style="display: none;"></span>
                            <?php if (isset($errors['quantity'])): ?>
                                <span class="error-msg"><?php echo $errors['quantity']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>📝 Important Notes:</strong>
                        <ul>
                            <li>Record milk collection immediately after milking</li>
                            <li>Verify the cattle tag ID before recording</li>
                            <li>Each cattle can have only one record per shift per day</li>
                            <li>Quantity should be measured accurately</li>
                            <li>Report any unusual production patterns</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">💾 Record Collection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Reference -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>📊 Today's Collections Summary</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <?php
            $today = date('Y-m-d');
            $today_sql = "SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(quantity), 0) as total_quantity,
                            shift
                          FROM milk_collection
                          WHERE collection_date = ?
                          GROUP BY shift";
            $today_stmt = $conn->prepare($today_sql);
            $today_stmt->bind_param("s", $today);
            $today_stmt->execute();
            $today_result = $today_stmt->get_result();
            
            $morning_count = 0;
            $evening_count = 0;
            $morning_qty = 0;
            $evening_qty = 0;
            
            while ($row = $today_result->fetch_assoc()) {
                if ($row['shift'] === 'Morning') {
                    $morning_count = $row['count'];
                    $morning_qty = $row['total_quantity'];
                } else {
                    $evening_count = $row['count'];
                    $evening_qty = $row['total_quantity'];
                }
            }
            $today_stmt->close();
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="color: var(--text-medium); font-size: 0.9rem;">🌅 Morning Shift</div>
                            <div style="font-size: 1.5rem; font-weight: bold; margin-top: 0.25rem;">
                                <?php echo $morning_count; ?> collections
                            </div>
                            <div style="color: var(--text-medium); font-size: 0.9rem;">
                                <?php echo number_format($morning_qty, 2); ?> Liters
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="color: var(--text-medium); font-size: 0.9rem;">🌆 Evening Shift</div>
                            <div style="font-size: 1.5rem; font-weight: bold; margin-top: 0.25rem;">
                                <?php echo $evening_count; ?> collections
                            </div>
                            <div style="color: var(--text-medium); font-size: 0.9rem;">
                                <?php echo number_format($evening_qty, 2); ?> Liters
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
</div>

<script>
// Auto-select shift based on time
window.addEventListener('load', function() {
    const hour = new Date().getHours();
    const shiftSelect = document.getElementById('shiftSelect');
    
    if (hour >= 5 && hour < 12) {
        shiftSelect.value = 'Morning';
    } else if (hour >= 15 && hour < 20) {
        shiftSelect.value = 'Evening';
    }
});

// Real-time quantity validation
document.getElementById('quantityInput').addEventListener('input', function() {
    const quantity = parseFloat(this.value);
    const errorSpan = document.getElementById('quantityError');
    const submitBtn = document.getElementById('submitBtn');
    
    if (quantity > 42) {
        errorSpan.textContent = 'Quantity cannot exceed 42 liters per collection';
        errorSpan.style.display = 'block';
        submitBtn.disabled = true;
        this.style.borderColor = 'var(--danger)';
    } else if (quantity <= 0 && this.value !== '') {
        errorSpan.textContent = 'Quantity must be greater than 0';
        errorSpan.style.display = 'block';
        submitBtn.disabled = true;
        this.style.borderColor = 'var(--danger)';
    } else {
        errorSpan.style.display = 'none';
        submitBtn.disabled = false;
        this.style.borderColor = '';
    }
});

// Form validation
document.getElementById('milkForm').addEventListener('submit', function(e) {
    const quantity = parseFloat(document.querySelector('input[name="quantity"]').value);
    const date = document.querySelector('input[name="collection_date"]');
    const shift = document.getElementById('shiftSelect');
    const cattle = document.getElementById('cattleSelect');
    const quantityInput = document.getElementById('quantityInput');
    const quantityError = document.getElementById('quantityError');
    
    let isValid = true;
    
    // Clear previous error styling
    date.style.borderColor = '';
    shift.style.borderColor = '';
    cattle.style.borderColor = '';
    
    // Validate date
    if (!date.value) {
        isValid = false;
        date.style.borderColor = 'var(--danger)';
        date.focus();
        e.preventDefault();
    }
    
    // Check future date
    if (date.value) {
        const selectedDate = new Date(date.value);
        const today = new Date();
        if (selectedDate > today) {
            isValid = false;
            date.style.borderColor = 'var(--danger)';
            date.focus();
            e.preventDefault();
        }
    }
    
    // Validate shift
    if (!shift.value) {
        isValid = false;
        shift.style.borderColor = 'var(--danger)';
        if (isValid) shift.focus();
        e.preventDefault();
    }
    
    // Validate cattle
    if (!cattle.value) {
        isValid = false;
        cattle.style.borderColor = 'var(--danger)';
        if (isValid) cattle.focus();
        e.preventDefault();
    }
    
    // Validate quantity
    if (!quantity || quantity <= 0) {
        isValid = false;
        quantityError.textContent = 'Quantity must be greater than 0';
        quantityError.style.display = 'block';
        quantityInput.style.borderColor = 'var(--danger)';
        if (isValid) quantityInput.focus();
        e.preventDefault();
    }
    
    if (quantity > 42) {
        isValid = false;
        quantityError.textContent = 'Quantity cannot exceed 42 liters per collection';
        quantityError.style.display = 'block';
        quantityInput.style.borderColor = 'var(--danger)';
        if (isValid) quantityInput.focus();
        e.preventDefault();
    }
    
    return isValid;
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>