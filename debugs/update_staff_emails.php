<?php
/**
 * Script to update staff emails with encryption
 * RUN ONCE AND DELETE
 */

require_once 'backend/config/config.php';
require_once 'backend/helpers/functions.php';

echo "<h1>Updating Staff Emails</h1>";

try {
    $conn = getDBConnection();
    
    // Get all staff
    $result = $conn->query("SELECT staff_id, full_name, email FROM staff_users");
    
    if ($result->num_rows > 0) {
        $updated = 0;
        while ($row = $result->fetch_assoc()) {
            if (empty($row['email'])) continue;
            
            $encryptedEmail = encryptEmail($row['email']);
            $emailHash = hashEmailForSearch($row['email']);
            
            $stmt = $conn->prepare("UPDATE staff_users SET email = ?, email_hash = ? WHERE staff_id = ?");
            $stmt->bind_param("ssi", $encryptedEmail, $emailHash, $row['staff_id']);
            
            if ($stmt->execute()) {
                $updated++;
                echo "<p>✅ Updated: " . $row['full_name'] . " (" . $row['email'] . ")</p>";
            }
            $stmt->close();
        }
        echo "<p style='color:green;'><strong>✅ Updated $updated staff records.</strong></p>";
    } else {
        echo "<p>No staff found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='frontend/pages/login.php' class='btn btn-primary'>Go to Login</a>";
echo "<br><br><strong style='color:red;'>⚠️ DELETE THIS FILE AFTER USE!</strong>";
?>