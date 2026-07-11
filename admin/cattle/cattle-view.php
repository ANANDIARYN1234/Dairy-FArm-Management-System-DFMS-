<?php
/**
 * =========================================================
 * View Cattle Details - Complete Profile
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

// Get milk production stats
$milk_stats = $conn->query("
    SELECT 
        COUNT(*) as total_collections,
        SUM(quantity) as total_milk,
        AVG(quantity) as avg_milk,
        MAX(collection_date) as last_collection
    FROM milk_collection 
    WHERE cattle_id = {$cattle_id}
")->fetch_assoc();

// Get recent milk collections
$recent_milk = get_milk_by_cattle($cattle_id, 10);

// Get offspring
$offspring = $conn->query("
    SELECT cattle_id, tag_id, gender, dob, life_status as status, is_pregnant
    FROM cattle 
    WHERE parent_id = {$cattle_id}
    ORDER BY dob DESC
")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Cattle Details - ' . $cattle['tag_id'];
include '../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>🐄 Cattle Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="cattle-list.php">Cattle</a>
                <span>/</span>
                <span><?php echo htmlspecialchars($cattle['tag_id']); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Info Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🐄 <?php echo htmlspecialchars($cattle['tag_id']); ?></h3>
            <div style="display: flex; gap: 0.5rem;">
                <a href="cattle-edit.php?id=<?php echo $cattle_id; ?>" class="btn btn-primary btn-sm">✏️ Edit</a>
                <a href="cattle-delete.php?id=<?php echo $cattle_id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this cattle?')">🗑️ Delete</a>
                <a href="cattle-list.php" class="btn btn-secondary btn-sm">← Back to List</a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding: 1.5rem;">
            <!-- Left Column: Basic Information -->
            <div>
                <h4 style="color: var(--accent-blue); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--accent-blue);">Basic Information</h4>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 0.75rem 0; color: var(--text-medium); width: 40%;"><strong>Tag ID:</strong></td>
                        <td style="padding: 0.75rem 0;"><strong style="color: var(--accent-blue); font-size: 1.1rem;"><?php echo htmlspecialchars($cattle['tag_id']); ?></strong></td>
                    </tr>
                    <tr style="background: var(--bg-tertiary);">
                        <td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Cattle Type:</strong></td>
                        <td style="padding: 0.75rem 0.5rem;"><span class="badge badge-info" style="padding: 0.5rem 1rem;"><?php echo htmlspecialchars($cattle['type_name']); ?></span></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem 0; color: var(--text-medium);"><strong>Breed:</strong></td>
                        <td style="padding: 0.75rem 0;"><?php echo htmlspecialchars($cattle['breed_name']); ?></td>
                    </tr>
                    <tr style="background: var(--bg-tertiary);">
                        <td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Gender:</strong></td>
                        <td style="padding: 0.75rem 0.5rem;">
                            <?php if ($cattle['gender'] === 'Male'): ?>
                                <span style="color: var(--info); font-weight: 600;">♂ Male</span>
                            <?php else: ?>
                                <span style="color: var(--danger); font-weight: 600;">♀ Female</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem 0; color: var(--text-medium);"><strong>Date of Birth:</strong></td>
                        <td style="padding: 0.75rem 0;"><?php echo display_date($cattle['dob'], 'd M Y'); ?></td>
                    </tr>
                    <tr style="background: var(--bg-tertiary);">
                        <td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Age:</strong></td>
                        <td style="padding: 0.75rem 0.5rem;"><strong><?php echo calculate_age($cattle['dob']); ?> years old</strong></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem 0; color: var(--text-medium);"><strong>Status:</strong></td>
                        <td style="padding: 0.75rem 0;">
                            <?php echo get_status_badge($cattle['life_status'], $cattle['is_pregnant']); ?>
                            <?php if ($cattle['is_pregnant'] == 1 && $cattle['life_status'] === 'Alive'): ?>
                                <!-- <div style="color: var(--warning); font-size: 0.85rem; margin-top: 0.5rem;">
                                    ℹ️ Expected delivery tracking coming soon
                                </div> -->
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Right Column: Additional Details -->
            <div>
                <h4 style="color: var(--accent-blue); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--accent-blue);">Additional Details</h4>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 0.75rem 0; color: var(--text-medium); width: 40%;"><strong>Parent:</strong></td>
                        <td style="padding: 0.75rem 0;">
                            <?php if ($cattle['parent_tag']): ?>
                                <a href="cattle-view.php?id=<?php echo $cattle['parent_id']; ?>" style="color: var(--accent-blue); text-decoration: underline;">
                                    <?php echo htmlspecialchars($cattle['parent_tag']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: var(--text-light);">No parent recorded</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr style="background: var(--bg-tertiary);">
                        <td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Offspring:</strong></td>
                        <td style="padding: 0.75rem 0.5rem;"><strong><?php echo count($offspring); ?></strong> calf(s)</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem 0; color: var(--text-medium);"><strong>Added By:</strong></td>
                        <td style="padding: 0.75rem 0;"><?php echo htmlspecialchars($cattle['added_by']); ?></td>
                    </tr>
                    <tr style="background: var(--bg-tertiary);">
                        <td style="padding: 0.75rem 0.5rem; color: var(--text-medium);"><strong>Added On:</strong></td>
                        <td style="padding: 0.75rem 0.5rem;"><?php echo display_datetime($cattle['created_at'], 'd M Y, h:i A'); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem 0; color: var(--text-medium); vertical-align: top;"><strong>Notes:</strong></td>
                        <td style="padding: 0.75rem 0;">
                            <?php if ($cattle['notes']): ?>
                                <div style="background: #fffbeb; padding: 0.75rem; border-radius: 6px; border-left: 3px solid var(--warning);">
                                    <?php echo nl2br(htmlspecialchars($cattle['notes'])); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-light);">No notes available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Milk Production Stats -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🥛 Milk Production Statistics</h3>
            <a href="../milk/milk-add.php?cattle_id=<?php echo $cattle_id; ?>" class="btn btn-success btn-sm">+ Add Milk Record</a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; padding: 1.5rem;">
            <div style="background: linear-gradient(135deg, #e3f2fd, #bbdefb); padding: 1.5rem; border-radius: 12px; text-align: center; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--accent-blue);"><?php echo $milk_stats['total_collections']; ?></div>
                <div style="color: var(--text-dark); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">Total Collections</div>
            </div>
            <div style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9); padding: 1.5rem; border-radius: 12px; text-align: center; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--success);"><?php echo format_quantity($milk_stats['total_milk'] ?? 0); ?> <span style="font-size: 1.5rem;">L</span></div>
                <div style="color: var(--text-dark); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">Total Milk Produced</div>
            </div>
            <div style="background: linear-gradient(135deg, #fff3e0, #ffe0b2); padding: 1.5rem; border-radius: 12px; text-align: center; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--warning);"><?php echo format_quantity($milk_stats['avg_milk'] ?? 0); ?> <span style="font-size: 1.5rem;">L</span></div>
                <div style="color: var(--text-dark); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">Average per Collection</div>
            </div>
            <div style="background: linear-gradient(135deg, #fce4ec, #f8bbd0); padding: 1.5rem; border-radius: 12px; text-align: center; box-shadow: var(--shadow-sm);">
                <div style="font-size: 1.3rem; font-weight: bold; color: var(--text-dark); margin-top: 0.5rem;"><?php echo $milk_stats['last_collection'] ? display_date($milk_stats['last_collection']) : 'Never'; ?></div>
                <div style="color: var(--text-dark); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">Last Collection</div>
            </div>
        </div>

        <?php if (!empty($recent_milk)): ?>
            <div style="padding: 0 1.5rem 1.5rem;">
                <h4 style="color: var(--text-dark); margin-bottom: 1rem;">📊 Recent Milk Collections</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Quantity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_milk as $milk): ?>
                                <tr>
                                    <td><?php echo display_date($milk['collection_date'], 'd M Y'); ?></td>
                                    <td><span class="badge badge-info"><?php echo $milk['shift']; ?></span></td>
                                    <td><strong style="color: var(--success);"><?php echo format_quantity($milk['quantity']); ?> L</strong></td>
                                    <td>
                                        <a href="../milk/milk-view.php?id=<?php echo $milk['milk_id']; ?>" class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="../milk/milk-list.php?cattle_id=<?php echo $cattle_id; ?>" class="btn btn-secondary">View All Milk Records</a>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-medium);">
                <p style="font-size: 3rem; margin-bottom: 1rem;">🥛</p>
                <p style="font-size: 1.1rem; margin-bottom: 1rem;">No milk collection records yet</p>
                <a href="../milk/milk-add.php?cattle_id=<?php echo $cattle_id; ?>" class="btn btn-primary">Add First Collection</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Offspring -->
    <?php if (!empty($offspring)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">👶 Offspring (<?php echo count($offspring); ?> Calf<?php echo count($offspring) > 1 ? 's' : ''; ?>)</h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tag ID</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Age</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offspring as $calf): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($calf['tag_id']); ?></strong></td>
                            <td>
                                <?php if ($calf['gender'] === 'Male'): ?>
                                    <span style="color: var(--info);">♂ Male</span>
                                <?php else: ?>
                                    <span style="color: var(--danger);">♀ Female</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo display_date($calf['dob'], 'd M Y'); ?></td>
                            <td><?php echo calculate_age($calf['dob']); ?> years</td>
                            <td><?php echo get_status_badge($calf['status'], $calf['is_pregnant']); ?></td>
                            <td>
                                <a href="cattle-view.php?id=<?php echo $calf['cattle_id']; ?>" class="btn btn-info btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">⚡ Quick Actions</h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; padding: 1.5rem;">
            <a href="../milk/milk-add.php?cattle_id=<?php echo $cattle_id; ?>" class="btn btn-success" style="padding: 1.2rem; text-align: center; display: flex; align-items: center; justify-content: center; height: 50px;">
                🥛 Add Milk Collection
            </a>
            <a href="cattle-edit.php?id=<?php echo $cattle_id; ?>" class="btn btn-primary" style="padding: 1.2rem; text-align: center; display: flex; align-items: center; justify-content: center; height: 50px;">
                ✏️ Edit Information
            </a>
            <a href="cattle-list.php" class="btn btn-secondary" style="padding: 1.2rem; text-align: center; display: flex; align-items: center; justify-content: center; height: 50px;">
                📋 Back to Cattle List
            </a>
            <a href="cattle-add.php?parent_id=<?php echo $cattle_id; ?>" class="btn btn-info" style="padding: 1.2rem; text-align: center; display: flex; align-items: center; justify-content: center; height: 50px;">
                👶 Register Offspring
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>