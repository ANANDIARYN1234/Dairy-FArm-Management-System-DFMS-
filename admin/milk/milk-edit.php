<?php
// admin/milk/milk-edit.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = 'Edit Milk Collection';

$milk_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($milk_id <= 0) {
    $_SESSION['error_message'] = "Invalid milk record ID";
    header("Location: milk-list.php");
    exit();
}

// Fetch milk record
$stmt = $conn->prepare("SELECT mc.*, c.tag_id, c.cattle_id, ct.type_name 
                        FROM milk_collection mc 
                        JOIN cattle c ON mc.cattle_id = c.cattle_id 
                        JOIN cattle_type ct ON c.type_id = ct.type_id
                        WHERE mc.milk_id = ?");
$stmt->bind_param("i", $milk_id);
$stmt->execute();
$milk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$milk) {
    $_SESSION['error_message'] = "Milk record not found";
    header("Location: milk-list.php");
    exit();
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $collection_date = trim($_POST['collection_date']);
    $shift = $_POST['shift'];
    $quantity = floatval($_POST['quantity']);
    $cattle_id = intval($_POST['cattle_id']);
    
    // Validation
    if (empty($collection_date)) {
        $errors[] = "Collection date is required";
    }
    
    if (empty($shift)) {
        $errors[] = "Shift is required";
    }
    
    if ($cattle_id <= 0) {
        $errors[] = "Please select a cattle";
    }
    
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0";
    }
    
    // Check for duplicate entry
    if (empty($errors)) {
        $check = $conn->prepare("SELECT milk_id FROM milk_collection 
                                WHERE cattle_id = ? AND collection_date = ? AND shift = ? AND milk_id != ?");
        $check->bind_param("issi", $cattle_id, $collection_date, $shift, $milk_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Duplicate entry: This cattle already has a record for this date and shift";
        }
        $check->close();
    }
    
    // Update record
    if (empty($errors)) {
        $update_stmt = $conn->prepare("UPDATE milk_collection 
                                       SET collection_date = ?, shift = ?, quantity = ?, cattle_id = ? 
                                       WHERE milk_id = ?");
        $update_stmt->bind_param("ssdii", $collection_date, $shift, $quantity, $cattle_id, $milk_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Milk record updated successfully!";
            header("Location: milk-view.php?id=" . $milk_id);
            exit();
        } else {
            $errors[] = "Failed to update record: " . $conn->error;
        }
        $update_stmt->close();
    }
}

// Fetch all available cattle
$cattle_query = "SELECT c.cattle_id, c.tag_id, ct.type_name, b.breed_name 
                 FROM cattle c 
                 JOIN cattle_type ct ON c.type_id = ct.type_id 
                 JOIN breed b ON c.breed_id = b.breed_id
                 WHERE c.life_status = 'Alive' AND c.gender = 'Female'
                 ORDER BY c.tag_id";
$cattle_result = $conn->query($cattle_query);

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb and Actions -->
    <div class="page-header">
        <div class="header-content">
            <h1>✏️ Edit Milk Collection</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="milk-list.php">Milk Records</a>
                <span>/</span>
                <a href="milk-view.php?id=<?php echo $milk_id; ?>">Record #<?php echo $milk_id; ?></a>
                <span>/</span>
                <span>Edit</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="milk-view.php?id=<?php echo $milk_id; ?>" class="btn btn-secondary">← Back to View</a>
            <a href="milk-list.php" class="btn btn-secondary">📋 All Records</a>
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

    <!-- Edit Form - Centered -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>📝 Edit Record: <?php echo htmlspecialchars($milk['tag_id']); ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="editMilkForm">
                    <div class="form-grid">
                        <!-- Collection Date -->
                        <div class="form-group">
                            <label class="form-label required">Collection Date</label>
                            <input type="date" name="collection_date" class="form-control" 
                                   value="<?php echo isset($_POST['collection_date']) ? htmlspecialchars($_POST['collection_date']) : $milk['collection_date']; ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Shift -->
                        <div class="form-group">
                            <label class="form-label required">Shift</label>
                            <select name="shift" class="form-control" required>
                                <option value="Morning" <?php echo ($milk['shift'] === 'Morning' || (isset($_POST['shift']) && $_POST['shift'] === 'Morning')) ? 'selected' : ''; ?>>
                                    🌅 Morning
                                </option>
                                <option value="Evening" <?php echo ($milk['shift'] === 'Evening' || (isset($_POST['shift']) && $_POST['shift'] === 'Evening')) ? 'selected' : ''; ?>>
                                    🌆 Evening
                                </option>
                            </select>
                        </div>

                        <!-- Cattle Selection -->
                        <div class="form-group">
                            <label class="form-label required">Select Cattle</label>
                            <select name="cattle_id" id="cattleSelect" class="form-control" required>
                                <option value="">-- Select Cattle --</option>
                                <?php 
                                if ($cattle_result->num_rows > 0):
                                    while ($cattle = $cattle_result->fetch_assoc()): 
                                        $selected = ($cattle['cattle_id'] == $milk['cattle_id']) ? 'selected' : '';
                                        if (isset($_POST['cattle_id']) && $_POST['cattle_id'] == $cattle['cattle_id']) {
                                            $selected = 'selected';
                                        }
                                ?>
                                    <option value="<?php echo $cattle['cattle_id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($cattle['tag_id']); ?> 
                                        (<?php echo htmlspecialchars($cattle['type_name']); ?> - <?php echo htmlspecialchars($cattle['breed_name']); ?>)
                                    </option>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <option value="" disabled>No female cattle available</option>
                                <?php endif; ?>
                            </select>
                            <small class="form-hint">Only active female cattle are shown</small>
                        </div>

                        <!-- Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Quantity (Liters)</label>
                            <input type="number" name="quantity" class="form-control" 
                                   step="0.01" min="0.01" 
                                   value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : $milk['quantity']; ?>" 
                                   placeholder="Enter quantity in liters" required>
                            <small class="form-hint">Example: 15.50</small>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <!-- <div class="info-box">
                        <strong>ℹ Note:</strong>
                        <ul>
                            <li>Collection date cannot be in the future</li>
                            <li>Each cattle can have only one record per date per shift</li>
                            <li>Quantity must be greater than 0</li>
                            <li>Only active female cattle are available for selection</li>
                        </ul>
                    </div> -->

                    <!-- Original Record Info -->
                    <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                        <strong>📋 Original Record:</strong>
                        <ul>
                            <li><strong>Cattle:</strong> <?php echo htmlspecialchars($milk['tag_id']); ?> (<?php echo htmlspecialchars($milk['type_name']); ?>)</li>
                            <li><strong>Date:</strong> <?php echo date('d M Y', strtotime($milk['collection_date'])); ?></li>
                            <li><strong>Shift:</strong> <?php echo $milk['shift']; ?></li>
                            <li><strong>Original Quantity:</strong> <?php echo number_format($milk['quantity'], 2); ?> Liters</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <a href="milk-view.php?id=<?php echo $milk_id; ?>" class="btn btn-secondary">❌ Cancel</a>
                        <button type="submit" class="btn btn-primary">💾 Update Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('editMilkForm').addEventListener('submit', function(e) {
    const cattleId = document.querySelector('select[name="cattle_id"]').value;
    const quantity = parseFloat(document.querySelector('input[name="quantity"]').value);
    const date = document.querySelector('input[name="collection_date"]').value;
    
    if (!cattleId || cattleId === '') {
        e.preventDefault();
        alert('Please select a cattle');
        return false;
    }
    
    if (quantity <= 0 || isNaN(quantity)) {
        e.preventDefault();
        alert('Please enter a valid quantity greater than 0');
        return false;
    }
    
    if (!date) {
        e.preventDefault();
        alert('Please select a collection date');
        return false;
    }
    
    // Confirm update
    if (!confirm('Are you sure you want to update this milk record?')) {
        e.preventDefault();
        return false;
    }
});

// Highlight selected cattle
document.getElementById('cattleSelect').addEventListener('change', function() {
    if (this.value) {
        this.style.borderColor = 'var(--success)';
        this.style.background = '#d4edda';
    } else {
        this.style.borderColor = '';
        this.style.background = '';
    }
});

// Auto-highlight if cattle is already selected
window.addEventListener('DOMContentLoaded', function() {
    const cattleSelect = document.getElementById('cattleSelect');
    if (cattleSelect.value) {
        cattleSelect.style.borderColor = 'var(--success)';
        cattleSelect.style.background = '#d4edda';
    }
});


</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>