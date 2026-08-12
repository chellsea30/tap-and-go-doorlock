<?php
/**
 * Update Admin Password
 * Location: /frontend/admin/update_password.php
 */

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

$conn = getDBConnection();

$email = 'albanochellsea30@gmail.com';
$new_password = 'Albano200430@@';

// Hash the new password
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed, $email);

if ($stmt->execute()) {
    echo "<h2 style='color: green;'>✅ Password Updated Successfully!</h2>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
    echo "<p><strong>New Password:</strong> " . htmlspecialchars($new_password) . "</p>";
    echo "<p><strong>Hash:</strong> " . $hashed . "</p>";
    echo "<br><a href='login.php' style='color: blue;'>Go to Login</a>";
} else {
    echo "<h2 style='color: red;'>❌ Failed to update password</h2>";
    echo "<p>Error: " . $stmt->error . "</p>";
}

$stmt->close();
?>