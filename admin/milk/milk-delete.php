<?php
// =========================================================
// FILE 3: milk-delete.php
// =========================================================
?>
<?php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_admin();

$milk_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT mc.*, c.tag_id, ct.type_name FROM milk_collection mc JOIN cattle c ON mc.cattle_id = c.cattle_id JOIN cattle_type ct ON c.type_id = ct.type_id WHERE mc.milk_id = ?");
$stmt->bind_param("i", $milk_id);
$stmt->execute();
$milk = $stmt->get_result()->fetch_assoc();

if (!$milk) {
    set_flash_message('Milk record not found', 'error');
    redirect('milk-list.php');
}

$sold_count = $conn->query("SELECT COUNT(*) as count FROM sale_milk WHERE milk_id = {$milk_id}")->fetch_assoc()['count'];

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    if ($sold_count > 0) {
        set_flash_message("Cannot delete - this milk has been sold", 'error');
        redirect('milk-view.php?id=' . $milk_id);
    }
    $stmt = $conn->prepare("DELETE FROM milk_collection WHERE milk_id = ?");
    $stmt->bind_param("i", $milk_id);
    if ($stmt->execute()) {
        set_flash_message("Milk record deleted successfully!", 'success');
    } else {
        set_flash_message('Failed to delete', 'error');
    }
    redirect('milk-list.php');
}

$page_title = 'Delete Milk Record';
include '../../includes/header.php';
?>
<div class="dashboard-header"><h1>Delete Milk Record</h1></div>
<div class="card" style="max-width: 600px;">
    <div class="card-header" style="background: #fff5f5;"><h3 class="card-title" style="color: var(--danger);">⚠️ Confirm Deletion</h3></div>
    <div style="padding: 2rem;">
        <div style="background: var(--bg-tertiary); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <p><strong>Date:</strong> <?php echo display_date($milk['collection_date']); ?></p>
            <p><strong>Shift:</strong> <?php echo $milk['shift']; ?></p>
            <p><strong>Cattle:</strong> <?php echo $milk['tag_id']; ?> (<?php echo $milk['type_name']; ?>)</p>
            <p><strong>Quantity:</strong> <?php echo format_quantity($milk['quantity']); ?> L</p>
        </div>
        <?php if ($sold_count > 0): ?>
            <div class="alert alert-error"><span>Cannot delete - this milk has been sold!</span></div>
            <a href="milk-list.php" class="btn btn-primary">Back to List</a>
        <?php else: ?>
            <div class="alert alert-warning"><span>This action cannot be undone!</span></div>
            <div style="display: flex; gap: 1rem;">
                <a href="milk-delete.php?id=<?php echo $milk_id; ?>&confirm=yes" class="btn btn-danger" onclick="return confirm('Are you sure?')">Yes, Delete</a>
                <a href="milk-view.php?id=<?php echo $milk_id; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>