<?php
/**
 * =========================================================
 * Edit Cattle Type
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';

require_admin();

$type_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get cattle type
$stmt = $conn->prepare("SELECT * FROM cattle_type WHERE type_id = ?");
$stmt->bind_param("i", $type_id);
$stmt->execute();
$type = $stmt->get_result()->fetch_assoc();

if (!$type) {
    set_flash_message('Cattle type not found', 'error');
    redirect('types-list.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_name = clean($_POST['type_name'] ?? '');
    
    // Validation
    $validator = new Validator($_POST);
    $validator->required('type_name', 'Type name is required')
              ->min('type_name', 2, 'Type name must be at least 2 characters')
              ->max('type_name', 50, 'Type name must not exceed 50 characters');
    
    // Check if type name already exists (excluding current type)
    $check = $conn->prepare("SELECT type_id FROM cattle_type WHERE type_name = ? AND type_id != ?");
    $check->bind_param("si", $type_name, $type_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $validator->errors()['type_name'] = 'This cattle type already exists';
    }
    
    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        // Update cattle type
        $stmt = $conn->prepare("UPDATE cattle_type SET type_name = ? WHERE type_id = ?");
        $stmt->bind_param("si", $type_name, $type_id);
        
        if ($stmt->execute()) {
            set_flash_message("Cattle type updated successfully!", 'success');
            redirect('types-list.php');
        } else {
            $errors['general'] = 'Failed to update cattle type. Please try again.';
        }
    }
}

$page_title = 'Edit Cattle Type';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>✏️ Edit Cattle Type</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="types-list.php">Cattle Management</a>
                <span>/</span>
                <span>Edit Type</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="types-list.php" class="btn btn-secondary">
                ← Back to Types
            </a>
        </div>
    </div>

    <!-- Form Container - Centered -->
    <div class="form-container" style="max-width: 800px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3>📋 Edit: <?php echo htmlspecialchars($type['type_name']); ?></h3>
            </div>

            <div class="card-body" style="padding: 2rem;">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <span class="alert-message"><?php echo $errors['general']; ?></span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="editTypeForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="form-group">
                        <label for="type_name" class="form-label required">Type Name</label>
                        <input 
                            type="text" 
                            name="type_name" 
                            id="type_name" 
                            class="form-control"
                            value="<?php echo htmlspecialchars($_POST['type_name'] ?? $type['type_name']); ?>"
                            required
                            autofocus
                        >
                        <?php if (isset($errors['type_name'])): ?>
                            <span class="error-msg"><?php echo $errors['type_name']; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions" style="margin-top: 2rem; justify-content: flex-end;">
                        <a href="types-list.php" class="btn btn-secondary">
                            ❌ Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            💾 Update Type
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Associated Breeds Card -->
        <?php
        // Get breeds count for this type
        $breed_count = $conn->query("SELECT COUNT(*) as count FROM breed WHERE type_id = {$type_id}")->fetch_assoc()['count'];
        ?>
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3>📊 Associated Breeds</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="info-box">
                    <p style="color: var(--text-medium); margin-bottom: 1rem;">
                        This type has <strong style="color: var(--accent-blue);"><?php echo $breed_count; ?></strong> breed(s) associated with it.
                    </p>
                    <?php if ($breed_count > 0): ?>
                        <a href="breeds-list.php?type_id=<?php echo $type_id; ?>" class="btn btn-info btn-sm">
                            👁️ View Breeds
                        </a>
                    <?php else: ?>
                        <p style="color: var(--text-light); font-style: italic; margin: 0;">
                            No breeds currently associated with this type.
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
document.getElementById('editTypeForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    const typeName = document.getElementById('type_name');
    
    // Remove previous error states
    document.querySelectorAll('.error-msg').forEach(msg => msg.remove());
    document.querySelectorAll('.form-control').forEach(input => input.style.borderColor = '');
    
    if (!typeName.value.trim()) {
        showError(typeName, 'Type name is required');
        isValid = false;
    } else if (typeName.value.trim().length < 2) {
        showError(typeName, 'Type name must be at least 2 characters');
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