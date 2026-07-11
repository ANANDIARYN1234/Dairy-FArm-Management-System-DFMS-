<?php
// employee/cattle/cattle-view.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Cattle Details";
$cattle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cattle_id <= 0) {
    $_SESSION['error_message'] = "Invalid cattle ID";
    header("Location: cattle-list.php");
    exit();
}

// Fetch cattle details
$sql = "SELECT c.*, ct.type_name, b.breed_name,
        (SELECT tag_id FROM cattle WHERE cattle_id = c.parent_id) as parent_tag
        FROM cattle c
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        WHERE c.cattle_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cattle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Cattle not found";
    header("Location: cattle-list.php");
    exit();
}

$cattle = $result->fetch_assoc();
$stmt->close();

// Calculate age
$age_years = floor((strtotime('now') - strtotime($cattle['dob'])) / (365 * 24 * 60 * 60));
$age_months = floor(((strtotime('now') - strtotime($cattle['dob'])) % (365 * 24 * 60 * 60)) / (30 * 24 * 60 * 60));

// Get milk production stats (if female)
if ($cattle['gender'] === 'Female') {
    $milk_stats_sql = "SELECT 
                        COUNT(*) as total_collections,
                        COALESCE(SUM(quantity), 0) as total_milk,
                        COALESCE(AVG(quantity), 0) as avg_milk,
                        MAX(collection_date) as last_collection
                       FROM milk_collection
                       WHERE cattle_id = ?";
    $milk_stmt = $conn->prepare($milk_stats_sql);
    $milk_stmt->bind_param("i", $cattle_id);
    $milk_stmt->execute();
    $milk_stats = $milk_stmt->get_result()->fetch_assoc();
    $milk_stmt->close();
}

// Get offspring count
$offspring_sql = "SELECT COUNT(*) as count FROM cattle WHERE parent_id = ?";
$offspring_stmt = $conn->prepare($offspring_sql);
$offspring_stmt->bind_param("i", $cattle_id);
$offspring_stmt->execute();
$offspring_count = $offspring_stmt->get_result()->fetch_assoc()['count'];
$offspring_stmt->close();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🐄 Cattle Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="cattle-list.php">Cattle</a>
                <span>/</span>
                <span>View Details</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="cattle-list.php" class="btn btn-primary">← Back to List</a>
        </div>
    </div>

    <!-- Info Alert -->
    <!-- <div class="alert alert-info">
        <span class="alert-icon">ℹ</span>
        <span class="alert-message">
            <strong>Read-Only:</strong> You are viewing cattle information. Contact administrator for any updates.
        </span>
    </div> -->
    <?php if ($cattle['gender'] === 'Female' && isset($milk_stats)): ?>
        <!-- Milk Production Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">📊</div>
                <div class="stat-details">
                    <span class="stat-label">Total Collections</span>
                    <span class="stat-value"><?php echo $milk_stats['total_collections']; ?></span>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">🥛</div>
                <div class="stat-details">
                    <span class="stat-label">Total Milk</span>
                    <span class="stat-value"><?php echo number_format($milk_stats['total_milk'], 2); ?> L</span>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">📈</div>
                <div class="stat-details">
                    <span class="stat-label">Average per Collection</span>
                    <span class="stat-value"><?php echo number_format($milk_stats['avg_milk'], 2); ?> L</span>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">📅</div>
                <div class="stat-details">
                    <span class="stat-label">Last Collection</span>
                    <span class="stat-value" style="font-size: 1.2rem;">
                        <?php echo $milk_stats['last_collection'] ? date('d M Y', strtotime($milk_stats['last_collection'])) : 'Never'; ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="customer-details">
        <!-- Basic Information -->
        <div class="card">
            <div class="card-header" style="background: var(--accent-blue); color: white;">
                <h3>📋 Basic Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Tag ID</div>
                    <div class="detail-value">
                        <strong style="font-size: 1.3rem; color: var(--accent-blue);">
                            <?php echo htmlspecialchars($cattle['tag_id']); ?>
                        </strong>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Type & Breed</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($cattle['type_name']); ?> - 
                        <?php echo htmlspecialchars($cattle['breed_name']); ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Gender</div>
                    <div class="detail-value">
                        <?php if ($cattle['gender'] === 'Male'): ?>
                            <span class="badge badge-info">♂ Male</span>
                        <?php else: ?>
                            <span class="badge badge-warning">♀ Female</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Date of Birth</div>
                    <div class="detail-value">
                        <?php echo date('d M Y', strtotime($cattle['dob'])); ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Age</div>
                    <div class="detail-value">
                        <?php if ($age_years > 0): ?>
                            <strong><?php echo $age_years; ?></strong> years 
                            <strong><?php echo $age_months; ?></strong> months
                        <?php else: ?>
                            <strong><?php echo $age_months; ?></strong> months
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php
                        $status_class = [
                            'Alive' => 'success',
                            'Pregnant' => 'warning',
                            'Sold' => 'info',
                            'Dead' => 'secondary'
                        ];
                        ?>
                        <span class="badge badge-<?php echo $status_class[$cattle['life_status']]; ?>">
                            <?php echo $cattle['life_status']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parent & Offspring Info -->
        <div class="card">
            <div class="card-header">
                <h3>👪 Lineage Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Parent</div>
                    <div class="detail-value">
                        <?php if (!empty($cattle['parent_tag'])): ?>
                            <strong><?php echo htmlspecialchars($cattle['parent_tag']); ?></strong>
                        <?php else: ?>
                            <span style="color: var(--text-medium);">No parent recorded</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Offspring Count</div>
                    <div class="detail-value">
                        <strong><?php echo $offspring_count; ?></strong> 
                        <?php echo $offspring_count == 1 ? 'offspring' : 'offspring'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    

    <!-- Notes -->
    <?php if (!empty($cattle['notes'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>📝 Notes</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--info);">
                    <?php echo nl2br(htmlspecialchars($cattle['notes'])); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
include '../../includes/footer.php';
?>