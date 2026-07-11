<?php
// admin/reports/cattle/breeding-report.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Breeding Report";

// Fetch mothers with offspring count - FIXED: Changed 'status' to 'life_status'
$mothers_sql = "SELECT 
                    m.cattle_id,
                    m.tag_id,
                    m.dob,
                    ct.type_name,
                    b.breed_name,
                    m.life_status,
                    m.is_pregnant,
                    COUNT(c.cattle_id) as offspring_count,
                    TIMESTAMPDIFF(YEAR, m.dob, CURDATE()) as age_years
                FROM cattle m
                JOIN cattle_type ct ON m.type_id = ct.type_id
                JOIN breed b ON m.breed_id = b.breed_id
                LEFT JOIN cattle c ON m.cattle_id = c.parent_id
                WHERE m.gender = 'Female'
                GROUP BY m.cattle_id
                HAVING offspring_count > 0
                ORDER BY offspring_count DESC, m.tag_id";
$mothers = $conn->query($mothers_sql);

// Get total statistics
$stats_sql = "SELECT 
                COUNT(DISTINCT parent_id) as total_mothers,
                COUNT(*) as total_offspring
              FROM cattle 
              WHERE parent_id IS NOT NULL";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get pregnant cattle - FIXED: Changed to use is_pregnant field
$pregnant_sql = "SELECT COUNT(*) as count FROM cattle WHERE is_pregnant = 1 AND gender = 'Female'";
$pregnant_count = $conn->query($pregnant_sql)->fetch_assoc()['count'];

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>👶 Breeding Report</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Breeding Report</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">👶</div>
            <div class="stat-details">
                <span class="stat-label">Total Offspring</span>
                <span class="stat-value"><?php echo $stats['total_offspring']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Mother Cattle</span>
                <span class="stat-value"><?php echo $stats['total_mothers']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🤰</div>
            <div class="stat-details">
                <span class="stat-label">Currently Pregnant</span>
                <span class="stat-value"><?php echo $pregnant_count; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Avg Offspring</span>
                <span class="stat-value">
                    <?php echo $stats['total_mothers'] > 0 ? number_format($stats['total_offspring'] / $stats['total_mothers'], 1) : 0; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Mothers with Offspring -->
    <div class="card">
        <div class="card-header">
            <h3>👩‍👦 Mother Cattle & Offspring</h3>
        </div>
        <div class="card-body">
            <?php if ($mothers && $mothers->num_rows > 0): ?>
                <?php while ($mother = $mothers->fetch_assoc()): ?>
                    <div style="margin-bottom: 2rem; padding: 1.5rem; background: var(--bg-tertiary); border-radius: 12px;">
                        <!-- Mother Info -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color);">
                            <div>
                                <h4 style="margin: 0 0 0.5rem 0; color: var(--accent-blue);">
                                    🐄 <?php echo htmlspecialchars($mother['tag_id']); ?>
                                </h4>
                                <div style="color: var(--text-medium);">
                                    <?php echo htmlspecialchars($mother['type_name']); ?> / 
                                    <?php echo htmlspecialchars($mother['breed_name']); ?> • 
                                    Age: <?php echo $mother['age_years']; ?> years
                                </div>
                            </div>
                            <div>
                                <span class="badge badge-primary" style="font-size: 1.1rem; padding: 0.5rem 1rem;">
                                    <?php echo $mother['offspring_count']; ?> Offspring
                                </span>
                                <?php if ($mother['is_pregnant'] == 1): ?>
                                    <span class="badge badge-warning" style="font-size: 1.1rem; padding: 0.5rem 1rem; margin-left: 0.5rem;">
                                        🤰 Pregnant
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Offspring List -->
                        <div style="margin-left: 2rem;">
                            <strong style="color: var(--text-dark); margin-bottom: 0.5rem; display: block;">Offspring:</strong>
                            <?php
                            // FIXED: Changed 'status' to 'life_status'
                            $offspring_sql = "SELECT 
                                                c.cattle_id,
                                                c.tag_id,
                                                c.gender,
                                                c.dob,
                                                c.life_status,
                                                TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) as age_years,
                                                TIMESTAMPDIFF(MONTH, c.dob, CURDATE()) as age_months
                                              FROM cattle c
                                              WHERE c.parent_id = ?
                                              ORDER BY c.dob DESC";
                            $offspring_stmt = $conn->prepare($offspring_sql);
                            $offspring_stmt->bind_param("i", $mother['cattle_id']);
                            $offspring_stmt->execute();
                            $offspring = $offspring_stmt->get_result();
                            ?>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                                <?php while ($child = $offspring->fetch_assoc()): ?>
                                    <div style="padding: 1rem; background: white; border-radius: 8px; border-left: 4px solid var(--accent-blue);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong><?php echo htmlspecialchars($child['tag_id']); ?></strong>
                                                <span class="badge badge-<?php echo $child['gender'] === 'Male' ? 'info' : 'primary'; ?>" 
                                                      style="margin-left: 0.5rem; font-size: 0.8rem;">
                                                    <?php echo $child['gender'] === 'Male' ? '♂' : '♀'; ?>
                                                </span>
                                            </div>
                                            <?php
                                            $status_class = [
                                                'Alive' => 'success',
                                                'Pregnant' => 'warning',
                                                'Sold' => 'info',
                                                'Dead' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge badge-<?php echo $status_class[$child['life_status']] ?? 'secondary'; ?>" style="font-size: 0.75rem;">
                                                <?php echo $child['life_status']; ?>
                                            </span>
                                        </div>
                                        <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-medium);">
                                            Born: <?php echo date('d M Y', strtotime($child['dob'])); ?><br>
                                            Age: <?php echo $child['age_years']; ?>y <?php echo $child['age_months'] % 12; ?>m
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <?php $offspring_stmt->close(); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">👶</span>
                    <p>No breeding records found</p>
                    <small style="color: var(--text-medium);">No cattle have recorded offspring yet</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <strong>ℹ About This Report:</strong>
        <ul>
            <li>Shows female cattle (mothers) with their offspring</li>
            <li>Offspring are tracked through the parent_id field</li>
            <li>Currently pregnant cattle are highlighted</li>
            <li>To add offspring: Edit cattle and set the mother as parent</li>
        </ul>
    </div>
</div>

<script>
function exportPDF() {
    window.print();
}
</script>
<?php
$conn->close();
include '../../../includes/footer.php';
?>