<?php
/**
 * =========================================================
 * Delete Cattle Type
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_admin();

$type_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($type_id <= 0) {
    set_flash_message('Invalid cattle type', 'error');
    redirect('types-list.php');
}

// Get cattle type
$stmt = $conn->prepare("SELECT * FROM cattle_type WHERE type_id = ?");
$stmt->bind_param("i", $type_id);
$stmt->execute();
$type = $stmt->get_result()->fetch_assoc();

if (!$type) {
    set_flash_message('Cattle type not found', 'error');
    redirect('types-list.php');
}

// Check if type has breeds
$breed_count = $conn->query("SELECT COUNT(*) as count FROM breed WHERE type_id = {$type_id}")->fetch_assoc()['count'];
$has_breeds = $breed_count > 0;

// Get list of breeds if any
$breeds_list = [];
if ($has_breeds) {
    $breeds_result = $conn->query("SELECT breed_name FROM breed WHERE type_id = {$type_id} ORDER BY breed_name ASC");
    while ($breed = $breeds_result->fetch_assoc()) {
        $breeds_list[] = $breed['breed_name'];
    }
}

// If confirmed, try to delete
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Double-check breeds before deletion
    if ($has_breeds) {
        set_flash_message("Cannot delete '{$type['type_name']}' - it has {$breed_count} breed(s) associated with it. Please delete all breeds first.", 'error');
        redirect('types-list.php');
    }
    
    $stmt = $conn->prepare("DELETE FROM cattle_type WHERE type_id = ?");
    $stmt->bind_param("i", $type_id);
    
    if ($stmt->execute()) {
        set_flash_message("Cattle type '{$type['type_name']}' deleted successfully!", 'success');
    } else {
        set_flash_message('Failed to delete cattle type', 'error');
    }
    
    redirect('types-list.php');
}

$page_title = 'Delete Cattle Type';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>🗑️ Delete Cattle Type</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="types-list.php">Cattle Management</a>
                <span>/</span>
                <span>Delete Type</span>
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
            <div class="card-header" style="background: <?php echo $has_breeds ? '#fff5f5' : '#fff5f5'; ?>;">
                <h3 style="color: var(--danger);">
                    <?php echo $has_breeds ? '⛔ Cannot Delete' : '⚠️ Confirm Deletion'; ?>
                </h3>
            </div>

            <div class="card-body" style="padding: 2rem;">
                <?php if ($has_breeds): ?>
                    <!-- Error: Has Associated Breeds -->
                    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                        <span class="alert-icon">✕</span>
                        <span><strong>Cannot Delete:</strong> This cattle type has associated breeds that must be removed first.</span>
                    </div>

                    <p style="font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--text-dark);">
                        You cannot delete this cattle type because it has <strong><?php echo $breed_count; ?></strong> breed(s) associated with it.
                    </p>
                    
                    <div style="background: var(--bg-tertiary); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="color: var(--accent-blue); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($type['type_name']); ?>
                        </h3>
                        <p style="color: var(--text-medium); margin: 0.5rem 0;">
                            <strong>Type ID:</strong> <?php echo $type_id; ?><br>
                            <strong>Associated Breeds:</strong> <?php echo $breed_count; ?>
                        </p>
                        
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                            <p style="font-weight: 600; color: var(--text-dark); margin-bottom: 0.5rem;">Breeds List:</p>
                            <ul style="list-style: disc; padding-left: 2rem; margin: 0; color: var(--text-medium);">
                                <?php foreach ($breeds_list as $breed_name): ?>
                                    <li><?php echo htmlspecialchars($breed_name); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <span class="alert-icon">ℹ️</span>
                        <span><strong>Solution:</strong> To delete this type, you must first delete or reassign all associated breeds.</span>
                    </div>

                    <div class="form-actions" style="margin-top: 2rem; justify-content: flex-end;">
                        <a href="types-list.php" class="btn btn-secondary">
                            ← Back to Types
                        </a>
                        <a href="breeds-list.php?type_id=<?php echo $type_id; ?>" class="btn btn-primary">
                            📝 View Breeds
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Confirm Deletion: No Associated Breeds -->
                    <p style="font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--text-dark);">
                        Are you sure you want to delete this cattle type?
                    </p>
                    
                    <div style="background: var(--bg-tertiary); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="color: var(--accent-blue); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($type['type_name']); ?>
                        </h3>
                        <p style="color: var(--text-medium); margin: 0;">
                            <strong>Type ID:</strong> <?php echo $type_id; ?><br>
                            <strong>Associated Breeds:</strong> None
                        </p>
                    </div>

                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <span><strong>Warning:</strong> This action cannot be undone!</span>
                    </div>

                    <div class="form-actions" style="margin-top: 2rem; justify-content: flex-end;">
                        <a href="types-list.php" class="btn btn-secondary">
                            ❌ No, Cancel
                        </a>
                        <a href="type-delete.php?id=<?php echo $type_id; ?>&confirm=yes" 
                           class="btn btn-danger"
                           onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                            🗑️ Yes, Delete Type
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>