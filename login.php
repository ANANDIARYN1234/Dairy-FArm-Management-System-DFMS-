<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Login Page - Professional UI
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'employee/dashboard.php');
}

// Handle login
$error = '';
$success = '';

// this is role as validation which is temporarily disabled so commented....
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $email = trim($_POST['email'] ?? '');
//     $password = $_POST['password'] ?? '';
//     // $role = $_POST['role'] ?? '';
    
//     // Server-side validation
//     if (empty($email) || empty($password) || empty($role)) {
//         $error = 'All fields are required.';
//     } elseif (!is_valid_email($email)) {
//         $error = 'Invalid email format.';
//     } elseif (!in_array($role, ['Admin', 'Employee'])) {
//         $error = 'Invalid role selected.';
//     } else {
//         $result = login_user($email, $password, $role);
        
//         if ($result['success']) {
//             redirect($result['role'] === 'Admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
//         } else {
//             $error = $result['message'];
//         }
//     }
// }
//  role as validation  ended here....


// Handle login without role as validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Server-side validation
    if (empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!is_valid_email($email)) {
        $error = 'Invalid email format.';
    } else {
        $result = login_user($email, $password);
        
        if ($result['success']) {
            redirect($result['role'] === 'Admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}

// Check URL parameters
if (isset($_GET['error']) && $_GET['error'] === 'login_required') {
    $error = 'Please login to access that page.';
}
if (isset($_GET['logout'])) {
    $success = 'Logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <!-- Left Side - Brand Section -->
        <div class="login-left">
            <div class="login-image-wrapper">
                <img src="assets/images/loginside.png" alt="Login Side Image" class="login-side-image">
            </div>
        </div>


        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-form-wrapper">
                <div class="form-header">
                    <h2>Welcome Back!</h2>
                    <p>Enter your credentials to access dashboard</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">✕</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✓</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate>
                    <!-- Role Selection -->
                    <!-- <div class="form-group">
                        <label for="role">Login As</label>
                        <select name="role" id="role" required>
                            <option value="">-- Select Role --</option>
                            <option value="Admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Admin') ? 'selected' : ''; ?>>Administrator</option>
                            <option value="Employee" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Employee') ? 'selected' : ''; ?>>Employee</option>
                        </select>
                        <span class="input-icon">👤</span>
                        <span class="error-msg" id="roleError"></span>
                    </div> -->

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            placeholder="your.email@example.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                            autocomplete="email"
                        >
                        <span class="input-icon">📧</span>
                        <span class="error-msg" id="emailError"></span>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <span class="input-icon">🔒</span>
                        <span class="toggle-password">👁️</span>
                        <span class="error-msg" id="passwordError"></span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-block">
                        Login to Dashboard
                    </button>
                </form>

                <div class="form-footer">
                    <a href="index.php" class="back-link">
                        <span>←</span>
                        <span>Back to Home</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            let isValid = true;
            clearErrors();
            
            // Role validation
            // const role = document.getElementById('role');
            // if (!role.value) {
            //     showError(role, 'Please select a role');
            //     isValid = false;
            // }
            
            // Email validation
            const email = document.getElementById('email');
            const emailValue = email.value.trim();
            if (!emailValue) {
                showError(email, 'Email is required');
                isValid = false;
            } else if (!validateEmail(emailValue)) {
                showError(email, 'Invalid email format');
                isValid = false;
            }
            
            // Password validation
            const password = document.getElementById('password');
            if (!password.value) {
                showError(password, 'Password is required');
                isValid = false;
            } else if (password.value.length < 6) {
                showError(password, 'Password must be at least 6 characters');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });


        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        document.querySelector('.toggle-password').addEventListener('click', togglePassword);


      

        // if (!isValid) {
        //     e.preventDefault();
        //     hideLoading(submitBtn);  // This resets the loader instantly
        //     return false;
        // }
    </script>
</body>
</html>