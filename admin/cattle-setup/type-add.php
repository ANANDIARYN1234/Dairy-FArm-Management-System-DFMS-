<?php
/**
 * =========================================================
 * Add Cattle Type
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';

require_admin();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_name = clean($_POST['type_name'] ?? '');
    
    // Validation
    $validator = new Validator($_POST);
    $validator->required('type_name', 'Type name is required')
              ->min('type_name', 2, 'Type name must be at least 2 characters')
              ->max('type_name', 50, 'Type name must not exceed 50 characters');
    
    // Check if type already exists (case-insensitive)
    if (!empty($type_name)) {
        $check = $conn->prepare("SELECT type_id FROM cattle_type WHERE LOWER(type_name) = LOWER(?)");
        $check->bind_param("s", $type_name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $validator->errors()['type_name'] = 'This cattle type already exists';
        }
        $check->close();
    }
    
    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        try {
            // Insert cattle type
            $stmt = $conn->prepare("INSERT INTO cattle_type (type_name) VALUES (?)");
            $stmt->bind_param("s", $type_name);
            
            if ($stmt->execute()) {
                set_flash_message("Cattle type '{$type_name}' added successfully!", 'success');
                redirect('types-list.php');
            } else {
                $errors['general'] = 'Failed to add cattle type. Please try again.';
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // Catch duplicate entry error
            if ($e->getCode() == 1062) {
                $errors['type_name'] = 'This cattle type already exists';
            } else {
                $errors['general'] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Add Cattle Type';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>➕ Add New Cattle Type</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="types-list.php">Cattle Management</a>
                <span>/</span>
                <span>Add Type</span>
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
                <h3>📋 Cattle Type Information</h3>
            </div>

            <div class="card-body" style="padding: 2rem;">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <span class="alert-message"><?php echo $errors['general']; ?></span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="addTypeForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="form-group">
                        <label for="type_name" class="form-label required">Type Name</label>
                        <input 
                            type="text" 
                            name="type_name" 
                            id="type_name" 
                            class="form-control <?php echo isset($errors['type_name']) ? 'error' : ''; ?>"
                            placeholder="e.g., Cow, Buffalo, Goat"
                            value="<?php echo htmlspecialchars($_POST['type_name'] ?? ''); ?>"
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
                            💾 Add Cattle Type
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Common Types Reference -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3>📚 Common Cattle Types</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="info-box">
                    <ul style="list-style: disc; padding-left: 2rem; color: var(--text-dark); margin: 0;">
                        <li><strong>Cow</strong> - Most common dairy animal</li>
                        <li><strong>Buffalo</strong> - High-fat milk producer</li>
                        <li><strong>Goat</strong> - Small-scale dairy farming</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Client-side validation
document.getElementById('addTypeForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    const typeName = document.getElementById('type_name');
    
    // Remove previous error states
    document.querySelectorAll('.error-msg:not([data-server])').forEach(msg => msg.remove());
    document.querySelectorAll('.form-control').forEach(input => {
        if (!input.classList.contains('error')) {
            input.style.borderColor = '';
        }
    });
    
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