<?php
/**
 * =========================================================
 * Cattle Types List
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_admin();

// Get all cattle types
$result = $conn->query("SELECT * FROM cattle_type ORDER BY type_name ASC");

$page_title = 'Cattle Types';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>🐄 Cattle Types Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Cattle Management</span>
                <span>/</span>
                <span>Types</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="../dashboard.php" class="btn btn-secondary">
                ← Back
            </a>
            <a href="type-add.php" class="btn btn-primary btn-sm">+ Add New Type</a>
            <a href="breed-add.php" class="btn btn-success btn-sm">+ Add New Breed</a>

        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 All Cattle Types</h3>
            
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>S.N.</th>
                            <th>ID</th>
                            <th>Type Name</th>
                            <th>Breeds Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $serial = 1;
                        while ($type = $result->fetch_assoc()): 
                        ?>
                            <?php
                            // Count breeds for this type
                            $breed_count = $conn->query("SELECT COUNT(*) as count FROM breed WHERE type_id = {$type['type_id']}")->fetch_assoc()['count'];
                            ?>
                            <tr>
                                <td><?php echo $serial++; ?></td>
                                <td><?php echo $type['type_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($type['type_name']); ?></strong></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $breed_count; ?> breeds</span>
                                </td>
                                <td>
                                    <a href="breeds-list.php?type_id=<?php echo $type['type_id']; ?>" class="btn btn-info btn-sm">👁️ View Breeds</a>
                                    <a href="type-edit.php?id=<?php echo $type['type_id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                    <a href="type-delete.php?id=<?php echo $type['type_id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Delete this cattle type? This will also delete all associated breeds!')">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-medium);">
                <p style="font-size: 3rem; margin-bottom: 1rem;">📋</p>
                <p style="font-size: 1.2rem; margin-bottom: 1rem;">No cattle types found</p>
                <a href="type-add.php" class="btn btn-primary">➕ Add Your First Type</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🔗 Quick Links</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="breeds-list.php" class="btn btn-info btn-block">📝 View All Breeds</a>
                <a href="breed-add.php" class="btn btn-success btn-block">➕ Add New Breed</a>
                <a href="../cattle/cattle-list.php" class="btn btn-secondary btn-block">🐮 View Cattle</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>