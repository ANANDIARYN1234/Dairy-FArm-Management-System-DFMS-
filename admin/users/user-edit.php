<?php
// admin/users/user-edit.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Edit User";
$errors = [];
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    $_SESSION['error_message'] = "Invalid user ID";
    header("Location: user-list.php");
    exit();
}

// Fetch user details
$sql = "SELECT u.*, r.role_name FROM user u 
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

// Prevent editing Admin role
$is_admin = ($user['role_name'] === 'Admin');
$current_user_id = get_user_id();

// Prevent admin from editing their own role
if ($is_admin && $user_id == $current_user_id) {
    $can_edit_role = false;
} else {
    $can_edit_role = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $status = $_POST['status'];
    $change_password = isset($_POST['change_password']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Security: Prevent changing Admin user's role
    if ($is_admin) {
        $role_id = $user['role_id']; // Keep existing role
    } else {
        // For employees, always set to Employee role
        $employee_role_sql = "SELECT role_id FROM role WHERE role_name = 'Employee' LIMIT 1";
        $employee_role = $conn->query($employee_role_sql)->fetch_assoc();
        $role_id = $employee_role['role_id'];
    }

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

    // Password validation if changing
    if ($change_password) {
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }

        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }

    // Update database
    if (empty($errors)) {
        if ($change_password) {
            $hashed_password = hash_password($new_password);
            $update_sql = "UPDATE user 
                          SET full_name = ?, email = ?, password = ?, contact = ?, 
                              role_id = ?, status = ?
                          WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssisi", $full_name, $email, $hashed_password, 
                                     $contact, $role_id, $status, $user_id);
        } else {
            $update_sql = "UPDATE user 
                          SET full_name = ?, email = ?, contact = ?, 
                              role_id = ?, status = ?
                          WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssisi", $full_name, $email, $contact, 
                                     $role_id, $status, $user_id);
        }

        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "User updated successfully!";
            header("Location: user-list.php");
            exit();
        } else {
            $errors[] = "Failed to update user: " . $conn->error;
        }
        $update_stmt->close();
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>✏️ Edit <?php echo $is_admin ? 'Administrator' : 'Employee'; ?></h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="user-list.php">Users</a>
                <span>/</span>
                <span>Edit User</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="user-view.php?id=<?php echo $user_id; ?>" class="btn btn-info">👁 View Details</a>
            <a href="user-list.php" class="btn btn-secondary">← Back to List</a>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- Admin Warning -->
    <div class="alert alert-warning">
        <span class="alert-icon">⚠</span>
        <div class="alert-message">
            <strong>Administrator Account:</strong> This is the system administrator. The role cannot be changed for security purposes.
        </div>
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

    <!-- Edit User Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>👤 User Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="userForm">
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
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact" class="form-control" 
                                   placeholder="Enter contact number"
                                   value="<?php echo htmlspecialchars($user['contact'] ?? ''); ?>">
                        </div>

                        <!-- Role (Fixed) -->
                        <div class="form-group">
                            <label class="form-label required">Role</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo $is_admin ? '👑 Administrator' : '👤 Employee'; ?>" 
                                   readonly style="background: var(--bg-tertiary); cursor: not-allowed;">
                            <small class="form-hint">Role is fixed for security</small>
                        </div>

                        <!-- Status -->
                        <div class="form-group">
                            <label class="form-label required">Status</label>
                            <select name="status" class="form-control" required 
                                    <?php echo ($is_admin && $user_id == $current_user_id) ? 'disabled' : ''; ?>>
                                <option value="Active" <?php echo $user['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $user['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <?php if ($is_admin && $user_id == $current_user_id): ?>
                                <small class="form-hint">You cannot deactivate your own account</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Change Password Section -->
                    <div style="margin-top: 2rem; padding: 1.5rem; background: var(--bg-tertiary); border-radius: 8px;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="change_password" id="changePasswordCheck" 
                                       style="width: 18px; height: 18px;">
                                <strong>Change Password</strong>
                            </label>
                        </div>

                        <div id="passwordFields" style="display: none;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" 
                                           placeholder="Enter new password" minlength="6" id="newPassword">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" 
                                           placeholder="Confirm new password" minlength="6" id="confirmPassword">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Box -->
                    <div class="alert alert-info" style="margin-top: 1.5rem;">
                        <span class="alert-icon">ℹ</span>
                        <div class="alert-message">
                            <strong>Note:</strong>
                            <ul style="margin: 0.5rem 0 0 1.5rem;">
                                <li>Setting status to Inactive will prevent user login</li>
                                <li>Password changes will require the user to login again</li>
                                <?php if ($is_admin): ?>
                                <li>Administrator role cannot be changed</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <a href="user-list.php" class="btn btn-secondary">❌ Cancel</a>
                        <button type="submit" class="btn btn-primary">💾 Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password fields
document.getElementById('changePasswordCheck').addEventListener('change', function() {
    const passwordFields = document.getElementById('passwordFields');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    if (this.checked) {
        passwordFields.style.display = 'block';
        newPassword.required = true;
        confirmPassword.required = true;
    } else {
        passwordFields.style.display = 'none';
        newPassword.required = false;
        confirmPassword.required = false;
        newPassword.value = '';
        confirmPassword.value = '';
    }
});

// Form validation
document.getElementById('userForm').addEventListener('submit', function(e) {
    const changePassword = document.getElementById('changePasswordCheck').checked;
    
    if (changePassword) {
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            return false;
        }
    }
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>