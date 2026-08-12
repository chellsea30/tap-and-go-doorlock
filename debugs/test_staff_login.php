<?php
/**
 * Staff Login Debugger
 * I-save sa: tap-and-go-doorlock/test_staff_login.php
 */

echo "<h1>🔍 Staff Login Debugger</h1>";

require_once 'backend/config/config.php';
require_once 'backend/helpers/functions.php';

$conn = getDBConnection();

// ============================================================
// 1. CHECK STAFF TABLE
// ============================================================
echo "<h2>1. Staff Table Check</h2>";

$result = $conn->query("SHOW TABLES LIKE 'staff_users'");
if ($result && $result->num_rows > 0) {
    echo "✅ staff_users table exists<br>";
} else {
    echo "❌ staff_users table does NOT exist!<br>";
    echo "Run this SQL:<br>";
    echo "<pre>
    CREATE TABLE IF NOT EXISTS staff_users (
        staff_id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id_number VARCHAR(50) UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        email_hash VARCHAR(64) DEFAULT NULL,
        email_verified TINYINT(1) DEFAULT 0,
        password_hash VARCHAR(255) NOT NULL,
        otp_expiry DATETIME DEFAULT NULL,
        department VARCHAR(50) DEFAULT 'Dormitory Staff',
        phone VARCHAR(20) DEFAULT NULL,
        avatar VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        math_attempts INT DEFAULT 0,
        math_blocked_until DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    </pre>";
    exit();
}

// ============================================================
// 2. CHECK COLUMNS
// ============================================================
echo "<h2>2. Column Check</h2>";

$columns = ['staff_id', 'staff_id_number', 'full_name', 'email', 'email_hash', 'password_hash', 'is_active'];
$missing = [];

foreach ($columns as $col) {
    $result = $conn->query("SHOW COLUMNS FROM staff_users LIKE '$col'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Column '$col' exists<br>";
    } else {
        echo "❌ Column '$col' is MISSING!<br>";
        $missing[] = $col;
    }
}

if (!empty($missing)) {
    echo "<br>Add missing columns:<br>";
    echo "<pre>";
    foreach ($missing as $col) {
        switch ($col) {
            case 'email_hash':
                echo "ALTER TABLE staff_users ADD COLUMN email_hash VARCHAR(64) DEFAULT NULL;\n";
                break;
            case 'password_hash':
                echo "ALTER TABLE staff_users ADD COLUMN password_hash VARCHAR(255) NOT NULL;\n";
                break;
            case 'is_active':
                echo "ALTER TABLE staff_users ADD COLUMN is_active TINYINT(1) DEFAULT 1;\n";
                break;
        }
    }
    echo "</pre>";
}

// ============================================================
// 3. CHECK STAFF USERS
// ============================================================
echo "<h2>3. Staff Users</h2>";

$result = $conn->query("SELECT staff_id, staff_id_number, full_name, email, is_active FROM staff_users");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Staff ID</th><th>Name</th><th>Email</th><th>Active</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['staff_id']}</td>";
        echo "<td>{$row['staff_id_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>" . ($row['is_active'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No staff users found!<br>";
    echo "Add a staff user:<br>";
    echo "<pre>
    INSERT INTO staff_users (
        staff_id_number, 
        full_name, 
        email, 
        password_hash, 
        department, 
        is_active
    ) VALUES (
        'STAFF-001',
        'Mylene C. Samiling',
        'mylenesamiling@gmail.com',
        '\\$2y\\$10\\$2YftSmggwnbujsyUGBYDKeQnqmdDXY8ifPeRWNRE6740sy0InhFPu',
        'Dormitory Management',
        1
    );
    </pre>";
}

// ============================================================
// 4. TEST AUTHENTICATION
// ============================================================
echo "<h2>4. Test Authentication</h2>";

$testEmail = 'mylenesamiling@gmail.com';
$testPassword = 'Staff@123';

echo "Testing: Email = $testEmail, Password = $testPassword<br>";

$result = authenticateStaffByEmail($testEmail, $testPassword);

echo "<pre>";
print_r($result);
echo "</pre>";

if ($result['success']) {
    echo "✅ AUTHENTICATION SUCCESSFUL!<br>";
    echo "Staff ID: " . ($result['staff_id'] ?? 'N/A') . "<br>";
    echo "Name: " . ($result['full_name'] ?? 'N/A') . "<br>";
} else {
    echo "❌ AUTHENTICATION FAILED!<br>";
    echo "Error: " . ($result['message'] ?? 'Unknown error') . "<br>";
}

// ============================================================
// 5. CHECK SESSION
// ============================================================
echo "<h2>5. Session Check</h2>";

session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session variables:<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// ============================================================
// 6. FIX RECOMMENDATIONS
// ============================================================
echo "<h2>6. Fix Recommendations</h2>";

$issues = [];

// Check if staff_users table has data
$result = $conn->query("SELECT COUNT(*) as count FROM staff_users");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $issues[] = "No staff users found. Add a staff user using the SQL above.";
}

// Check if email_hash column exists and has data
$result = $conn->query("SHOW COLUMNS FROM staff_users LIKE 'email_hash'");
if ($result && $result->num_rows > 0) {
    // Check if any staff has email_hash
    $result = $conn->query("SELECT COUNT(*) as count FROM staff_users WHERE email_hash IS NOT NULL");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $issues[] = "Staff users exist but email_hash is empty. The authenticate function may fail.";
        $issues[] = "Run: UPDATE staff_users SET email_hash = SHA2(email, 256) WHERE email_hash IS NULL;";
    }
}

// Check if password_hash is set
$result = $conn->query("SELECT COUNT(*) as count FROM staff_users WHERE password_hash IS NULL OR password_hash = ''");
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    $issues[] = "Some staff users have no password hash. They need to reset their password.";
}

if (empty($issues)) {
    echo "✅ All checks passed! Staff login should work.<br>";
} else {
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
}

echo "<br><a href='frontend/pages/staff-info.php' class='btn btn-primary'>Go to Staff Info</a>";
echo " | <a href='frontend/pages/add-staff.php' class='btn btn-success'>Add Staff</a>";
echo " | <a href='login.php' class='btn btn-secondary'>Go to Login</a>";
?>