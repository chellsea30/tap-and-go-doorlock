<?php
echo "<h1>Database Connection Test</h1>";

// Load config
require_once 'backend/config/config.php';

try {
    $conn = getDBConnection();
    echo "✅ Database connection successful!<br>";
    echo "Server: " . $conn->server_info . "<br>";
    echo "Database: " . DB_NAME . "<br>";
    
    // Check if admin user exists
    $result = $conn->query("SELECT * FROM admin_users WHERE username = 'admin'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "✅ Admin user found: " . $user['username'] . "<br>";
        echo "Password hash: " . $user['password_hash'] . "<br>";
    } else {
        echo "❌ Admin user NOT found!<br>";
        echo "Creating admin user...<br>";
        
        // Create admin user
        $password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $username = 'admin';
        $full_name = 'System Administrator';
        $email = 'admin@tapandgo.com';
        $role = 'administrator';
        $is_active = 1;
        $stmt->bind_param("sssssi", $username, $password_hash, $full_name, $email, $role, $is_active);
        
        if ($stmt->execute()) {
            echo "✅ Admin user created!<br>";
            echo "Username: admin<br>";
            echo "Password: Admin@123<br>";
        } else {
            echo "❌ Failed to create admin: " . $conn->error . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='frontend/pages/login.php'>Go to Login Page</a>";
?>