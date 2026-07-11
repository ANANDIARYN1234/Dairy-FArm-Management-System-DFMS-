<?php
/**
 * =========================================================
 * Edit Breed
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';

require_admin();

$breed_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get breed
$stmt = $conn->prepare("
    SELECT b.*, ct.type_name 
    FROM breed b
    JOIN cattle_type ct ON b.type_id = ct.type_id
    WHERE b.breed_id = ?
");
$stmt->bind_param("i", $breed_id);
$stmt->execute();
$breed = $stmt->get_result()->fetch_assoc();

if (!$breed) {
    set_flash_message('Breed not found', 'error');
    redirect('breeds-list.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $breed_name = clean($_POST['breed_name'] ?? '');
    $type_id = (int)($_POST['type_id'] ?? 0);
    
    // Validation
    $validator = new Validator($_POST);
    $validator->required('breed_name', 'Breed name is required')
              ->min('breed_name', 2, 'Breed name must be at least 2 characters')
              ->max('breed_name', 100, 'Breed name must not exceed 100 characters')
              ->required('type_id', 'Cattle type is required');
    
    if ($type_id <= 0) {
        $validator->errors()['type_id'] = 'Please select a valid cattle type';
    }
    
    // Check if breed already exists (excluding current breed)
    if ($type_id > 0) {
        $check = $conn->prepare("SELECT breed_id FROM breed WHERE breed_name = ? AND type_id = ? AND breed_id != ?");
        $check->bind_param("sii", $breed_name, $type_id, $breed_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $validator->errors()['breed_name'] = 'This breed already exists for the selected type';
        }
    }
    
    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        // Update breed
        $stmt = $conn->prepare("UPDATE breed SET breed_name = ?, type_id = ? WHERE breed_id = ?");
        $stmt->bind_param("sii", $breed_name, $type_id, $breed_id);
        
        if ($stmt->execute()) {
            set_flash_message("Breed updated successfully!", 'success');
            redirect('breeds-list.php?type_id=' . $type_id);
        } else {
            $errors['general'] = 'Failed to update breed. Please try again.';
        }
    }
}

$page_title = 'Edit Breed';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>✏️ Edit Breed</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="types-list.php">Cattle Management</a>
                <span>/</span>
                <a href="breeds-list.php?type_id=<?php echo $breed['type_id']; ?>">Breeds</a>
                <span>/</span>
                <span>Edit Breed</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="breeds-list.php?type_id=<?php echo $breed['type_id']; ?>" class="btn btn-secondary">
                ← Back to Breeds
            </a>
        </div>
    </div>

    <!-- Form Container - Centered -->
    <div class="form-container" style="max-width: 800px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3>📋 Edit: <?php echo htmlspecialchars($breed['breed_name']); ?></h3>
            </div>

            <div class="card-body" style="padding: 2rem;">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <span class="alert-message"><?php echo $errors['general']; ?></span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="editBreedForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="type_id" class="form-label required">Cattle Type</label>
                            <select name="type_id" id="type_id" class="form-control" required>
                                <option value="">-- Select Cattle Type --</option>
                                <?php
                                $types = $conn->query("SELECT * FROM cattle_type ORDER BY type_name ASC");
                                while ($type = $types->fetch_assoc()):
                                    $selected = (isset($_POST['type_id']) ? $_POST['type_id'] : $breed['type_id']) == $type['type_id'] ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $type['type_id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <?php if (isset($errors['type_id'])): ?>
                                <span class="error-msg"><?php echo $errors['type_id']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="breed_name" class="form-label required">Breed Name</label>
                            <input 
                                type="text" 
                                name="breed_name" 
                                id="breed_name" 
                                class="form-control"
                                placeholder="e.g., Holstein, Jersey, Murrah"
                                value="<?php echo htmlspecialchars($_POST['breed_name'] ?? $breed['breed_name']); ?>"
                                required
                                autofocus
                            >
                            <?php if (isset($errors['breed_name'])): ?>
                                <span class="error-msg"><?php echo $errors['breed_name']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 2rem; justify-content: flex-end;">
                        <a href="breeds-list.php?type_id=<?php echo $breed['type_id']; ?>" class="btn btn-secondary">
                            ❌ Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            💾 Update Breed
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Usage Information Card -->
        <?php
        // Get cattle count for this breed
        $cattle_count = $conn->query("SELECT COUNT(*) as count FROM cattle WHERE breed_id = {$breed_id}")->fetch_assoc()['count'];
        ?>
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3>📊 Usage Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="info-box">
                    <p style="color: var(--text-medium); margin-bottom: 1rem;">
                        This breed is currently assigned to <strong style="color: var(--accent-blue);"><?php echo $cattle_count; ?></strong> cattle.
                    </p>
                    <?php if ($cattle_count > 0): ?>
                        <a href="../cattle/cattle-list.php?breed_id=<?php echo $breed_id; ?>" class="btn btn-info btn-sm">
                            👁️ View Cattle
                        </a>
                    <?php else: ?>
                        <p style="color: var(--text-light); font-style: italic; margin: 0;">
                            No cattle currently assigned to this breed.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Client-side validation
document.getElementById('editBreedForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    const typeId = document.getElementById('type_id');
    const breedName = document.getElementById('breed_name');
    
    // Remove previous error states
    document.querySelectorAll('.error-msg').forEach(msg => msg.remove());
    document.querySelectorAll('.form-control').forEach(input => input.style.borderColor = '');
    
    if (!typeId.value) {
        showError(typeId, 'Please select a cattle type');
        isValid = false;
    }
    
    if (!breedName.value.trim()) {
        showError(breedName, 'Breed name is required');
        isValid = false;
    } else if (breedName.value.trim().length < 2) {
        showError(breedName, 'Breed name must be at least 2 characters');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
    }
});

function showError(input, message) {
    input.style.borderColor = 'var(--danger)';
    const errorMsg = document.createElement('span');
    errorMsg.className = 'error-msg';
    errorMsg.textContent = message;
    errorMsg.style.display = 'block';
    errorMsg.style.color = 'var(--danger)';
    errorMsg.style.fontSize = '0.875rem';
    errorMsg.style.marginTop = '0.375rem';
    input.parentNode.appendChild(errorMsg);
}
</script>