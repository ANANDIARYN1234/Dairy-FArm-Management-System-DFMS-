<?php
/**
 * =========================================================
 * Edit Cattle - Mapped to your specific DB Columns
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';
require_once '../../includes/database-helpers.php';

require_admin();

$cattle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Fetch cattle data
$stmt = $conn->prepare("SELECT * FROM cattle WHERE cattle_id = ?");
$stmt->bind_param("i", $cattle_id);
$stmt->execute();
$cattle = $stmt->get_result()->fetch_assoc();

if (!$cattle) {
    set_flash_message('Cattle not found', 'error');
    redirect('cattle-list.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Sanitize Inputs
    $tag_id      = clean($_POST['tag_id'] ?? '');
    $gender      = clean($_POST['gender'] ?? '');
    $dob         = clean($_POST['dob'] ?? '');
    $type_id     = (int)($_POST['type_id'] ?? 0);
    $breed_id    = (int)($_POST['breed_id'] ?? 0);
    $life_status = clean($_POST['life_status'] ?? 'Alive'); 
    $is_pregnant = isset($_POST['is_pregnant']) ? 1 : 0;
    $parent_id   = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $notes       = clean($_POST['notes'] ?? '');

    // Logic: Only Alive Females can be pregnant
    if ($gender !== 'Female' || $life_status !== 'Alive') {
        $is_pregnant = 0;
    }

    // 3. Update Database using your exact column names
    $update_sql = "UPDATE cattle 
                   SET tag_id=?, gender=?, dob=?, breed_id=?, type_id=?, 
                       life_status=?, is_pregnant=?, parent_id=?, notes=? 
                   WHERE cattle_id=?";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssiisissi", 
        $tag_id, $gender, $dob, $breed_id, $type_id, 
        $life_status, $is_pregnant, $parent_id, $notes, $cattle_id
    );

    if ($stmt->execute()) {
        set_flash_message("Cattle updated successfully!", 'success');
        header("Location: cattle-view.php?id=" . $cattle_id);
        exit;
    } else {
        $errors['general'] = "Database Error: " . $conn->error;
    }
}

$page_title = 'Edit Cattle';
include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>✏️ Edit Cattle: <?php echo htmlspecialchars($cattle['tag_id']); ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="cattle-list.php">Cattle</a>
                <span>/</span>
                <span>Edit</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="cattle-view.php?id=<?php echo $cattle_id; ?>" class="btn btn-info btn-sm">👁️ View Details</a>
            <a href="cattle-list.php" class="btn btn-secondary btn-sm">📋 Back to List</a>
        </div>
    </div>

    <div class="form-container" style="max-width: 900px; margin: 20px auto;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Cattle Information</h3>
            </div>
            
            <div class="card-body" style="padding:20px;">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="editForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        
                        <div class="form-group">
                            <label>Tag ID <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="tag_id" class="form-control" value="<?php echo htmlspecialchars($cattle['tag_id']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Gender <span style="color: var(--danger);">*</span></label>
                            <select name="gender" id="gender" class="form-control" onchange="checkPregnancyLogic()" required>
                                <option value="Male" <?php echo ($cattle['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($cattle['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Life Status <span style="color: var(--danger);">*</span></label>
                            <select name="life_status" id="life_status" class="form-control" onchange="checkPregnancyLogic()" required>
                                <option value="Alive" <?php echo ($cattle['life_status'] == 'Alive') ? 'selected' : ''; ?>>Alive</option>
                                <option value="Sold" <?php echo ($cattle['life_status'] == 'Sold') ? 'selected' : ''; ?>>Sold</option>
                                <option value="Dead" <?php echo ($cattle['life_status'] == 'Dead') ? 'selected' : ''; ?>>Dead</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?php echo $cattle['dob']; ?>">
                        </div>

                        <div class="form-group">
                            <label>Cattle Type <span style="color: var(--danger);">*</span></label>
                            <select name="type_id" id="type_id" class="form-control" onchange="fetchBreeds(this.value)" required>
                                <option value="">-- Select Type --</option>
                                <?php
                                $types = $conn->query("SELECT * FROM cattle_type ORDER BY type_name");
                                while($t = $types->fetch_assoc()):
                                    $sel = ($t['type_id'] == $cattle['type_id']) ? 'selected' : '';
                                    echo "<option value='{$t['type_id']}' $sel>{$t['type_name']}</option>";
                                endwhile;
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Breed <span style="color: var(--danger);">*</span></label>
                            <select name="breed_id" id="breed_id" class="form-control" required>
                                <option value="">Loading...</option>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column: 1/-1;">
                            <label>Parent Cattle (Optional)</label>
                            <select name="parent_id" class="form-control">
                                <option value="">-- No Parent --</option>
                                <?php
                                $parents = $conn->query("SELECT cattle_id, tag_id FROM cattle WHERE cattle_id != {$cattle_id} ORDER BY tag_id");
                                while($p = $parents->fetch_assoc()):
                                    $sel = ($p['cattle_id'] == $cattle['parent_id']) ? 'selected' : '';
                                    echo "<option value='{$p['cattle_id']}' $sel>{$p['tag_id']}</option>";
                                endwhile;
                                ?>
                            </select>
                        </div>

                        <div id="pregnancy-container" style="grid-column: 1/-1; display:none; background:#fff5f7; padding:15px; border:1px solid #ffd1dc; border-radius:8px;">
                            <label style="color:#d63384; font-weight:bold; cursor:pointer; display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="is_pregnant" id="is_pregnant" value="1" <?php echo ($cattle['is_pregnant'] == 1) ? 'checked' : ''; ?> style="width:20px; height:20px;">
                                🤰 Mark as Pregnant
                            </label>
                            <small style="color: var(--text-medium); display: block; margin-top: 8px;">
                                This option is only available for alive female cattle.
                            </small>
                        </div>

                        <div class="form-group" style="grid-column: 1/-1;">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="Any additional information about this cattle..."><?php echo htmlspecialchars($cattle['notes']); ?></textarea>
                        </div>
                    </div>

                    <div style="margin-top:20px; display:flex; gap:10px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                        <a href="cattle-view.php?id=<?php echo $cattle_id; ?>" class="btn btn-secondary">✖ Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
/**
 * Handles showing/hiding pregnancy checkbox
 */
function checkPregnancyLogic() {
    const gender = document.getElementById('gender').value;
    const lifeStatus = document.getElementById('life_status').value;
    const container = document.getElementById('pregnancy-container');
    const checkbox = document.getElementById('is_pregnant');

    if (gender === 'Female' && lifeStatus === 'Alive') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
        checkbox.checked = false;
    }
}

/**
 * Handles the Breed Dropdown via AJAX
 */
function fetchBreeds(typeId) {
    const breedSelect = document.getElementById('breed_id');
    const currentBreed = "<?php echo $cattle['breed_id']; ?>";
    
    if (!typeId) {
        breedSelect.innerHTML = '<option value="">-- Select Type First --</option>';
        return;
    }

    breedSelect.innerHTML = '<option value="">Loading...</option>';

    // Relative path to your API
    fetch('../../api/get-breeds.php?type_id=' + typeId)
        .then(response => response.json())
        .then(data => {
            breedSelect.innerHTML = '<option value="">-- Select Breed --</option>';
            if(data.success && data.breeds.length > 0) {
                data.breeds.forEach(b => {
                    const selected = (b.breed_id == currentBreed) ? 'selected' : '';
                    breedSelect.innerHTML += `<option value="${b.breed_id}" ${selected}>${b.breed_name}</option>`;
                });
            } else {
                breedSelect.innerHTML = '<option value="">No breeds available</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching breeds:', err);
            breedSelect.innerHTML = '<option value="">Error Loading Breeds</option>';
        });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    checkPregnancyLogic();
    const typeId = document.getElementById('type_id').value;
    if (typeId) {
        fetchBreeds(typeId);
    }
});
</script>