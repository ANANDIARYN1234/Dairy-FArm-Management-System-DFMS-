<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Database Configuration - Clean & Simple
 * =========================================================
 */

// Prevent direct access
defined('DFMS_EXEC') or die('Access Denied');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dfms_db');

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// Site Configuration
define('SITE_NAME', 'Dairy Farm Management System');
define('SITE_URL', 'http://localhost/dfms_db/');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// Error Reporting (Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Set MySQL timezone to match PHP timezone
    $conn->query("SET time_zone = '+05:45'");
    
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

/**
 * Helper Functions
 */

// Sanitize input
function clean($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

// Redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check if logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if admin
function is_admin() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin';
}

// Check if employee
function is_employee() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Employee';
}

// Get current user ID
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user name
function get_user_name() {
    return $_SESSION['full_name'] ?? 'Guest';
}

// Format date
function format_date($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

// Format currency
function format_currency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

// Validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Display flash messages (Add this to your config.php)
function get_flash_message() {
    $output = '';
    
    if (isset($_SESSION['success_message'])) {
        $output .= '<div class="alert alert-success">
                        <span class="alert-icon">✓</span>
                        <span class="alert-message">' . htmlspecialchars($_SESSION['success_message']) . '</span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>';
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['error_message'])) {
        $output .= '<div class="alert alert-error">
                        <span class="alert-icon">✕</span>
                        <span class="alert-message">' . htmlspecialchars($_SESSION['error_message']) . '</span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>';
        unset($_SESSION['error_message']);
    }
    
    if (isset($_SESSION['warning_message'])) {
        $output .= '<div class="alert alert-warning">
                        <span class="alert-icon">⚠</span>
                        <span class="alert-message">' . htmlspecialchars($_SESSION['warning_message']) . '</span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>';
        unset($_SESSION['warning_message']);
    }
    
    if (isset($_SESSION['info_message'])) {
        $output .= '<div class="alert alert-info">
                        <span class="alert-icon">ℹ</span>
                        <span class="alert-message">' . htmlspecialchars($_SESSION['info_message']) . '</span>
                        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                    </div>';
        unset($_SESSION['info_message']);
    }
    
    return $output;
}

// Generate alert HTML
function show_alert($message, $type = 'info') {
    $icons = [
        'success' => '✓',
        'error' => '✕',
        'warning' => '⚠',
        'info' => 'ℹ'
    ];
    $icon = $icons[$type] ?? 'ℹ';
    
    return "<div class='alert alert-{$type}'>
                <span class='alert-icon'>{$icon}</span>
                <span class='alert-message'>{$message}</span>
            </div>";
}
?>