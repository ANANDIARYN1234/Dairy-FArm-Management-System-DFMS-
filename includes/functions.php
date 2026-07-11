<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Helper Functions
 * =========================================================
 */

defined('DFMS_EXEC') or die('Access Denied');

/**
 * ==========================================================
 * DATE & TIME FUNCTIONS
 * ==========================================================
 */

/**
 * Get current date in Nepal timezone
 */
function get_current_date($format = 'Y-m-d') {
    return date($format);
}

/**
 * Get current time
 */
function get_current_time($format = 'H:i:s') {
    return date($format);
}

/**
 * Get current datetime
 */
function get_current_datetime($format = 'Y-m-d H:i:s') {
    return date($format);
}

/**
 * Format date for display
 */
function display_date($date, $format = 'd M Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function display_datetime($datetime, $format = 'd M Y h:i A') {
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Calculate age from date of birth
 */
function calculate_age($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

/**
 * Get time ago (e.g., "5 minutes ago")
 */
function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('d M Y', $time);
    }
}

/**
 * ==========================================================
 * NUMBER & CURRENCY FUNCTIONS
 * ==========================================================
 */

/**
 * Format number with decimals
 */
function format_number($number, $decimals = 2) {
    return number_format($number, $decimals);
}

/**
 * Format quantity (remove unnecessary decimals)
 */
function format_quantity($quantity) {
    $quantity = floatval($quantity);
    return $quantity == floor($quantity) ? number_format($quantity, 0) : number_format($quantity, 2);
}

/**
 * Format percentage
 */
function format_percentage($value, $decimals = 1) {
    return number_format($value, $decimals) . '%';
}

/**
 * ==========================================================
 * STRING FUNCTIONS
 * ==========================================================
 */

/**
 * Truncate string with ellipsis
 */
function truncate_text($text, $length = 50, $ellipsis = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $ellipsis;
}

/**
 * Generate slug from string
 */
function generate_slug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Sanitize filename
 */
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

/**
 * ==========================================================
 * STATUS & BADGE FUNCTIONS
 * ==========================================================
 */

/**
 * Get status badge HTML
 */
/**
 * Get status badge with pregnancy support
 * Pass both life_status and is_pregnant
 */
function get_status_badge($life_status, $is_pregnant = 0) {
    // If alive and pregnant, show as Pregnant
    if ($life_status === 'Alive' && $is_pregnant == 1) {
        return '<span class="badge badge-warning">🤰 Pregnant</span>';
    }
    
    // Otherwise, show regular status
    switch ($life_status) {
        case 'Alive':
            return '<span class="badge badge-success">✓ Alive</span>';
        case 'Sold':
            return '<span class="badge badge-info">🪙 Sold</span>';
        case 'Dead':
            return '<span class="badge badge-danger">💔 Dead</span>';
        default:
            return '<span class="badge badge-secondary">' . htmlspecialchars($life_status) . '</span>';
    }
}

/**
 * Get role badge
 */
function get_role_badge($role) {
    $badges = [
        'Admin' => '<span class="badge badge-primary">Admin</span>',
        'Employee' => '<span class="badge badge-info">Employee</span>',
    ];
    
    return $badges[$role] ?? '<span class="badge badge-secondary">' . htmlspecialchars($role) . '</span>';
}

/**
 * ==========================================================
 * DATABASE QUERY HELPERS
 * ==========================================================
 */

/**
 * Get single record
 */
function get_record($table, $id_column, $id_value) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM {$table} WHERE {$id_column} = ? LIMIT 1");
    $stmt->bind_param("i", $id_value);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Get all records
 */
function get_all_records($table, $order_by = null) {
    global $conn;
    
    $sql = "SELECT * FROM {$table}";
    if ($order_by) {
        $sql .= " ORDER BY {$order_by}";
    }
    
    $result = $conn->query($sql);
    $records = [];
    
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    return $records;
}

/**
 * Count records
 */
function count_records($table, $where = null) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as total FROM {$table}";
    if ($where) {
        $sql .= " WHERE {$where}";
    }
    
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Delete record
 */
function delete_record($table, $id_column, $id_value) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$id_column} = ?");
    $stmt->bind_param("i", $id_value);
    
    return $stmt->execute();
}

/**
 * ==========================================================
 * DROPDOWN/SELECT HELPERS
 * ==========================================================
 */

/**
 * Generate dropdown options
 */
function generate_options($data, $value_field, $text_field, $selected = null) {
    $html = '';
    
    foreach ($data as $item) {
        $value = $item[$value_field];
        $text = $item[$text_field];
        $is_selected = ($value == $selected) ? 'selected' : '';
        
        $html .= "<option value=\"{$value}\" {$is_selected}>{$text}</option>";
    }
    
    return $html;
}

/**
 * Get cattle types dropdown
 */
function get_cattle_types_dropdown($selected = null) {
    global $conn;
    
    $result = $conn->query("SELECT type_id, type_name FROM cattle_type ORDER BY type_name");
    $options = '<option value="">-- Select Type --</option>';
    
    while ($row = $result->fetch_assoc()) {
        $is_selected = ($row['type_id'] == $selected) ? 'selected' : '';
        $options .= "<option value=\"{$row['type_id']}\" {$is_selected}>{$row['type_name']}</option>";
    }
    
    return $options;
}

/**
 * Get roles dropdown
 */
function get_roles_dropdown($selected = null) {
    global $conn;
    
    $result = $conn->query("SELECT role_id, role_name FROM role ORDER BY role_name");
    $options = '<option value="">-- Select Role --</option>';
    
    while ($row = $result->fetch_assoc()) {
        $is_selected = ($row['role_id'] == $selected) ? 'selected' : '';
        $options .= "<option value=\"{$row['role_id']}\" {$is_selected}>{$row['role_name']}</option>";
    }
    
    return $options;
}

/**
 * ==========================================================
 * PAGINATION HELPERS
 * ==========================================================
 */

/**
 * Calculate pagination offset
 */
function get_pagination_offset($page, $per_page) {
    return ($page - 1) * $per_page;
}

/**
 * Generate pagination HTML
 */
function generate_pagination($total_records, $per_page, $current_page, $url) {
    $total_pages = ceil($total_records / $per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $html .= "<a href=\"{$url}?page={$prev_page}\" class=\"page-link\">← Previous</a>";
    }
    
    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $html .= "<a href=\"{$url}?page={$i}\" class=\"page-link {$active}\">{$i}</a>";
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $html .= "<a href=\"{$url}?page={$next_page}\" class=\"page-link\">Next →</a>";
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * ==========================================================
 * FILE UPLOAD HELPERS
 * ==========================================================
 */

/**
 * Handle file upload
 */
function handle_file_upload($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed'];
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $new_filename = uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $new_filename];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * ==========================================================
 * NOTIFICATION FUNCTIONS
 * ==========================================================
 */

/**
 * Set flash message
 */
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 */
// function get_flash_message() {
//     if (isset($_SESSION['flash_message'])) {
//         $message = $_SESSION['flash_message'];
//         $type = $_SESSION['flash_type'] ?? 'info';
        
//         unset($_SESSION['flash_message']);
//         unset($_SESSION['flash_type']);
        
//         return show_alert($message, $type);
//     }
    
//     return '';
// }

/**
 * ==========================================================
 * SECURITY HELPERS
 * ==========================================================
 */

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF field for forms
 */
function csrf_field() {
    $token = generate_csrf_token();
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}
?>

