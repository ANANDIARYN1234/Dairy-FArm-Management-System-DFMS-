<?php
// admin/users/role-management.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Role Management";

// Fetch all roles with user counts
$sql = "SELECT r.*, COUNT(u.user_id) as user_count
        FROM role r
        LEFT JOIN user u ON r.role_id = u.role_id
        GROUP BY r.role_id
        ORDER BY r.role_name";
$result = $conn->query($sql);

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🔐 Role Management</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="user-list.php">Users</a>
                <span>/</span>
                <span>Role Management</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="user-list.php" class="btn btn-primary">← Back to Users</a>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info">
        <span class="alert-icon">ℹ</span>
        <div class="alert-message">
            <strong>Information:</strong>
            <p style="margin-top: 0.5rem;">
                This system uses a predefined role structure with one Administrator and multiple Employees. 
                Roles cannot be added or deleted to maintain system integrity and security.
            </p>
        </div>
    </div>

    <!-- Roles List -->
    <div class="customer-details">
        <?php while ($role = $result->fetch_assoc()): ?>
            <div class="card">
                <div class="card-header" style="background: <?php echo $role['role_name'] === 'Admin' ? 'var(--warning)' : 'var(--info)'; ?>; color: white;">
                    <h3>
                        <?php echo $role['role_name'] === 'Admin' ? '👑' : '👤'; ?> 
                        <?php echo htmlspecialchars($role['role_name']); ?>
                        <span class="badge" style="background: rgba(255,255,255,0.3); float: right;">
                            <?php echo $role['user_count']; ?> <?php echo $role['user_count'] == 1 ? 'user' : 'users'; ?>
                        </span>
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <?php if ($role['role_name'] === 'Admin'): ?>
                        <h4 style="color: var(--text-dark); margin-bottom: 1rem;">👑 Administrator Permissions</h4>
                        <div class="info-box">
                            <strong>Full System Access:</strong>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                <div>
                                    <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">👥 User Management</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Create, edit, delete employees</li>
                                        <li>View roles and permissions</li>
                                        <li>View user activity logs</li>
                                        <li>Activate/deactivate accounts</li>
                                    </ul>
                                </div>

                                <div>
                                    <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">🐄 Cattle Management</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Add, edit, delete cattle</li>
                                        <li>Manage types and breeds</li>
                                        <li>Track breeding records</li>
                                        <li>View cattle health status</li>
                                    </ul>
                                </div>

                                <div>
                                    <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">🥛 Milk Management</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Record milk production</li>
                                        <li>Edit/delete milk records</li>
                                        <li>View production reports</li>
                                        <li>Track daily collections</li>
                                    </ul>
                                </div>

                                <div>
                                    <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">🛒 Sales & Customers</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Manage all customer accounts</li>
                                        <li>View/edit/delete all sales</li>
                                        <li>Process all payments</li>
                                        <li>View customer ledgers</li>
                                    </ul>
                                </div>

                                <div>
                                    <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">📦 Inventory</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Manage inventory items</li>
                                        <li>Track stock levels</li>
                                        <li>Record stock transactions</li>
                                        <li>View low stock alerts</li>
                                    </ul>
                                </div>

                                <div>
                                    <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">📊 Reports & Analytics</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>All financial reports</li>
                                        <li>Production analytics</li>
                                        <li>Sales reports</li>
                                        <li>Custom report generation</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <h4 style="color: var(--text-dark); margin-bottom: 1rem;">👤 Employee Permissions</h4>
                        <div class="info-box">
                            <strong>Limited Access:</strong>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                <div>
                                    <h6 style="color: var(--success); margin-bottom: 0.5rem;">✓ Allowed Actions</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>View cattle list (read-only)</li>
                                        <li>Add milk collection records</li>
                                        <li>View own milk records</li>
                                        <li>Add walk-in customers</li>
                                        <li>Record sales they made</li>
                                        <li>Record payments they received</li>
                                        <li>View inventory items</li>
                                        <li>Record inventory usage</li>
                                        <li>View assigned reports</li>
                                        <li>Update own profile</li>
                                    </ul>
                                </div>

                                <div>
                                    <h6 style="color: var(--danger); margin-bottom: 0.5rem;">✕ Restricted Actions</h6>
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Cannot manage users/employees</li>
                                        <li>Cannot add/edit cattle</li>
                                        <li>Cannot edit/delete customers</li>
                                        <li>Cannot edit others' milk records</li>
                                        <li>Cannot edit/delete others' sales</li>
                                        <li>Cannot view all financial reports</li>
                                        <li>Cannot manage inventory items</li>
                                        <li>Cannot modify system settings</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Employee Workflow -->
                        <div style="margin-top: 1.5rem; padding: 1rem; background: #e8f5e9; border-radius: 8px; border-left: 4px solid var(--success);">
                            <strong style="color: var(--success);">📋 Typical Employee Workflow:</strong>
                            <ol style="margin: 0.5rem 0 0 1.5rem; color: var(--text-dark);">
                                <li>Collect milk from cattle → Record in system</li>
                                <li>Customer walks in to buy milk → Add walk-in customer</li>
                                <li>Record the sale → Select available milk</li>
                                <li>Receive payment → Record payment transaction</li>
                                <li>Use inventory items → Record usage</li>
                                <li>View their own activity reports</li>
                            </ol>
                        </div>
                    <?php endif; ?>

                    <!-- Users with this role -->
                    <?php if ($role['user_count'] > 0): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
                            <h6 style="color: var(--text-dark); margin-bottom: 1rem;">
                                Users with this role (<?php echo $role['user_count']; ?>):
                            </h6>
                            <?php
                            $users_sql = "SELECT user_id, full_name, email, status 
                                         FROM user 
                                         WHERE role_id = ? 
                                         ORDER BY full_name 
                                         LIMIT 10";
                            $users_stmt = $conn->prepare($users_sql);
                            $users_stmt->bind_param("i", $role['role_id']);
                            $users_stmt->execute();
                            $users_result = $users_stmt->get_result();
                            ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 0.75rem;">
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <div style="padding: 0.75rem; background: var(--bg-tertiary); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                            <small style="color: var(--text-medium);"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </div>
                                        <div>
                                            <?php if ($user['status'] === 'Active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                <?php $users_stmt->close(); ?>
                            </div>
                            <?php if ($role['user_count'] > 10): ?>
                                <p style="margin-top: 1rem; text-align: center;">
                                    <a href="user-list.php?role=<?php echo urlencode($role['role_name']); ?>" class="btn btn-sm btn-primary">
                                        View All <?php echo $role['user_count']; ?> Users →
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; text-align: center; color: var(--text-medium);">
                            No users assigned to this role yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Additional Information -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Role Management Guidelines</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="info-box">
                <strong>Important Notes:</strong>
                <ul>
                    <li><strong>Single Admin System:</strong> Only one administrator exists for security. The admin role cannot be duplicated or modified</li>
                    <li><strong>Employee Capabilities:</strong> Employees can perform daily operations (milk collection, sales, payments) but cannot manage system settings or users</li>
                    <li><strong>Data Ownership:</strong> Employees can only edit/delete records they created, ensuring accountability</li>
                    <li><strong>Security:</strong> Role hierarchy is enforced at both database and application levels</li>
                    <li><strong>Best Practice:</strong> Regularly review user activities and deactivate accounts when employees leave</li>
                    <li><strong>Walk-in Customers:</strong> Employees can add new customers for immediate sales transactions</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Permission Comparison -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Quick Permission Comparison</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>👑 Admin</th>
                            <th>👤 Employee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>User Management</strong></td>
                            <td><span class="badge badge-success">Full Access</span></td>
                            <td><span class="badge badge-danger">No Access</span></td>
                        </tr>
                        <tr>
                            <td><strong>Cattle Management</strong></td>
                            <td><span class="badge badge-success">Add/Edit/Delete</span></td>
                            <td><span class="badge badge-warning">View Only</span></td>
                        </tr>
                        <tr>
                            <td><strong>Milk Collection</strong></td>
                            <td><span class="badge badge-success">Full Access</span></td>
                            <td><span class="badge badge-success">Add/View Own</span></td>
                        </tr>
                        <tr>
                            <td><strong>Customer Management</strong></td>
                            <td><span class="badge badge-success">Full Access</span></td>
                            <td><span class="badge badge-warning">Add Walk-ins Only</span></td>
                        </tr>
                        <tr>
                            <td><strong>Sales Recording</strong></td>
                            <td><span class="badge badge-success">All Sales</span></td>
                            <td><span class="badge badge-success">Own Sales Only</span></td>
                        </tr>
                        <tr>
                            <td><strong>Payment Processing</strong></td>
                            <td><span class="badge badge-success">All Payments</span></td>
                            <td><span class="badge badge-success">Own Payments Only</span></td>
                        </tr>
                        <tr>
                            <td><strong>Inventory Management</strong></td>
                            <td><span class="badge badge-success">Full Access</span></td>
                            <td><span class="badge badge-warning">View & Record Usage</span></td>
                        </tr>
                        <tr>
                            <td><strong>Financial Reports</strong></td>
                            <td><span class="badge badge-success">All Reports</span></td>
                            <td><span class="badge badge-warning">Limited Reports</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../../includes/footer.php';
?>