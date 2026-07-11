<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Authentication - Enhanced with Employee Permissions
 * =========================================================
 */

defined('DFMS_EXEC') or die('Access Denied');

/**
 * Check authentication - redirect to login if not authenticated
 */
function checkAuth() {
    if (!is_logged_in()) {
        $_SESSION['error_message'] = "Please login to access this page";
        redirect(SITE_URL . "login.php");
    }
    check_session_timeout();
}

/**
 * Check user role - redirect to 403 if not authorized
 * @param array $allowed_roles - Array of allowed role names
 */
function checkRole($allowed_roles = []) {
    if (!is_logged_in()) {
        $_SESSION['error_message'] = "Please login to access this page";
        redirect(SITE_URL . "login.php");
    }
    
    if (!empty($allowed_roles) && !in_array($_SESSION['role_name'], $allowed_roles)) {
        redirect(SITE_URL . "403.php");
    }
}

/**
 * Check session timeout
 */
function check_session_timeout() {
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        
        if ($inactive > SESSION_TIMEOUT) {
            logout_user();
        }
        
        $_SESSION['last_activity'] = time();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Login user with email, password and role
 */
// function login_user($email, $password, $role_name) {
//     global $conn;
    
//     $email = trim($email);
//     $role_name = trim($role_name);
    
//     // Prepare statement
//     $stmt = $conn->prepare("
//         SELECT u.user_id, u.full_name, u.email, u.password, u.status, 
//                r.role_id, r.role_name 
//         FROM user u 
//         JOIN role r ON u.role_id = r.role_id 
//         WHERE u.email = ? AND r.role_name = ?
//         LIMIT 1
//     ");
    
//     $stmt->bind_param("ss", $email, $role_name);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     if ($result->num_rows === 1) {
//         $user = $result->fetch_assoc();
        
//         // Check if account is active
//         if ($user['status'] !== 'Active') {
//             return [
//                 'success' => false,
//                 'message' => 'Your account has been deactivated.'
//             ];
//         }
        
//         // Verify password
//         if (verify_password($password, $user['password'])) {
//             // Set session
//             $_SESSION['user_id'] = $user['user_id'];
//             $_SESSION['full_name'] = $user['full_name'];
//             $_SESSION['email'] = $user['email'];
//             $_SESSION['role_id'] = $user['role_id'];
//             $_SESSION['role_name'] = $user['role_name'];
//             $_SESSION['login_time'] = time();
//             $_SESSION['last_activity'] = time();
            
//             return [
//                 'success' => true,
//                 'message' => 'Login successful!',
//                 'role' => $user['role_name']
//             ];
//         } else {
//             return [
//                 'success' => false,
//                 'message' => 'Invalid credentials.'
//             ];
//         }
//     } else {
//         return [
//             'success' => false,
//             'message' => 'Invalid credentials or role mismatch.'
//         ];
//     }
// }
// login with role-as, username and password  ended here....

/**
 * Login user with email and password
 */
function login_user($email, $password) {
    global $conn;
    
    $email = trim($email);
    
    // Prepare statement
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.email, u.password, u.status, 
               r.role_id, r.role_name 
        FROM user u 
        JOIN role r ON u.role_id = r.role_id 
        WHERE u.email = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] !== 'Active') {
            return [
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ];
        }
        
        // Verify password
        if (verify_password($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            return [
                'success' => true,
                'message' => 'Login successful!',
                'role' => $user['role_name']
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid credentials.'
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'Invalid credentials.'
        ];
    }
}

/**
 * Logout user
 */
function logout_user() {
    session_unset();
    session_destroy();
    redirect(SITE_URL . "login.php?logout=1");
}

/**
 * Require login (Alternative to checkAuth)
 */
function require_login() {
    if (!is_logged_in()) {
        redirect(SITE_URL . "login.php?error=login_required");
    }
    check_session_timeout();
}

/**
 * Require admin
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        redirect(SITE_URL . "403.php");
    }
}

/**
 * Require employee (or admin)
 */
function require_employee() {
    require_login();
    if (!is_employee() && !is_admin()) {
        redirect(SITE_URL . "403.php");
    }
}

/**
 * Create employee account (Admin only)
 */
function create_employee($full_name, $email, $password, $contact) {
    global $conn;
    
    // Server-side validation
    if (empty($full_name) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'All fields are required.'];
    }
    
    if (!is_valid_email($email)) {
        return ['success' => false, 'message' => 'Invalid email format.'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }
    
    // Check if email exists
    $check = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Email already exists.'];
    }
    
    // Get Employee role_id
    $role_result = $conn->query("SELECT role_id FROM role WHERE role_name = 'Employee'");
    $role_id = $role_result->fetch_assoc()['role_id'];
    
    // Hash password
    $hashed = hash_password($password);
    
    // Insert employee
    $stmt = $conn->prepare("
        INSERT INTO user (full_name, email, password, contact, role_id, status) 
        VALUES (?, ?, ?, ?, ?, 'Active')
    ");
    
    $stmt->bind_param("ssssi", $full_name, $email, $hashed, $contact, $role_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Employee created successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to create employee.'];
    }
}

/**
 * Update employee
 */
function update_employee($user_id, $full_name, $email, $contact, $status) {
    global $conn;
    
    if (empty($full_name) || empty($email)) {
        return ['success' => false, 'message' => 'Name and email are required.'];
    }
    
    if (!is_valid_email($email)) {
        return ['success' => false, 'message' => 'Invalid email format.'];
    }
    
    // Check if email exists for other users
    $check = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Email already used by another user.'];
    }
    
    $stmt = $conn->prepare("
        UPDATE user 
        SET full_name = ?, email = ?, contact = ?, status = ?
        WHERE user_id = ?
    ");
    
    $stmt->bind_param("ssssi", $full_name, $email, $contact, $status, $user_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Employee updated successfully!'];
    } else {
        return ['success' => false, 'message' => 'Failed to update employee.'];
    }
}

/**
 * Change password
 */
function change_password($user_id, $old_password, $new_password) {
    global $conn;
    
    if (strlen($new_password) < 6) {
        return ['success' => false, 'message' => 'New password must be at least 6 characters.'];
    }
    
    // Get current password
    $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (verify_password($old_password, $user['password'])) {
            $new_hashed = hash_password($new_password);
            
            $update = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            $update->bind_param("si", $new_hashed, $user_id);
            
            if ($update->execute()) {
                return ['success' => true, 'message' => 'Password changed successfully!'];
            }
        } else {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
    }
    
    return ['success' => false, 'message' => 'Failed to change password.'];
}

// =========================================================
// 🆕 EMPLOYEE PERMISSION FUNCTIONS
// =========================================================

/**
 * Check if user can add customers
 * Both Admin and Employee can add customers (walk-ins)
 */
function can_add_customer() {
    return is_admin() || is_employee();
}

/**
 * Check if user can edit customers
 * Only Admin can edit customers
 */
function can_edit_customer() {
    return is_admin();
}

/**
 * Check if user can delete customers
 * Only Admin can delete customers
 */
function can_delete_customer() {
    return is_admin();
}

/**
 * Check if user can add sales
 * Both Admin and Employee can create sales
 */
function can_add_sale() {
    return is_admin() || is_employee();
}

/**
 * Check if user can edit sales
 * Only Admin can edit sales
 */
function can_edit_sale() {
    return is_admin();
}

/**
 * Check if user can delete sales
 * Only Admin can delete sales
 */
function can_delete_sale() {
    return is_admin();
}

/**
 * Check if user can record payments
 * Both Admin and Employee can record payments
 */
function can_record_payment() {
    return is_admin() || is_employee();
}

/**
 * Check if user can view all sales
 * Admin can view all, Employee only own sales
 */
function can_view_all_sales() {
    return is_admin();
}

/**
 * Get price by customer type
 * Retail = रु80, Wholesale = रु75, Dairy = रु70
 */
function get_price_by_customer_type($customer_type) {
    $prices = [
        'Retail' => 80.00,
        'Wholesale' => 75.00,
        'Dairy' => 70.00
    ];
    
    return $prices[$customer_type] ?? 80.00; // Default to Retail price
}

/**
 * Get customer type label with icon
 */
function get_customer_type_badge($customer_type) {
    $badges = [
        'Retail' => '<span class="badge badge-info">🛒 Retail</span>',
        'Wholesale' => '<span class="badge badge-primary">📦 Wholesale</span>',
        'Dairy' => '<span class="badge badge-success">🏭 Dairy</span>'
    ];
    
    return $badges[$customer_type] ?? '<span class="badge badge-secondary">' . $customer_type . '</span>';
}
?>