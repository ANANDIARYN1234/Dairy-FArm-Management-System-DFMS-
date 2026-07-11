<?php
/**
 * =========================================================
 * API: Get Breeds by Type
 * Returns breeds for a specific cattle type
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get type_id from request
$type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;

if ($type_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid type ID']);
    exit;
}

// Get breeds for this type
$stmt = $conn->prepare("SELECT breed_id, breed_name FROM breed WHERE type_id = ? ORDER BY breed_name ASC");
$stmt->bind_param("i", $type_id);
$stmt->execute();
$result = $stmt->get_result();

$breeds = [];
while ($row = $result->fetch_assoc()) {
    $breeds[] = $row;
}

echo json_encode([
    'success' => true,
    'breeds' => $breeds
]);
?>