<?php
/**
 * =========================================================
 * Breeds List
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_admin();

$type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;

// Get breeds
if ($type_id > 0) {
    $result = $conn->query("
        SELECT b.*, ct.type_name 
        FROM breed b
        JOIN cattle_type ct ON b.type_id = ct.type_id
        WHERE b.type_id = {$type_id}
        ORDER BY b.breed_name ASC
    ");
    
    $type_name = $conn->query("SELECT type_name FROM cattle_type WHERE type_id = {$type_id}")->fetch_assoc()['type_name'] ?? 'Unknown';
} else {
    $result = $conn->query("
        SELECT b.*, ct.type_name 
        FROM breed b
        JOIN cattle_type ct ON b.type_id = ct.type_id
        ORDER BY ct.type_name ASC, b.breed_name ASC
    ");
    $type_name = 'All Types';
}

$page_title = 'Breeds Management';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header with Breadcrumb -->
    <div class="page-header">
        <div class="header-content">
            <h1>📝 Breeds Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="types-list.php">Cattle Management</a>
                <span>/</span>
                <span>Breeds<?php echo $type_id > 0 ? " ({$type_name})" : ''; ?></span>
            </div>
        </div>
        <div class="header-actions">
            <?php if ($type_id > 0): ?>
                <a href="types-list.php" class="btn btn-secondary">
                    ← Back to Types
                </a>
            <?php else: ?>
                <a href="../dashboard.php" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter by Type -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🔍 Filter by Type</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                <a href="breeds-list.php" class="btn <?php echo $type_id === 0 ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                    All Types
                </a>
                <?php
                $types = $conn->query("SELECT * FROM cattle_type ORDER BY type_name ASC");
                while ($type = $types->fetch_assoc()):
                ?>
                    <a href="breeds-list.php?type_id=<?php echo $type['type_id']; ?>" 
                       class="btn <?php echo $type_id === $type['type_id'] ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                        <?php echo htmlspecialchars($type['type_name']); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Breeds Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php echo $type_id > 0 ? "🐄 {$type_name} Breeds" : '📋 All Breeds'; ?>
            </h3>
            <div style="display: flex; gap: 0.5rem;">
                <?php if ($type_id > 0): ?>
                    <a href="breeds-list.php" class="btn btn-secondary btn-sm">View All Breeds</a>
                <?php endif; ?>
                <a href="breed-add.php<?php echo $type_id > 0 ? "?type_id={$type_id}" : ''; ?>" class="btn btn-primary btn-sm">
                    ➕ Add New Breed
                </a>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Breed Name</th>
                            <th>Cattle Type</th>
                            <th>Cattle Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($breed = $result->fetch_assoc()): ?>
                            <?php
                            // Count cattle with this breed
                            $cattle_count = $conn->query("SELECT COUNT(*) as count FROM cattle WHERE breed_id = {$breed['breed_id']}")->fetch_assoc()['count'];
                            ?>
                            <tr>
                                <td><?php echo $breed['breed_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($breed['breed_name']); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($breed['type_name']); ?></span></td>
                                <td><?php echo $cattle_count; ?> cattle</td>
                                <td>
                                    <a href="breed-edit.php?id=<?php echo $breed['breed_id']; ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                    <a href="breed-delete.php?id=<?php echo $breed['breed_id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this breed?')">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-medium);">
                <p style="font-size: 3rem; margin-bottom: 1rem;">📋</p>
                <p style="font-size: 1.2rem; margin-bottom: 1rem;">No breeds found<?php echo $type_id > 0 ? " for {$type_name}" : ''; ?></p>
                <a href="breed-add.php<?php echo $type_id > 0 ? "?type_id={$type_id}" : ''; ?>" class="btn btn-primary">
                    ➕ Add First Breed
                </a>
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
                <a href="types-list.php" class="btn btn-info btn-block">🐄 Manage Types</a>
                <a href="breed-add.php" class="btn btn-success btn-block">➕ Add New Breed</a>
                <a href="../cattle/cattle-list.php" class="btn btn-secondary btn-block">🐮 View Cattle</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>