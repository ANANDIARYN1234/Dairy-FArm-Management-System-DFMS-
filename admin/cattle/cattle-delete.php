<?php
/**
 * =========================================================
 * Delete Cattle
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/database-helpers.php';

require_admin();

$cattle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get cattle
$cattle = get_cattle_by_id($cattle_id);

if (!$cattle) {
    set_flash_message('Cattle not found', 'error');
    redirect('cattle-list.php');
}

// Check if cattle has milk records
$milk_count = $conn->query("SELECT COUNT(*) as count FROM milk_collection WHERE cattle_id = {$cattle_id}")->fetch_assoc()['count'];

// Check if cattle has offspring
$offspring_count = $conn->query("SELECT COUNT(*) as count FROM cattle WHERE parent_id = {$cattle_id}")->fetch_assoc()['count'];

// If confirmed, delete
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    if ($milk_count > 0) {
        set_flash_message("Cannot delete '{$cattle['tag_id']}' - it has {$milk_count} milk collection records", 'error');
        redirect('cattle-view.php?id=' . $cattle_id);
    }
    
    if ($offspring_count > 0) {
        set_flash_message("Cannot delete '{$cattle['tag_id']}' - it has {$offspring_count} registered offspring", 'error');
        redirect('cattle-view.php?id=' . $cattle_id);
    }
    
    $stmt = $conn->prepare("DELETE FROM cattle WHERE cattle_id = ?");
    $stmt->bind_param("i", $cattle_id);
    
    if ($stmt->execute()) {
        set_flash_message("Cattle '{$cattle['tag_id']}' deleted successfully!", 'success');
    } else {
        set_flash_message('Failed to delete cattle', 'error');
    }
    
    redirect('cattle-list.php');
}

$page_title = 'Delete Cattle';
include '../../includes/header.php';
?>

<div class="dashboard-header">
    <h1>Delete Cattle</h1>
    <p>Confirm deletion</p>
</div>

<div class="card" style="max-width: 700px;">
    <div class="card-header" style="background: #fff5f5;">
        <h3 class="card-title" style="color: var(--danger);">⚠️ Confirm Deletion</h3>
    </div>

    <div style="padding: 2rem;">
        <p style="font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--text-dark);">
            Are you sure you want to delete this cattle record?
        </p>
        
        <div style="background: var(--bg-tertiary); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <h3 style="color: var(--accent-blue); margin-bottom: 1rem;">
                🏷️ <?php echo htmlspecialchars($cattle['tag_id']); ?>
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; color: var(--text-medium);">
                <div>
                    <p style="margin-bottom: 0.5rem;"><strong>Type:</strong> <?php echo htmlspecialchars($cattle['type_name']); ?></p>
                    <p style="margin-bottom: 0.5rem;"><strong>Breed:</strong> <?php echo htmlspecialchars($cattle['breed_name']); ?></p>
                    <p style="margin-bottom: 0.5rem;"><strong>Gender:</strong> <?php echo $cattle['gender']; ?></p>
                </div>
                <div>
                    <p style="margin-bottom: 0.5rem;"><strong>Age:</strong> <?php echo calculate_age($cattle['dob']); ?> years</p>
                    <p style="margin-bottom: 0.5rem;"><strong>Status:</strong> <?php echo get_status_badge($cattle['life_status']); ?></p>
                    <p style="margin-bottom: 0.5rem;"><strong>Added:</strong> <?php echo display_date($cattle['created_at']); ?></p>
                </div>
            </div>
        </div>

        <!-- Dependency Checks -->
        <div style="background: #fff9e6; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid var(--warning);">
            <h4 style="color: var(--text-dark); margin-bottom: 0.75rem;">📊 Related Records:</h4>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                    <strong>Milk Collections:</strong> 
                    <span style="color: <?php echo $milk_count > 0 ? 'var(--danger)' : 'var(--success)'; ?>; font-weight: bold;">
                        <?php echo $milk_count; ?> record(s)
                    </span>
                </li>
                <li style="padding: 0.5rem 0;">
                    <strong>Offspring:</strong> 
                    <span style="color: <?php echo $offspring_count > 0 ? 'var(--danger)' : 'var(--success)'; ?>; font-weight: bold;">
                        <?php echo $offspring_count; ?> calf(s)
                    </span>
                </li>
            </ul>
        </div>

        <?php if ($milk_count > 0 || $offspring_count > 0): ?>
            <div class="alert alert-error">
                <span class="alert-icon">✕</span>
                <div>
                    <strong>Cannot Delete!</strong><br>
                    <small>
                        <?php if ($milk_count > 0): ?>
                            This cattle has <?php echo $milk_count; ?> milk collection record(s).<br>
                        <?php endif; ?>
                        <?php if ($offspring_count > 0): ?>
                            This cattle has <?php echo $offspring_count; ?> registered offspring.<br>
                        <?php endif; ?>
                        Please remove these dependencies first.
                    </small>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <a href="cattle-view.php?id=<?php echo $cattle_id; ?>" class="btn btn-primary">
                    View Details
                </a>
                <a href="cattle-list.php" class="btn btn-secondary">
                    Back to List
                </a>
            </div>
            
        <?php else: ?>
            <div class="alert alert-warning">
                <span class="alert-icon">⚠</span>
                <span><strong>Warning:</strong> This action cannot be undone!</span>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <a href="cattle-delete.php?id=<?php echo $cattle_id; ?>&confirm=yes" 
                   class="btn btn-danger"
                   onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                    Yes, Delete Cattle
                </a>
                <a href="cattle-view.php?id=<?php echo $cattle_id; ?>" class="btn btn-primary">
                    No, Cancel
                </a>
                <a href="cattle-list.php" class="btn btn-secondary">
                    Back to List
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($milk_count > 0 || $offspring_count > 0): ?>
    <div class="card" style="max-width: 700px;">
        <div class="card-header">
            <h3 class="card-title">💡 What You Can Do</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p style="color: var(--text-medium); margin-bottom: 1rem;">
                To delete this cattle, you have the following options:
            </p>
            <ul style="color: var(--text-medium); padding-left: 1.5rem;">
                <?php if ($milk_count > 0): ?>
                    <li style="margin-bottom: 0.5rem;">Delete all <?php echo $milk_count; ?> milk collection record(s) first</li>
                <?php endif; ?>
                <?php if ($offspring_count > 0): ?>
                    <li style="margin-bottom: 0.5rem;">Update the <?php echo $offspring_count; ?> offspring record(s) to remove parent reference</li>
                <?php endif; ?>
                <li style="margin-bottom: 0.5rem;">Change the cattle status to "Sold" or "Dead" instead of deleting</li>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>