<?php
// admin/users/user-view.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "User Details";
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    $_SESSION['error_message'] = "Invalid user ID";
    header("Location: user-list.php");
    exit();
}

// Fetch user details
$sql = "SELECT u.*, r.role_name 
        FROM user u 
        JOIN role r ON u.role_id = r.role_id 
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "User not found";
    header("Location: user-list.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Get activity statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM sales WHERE user_id = ?) as total_sales,
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ?) as total_milk_records,
                (SELECT COUNT(*) FROM payment WHERE user_id = ?) as total_payments,
                (SELECT COUNT(*) FROM cattle WHERE user_id = ?) as total_cattle";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get recent activities
$activities = [];

// Recent sales
$sales_sql = "SELECT sales_id, sales_date, total_amount, 'Sale' as activity_type 
              FROM sales WHERE user_id = ? 
              ORDER BY sales_date DESC LIMIT 5";
$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param("i", $user_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
while ($row = $sales_result->fetch_assoc()) {
    $activities[] = $row;
}
$sales_stmt->close();

// Recent milk collections
$milk_sql = "SELECT milk_id, collection_date as sales_date, quantity as total_amount, 'Milk Collection' as activity_type 
             FROM milk_collection WHERE user_id = ? 
             ORDER BY collection_date DESC LIMIT 5";
$milk_stmt = $conn->prepare($milk_sql);
$milk_stmt->bind_param("i", $user_id);
$milk_stmt->execute();
$milk_result = $milk_stmt->get_result();
while ($row = $milk_result->fetch_assoc()) {
    $activities[] = $row;
}
$milk_stmt->close();

// Sort activities by date
usort($activities, function($a, $b) {
    return strtotime($b['sales_date']) - strtotime($a['sales_date']);
});
$activities = array_slice($activities, 0, 10);

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👤 User Details</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="user-list.php">Users</a>
                <span>/</span>
                <span>View User</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="user-edit.php?id=<?php echo $user_id; ?>" class="btn btn-warning">✏️ Edit</a>
            <a href="user-list.php" class="btn btn-primary">← Back to List</a>
        </div>
    </div>

    <div class="customer-details">
        <!-- User Information -->
        <div class="card">
            <div class="card-header" style="background: var(--accent-blue); color: white;">
                <h3>👤 User Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value">
                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value">
                        📧 <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Contact Number</div>
                    <div class="detail-value">
                        <?php if (!empty($user['contact'])): ?>
                            📞 <?php echo htmlspecialchars($user['contact']); ?>
                        <?php else: ?>
                            <span style="color: var(--text-medium);">Not provided</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Role</div>
                    <div class="detail-value">
                        <?php if ($user['role_name'] === 'Admin'): ?>
                            <span class="badge badge-warning">👑 Administrator</span>
                        <?php else: ?>
                            <span class="badge badge-info">👤 Employee</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php if ($user['status'] === 'Active'): ?>
                            <span class="badge badge-success">✓ Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">✕ Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 1rem;">
                    <div class="detail-label">Account Created</div>
                    <div class="detail-value">
                        <?php echo date('d M Y, h:i A', strtotime($user['created_at'])); ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Last Updated</div>
                    <div class="detail-value">
                        <?php echo date('d M Y, h:i A', strtotime($user['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Permissions -->
        <div class="card">
            <div class="card-header">
                <h3>🔐 Role & Permissions</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <?php if ($user['role_name'] === 'Admin'): ?>
                    <div class="info-box">
                        <strong>👑 Administrator Permissions:</strong>
                        <ul>
                            <li>✓ Full system access</li>
                            <li>✓ Manage users and roles</li>
                            <li>✓ Manage cattle, milk, sales, and inventory</li>
                            <li>✓ View all reports and analytics</li>
                            <li>✓ Manage customer accounts</li>
                            <li>✓ System configuration</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <strong>👤 Employee Permissions:</strong>
                        <ul>
                            <li>✓ View cattle list (read-only)</li>
                            <li>✓ Add and view milk records</li>
                            <li>✓ View inventory</li>
                            <li>✓ Record inventory usage</li>
                            <li>✓ View assigned reports</li>
                            <li>✕ No access to user management</li>
                            <li>✕ No access to financial reports</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Activity Statistics -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Sales</span>
                <span class="stat-value"><?php echo $stats['total_sales']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Milk Records</span>
                <span class="stat-value"><?php echo $stats['total_milk_records']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Payments Received</span>
                <span class="stat-value"><?php echo $stats['total_payments']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Cattle Records</span>
                <span class="stat-value"><?php echo $stats['total_cattle']; ?></span>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Recent Activity</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($activities)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activity Type</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($activity['sales_date'])); ?></td>
                                    <td>
                                        <?php if ($activity['activity_type'] === 'Sale'): ?>
                                            <span class="badge badge-primary">📊 Sale</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">🥛 Milk Collection</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($activity['activity_type'] === 'Sale'): ?>
                                            Sale #<?php echo $activity['sales_id']; ?> - रू <?php echo number_format($activity['total_amount'], 2); ?>
                                        <?php else: ?>
                                            Collection #<?php echo $activity['milk_id']; ?> - <?php echo number_format($activity['total_amount'], 2); ?> L
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📋</span>
                    <p>No activity recorded yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../../includes/footer.php';
?>