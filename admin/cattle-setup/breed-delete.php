<?php
/**
 * =========================================================
 * Delete Breed
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_admin();

$breed_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($breed_id <= 0) {
    set_flash_message('Invalid breed', 'error');
    redirect('breeds-list.php');
}

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

// Check if breed has cattle
$cattle_count = $conn->query("SELECT COUNT(*) as count FROM cattle WHERE breed_id = {$breed_id}")->fetch_assoc()['count'];
$has_cattle = $cattle_count > 0;

// Get list of cattle if any (limit to 10 for display)
$cattle_list = [];
if ($has_cattle) {
    $cattle_result = $conn->query("SELECT cattle_id, tag_id FROM cattle WHERE breed_id = {$breed_id} ORDER BY tag_id ASC LIMIT 10");
    while ($cattle = $cattle_result->fetch_assoc()) {
        $cattle_list[] = $cattle;
    }
}

// If confirmed, try to delete
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Double-check cattle before deletion
    if ($has_cattle) {
        set_flash_message("Cannot delete '{$breed['breed_name']}' - it has {$cattle_count} cattle associated with it. Please delete or reassign all cattle first.", 'error');
        redirect('breeds-list.php?type_id=' . $breed['type_id']);
    }
    
    $stmt = $conn->prepare("DELETE FROM breed WHERE breed_id = ?");
    $stmt->bind_param("i", $breed_id);
    
    if ($stmt->execute()) {
        set_flash_message("Breed '{$breed['breed_name']}' deleted successfully!", 'success');
    } else {
        set_flash_message('Failed to delete breed', 'error');
    }
    
    redirect('breeds-list.php?type_id=' . $breed['type_id']);
}

$page_title = 'Delete Breed';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>🗑️ Delete Breed</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="types-list.php">Cattle Management</a>
                <span>/</span>
                <a href="breeds-list.php?type_id=<?php echo $breed['type_id']; ?>">Breeds</a>
                <span>/</span>
                <span>Delete Breed</span>
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
            <div class="card-header" style="background: #fff5f5;">
                <h3 style="color: var(--danger);">
                    <?php echo $has_cattle ? '⛔ Cannot Delete' : '⚠️ Confirm Deletion'; ?>
                </h3>
            </div>

            <div class="card-body" style="padding: 2rem;">
                <?php if ($has_cattle): ?>
                    <!-- Error: Has Associated Cattle -->
                    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                        <span class="alert-icon">✕</span>
                        <span><strong>Cannot Delete:</strong> This breed has associated cattle that must be removed or reassigned first.</span>
                    </div>

                    <p style="font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--text-dark);">
                        You cannot delete this breed because it has <strong><?php echo $cattle_count; ?></strong> cattle associated with it.
                    </p>
                    
                    <div style="background: var(--bg-tertiary); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="color: var(--accent-blue); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($breed['breed_name']); ?>
                        </h3>
                        <p style="color: var(--text-medium); margin: 0.5rem 0;">
                            <strong>Type:</strong> <?php echo htmlspecialchars($breed['type_name']); ?><br>
                            <strong>Breed ID:</strong> <?php echo $breed_id; ?><br>
                            <strong>Associated Cattle:</strong> <?php echo $cattle_count; ?>
                        </p>
                        
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                            <p style="font-weight: 600; color: var(--text-dark); margin-bottom: 0.5rem;">
                                Sample Cattle (showing up to 10):
                            </p>
                            <ul style="list-style: disc; padding-left: 2rem; margin: 0; color: var(--text-medium);">
                                <?php foreach ($cattle_list as $cattle): ?>
                                    <li>
                                        <strong>Tag ID:</strong> <?php echo htmlspecialchars($cattle['tag_id']); ?>
                                    </li>
                                <?php endforeach; ?>
                                <?php if ($cattle_count > 10): ?>
                                    <li style="color: var(--text-light); font-style: italic;">
                                        ... and <?php echo ($cattle_count - 10); ?> more
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <span class="alert-icon">ℹ️</span>
                        <span><strong>Solution:</strong> To delete this breed, you must first delete or reassign all associated cattle to another breed.</span>
                    </div>

                    <div class="form-actions" style="margin-top: 2rem; justify-content: flex-end;">
                        <a href="breeds-list.php?type_id=<?php echo $breed['type_id']; ?>" class="btn btn-secondary">
                            ← Back to Breeds
                        </a>
                        <a href="../cattle/cattle-list.php?breed_id=<?php echo $breed_id; ?>" class="btn btn-primary">
                            🐮 View Cattle
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Confirm Deletion: No Associated Cattle -->
                    <p style="font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--text-dark);">
                        Are you sure you want to delete this breed?
                    </p>
                    
                    <div style="background: var(--bg-tertiary); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="color: var(--accent-blue); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($breed['breed_name']); ?>
                        </h3>
                        <p style="color: var(--text-medium); margin: 0;">
                            <strong>Type:</strong> <?php echo htmlspecialchars($breed['type_name']); ?><br>
                            <strong>Breed ID:</strong> <?php echo $breed_id; ?><br>
                            <strong>Associated Cattle:</strong> None
                        </p>
                    </div>

                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <span><strong>Warning:</strong> This action cannot be undone!</span>
                    </div>

                    <div class="form-actions" style="margin-top: 2rem; justify-content: flex-end;">
                        <a href="breeds-list.php?type_id=<?php echo $breed['type_id']; ?>" class="btn btn-secondary">
                            ❌ No, Cancel
                        </a>
                        <a href="breed-delete.php?id=<?php echo $breed_id; ?>&confirm=yes" 
                           class="btn btn-danger"
                           onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                            🗑️ Yes, Delete Breed
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>