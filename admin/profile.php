<?php
// admin/profile.php
session_start();
define('DFMS_EXEC', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "My Profile";
$user_id = get_user_id();
$errors = [];
$success = [];

// Fetch user details
$sql = "SELECT u.*, r.role_name 
        FROM user u 
        JOIN role r ON u.role_id = r.role_id 
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);

    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!is_valid_email($email)) {
        $errors[] = "Invalid email format";
    }

    if (!empty($contact) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $contact)) {
        $errors[] = "Invalid contact number format";
    }

    // Check if email already exists (excluding current user)
    if (empty($errors)) {
        $check_sql = "SELECT user_id FROM user WHERE email = ? AND user_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $check_stmt->close();
    }

    // Update profile
    if (empty($errors)) {
        $update_sql = "UPDATE user SET full_name = ?, email = ?, contact = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $full_name, $email, $contact, $user_id);

        if ($update_stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $success[] = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $errors[] = "Failed to update profile";
        }
        $update_stmt->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }

    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }

    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }

    // Verify current password
    if (empty($errors)) {
        $pass_sql = "SELECT password FROM user WHERE user_id = ?";
        $pass_stmt = $conn->prepare($pass_sql);
        $pass_stmt->bind_param("i", $user_id);
        $pass_stmt->execute();
        $stored_password = $pass_stmt->get_result()->fetch_assoc()['password'];
        $pass_stmt->close();

        if (!verify_password($current_password, $stored_password)) {
            $errors[] = "Current password is incorrect";
        }
    }

    // Update password
    if (empty($errors)) {
        $hashed_password = hash_password($new_password);
        $pass_update_sql = "UPDATE user SET password = ? WHERE user_id = ?";
        $pass_update_stmt = $conn->prepare($pass_update_sql);
        $pass_update_stmt->bind_param("si", $hashed_password, $user_id);

        if ($pass_update_stmt->execute()) {
            $success[] = "Password changed successfully!";
        } else {
            $errors[] = "Failed to change password";
        }
        $pass_update_stmt->close();
    }
}

// Get system statistics for admin
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM user WHERE role_id = 2) as total_employees,
                (SELECT COUNT(*) FROM customer WHERE status = 'Active') as active_customers,
                (SELECT COUNT(*) FROM cattle WHERE life_status IN ('Alive', 'Pregnant')) as active_cattle,
                (SELECT COUNT(*) FROM sales WHERE sales_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as monthly_sales";
$stats = $conn->query($stats_sql)->fetch_assoc();

include '../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👤 Administrator Profile</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>My Profile</span>
            </div>
        </div>
    </div>

    <!-- Success Messages -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <div class="alert-message">
                <strong>Success!</strong>
                <ul>
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <div class="alert-message">
                <strong>Error!</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <div class="customer-details">
        <!-- Profile Information -->
        <div class="card">
            <div class="card-header" style="background: var(--accent-blue); color: white;">
                <h3>📋 Profile Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="profileForm">
                    <div class="form-grid">
                        <!-- Full Name -->
                        <div class="form-group">
                            <label class="form-label required">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   placeholder="Enter full name" required
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label required">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="Enter email address" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>

                        <!-- Contact -->
                        <div class="form-group full-width">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact" class="form-control" 
                                   placeholder="Enter contact number"
                                   value="<?php echo htmlspecialchars($user['contact'] ?? ''); ?>">
                            <small class="form-hint">Optional</small>
                        </div>
                    </div>

                    <!-- Read-only fields -->
                    <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div>
                                <label style="color: var(--text-medium); font-size: 0.9rem;">Role</label>
                                <div style="font-weight: 600; margin-top: 0.25rem;">
                                    <span class="badge badge-warning">👑 <?php echo $user['role_name']; ?></span>
                                </div>
                            </div>
                            <div>
                                <label style="color: var(--text-medium); font-size: 0.9rem;">Status</label>
                                <div style="font-weight: 600; margin-top: 0.25rem;">
                                    <?php if ($user['status'] === 'Active'): ?>
                                        <span class="badge badge-success">✓ Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">✕ Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label style="color: var(--text-medium); font-size: 0.9rem;">Member Since</label>
                                <div style="font-weight: 600; margin-top: 0.25rem;">
                                    <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <label style="color: var(--text-medium); font-size: 0.9rem;">Last Updated</label>
                                <div style="font-weight: 600; margin-top: 0.25rem;">
                                    <?php echo date('d M Y', strtotime($user['updated_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            💾 Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header" style="background: var(--warning); color: white;">
                <h3>🔐 Change Password</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="passwordForm">
                    <div class="form-grid">
                        <!-- Current Password -->
                        <div class="form-group full-width">
                            <label class="form-label required">Current Password</label>
                            <input type="password" name="current_password" class="form-control" 
                                   placeholder="Enter current password" required>
                        </div>

                        <!-- New Password -->
                        <div class="form-group">
                            <label class="form-label required">New Password</label>
                            <input type="password" name="new_password" class="form-control" 
                                   placeholder="Enter new password" required minlength="6" id="newPassword">
                            <small class="form-hint">Minimum 6 characters</small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label class="form-label required">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm new password" required minlength="6" id="confirmPassword">
                        </div>
                    </div>

                    <!-- Password Guidelines -->
                    <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>🔒 Password Security:</strong>
                        <ul>
                            <li>Use a strong password with at least 6 characters</li>
                            <li>Combine uppercase, lowercase, numbers, and symbols</li>
                            <li>Avoid using personal information or common words</li>
                            <li>Never share your administrator password</li>
                            <li>Change password regularly for enhanced security</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" name="change_password" class="btn btn-warning">
                            🔐 Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- System Overview (Admin Specific) -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>📊 System Overview</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">👥</div>
                    <div class="stat-details">
                        <span class="stat-label">Employees</span>
                        <span class="stat-value"><?php echo $stats['total_employees']; ?></span>
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon">👤</div>
                    <div class="stat-details">
                        <span class="stat-label">Active Customers</span>
                        <span class="stat-value"><?php echo $stats['active_customers']; ?></span>
                    </div>
                </div>
                
                <div class="stat-card stat-info">
                    <div class="stat-icon">🐄</div>
                    <div class="stat-details">
                        <span class="stat-label">Active Cattle</span>
                        <span class="stat-value"><?php echo $stats['active_cattle']; ?></span>
                    </div>
                </div>
                
                <div class="stat-card stat-warning">
                    <div class="stat-icon">📈</div>
                    <div class="stat-details">
                        <span class="stat-label">Monthly Sales</span>
                        <span class="stat-value"><?php echo $stats['monthly_sales']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Admin Access & Permissions -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>👑 Administrator Access & Permissions</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="info-box">
                <strong>Your Administrator Privileges:</strong>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                    <div>
                        <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">👥 User Management</h6>
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>Create, edit, and delete users</li>
                            <li>Manage employee accounts</li>
                            <li>Control user access levels</li>
                            <li>View user activity</li>
                        </ul>
                    </div>

                    <div>
                        <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">💼 Business Operations</h6>
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>Manage customers</li>
                            <li>Manage sales</li>
                            <li>Process payments</li>
                            <li>Oversee daily operations</li>
                        </ul>
                    </div>

                    <div>
                        <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">🐄 Farm Management</h6>
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>Manage cattle</li>
                            <li>Manage inventory items</li>
                            <li>Track milk production</li>
                        </ul>
                    </div>

                    <div>
                        <h6 style="color: var(--accent-blue); margin-bottom: 0.5rem;">📦 Inventory & Reports</h6>
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>Manage inventory items</li>
                            <li>Track stock levels</li>
                            <li>Generate all reports</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Quick Links -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>⚡ Quick Links</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="users/user-list.php" class="btn btn-primary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">👥</div>
                    <strong>Manage Users</strong>
                </a>
                
                <a href="customers/customer-list.php" class="btn btn-success" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">👤</div>
                    <strong>View Customers</strong>
                </a>
                
                <a href="sales/sales-list.php" class="btn btn-warning" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛒</div>
                    <strong>Sales Records</strong>
                </a>
                
                <a href="reports/reports-dashboard.php" class="btn btn-info" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                    <strong>View Reports</strong>
                </a>
            </div>
        </div>
    </div>
</div> -->

<script>
// Password form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    if (confirm('Are you sure you want to change your password?')) {
        return true;
    } else {
        e.preventDefault();
        return false;
    }
});

// Profile form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const fullName = document.querySelector('input[name="full_name"]').value.trim();
    const email = document.querySelector('input[name="email"]').value.trim();
    
    if (fullName === '') {
        e.preventDefault();
        alert('Full name is required');
        return false;
    }
    
    if (email === '') {
        e.preventDefault();
        alert('Email is required');
        return false;
    }
});
</script>

<?php
$conn->close();
include '../includes/footer.php';
?>