<?php
/**
 * =========================================================
 * Add Breed
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';

require_admin();

$preselected_type = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;

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
    
    // Check if breed already exists for this type (case-insensitive)
    if ($type_id > 0 && !empty($breed_name)) {
        $check = $conn->prepare("SELECT breed_id FROM breed WHERE LOWER(breed_name) = LOWER(?) AND type_id = ?");
        $check->bind_param("si", $breed_name, $type_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $validator->errors()['breed_name'] = 'This breed already exists for the selected type';
        }
        $check->close();
    }
    
    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        try {
            // Insert breed
            $stmt = $conn->prepare("INSERT INTO breed (breed_name, type_id) VALUES (?, ?)");
            $stmt->bind_param("si", $breed_name, $type_id);
            
            if ($stmt->execute()) {
                set_flash_message("Breed '{$breed_name}' added successfully!", 'success');
                redirect('breeds-list.php?type_id=' . $type_id);
            } else {
                $errors['general'] = 'Failed to add breed. Please try again.';
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // Catch duplicate entry error
            if ($e->getCode() == 1062) {
                $errors['breed_name'] = 'This breed already exists for the selected type';
            } else {
                $errors['general'] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Add Breed';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>➕ Add New Breed</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="types-list.php">Cattle Management</a>
                <span>/</span>
                <a href="breeds-list.php<?php echo $preselected_type > 0 ? "?type_id={$preselected_type}" : ''; ?>">Breeds</a>
                <span>/</span>
                <span>Add Breed</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="breeds-list.php<?php echo $preselected_type > 0 ? "?type_id={$preselected_type}" : ''; ?>" class="btn btn-secondary">
                ← Back to Breeds
            </a>
        </div>
    </div>

    <!-- Form Container - Centered -->
    <div class="form-container" style="max-width: 800px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3>📋 Breed Information</h3>
            </div>

            <div class="card-body" style="padding: 2rem;">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <span class="alert-message"><?php echo $errors['general']; ?></span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="addBreedForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="type_id" class="form-label required">Cattle Type</label>
                            <select name="type_id" id="type_id" class="form-control <?php echo isset($errors['type_id']) ? 'error' : ''; ?>" required>
                                <option value="">-- Select Cattle Type --</option>
                                <?php
                                $types = $conn->query("SELECT * FROM cattle_type ORDER BY type_name ASC");
                                while ($type = $types->fetch_assoc()):
                                    $selected = ($preselected_type == $type['type_id'] || (isset($_POST['type_id']) && $_POST['type_id'] == $type['type_id'])) ? 'selected' : '';
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
                                class="form-control <?php echo isset($errors['breed_name']) ? 'error' : ''; ?>"
                                placeholder="e.g., Holstein, Jersey, Murrah"
                                value="<?php echo htmlspecialchars($_POST['breed_name'] ?? ''); ?>"
                                required
                            >
                            <?php if (isset($errors['breed_name'])): ?>
                                <span class="error-msg"><?php echo $errors['breed_name']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 2rem; justify-content: flex-end;">
                        <a href="breeds-list.php<?php echo $preselected_type > 0 ? "?type_id={$preselected_type}" : ''; ?>" class="btn btn-secondary">
                            ❌ Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            💾 Add Breed
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Common Breeds Reference -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3>📚 Common Cattle Breeds Reference</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="info-box">
                    <div style="margin-bottom: 1.5rem;">
                        <strong style="color: var(--accent-blue); font-size: 1.1rem;">🐄 Cow Breeds:</strong>
                        <ul style="list-style: disc; padding-left: 2rem; margin-top: 0.5rem; color: var(--text-dark);">
                            <li><strong>Holstein</strong> - High milk production (25-30L/day)</li>
                            <li><strong>Jersey</strong> - Rich, creamy milk with high butterfat</li>
                            <li><strong>Sahiwal</strong> - Heat tolerant, indigenous breed</li>
                            <li><strong>Gir</strong> - Good milk production, disease resistant</li>
                        </ul>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <strong style="color: var(--accent-blue); font-size: 1.1rem;">🐃 Buffalo Breeds:</strong>
                        <ul style="list-style: disc; padding-left: 2rem; margin-top: 0.5rem; color: var(--text-dark);">
                            <li><strong>Murrah</strong> - Best milk producer (10-15L/day)</li>
                            <li><strong>Jaffarabadi</strong> - Large size, good milk yield</li>
                            <li><strong>Mehsana</strong> - Dual purpose breed</li>
                            <li><strong>Surti</strong> - Compact size, good for small farms</li>
                        </ul>
                    </div>
                    <div>
                        <strong style="color: var(--accent-blue); font-size: 1.1rem;">🐐 Goat Breeds:</strong>
                        <ul style="list-style: disc; padding-left: 2rem; margin-top: 0.5rem; color: var(--text-dark);">
                            <li><strong>Saanen</strong> - High milk yield (3-4L/day)</li>
                            <li><strong>Boer</strong> - Excellent for meat production</li>
                            <li><strong>Jamunapari</strong> - Dual purpose (milk + meat)</li>
                            <li><strong>Barbari</strong> - Small size, good milk production</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Client-side validation
document.getElementById('addBreedForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    const typeId = document.getElementById('type_id');
    const breedName = document.getElementById('breed_name');
    
    // Remove previous error states (but keep server-side errors)
    document.querySelectorAll('.error-msg:not([data-server])').forEach(msg => msg.remove());
    document.querySelectorAll('.form-control').forEach(input => {
        if (!input.classList.contains('error')) {
            input.style.borderColor = '';
        }
    });
    
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