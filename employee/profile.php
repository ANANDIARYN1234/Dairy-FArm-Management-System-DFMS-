<?php
// employee/profile.php
session_start();
define('DFMS_EXEC', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['Employee']);

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

// Get activity statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ?) as total_collections,
                (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ?) as total_milk";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("ii", $user_id, $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

include '../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👤 My Profile</h1>
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
                                    <span class="badge badge-info">👤 <?php echo $user['role_name']; ?></span>
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
                        <strong>🔒 Password Guidelines:</strong>
                        <ul>
                            <li>Password must be at least 6 characters long</li>
                            <li>Use a combination of letters, numbers, and symbols</li>
                            <li>Avoid using personal information</li>
                            <li>Don't share your password with anyone</li>
                            <li>Change your password regularly for security</li>
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

    <!-- Activity Statistics -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Total Collections</span>
                <span class="stat-value"><?php echo $stats['total_collections']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Milk Collected</span>
                <span class="stat-value"><?php echo number_format($stats['total_milk'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📈</div>
            <div class="stat-details">
                <span class="stat-label">Average per Collection</span>
                <span class="stat-value">
                    <?php 
                    $avg = $stats['total_collections'] > 0 ? $stats['total_milk'] / $stats['total_collections'] : 0;
                    echo number_format($avg, 2); 
                    ?> L
                </span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⭐</div>
            <div class="stat-details">
                <span class="stat-label">Your Performance</span>
                <span class="stat-value" style="font-size: 1.2rem;">
                    <?php
                    if ($stats['total_collections'] > 100) echo "Excellent";
                    elseif ($stats['total_collections'] > 50) echo "Very Good";
                    elseif ($stats['total_collections'] > 20) echo "Good";
                    else echo "Getting Started";
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header">
            <h3>⚡ Quick Links</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="milk/milk-add.php" class="btn btn-success" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🥛</div>
                    <strong>Add Milk Collection</strong>
                </a>
                
                <a href="milk/my-collections.php" class="btn btn-info" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                    <strong>My Collections</strong>
                </a>
                
                <a href="reports/my-milk-records.php" class="btn btn-primary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                    <strong>My Reports</strong>
                </a>
                
                <a href="inventory/inventory-list.php" class="btn btn-warning" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📦</div>
                    <strong>View Inventory</strong>
                </a>
            </div>
        </div>
    </div>
</div>

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