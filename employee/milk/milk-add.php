<?php
// employee/milk/milk-add.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Add Milk Collection";
$errors = [];
$user_id = get_user_id();

// Fetch active female cattle - FIXED: Changed 'status' to 'life_status'
$cattle_sql = "SELECT c.cattle_id, c.tag_id, ct.type_name, b.breed_name
               FROM cattle c
               JOIN cattle_type ct ON c.type_id = ct.type_id
               JOIN breed b ON c.breed_id = b.breed_id
               WHERE c.life_status IN ('Alive', 'Pregnant') AND c.gender = 'Female'
               ORDER BY c.tag_id";
$cattle = $conn->query($cattle_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $collection_date = trim($_POST['collection_date']);
    $shift = $_POST['shift'];
    $cattle_id = intval($_POST['cattle_id']);
    $quantity = floatval($_POST['quantity']);

    // Validation
    if (empty($collection_date)) {
        $errors[] = "Collection date is required";
    }

    if ($cattle_id <= 0) {
        $errors[] = "Please select a cattle";
    }

    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0";
    }

    // Check if record already exists
    if (empty($errors)) {
        $check_sql = "SELECT milk_id FROM milk_collection 
                      WHERE cattle_id = ? AND collection_date = ? AND shift = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $cattle_id, $collection_date, $shift);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Milk collection already recorded for this cattle, date, and shift";
        }
        $check_stmt->close();
    }

    // Insert into database
    if (empty($errors)) {
        $sql = "INSERT INTO milk_collection (collection_date, shift, quantity, cattle_id, user_id) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdii", $collection_date, $shift, $quantity, $cattle_id, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Milk collection recorded successfully!";
            header("Location: my-collections.php");
            exit();
        } else {
            $errors[] = "Failed to record collection: " . $conn->error;
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
            <h1>🥛 Add Milk Collection</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Add Milk Collection</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="my-collections.php" class="btn btn-secondary">← My Collections</a>
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

    <!-- Add Milk Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>📋 Collection Details</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="milkForm">
                    <div class="form-grid">
                        <!-- Collection Date -->
                        <div class="form-group">
                            <label class="form-label required">Collection Date</label>
                            <input type="date" name="collection_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Shift -->
                        <div class="form-group">
                            <label class="form-label required">Shift</label>
                            <select name="shift" class="form-control" required id="shiftSelect">
                                <option value="">Select Shift</option>
                                <option value="Morning">🌅 Morning</option>
                                <option value="Evening">🌆 Evening</option>
                            </select>
                        </div>

                        <!-- Cattle -->
                        <div class="form-group">
                            <label class="form-label required">Select Cattle</label>
                            <select name="cattle_id" class="form-control" required id="cattleSelect">
                                <option value="">Select Cattle</option>
                                <?php 
                                if ($cattle && $cattle->num_rows > 0) {
                                    while ($cow = $cattle->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $cow['cattle_id']; ?>">
                                        <?php echo htmlspecialchars($cow['tag_id']); ?> - 
                                        <?php echo htmlspecialchars($cow['type_name']); ?> 
                                        (<?php echo htmlspecialchars($cow['breed_name']); ?>)
                                    </option>
                                <?php 
                                    endwhile;
                                } else {
                                    echo '<option value="">No female cattle available</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Quantity -->
                        <div class="form-group">
                            <label class="form-label required">Quantity (Liters)</label>
                            <input type="number" name="quantity" class="form-control" 
                                   step="0.01" min="0.01" placeholder="Enter quantity in liters" required>
                            <small class="form-hint">Enter milk quantity in liters</small>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <!-- <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>📝 Important Notes:</strong>
                        <ul>
                            <li>Record milk collection immediately after milking</li>
                            <li>Verify the cattle tag ID before recording</li>
                            <li>Each cattle can have only one record per shift per day</li>
                            <li>Quantity should be measured accurately</li>
                            <li>Report any unusual production patterns to administrator</li>
                        </ul>
                    </div> -->

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
                          WHERE user_id = ? AND collection_date = ?
                          GROUP BY shift";
            $today_stmt = $conn->prepare($today_sql);
            $today_stmt->bind_param("is", $user_id, $today);
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
    </div>
</div> -->

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

// Form validation
document.getElementById('milkForm').addEventListener('submit', function(e) {
    const quantity = parseFloat(document.querySelector('input[name="quantity"]').value);
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0');
        return false;
    }
    
    if (quantity > 50) {
        if (!confirm('Quantity seems unusually high (' + quantity + ' L). Are you sure?')) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>