<?php
// =========================================================
// FILE 4: milk-view.php
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
$stmt = $conn->prepare("SELECT mc.*, c.tag_id, c.cattle_id, ct.type_name, b.breed_name, u.full_name as collected_by FROM milk_collection mc JOIN cattle c ON mc.cattle_id = c.cattle_id JOIN cattle_type ct ON c.type_id = ct.type_id JOIN breed b ON c.breed_id = b.breed_id JOIN user u ON mc.user_id = u.user_id WHERE mc.milk_id = ?");
$stmt->bind_param("i", $milk_id);
$stmt->execute();
$milk = $stmt->get_result()->fetch_assoc();

if (!$milk) {
    set_flash_message('Milk record not found', 'error');
    redirect('milk-list.php');
}

$page_title = 'Milk Collection Details';
include '../../includes/header.php';
?>
<div class="dashboard-header"><h1>Milk Collection Details</h1></div>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">🥛 Collection Record</h3>
        <div style="display: flex; gap: 0.5rem;">
            <a href="milk-edit.php?id=<?php echo $milk_id; ?>" class="btn btn-primary btn-sm">✏️ Edit</a>
            <a href="milk-delete.php?id=<?php echo $milk_id; ?>" class="btn btn-danger btn-sm">🗑️ Delete</a>
            <a href="milk-list.php" class="btn btn-secondary btn-sm">← Back</a>
        </div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding: 1.5rem;">
        <div>
            <h4 style="color: var(--accent-blue); margin-bottom: 1rem;">Collection Details</h4>
            <table style="width: 100%;">
                <tr><td style="padding: 0.75rem 0; color: var(--text-medium); width: 40%;"><strong>Date:</strong></td><td><?php echo display_date($milk['collection_date'], 'd M Y'); ?></td></tr>
                <tr style="background: var(--bg-tertiary);"><td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Shift:</strong></td><td style="padding: 0.75rem 0.5rem;"><span class="badge <?php echo $milk['shift'] === 'Morning' ? 'badge-info' : 'badge-warning'; ?>"><?php echo $milk['shift']; ?></span></td></tr>
                <tr><td style="padding: 0.75rem 0; color: var(--text-medium);"><strong>Quantity:</strong></td><td><strong style="color: var(--success); font-size: 1.3rem;"><?php echo format_quantity($milk['quantity']); ?> L</strong></td></tr>
                <tr style="background: var(--bg-tertiary);"><td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Collected By:</strong></td><td style="padding: 0.75rem 0.5rem;"><?php echo htmlspecialchars($milk['collected_by']); ?></td></tr>
            </table>
        </div>
        <div>
            <h4 style="color: var(--accent-blue); margin-bottom: 1rem;">Cattle Information</h4>
            <table style="width: 100%;">
                <tr><td style="padding: 0.75rem 0; color: var(--text-medium); width: 40%;"><strong>Tag ID:</strong></td><td><a href="../cattle/cattle-view.php?id=<?php echo $milk['cattle_id']; ?>" style="color: var(--accent-blue); font-weight: 600;"><?php echo htmlspecialchars($milk['tag_id']); ?></a></td></tr>
                <tr style="background: var(--bg-tertiary);"><td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Type:</strong></td><td style="padding: 0.75rem 0.5rem;"><?php echo htmlspecialchars($milk['type_name']); ?></td></tr>
                <tr><td style="padding: 0.75rem 0; color: var(--text-medium);"><strong>Breed:</strong></td><td><?php echo htmlspecialchars($milk['breed_name']); ?></td></tr>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>