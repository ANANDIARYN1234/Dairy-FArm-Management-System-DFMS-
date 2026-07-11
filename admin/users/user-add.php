<?php
// admin/users/user-add.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Add Employee";
$errors = [];

// Fetch Employee role only
$roles_sql = "SELECT * FROM role WHERE role_name = 'Employee' ORDER BY role_name";
$roles = $conn->query($roles_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $status = $_POST['status'];

    // Force Employee role (security measure)
    $employee_role_sql = "SELECT role_id FROM role WHERE role_name = 'Employee' LIMIT 1";
    $employee_role = $conn->query($employee_role_sql)->fetch_assoc();
    $role_id = $employee_role['role_id'];

    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!is_valid_email($email)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (!empty($contact) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $contact)) {
        $errors[] = "Invalid contact number format";
    }

    // Check if email already exists
    if (empty($errors)) {
        $check_sql = "SELECT user_id FROM user WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $check_stmt->close();
    }

    // Insert into database
    if (empty($errors)) {
        $hashed_password = hash_password($password);
        
        $sql = "INSERT INTO user (full_name, email, password, contact, role_id, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssis", $full_name, $email, $hashed_password, $contact, $role_id, $status);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Employee added successfully!";
            header("Location: user-list.php");
            exit();
        } else {
            $errors[] = "Failed to add employee: " . $conn->error;
        }
        $stmt->close();
    }
}

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>➕ Add New Employee</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="user-list.php">Users</a>
                <span>/</span>
                <span>Add Employee</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="user-list.php" class="btn btn-secondary">← Back to List</a>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info">
        <span class="alert-icon">ℹ</span>
        <div class="alert-message">
            <strong>Note:</strong> You can only add employees. The system is configured with a single administrator for security purposes.
        </div>
    </div>

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

    <!-- Add Employee Form -->
    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h3>👤 Employee Information</h3>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <form method="POST" action="" id="userForm">
                    <div class="form-grid">
                        <!-- Full Name -->
                        <div class="form-group">
                            <label class="form-label required">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   placeholder="Enter full name" required
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label required">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="Enter email address" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <!-- Contact -->
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact" class="form-control" 
                                   placeholder="Enter contact number"
                                   value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
                            <small class="form-hint">Optional</small>
                        </div>

                        <!-- Role (Fixed as Employee) -->
                        <div class="form-group">
                            <label class="form-label required">Role</label>
                            <input type="text" class="form-control" value="👤 Employee" readonly 
                                   style="background: var(--bg-tertiary); cursor: not-allowed;">
                            <small class="form-hint">Fixed role for security</small>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label class="form-label required">Password</label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter password" required minlength="6"
                                   id="password">
                            <small class="form-hint">Minimum 6 characters</small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label class="form-label required">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm password" required minlength="6"
                                   id="confirm_password">
                        </div>

                        <!-- Status -->
                        <div class="form-group">
                            <label class="form-label required">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <div class="info-box" style="margin-top: 1.5rem;">
                        <strong>ℹ Employee Access:</strong>
                        <ul>
                            <li>✓ View cattle list (read-only)</li>
                            <li>✓ Add and view milk collection records</li>
                            <li>✓ View inventory and record usage</li>
                            <li>✓ View assigned reports</li>
                            <li>✕ No access to user management</li>
                            <li>✕ No access to customer or sales management</li>
                            <li>✕ No access to financial reports</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <button type="submit" class="btn btn-primary">💾 Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password match validation
document.getElementById('userForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});

// Show password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    let strength = 'Weak';
    let color = '#e74c3c';
    
    if (password.length >= 8) {
        strength = 'Medium';
        color = '#f39c12';
    }
    
    if (password.length >= 12 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
        strength = 'Strong';
        color = '#27ae60';
    }
    
    // Visual feedback (optional enhancement)
    this.style.borderColor = color;
});
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>