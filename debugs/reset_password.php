<?php
/**
 * Password Reset Script
 * WARNING: DELETE THIS FILE AFTER USE!
 */

require_once 'backend/config/config.php';

echo "<h1>🔑 Password Reset Tool</h1>";

try {
    $conn = getDBConnection();
    
    // Check if admin exists
    $result = $conn->query("SELECT admin_id, username FROM admin_users WHERE username = 'admin'");
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "✅ Admin user found: " . $user['username'] . "<br>";
        
        // Generate new password hash
        $newPassword = 'Admin@123';
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'admin'");
        $stmt->bind_param("s", $newHash);
        
        if ($stmt->execute()) {
            echo "✅ Password updated successfully!<br><br>";
            echo "<div style='background: #d1fae5; padding: 20px; border-radius: 12px;'>";
            echo "<strong style='color: #065f46;'>Username:</strong> admin<br>";
            echo "<strong style='color: #065f46;'>Password:</strong> Admin@123<br>";
            echo "</div>";
            
            // Show the hash for debugging
            echo "<br><strong>New Hash:</strong> " . $newHash . "<br>";
        } else {
            echo "❌ Failed to update: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Admin user NOT found!<br>";
        echo "Creating admin user...<br>";
        
        $username = 'admin';
        $password_hash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $full_name = 'System Administrator';
        $email = 'admin@tapandgo.com';
        $role = 'administrator';
        $is_active = 1;
        
        $stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $username, $password_hash, $full_name, $email, $role, $is_active);
        
        if ($stmt->execute()) {
            echo "✅ Admin user created!<br><br>";
            echo "<div style='background: #d1fae5; padding: 20px; border-radius: 12px;'>";
            echo "<strong style='color: #065f46;'>Username:</strong> admin<br>";
            echo "<strong style='color: #065f46;'>Password:</strong> Admin@123<br>";
            echo "</div>";
        } else {
            echo "❌ Failed to create: " . $conn->error . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='frontend/pages/login.php' class='btn btn-primary'>Go to Login</a>";
echo "<br><br><strong style='color: red;'>⚠️ DELETE THIS FILE AFTER USE!</strong>";
?>