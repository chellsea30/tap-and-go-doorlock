<?php
// check_staff.php - I-save sa root
require_once 'backend/config/config.php';

$conn = getDBConnection();

echo "<h1>Staff Table Check</h1>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'staff_users'");
if ($result && $result->num_rows > 0) {
    echo "✅ staff_users table exists<br><br>";
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
        is_active TINYINT(1) DEFAULT 1,
        math_attempts INT DEFAULT 0,
        math_blocked_until DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    </pre>";
    exit();
}

// Show staff users
$result = $conn->query("SELECT staff_id, full_name, email, is_active FROM staff_users");
if ($result && $result->num_rows > 0) {
    echo "<h2>Staff Users:</h2>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Active</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['staff_id']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>" . ($row['is_active'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No staff users found!<br>";
    echo "Add a staff user using the form above or SQL.<br>";
}

echo "<br><a href='frontend/pages/add-staff.php' class='btn btn-primary'>Add Staff</a>";
echo " | <a href='frontend/pages/staff-info.php' class='btn btn-secondary'>Staff Info</a>";
?>