<?php
/**
 * =========================================================
 * Cattle List - View All Cattle
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/database-helpers.php';

require_admin();

// Get filter parameters
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : '';
$type_filter = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
$breed_filter = isset($_GET['breed_id']) ? (int)$_GET['breed_id'] : 0;
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Build query with virtual status column
$sql = "SELECT c.*, ct.type_name, b.breed_name, u.full_name as added_by,
        CASE 
            WHEN c.life_status = 'Alive' AND c.is_pregnant = 1 THEN 'Pregnant'
            ELSE c.life_status 
        END AS display_status
        FROM cattle c
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        JOIN user u ON c.user_id = u.user_id
        WHERE 1=1";

if ($status_filter) {
    if ($status_filter === 'Pregnant') {
        $sql .= " AND c.is_pregnant = 1 AND c.life_status = 'Alive'";
    } else {
        $sql .= " AND c.life_status = '{$status_filter}'";
    }
}

if ($type_filter > 0) {
    $sql .= " AND c.type_id = {$type_filter}";
}

if ($breed_filter > 0) {
    $sql .= " AND c.breed_id = {$breed_filter}";
}

if ($search) {
    $sql .= " AND (c.tag_id LIKE '%{$search}%' OR c.notes LIKE '%{$search}%')";
}

$sql .= " ORDER BY c.created_at DESC";

$result = $conn->query($sql);

// Get counts for statistics
$total_cattle = count_records('cattle', "life_status = 'Alive'");
$pregnant_count = count_records('cattle', "life_status = 'Alive' AND is_pregnant = 1");
$sold_count = count_records('cattle', "life_status = 'Sold'");
$dead_count = count_records('cattle', "life_status = 'Dead'");

$page_title = 'Cattle Management';
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🐄 Cattle Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Manage Cattle</span>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">
        <div class="stat-card success">
            <div class="stat-header">
                <span class="stat-title">Active Cattle</span>
                <span class="stat-icon">🐄</span>
            </div>
            <div class="stat-value"><?php echo $total_cattle; ?></div>
            <div class="stat-label">Currently alive</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-header">
                <span class="stat-title">Sold</span>
                <span class="stat-icon">🪙</span>
            </div>
            <div class="stat-value"><?php echo $sold_count; ?></div>
            <div class="stat-label">Sold cattle</div>
        </div>

        <div class="stat-card info">
            <div class="stat-header">
                <span class="stat-title">Pregnant</span>
                <span class="stat-icon">🐄</span>
            </div>
            <div class="stat-value"><?php echo $pregnant_count; ?></div>
            <div class="stat-label">Expecting calves</div>
        </div>

        <div class="stat-card danger">
            <div class="stat-header">
                <span class="stat-title">Deceased</span>
                <span class="stat-icon">💔</span>
            </div>
            <div class="stat-value"><?php echo $dead_count; ?></div>
            <div class="stat-label">Lost cattle</div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">All Cattle (<?php echo $result->num_rows; ?> records)</h3>
            <a href="cattle-add.php" class="btn btn-primary btn-sm">+ Add New Cattle</a>
        </div>

        <!-- Filters -->
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); background: var(--bg-tertiary);">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                
                <div class="form-group" style="margin: 0;">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="🔍 Search by Tag ID or Notes..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        style="height: 40px; padding: 0.5rem 1rem;"
                        class="form-control"
                    >
                </div>

                <div class="form-group" style="margin: 0;">
                    <select name="status" class="form-control" style="height: 40px; padding: 0.5rem 2.5rem 0.5rem 1rem;">
                        <option value="">All Status</option>
                        <option value="Alive" <?php echo $status_filter === 'Alive' ? 'selected' : ''; ?>>Alive</option>
                        <option value="Pregnant" <?php echo $status_filter === 'Pregnant' ? 'selected' : ''; ?>>Pregnant</option>
                        <option value="Sold" <?php echo $status_filter === 'Sold' ? 'selected' : ''; ?>>Sold</option>
                        <option value="Dead" <?php echo $status_filter === 'Dead' ? 'selected' : ''; ?>>Dead</option>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <select name="type_id" class="form-control" style="height: 40px; padding: 0.5rem 2.5rem 0.5rem 1rem;">
                        <option value="">All Types</option>
                        <?php
                        $types = $conn->query("SELECT * FROM cattle_type ORDER BY type_name");
                        while ($type = $types->fetch_assoc()):
                        ?>
                            <option value="<?php echo $type['type_id']; ?>" <?php echo $type_filter == $type['type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.5rem;">
                        🔍 Filter
                    </button>
                    <a href="cattle-list.php" class="btn btn-secondary" style="height: 40px; padding: 0 1.5rem; line-height: 40px; text-decoration: none;">
                        ✖ Reset
                    </a>
                </div>
            </form>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table" id="cattleTable">
                    <thead>
                        <tr>
                            <th>Tag ID</th>
                            <th>Type</th>
                            <th>Breed</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Status</th>
                            <th>Added By</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cattle = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--accent-blue);">
                                        <?php echo htmlspecialchars($cattle['tag_id']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo htmlspecialchars($cattle['type_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($cattle['breed_name']); ?></td>
                                <td>
                                    <?php if ($cattle['gender'] === 'Male'): ?>
                                        <span style="color: var(--info); font-weight: 500;">♂ Male</span>
                                    <?php else: ?>
                                        <span style="color: var(--danger); font-weight: 500;">♀ Female</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo calculate_age($cattle['dob']); ?> years</td>
                                <td><?php echo get_status_badge($cattle['life_status'], $cattle['is_pregnant']); ?></td>
                                <td><?php echo htmlspecialchars($cattle['added_by']); ?></td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <a href="cattle-view.php?id=<?php echo $cattle['cattle_id']; ?>" 
                                       class="btn btn-info btn-sm" 
                                       title="View Details"
                                       style="margin: 0 2px;">👁️</a>
                                    <a href="cattle-edit.php?id=<?php echo $cattle['cattle_id']; ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       title="Edit"
                                       style="margin: 0 2px;">✏️</a>
                                    <a href="cattle-delete.php?id=<?php echo $cattle['cattle_id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       title="Delete"
                                       style="margin: 0 2px;"
                                       onclick="return confirm('Are you sure you want to delete cattle <?php echo htmlspecialchars($cattle['tag_id']); ?>? This action cannot be undone.')">🗑️</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-medium);">
                <p style="font-size: 3rem; margin-bottom: 1rem;">🐄</p>
                <p style="font-size: 1.2rem; margin-bottom: 1rem; font-weight: 500;">No cattle found</p>
                <?php if ($status_filter || $type_filter || $search): ?>
                    <p style="margin-bottom: 1rem; color: var(--text-light);">Try adjusting your filters to see more results</p>
                    <a href="cattle-list.php" class="btn btn-secondary">Clear All Filters</a>
                <?php else: ?>
                    <p style="margin-bottom: 1rem; color: var(--text-light);">Get started by adding your first cattle to the system</p>
                    <a href="cattle-add.php" class="btn btn-primary">+ Add Your First Cattle</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Real-time table search (client-side filtering)
const searchInput = document.querySelector('input[name="search"]');
if (searchInput) {
    // Only enable real-time search if not using server-side search
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#cattleTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
}

// Add confirmation with details for delete
document.querySelectorAll('a[href*="cattle-delete.php"]').forEach(link => {
    link.addEventListener('click', function(e) {
        const tagId = this.closest('tr').querySelector('td:first-child strong').textContent;
        if (!confirm(`Are you sure you want to delete cattle "${tagId}"?\n\nThis will also delete:\n- All milk collection records\n- Offspring relationships\n\nThis action cannot be undone!`)) {
            e.preventDefault();
        }
    });
});
</script>