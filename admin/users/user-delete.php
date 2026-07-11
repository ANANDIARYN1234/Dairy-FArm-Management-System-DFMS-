<?php
// admin/users/user-delete.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user_id = get_user_id();

if ($user_id <= 0) {
    $_SESSION['error_message'] = "Invalid user ID";
    header("Location: user-list.php");
    exit();
}

// Prevent deleting own account
if ($user_id == $current_user_id) {
    $_SESSION['error_message'] = "You cannot delete your own account";
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

// Prevent deleting Admin users
if ($user['role_name'] === 'Admin') {
    $_SESSION['error_message'] = "Cannot delete administrator account for security purposes";
    header("Location: user-list.php");
    exit();
}

// Check if user has related records
$checks = [
    'sales' => "SELECT COUNT(*) as count FROM sales WHERE user_id = ?",
    'milk_collection' => "SELECT COUNT(*) as count FROM milk_collection WHERE user_id = ?",
    'payments' => "SELECT COUNT(*) as count FROM payment WHERE user_id = ?",
    'cattle' => "SELECT COUNT(*) as count FROM cattle WHERE user_id = ?"
];

$has_records = false;
$record_counts = [];

foreach ($checks as $table => $query) {
    $check_stmt = $conn->prepare($query);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $count = $check_stmt->get_result()->fetch_assoc()['count'];
    $record_counts[$table] = $count;
    if ($count > 0) {
        $has_records = true;
    }
    $check_stmt->close();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if ($has_records) {
        $_SESSION['error_message'] = "Cannot delete user with existing records";
        header("Location: user-list.php");
        exit();
    }

    $delete_sql = "DELETE FROM user WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);

    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Employee deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete employee: " . $conn->error;
    }
    $delete_stmt->close();
    
    header("Location: user-list.php");
    exit();
}

$page_title = "Delete Employee";
include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>🗑 Delete Employee</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="user-list.php">Users</a>
                <span>/</span>
                <span>Delete Employee</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="user-list.php" class="btn btn-secondary">← Back to List</a>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="form-container">
        <div class="card" style="border: 2px solid var(--danger);">
            <div class="card-header" style="background: var(--danger); color: white;">
                <h3>⚠ Confirm Deletion</h3>
            </div>
            <div class="card-body" style="padding: 2rem;">
                <?php if ($has_records): ?>
                    <!-- Cannot Delete - Has Records -->
                    <div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <div class="alert-message">
                            <strong>Cannot Delete Employee!</strong>
                            <p style="margin-top: 0.5rem;">
                                This employee has existing records in the system and cannot be deleted.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Employee Details:</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Role:</strong> <span class="badge badge-info">👤 Employee</span></p>
                    </div>

                    <div class="customer-info">
                        <h6>Associated Records:</h6>
                        <?php if ($record_counts['sales'] > 0): ?>
                            <p>📊 Sales Records: <strong><?php echo $record_counts['sales']; ?></strong></p>
                        <?php endif; ?>
                        <?php if ($record_counts['milk_collection'] > 0): ?>
                            <p>🥛 Milk Collections: <strong><?php echo $record_counts['milk_collection']; ?></strong></p>
                        <?php endif; ?>
                        <?php if ($record_counts['payments'] > 0): ?>
                            <p>💰 Payments: <strong><?php echo $record_counts['payments']; ?></strong></p>
                        <?php endif; ?>
                        <?php if ($record_counts['cattle'] > 0): ?>
                            <p>🐄 Cattle Records: <strong><?php echo $record_counts['cattle']; ?></strong></p>
                        <?php endif; ?>
                    </div>

                    <div class="info-box">
                        <strong>ℹ Options:</strong>
                        <ul>
                            <li>Set employee status to "Inactive" instead of deleting</li>
                            <li>This will prevent the employee from logging in</li>
                            <li>All historical records will be preserved</li>
                        </ul>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="user-edit.php?id=<?php echo $user_id; ?>" class="btn btn-warning">
                            ✏️ Edit Employee (Set Inactive)
                        </a>
                        <a href="user-list.php" class="btn btn-secondary">
                            ← Go Back
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Can Delete - No Records -->
                    <div class="alert alert-warning">
                        <span class="alert-icon">⚠</span>
                        <div class="alert-message">
                            <strong>Warning!</strong>
                            <p style="margin-top: 0.5rem;">
                                You are about to permanently delete this employee. This action cannot be undone.
                            </p>
                        </div>
                    </div>

                    <div class="customer-info">
                        <h6>Employee Details:</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($user['contact'] ?? 'N/A'); ?></p>
                        <p><strong>Role:</strong> <span class="badge badge-info">👤 Employee</span></p>
                        <p><strong>Status:</strong> 
                            <?php if ($user['status'] === 'Active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <form method="POST" action="" id="deleteForm">
                        <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; margin: 1.5rem 0;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="confirmCheck" required style="width: 18px; height: 18px;">
                                <span>I understand this action is permanent and cannot be undone</span>
                            </label>
                        </div>

                        <div style="display: flex; justify-content: center; gap: 1rem;">
                            <a href="user-list.php" class="btn btn-secondary">❌ Cancel</a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                🗑 Delete Employee
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Enable delete button only when checkbox is checked
const confirmCheck = document.getElementById('confirmCheck');
const deleteBtn = document.getElementById('deleteBtn');

if (confirmCheck && deleteBtn) {
    confirmCheck.addEventListener('change', function() {
        deleteBtn.disabled = !this.checked;
        if (this.checked) {
            deleteBtn.style.opacity = '1';
            deleteBtn.style.cursor = 'pointer';
        } else {
            deleteBtn.style.opacity = '0.5';
            deleteBtn.style.cursor = 'not-allowed';
        }
    });

    // Final confirmation before deletion
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        if (!confirm('Are you absolutely sure you want to delete this employee? This action cannot be undone!')) {
            e.preventDefault();
            return false;
        }
    });
}
</script>

<?php
$conn->close();
include '../../includes/footer.php';
?>