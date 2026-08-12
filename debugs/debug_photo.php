<?php
echo "<h1>Photo Debug</h1>";

require_once 'backend/config/config.php';

try {
    $conn = getDBConnection();
    
    // Check if photo column exists
    $result = $conn->query("SHOW COLUMNS FROM resident_profiles LIKE 'photo'");
    if ($result->num_rows > 0) {
        echo "✅ Photo column exists<br><br>";
    } else {
        echo "❌ Photo column does NOT exist!<br>";
        echo "Run: ALTER TABLE resident_profiles ADD COLUMN photo VARCHAR(255);<br><br>";
    }
    
    // Check if any photos are saved
    $result = $conn->query("SELECT user_id, photo FROM resident_profiles WHERE photo IS NOT NULL AND photo != ''");
    if ($result && $result->num_rows > 0) {
        echo "✅ Photos found in database:<br>";
        while ($row = $result->fetch_assoc()) {
            echo "User ID: " . $row['user_id'] . " - Photo: " . $row['photo'] . "<br>";
            
            // Check if file exists
            $base_path = dirname(__FILE__);
            $full_path = $base_path . '/' . $row['photo'];
            if (file_exists($full_path)) {
                echo "✅ File exists at: " . $full_path . "<br>";
            } else {
                echo "❌ File NOT found at: " . $full_path . "<br>";
                // Try alternative path
                $alt_path = $base_path . '/frontend/assets/uploads/residents/' . basename($row['photo']);
                if (file_exists($alt_path)) {
                    echo "✅ File exists at alternative path: " . $alt_path . "<br>";
                } else {
                    echo "❌ File NOT found at alternative path either<br>";
                }
            }
            echo "<hr>";
        }
    } else {
        echo "❌ No photos found in database!<br>";
    }
    
    // Check upload folder
    $upload_dir = 'frontend/assets/uploads/residents/';
    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);
        echo "<br>Files in upload folder:<br>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "- " . $file . "<br>";
            }
        }
    } else {
        echo "❌ Upload folder does not exist: " . $upload_dir . "<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>