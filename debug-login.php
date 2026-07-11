<!-- <?php
/**
 * =========================================================
 * DEBUG LOGIN - Find the Issue
 * Save this as: debug-login.php
 * Access: http://localhost/dfms/debug-login.php
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);
require_once 'includes/config.php';

echo "<h2>Debug Login Test</h2>";
echo "<hr>";

// Test 1: Check database connection
echo "<h3>1. Database Connection</h3>";
if ($conn->ping()) {
    echo "✅ Database connected successfully<br>";
} else {
    echo "❌ Database connection failed<br>";
    die();
}

// Test 2: Check if admin user exists
echo "<h3>2. Check Admin User</h3>";
$result = $conn->query("SELECT user_id, full_name, email, role_id, status FROM user WHERE email = 'admin@dfms.com'");

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "✅ Admin user found:<br>";
    echo "- User ID: " . $user['user_id'] . "<br>";
    echo "- Name: " . $user['full_name'] . "<br>";
    echo "- Email: " . $user['email'] . "<br>";
    echo "- Role ID: " . $user['role_id'] . "<br>";
    echo "- Status: " . $user['status'] . "<br>";
} else {
    echo "❌ Admin user not found in database<br>";
    echo "<br><strong>Solution: Run this SQL in phpMyAdmin:</strong><br><pre>";
    echo "INSERT INTO user (full_name, email, password, contact, role_id, status) 
VALUES (
    'System Administrator', 
    'admin@dfms.com', 
    '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    '9801234567', 
    1, 
    'Active'
);";
    echo "</pre>";
    die();
}

// Test 3: Check role
echo "<h3>3. Check Role</h3>";
$role_result = $conn->query("SELECT role_id, role_name FROM role WHERE role_id = " . $user['role_id']);

if ($role_result->num_rows > 0) {
    $role = $role_result->fetch_assoc();
    echo "✅ Role found:<br>";
    echo "- Role ID: " . $role['role_id'] . "<br>";
    echo "- Role Name: " . $role['role_name'] . "<br>";
} else {
    echo "❌ Role not found<br>";
}

// Test 4: Check password hash
echo "<h3>4. Check Password Hash</h3>";
$hash_result = $conn->query("SELECT password FROM user WHERE email = 'admin@dfms.com'");
$stored_hash = $hash_result->fetch_assoc()['password'];

echo "Stored hash: " . $stored_hash . "<br>";
echo "Hash length: " . strlen($stored_hash) . " characters<br>";

// Test 5: Verify password
echo "<h3>5. Test Password Verification</h3>";
$test_password = 'admin123';
echo "Testing password: <strong>{$test_password}</strong><br>";

if (password_verify($test_password, $stored_hash)) {
    echo "✅ Password verification SUCCESSFUL<br>";
} else {
    echo "❌ Password verification FAILED<br>";
    echo "<br><strong>Creating new hash for 'admin123':</strong><br>";
    $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "<pre>New hash: " . $new_hash . "</pre>";
    echo "<br><strong>Update your database with this SQL:</strong><br><pre>";
    echo "UPDATE user SET password = '{$new_hash}' WHERE email = 'admin@dfms.com';";
    echo "</pre>";
}

// Test 6: Full login simulation
echo "<h3>6. Simulate Full Login</h3>";

$email = 'admin@dfms.com';
$password = 'admin123';
$role_name = 'Admin';

$stmt = $conn->prepare("
    SELECT u.user_id, u.full_name, u.email, u.password, u.status, 
           r.role_id, r.role_name 
    FROM user u 
    JOIN role r ON u.role_id = r.role_id 
    WHERE u.email = ? AND r.role_name = ?
    LIMIT 1
");

$stmt->bind_param("ss", $email, $role_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "✅ User query successful<br>";
    
    if ($user['status'] !== 'Active') {
        echo "❌ Account is not active<br>";
    } else {
        echo "✅ Account is active<br>";
        
        if (password_verify($password, $user['password'])) {
            echo "✅ PASSWORD MATCH! Login should work!<br>";
            echo "<br><strong style='color: green;'>ALL TESTS PASSED! ✓</strong><br>";
            echo "<a href='login.php'>Go to Login Page</a>";
        } else {
            echo "❌ PASSWORD MISMATCH<br>";
        }
    }
} else {
    echo "❌ No user found with email '{$email}' and role '{$role_name}'<br>";
    echo "Check if:<br>";
    echo "1. Email is correct<br>";
    echo "2. Role name matches exactly (case-sensitive)<br>";
    echo "3. User is linked to correct role_id<br>";
}

echo "<hr>";
echo "<h3>Quick Fix Commands</h3>";
echo "<strong>If tests failed, run these in phpMyAdmin:</strong><br><br>";

// Generate a fresh password hash
$fresh_hash = password_hash('admin123', PASSWORD_DEFAULT);

echo "<pre>";
echo "-- Delete existing admin (if any)
DELETE FROM user WHERE email = 'admin@dfms.com';

-- Create fresh admin account
INSERT INTO user (full_name, email, password, contact, role_id, status) 
VALUES (
    'System Administrator', 
    'admin@dfms.com', 
    '{$fresh_hash}', 
    '9801234567', 
    1, 
    'Active'
);
";
echo "</pre>";

echo "<br><strong>After running the above SQL, login with:</strong><br>";
echo "- Role: Admin<br>";
echo "- Email: admin@dfms.com<br>";
echo "- Password: admin123<br>";
?> -->