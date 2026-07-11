<?php
// admin/reports/cattle/age-distribution.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Cattle Age Distribution";

// Fetch age distribution - FIXED: Changed 'status' to 'life_status'
$sql = "SELECT 
            c.cattle_id,
            c.tag_id,
            c.gender,
            c.dob,
            ct.type_name,
            b.breed_name,
            c.life_status,
            TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) as age_years,
            TIMESTAMPDIFF(MONTH, c.dob, CURDATE()) as age_months,
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) < 1 THEN 'Calf'
                WHEN TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) BETWEEN 1 AND 3 THEN 'Young'
                ELSE 'Adult'
            END as age_group
        FROM cattle c
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        WHERE c.life_status IN ('Alive', 'Pregnant')
        ORDER BY age_years DESC, tag_id";
$result = $conn->query($sql);

// Calculate age group counts - FIXED: Changed 'status' to 'life_status'
$group_sql = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 1 THEN 'Calf'
                    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 1 AND 3 THEN 'Young'
                    ELSE 'Adult'
                END as age_group,
                COUNT(*) as count
              FROM cattle
              WHERE life_status IN ('Alive', 'Pregnant')
              GROUP BY age_group";
$groups = $conn->query($group_sql);

$age_groups = ['Calf' => 0, 'Young' => 0, 'Adult' => 0];
while ($row = $groups->fetch_assoc()) {
    $age_groups[$row['age_group']] = $row['count'];
}
$total_cattle = array_sum($age_groups);

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📅 Cattle Age Distribution</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Age Distribution</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
        </div>
    </div>

    <!-- Age Group Summary -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Total Active Cattle</span>
                <span class="stat-value"><?php echo $total_cattle; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">👶</div>
            <div class="stat-details">
                <span class="stat-label">Calves (0-1 year)</span>
                <span class="stat-value"><?php echo $age_groups['Calf']; ?></span>
                <small style="color: var(--text-medium);">
                    <?php echo $total_cattle > 0 ? number_format(($age_groups['Calf'] / $total_cattle) * 100, 1) : 0; ?>%
                </small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🐮</div>
            <div class="stat-details">
                <span class="stat-label">Young (1-3 years)</span>
                <span class="stat-value"><?php echo $age_groups['Young']; ?></span>
                <small style="color: var(--text-medium);">
                    <?php echo $total_cattle > 0 ? number_format(($age_groups['Young'] / $total_cattle) * 100, 1) : 0; ?>%
                </small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Adults (3+ years)</span>
                <span class="stat-value"><?php echo $age_groups['Adult']; ?></span>
                <small style="color: var(--text-medium);">
                    <?php echo $total_cattle > 0 ? number_format(($age_groups['Adult'] / $total_cattle) * 100, 1) : 0; ?>%
                </small>
            </div>
        </div>
    </div>

    <!-- Visual Distribution -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Age Group Distribution</h3>
        </div>
        <div class="card-body" style="padding: 2rem;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem;">
                <?php foreach ($age_groups as $group => $count): ?>
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">
                            <?php 
                            echo $group === 'Calf' ? '👶' : ($group === 'Young' ? '🐮' : '🐄');
                            ?>
                        </div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--accent-blue);">
                            <?php echo $count; ?>
                        </div>
                        <div style="color: var(--text-medium); margin-bottom: 1rem;">
                            <?php echo $group; ?> 
                            (<?php echo $total_cattle > 0 ? number_format(($count / $total_cattle) * 100, 1) : 0; ?>%)
                        </div>
                        <div style="height: 10px; background: var(--border-color); border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; background: var(--accent-blue); 
                                        width: <?php echo $total_cattle > 0 ? ($count / $total_cattle) * 100 : 0; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Detailed List -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Detailed Age List</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tag ID</th>
                            <th>Type/Breed</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Age (Years.Months)</th>
                            <th>Age Group</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['tag_id']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['type_name']); ?> / 
                                        <?php echo htmlspecialchars($row['breed_name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['gender'] === 'Male' ? 'info' : 'primary'; ?>">
                                            <?php echo $row['gender'] === 'Male' ? '♂ Male' : '♀ Female'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($row['dob'])); ?></td>
                                    <td>
                                        <strong><?php echo $row['age_years']; ?></strong> years 
                                        <strong><?php echo $row['age_months'] % 12; ?></strong> months
                                    </td>
                                    <td>
                                        <?php
                                        $group_class = [
                                            'Calf' => 'info',
                                            'Young' => 'warning',
                                            'Adult' => 'success'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $group_class[$row['age_group']]; ?>">
                                            <?php echo $row['age_group']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'Alive' => 'success',
                                            'Pregnant' => 'warning',
                                            'Sold' => 'info',
                                            'Dead' => 'danger'
                                        ];
                                        $status = $row['life_status'];
                                        ?>
                                        <span class="badge badge-<?php echo $status_class[$status] ?? 'secondary'; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="empty-icon">🐄</span>
                                        <p>No active cattle found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<<script>
function exportPDF() {
    window.print();
}
</script>

<?php
$conn->close();
include '../../../includes/footer.php';
?>