<?php
/**
 * =========================================================
 * Add New Cattle
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and collect form data
    $tag_id      = clean($_POST['tag_id'] ?? '');
    $gender      = clean($_POST['gender'] ?? '');
    $dob         = clean($_POST['dob'] ?? '');
    $type_id     = (int)($_POST['type_id'] ?? 0);
    $breed_id    = (int)($_POST['breed_id'] ?? 0);
    $life_status = clean($_POST['life_status'] ?? 'Alive');
    $is_pregnant = isset($_POST['is_pregnant']) ? 1 : 0;
    $parent_id   = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $notes       = clean($_POST['notes'] ?? '');
    $user_id     = get_user_id();
    
    // Logic: Only Alive Females can be pregnant
    if ($gender !== 'Female' || $life_status !== 'Alive') {
        $is_pregnant = 0;
    }
    
    // Validation
    $validator = new Validator($_POST);
    $validator->required('tag_id', 'Tag ID is required')
              ->min('tag_id', 2, 'Tag ID must be at least 2 characters')
              ->required('gender', 'Gender is required')
              ->required('dob', 'Date of birth is required')
              ->date('dob', 'Y-m-d', 'Invalid date format')
              ->before_today('dob', 'Date of birth cannot be in the future')
              ->required('type_id', 'Cattle type is required')
              ->required('breed_id', 'Breed is required')
              ->required('life_status', 'Life status is required');
    
    // Check if tag_id already exists
    $check = $conn->prepare("SELECT cattle_id FROM cattle WHERE tag_id = ?");
    $check->bind_param("s", $tag_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $validator->errors()['tag_id'] = 'This Tag ID already exists';
    }
    
    // Process form if validation passes
    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        // Prepare INSERT statement
        $stmt = $conn->prepare("
            INSERT INTO cattle (tag_id, gender, dob, breed_id, type_id, life_status, is_pregnant, parent_id, notes, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sssiisiisi",
            $tag_id, 
            $gender, 
            $dob, 
            $breed_id, 
            $type_id, 
            $life_status, 
            $is_pregnant, 
            $parent_id, 
            $notes, 
            $user_id
        );
        
        // Execute with error handling
        try {
            if ($stmt->execute()) {
                set_flash_message("Cattle '{$tag_id}' added successfully!", 'success');
                redirect('cattle-list.php');
            } else {
                $errors['general'] = 'Failed to add cattle. Please try again.';
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) { // Duplicate Entry error
                $errors['tag_id'] = "The Tag ID '{$tag_id}' is already registered. Please use a unique ID.";
            } else {
                $errors['general'] = "Database error: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Add New Cattle';
include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>➕ Add New Cattle</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="cattle-list.php">Cattle Management</a>
                <span>/</span>
                <span>Add Cattle</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="cattle-list.php" class="btn btn-secondary btn-sm">← Back to List</a>
        </div>
    </div>
   
    <div class="form-container" style="max-width: 800px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Cattle Information</h3>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error" style="margin: 1rem 1.5rem 0;">
                    <span class="alert-icon">✕</span>
                    <span><?php echo $errors['general']; ?></span>
                </div>
            <?php endif; ?>

            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="addCattleForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        
                        <!-- Tag ID -->
                        <div class="form-group">
                            <label for="tag_id">Tag ID / Identification <span style="color: red;">*</span></label>
                            <input 
                                type="text" 
                                name="tag_id" 
                                id="tag_id"
                                class="form-control"
                                placeholder="e.g., C001, BUF-123"
                                value="<?php echo htmlspecialchars($_POST['tag_id'] ?? ''); ?>"
                                required
                                autofocus
                            >
                            <?php if (isset($errors['tag_id'])): ?>
                                <span class="error-msg" style="color: var(--danger); font-size: 0.875rem; display: block; margin-top: 0.25rem;"><?php echo $errors['tag_id']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Gender -->
                        <div class="form-group">
                            <label for="gender">Gender <span style="color: red;">*</span></label>
                            <select name="gender" id="gender" class="form-control" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>♂ Male</option>
                                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>♀ Female</option>
                            </select>
                            <?php if (isset($errors['gender'])): ?>
                                <span class="error-msg" style="color: var(--danger); font-size: 0.875rem; display: block; margin-top: 0.25rem;"><?php echo $errors['gender']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Date of Birth -->
                        <div class="form-group">
                            <label for="dob">Date of Birth <span style="color: red;">*</span></label>
                            <input 
                                type="date" 
                                name="dob" 
                                id="dob"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>"
                                max="<?php echo date('Y-m-d'); ?>"
                                required
                            >
                            <?php if (isset($errors['dob'])): ?>
                                <span class="error-msg" style="color: var(--danger); font-size: 0.875rem; display: block; margin-top: 0.25rem;"><?php echo $errors['dob']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Life Status -->
                        <div class="form-group">
                            <label for="life_status">Life Status <span style="color: red;">*</span></label>
                            <select name="life_status" id="life_status" class="form-control" required>
                                <option value="Alive" <?php echo (!isset($_POST['life_status']) || $_POST['life_status'] === 'Alive') ? 'selected' : ''; ?>>Alive</option>
                                <option value="Sold" <?php echo (isset($_POST['life_status']) && $_POST['life_status'] === 'Sold') ? 'selected' : ''; ?>>Sold</option>
                                <option value="Dead" <?php echo (isset($_POST['life_status']) && $_POST['life_status'] === 'Dead') ? 'selected' : ''; ?>>Dead</option>
                            </select>
                            <?php if (isset($errors['life_status'])): ?>
                                <span class="error-msg" style="color: var(--danger); font-size: 0.875rem; display: block; margin-top: 0.25rem;"><?php echo $errors['life_status']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Cattle Type -->
                        <div class="form-group">
                            <label for="type_id">Cattle Type <span style="color: red;">*</span></label>
                            <select name="type_id" id="type_id" class="form-control" required>
                                <option value="">-- Select Type --</option>
                                <?php
                                $types = $conn->query("SELECT * FROM cattle_type ORDER BY type_name");
                                while ($type = $types->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $type['type_id']; ?>" <?php echo (isset($_POST['type_id']) && $_POST['type_id'] == $type['type_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <?php if (isset($errors['type_id'])): ?>
                                <span class="error-msg" style="color: var(--danger); font-size: 0.875rem; display: block; margin-top: 0.25rem;"><?php echo $errors['type_id']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Breed -->
                        <div class="form-group">
                            <label for="breed_id">Breed <span style="color: red;">*</span></label>
                            <select name="breed_id" id="breed_id" class="form-control" required>
                                <option value="">-- Select Type First --</option>
                            </select>
                            <?php if (isset($errors['breed_id'])): ?>
                                <span class="error-msg" style="color: var(--danger); font-size: 0.875rem; display: block; margin-top: 0.25rem;"><?php echo $errors['breed_id']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Parent (Optional) -->
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="parent_id">Parent (Optional)</label>
                            <select name="parent_id" id="parent_id" class="form-control">
                                <option value="">-- No Parent --</option>
                                <?php
                                $parents = $conn->query("SELECT cattle_id, tag_id FROM cattle WHERE life_status IN ('Alive') ORDER BY tag_id");
                                while ($parent = $parents->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $parent['cattle_id']; ?>" <?php echo (isset($_POST['parent_id']) && $_POST['parent_id'] == $parent['cattle_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($parent['tag_id']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Pregnancy Status -->
                        <div id="pregnancy-container" style="grid-column: 1/-1; display:none; background:#fff5f7; padding:15px; border:1px solid #ffd1dc; border-radius:8px;">
                            <label style="color:#d63384; font-weight:bold; cursor:pointer; display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="is_pregnant" id="is_pregnant" value="1" <?php echo (isset($_POST['is_pregnant']) && $_POST['is_pregnant']) ? 'checked' : ''; ?> style="width:20px; height:20px;">
                                🤰 Mark as Pregnant
                            </label>
                            <small style="color: var(--text-medium); display: block; margin-top: 8px;">
                                This option is only available for alive female cattle.
                            </small>
                        </div>

                        <!-- Notes -->
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="notes">Notes / Remarks</label>
                            <textarea 
                                name="notes" 
                                id="notes"
                                class="form-control"
                                rows="4" 
                                placeholder="Any additional information about this cattle..."
                            ><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                        <button type="submit" class="btn btn-primary">
                            💾 Add Cattle
                        </button>
                        <a href="cattle-list.php" class="btn btn-secondary">
                            ✖ Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Load breeds based on type selection
function loadBreeds(typeId, selectedBreedId = null) {
    const breedSelect = document.getElementById('breed_id');
    
    if (!typeId) {
        breedSelect.innerHTML = '<option value="">-- Select Type First --</option>';
        return;
    }
    
    breedSelect.innerHTML = '<option value="">Loading...</option>';
    breedSelect.disabled = true;
    
    const url = `../../api/get-breeds.php?type_id=${typeId}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            breedSelect.disabled = false;
            if (data.success && data.breeds && data.breeds.length > 0) {
                breedSelect.innerHTML = '<option value="">-- Select Breed --</option>';
                data.breeds.forEach(breed => {
                    const selected = (selectedBreedId && breed.breed_id == selectedBreedId) ? 'selected' : '';
                    breedSelect.innerHTML += `<option value="${breed.breed_id}" ${selected}>${breed.breed_name}</option>`;
                });
            } else {
                breedSelect.innerHTML = '<option value="">No breeds available for this type</option>';
            }
        })
        .catch(error => {
            console.error('Error loading breeds:', error);
            breedSelect.disabled = false;
            breedSelect.innerHTML = '<option value="">Error loading breeds. Please try again.</option>';
        });
}

// Toggle pregnancy section based on gender and life status
function togglePregnancySection() {
    const gender = document.getElementById('gender').value;
    const lifeStatus = document.getElementById('life_status').value;
    const pregnancyContainer = document.getElementById('pregnancy-container');
    const pregnancyCheckbox = document.getElementById('is_pregnant');
    
    if (gender === 'Female' && lifeStatus === 'Alive') {
        pregnancyContainer.style.display = 'block';
    } else {
        pregnancyContainer.style.display = 'none';
        if (pregnancyCheckbox) pregnancyCheckbox.checked = false;
    }
}

// Wait for DOM to load
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type_id');
    const genderSelect = document.getElementById('gender');
    const lifeStatusSelect = document.getElementById('life_status');
    const selectedBreedId = '<?php echo $_POST['breed_id'] ?? ''; ?>';
    
    // Load breeds if type is pre-selected (after form submission with errors)
    if (typeSelect && typeSelect.value) {
        loadBreeds(typeSelect.value, selectedBreedId);
    }
    
    // Add event listener for type change
    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            loadBreeds(this.value);
        });
    }
    
    // Show/hide pregnancy section on load
    togglePregnancySection();
    
    // Add event listeners to gender and life status
    if (genderSelect) {
        genderSelect.addEventListener('change', togglePregnancySection);
    }
    if (lifeStatusSelect) {
        lifeStatusSelect.addEventListener('change', togglePregnancySection);
    }
    
    // Form validation
    const form = document.getElementById('addCattleForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            let firstError = null;
            
            // Validate Tag ID
            const tagId = document.getElementById('tag_id');
            if (tagId && !tagId.value.trim()) {
                isValid = false;
                if (!firstError) firstError = tagId;
            }
            
            // Validate Gender
            const gender = document.getElementById('gender');
            if (gender && !gender.value) {
                isValid = false;
                if (!firstError) firstError = gender;
            }
            
            // Validate Date of Birth
            const dob = document.getElementById('dob');
            if (dob && !dob.value) {
                isValid = false;
                if (!firstError) firstError = dob;
            }
            
            // Validate Cattle Type
            const typeId = document.getElementById('type_id');
            if (typeId && !typeId.value) {
                isValid = false;
                if (!firstError) firstError = typeId;
            }
            
            // Validate Breed
            const breedId = document.getElementById('breed_id');
            if (breedId && !breedId.value) {
                isValid = false;
                if (!firstError) firstError = breedId;
            }
            
            if (!isValid) {
                e.preventDefault();
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                alert('Please fill in all required fields.');
            }
        });
    }
});
</script>