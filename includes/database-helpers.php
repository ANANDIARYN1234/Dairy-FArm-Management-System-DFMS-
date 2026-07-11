<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Database Helper Functions - Common Queries
 * =========================================================
 */

defined('DFMS_EXEC') or die('Access Denied');

/**
 * ==========================================================
 * DASHBOARD STATISTICS
 * ==========================================================
 */

/**
 * Get total cattle count
 */
function get_total_cattle($life_status = 'Alive') {
    global $conn;

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM cattle 
        WHERE life_status = ?
    ");
    $stmt->bind_param("s", $life_status);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Get pregnant cattle count
 */
function get_pregnant_cattle_count() {
    global $conn;

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM cattle
        WHERE life_status = 'Alive'
          AND is_pregnant = 1
    ");

    return $result->fetch_assoc()['total'];
}



/**
 * Get today's milk production
 */
function get_today_milk_production() {
    global $conn;
    
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total FROM milk_collection WHERE collection_date = '{$today}'");
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Get total customers
 */
function get_total_customers($status = 'Active') {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM customer WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['total'];
}

/**
 * Get today's sales total
 */
function get_today_sales_total() {
    global $conn;
    
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE sales_date = '{$today}'");
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Get pending payments total
 */
function get_pending_payments() {
    global $conn;
    
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE sales_status IN ('Due', 'Partial')");
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Get low stock items count
 */
function get_low_stock_count() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM inventory WHERE current_quantity <= minimum_quantity");
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * ==========================================================
 * CATTLE QUERIES
 * ==========================================================
 */

/**
 * Get all cattle with details
 */
function get_all_cattle($limit = null) {
    global $conn;
    
    $sql = "SELECT c.*, ct.type_name, b.breed_name, u.full_name as added_by 
            FROM cattle c
            JOIN cattle_type ct ON c.type_id = ct.type_id
            JOIN breed b ON c.breed_id = b.breed_id
            JOIN user u ON c.user_id = u.user_id
            ORDER BY c.created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT {$limit}";
    }
    
    $result = $conn->query($sql);
    $cattle = [];
    
    while ($row = $result->fetch_assoc()) {
        $cattle[] = $row;
    }
    
    return $cattle;
}

/**
 * Get cattle by ID
 */
function get_cattle_by_id($cattle_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT c.*, ct.type_name, b.breed_name, u.full_name as added_by,
               parent.tag_id as parent_tag
        FROM cattle c
        JOIN cattle_type ct ON c.type_id = ct.type_id
        JOIN breed b ON c.breed_id = b.breed_id
        JOIN user u ON c.user_id = u.user_id
        LEFT JOIN cattle parent ON c.parent_id = parent.cattle_id
        WHERE c.cattle_id = ?
    ");
    
    $stmt->bind_param("i", $cattle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Get breeds by cattle type
 */
function get_breeds_by_type($type_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT breed_id, breed_name FROM breed WHERE type_id = ? ORDER BY breed_name");
    $stmt->bind_param("i", $type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $breeds = [];
    while ($row = $result->fetch_assoc()) {
        $breeds[] = $row;
    }
    
    return $breeds;
}

/**
 * ==========================================================
 * MILK COLLECTION QUERIES
 * ==========================================================
 */

/**
 * Get recent milk collections
 */
function get_recent_milk_collections($limit = 10) {
    global $conn;
    
    $sql = "SELECT mc.*, c.tag_id, ct.type_name, u.full_name as collected_by
            FROM milk_collection mc
            JOIN cattle c ON mc.cattle_id = c.cattle_id
            JOIN cattle_type ct ON c.type_id = ct.type_id
            JOIN user u ON mc.user_id = u.user_id
            ORDER BY mc.collection_date DESC, mc.shift DESC
            LIMIT {$limit}";
    
    $result = $conn->query($sql);
    $collections = [];
    
    while ($row = $result->fetch_assoc()) {
        $collections[] = $row;
    }
    
    return $collections;
}

/**
 * Get milk collection by cattle
 */
function get_milk_by_cattle($cattle_id, $limit = null) {
    global $conn;
    
    $sql = "SELECT * FROM milk_collection WHERE cattle_id = ? ORDER BY collection_date DESC, shift DESC";
    
    if ($limit) {
        $sql .= " LIMIT {$limit}";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cattle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $collections = [];
    while ($row = $result->fetch_assoc()) {
        $collections[] = $row;
    }
    
    return $collections;
}

/**
 * ==========================================================
 * CUSTOMER QUERIES
 * ==========================================================
 */

/**
 * Get all customers
 */
function get_all_customers($status = null) {
    global $conn;
    
    $sql = "SELECT * FROM customer";
    
    if ($status) {
        $sql .= " WHERE status = '{$status}'";
    }
    
    $sql .= " ORDER BY customer_name ASC";
    
    $result = $conn->query($sql);
    $customers = [];
    
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    return $customers;
}

/**
 * Get customer by ID
 */
function get_customer_by_id($customer_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM customer WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * ==========================================================
 * SALES QUERIES
 * ==========================================================
 */

/**
 * Get recent sales
 */
function get_recent_sales($limit = 10) {
    global $conn;
    
    $sql = "SELECT s.*, c.customer_name, u.full_name as recorded_by
            FROM sales s
            JOIN customer c ON s.customer_id = c.customer_id
            JOIN user u ON s.user_id = u.user_id
            ORDER BY s.sales_date DESC, s.created_at DESC
            LIMIT {$limit}";
    
    $result = $conn->query($sql);
    $sales = [];
    
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    
    return $sales;
}

/**
 * Get sale by ID with details
 */
function get_sale_by_id($sales_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT s.*, c.customer_name, c.phone, u.full_name as recorded_by
        FROM sales s
        JOIN customer c ON s.customer_id = c.customer_id
        JOIN user u ON s.user_id = u.user_id
        WHERE s.sales_id = ?
    ");
    
    $stmt->bind_param("i", $sales_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * ==========================================================
 * INVENTORY QUERIES
 * ==========================================================
 */

/**
 * Get all inventory items
 */
function get_all_inventory($category = null) {
    global $conn;
    
    $sql = "SELECT * FROM inventory";
    
    if ($category) {
        $sql .= " WHERE category = '{$category}'";
    }
    
    $sql .= " ORDER BY item_name ASC";
    
    $result = $conn->query($sql);
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return $items;
}

/**
 * Get low stock items
 */
function get_low_stock_items() {
    global $conn;
    
    $result = $conn->query("SELECT * FROM low_stock_inventory ORDER BY shortage DESC");
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return $items;
}

/**
 * Get inventory item by ID
 */
function get_inventory_by_id($inventory_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE inventory_id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * ==========================================================
 * USER QUERIES
 * ==========================================================
 */

/**
 * Get all users
 */
function get_all_users($role_name = null) {
    global $conn;
    
    $sql = "SELECT u.*, r.role_name 
            FROM user u
            JOIN role r ON u.role_id = r.role_id";
    
    if ($role_name) {
        $sql .= " WHERE r.role_name = '{$role_name}'";
    }
    
    $sql .= " ORDER BY u.full_name ASC";
    
    $result = $conn->query($sql);
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Get user by ID
 */
function get_user_by_id($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT u.*, r.role_name 
        FROM user u
        JOIN role r ON u.role_id = r.role_id
        WHERE u.user_id = ?
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * ==========================================================
 * ACTIVITY LOG
 * ==========================================================
 */

/**
 * Log activity
 */
function log_activity($action, $details, $table_name = null, $record_id = null) {
    global $conn;
    
    $user_id = get_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("
        INSERT INTO activity_log (user_id, action, details, table_name, record_id, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("isssss", $user_id, $action, $details, $table_name, $record_id, $ip_address);
    $stmt->execute();
}
?>