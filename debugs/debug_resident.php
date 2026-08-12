<?php
echo "<h1>Resident Registration Debug</h1>";

require_once 'backend/config/config.php';

try {
    $conn = getDBConnection();
    echo "✅ Database connected<br><br>";
    
    // Check users table
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "✅ users table exists<br>";
        
        $result = $conn->query("SHOW COLUMNS FROM users");
        echo "Columns in users table:<br>";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
        }
    } else {
        echo "❌ users table does NOT exist!<br>";
    }
    
    echo "<br>";
    
    // Check resident_profiles table
    $result = $conn->query("SHOW TABLES LIKE 'resident_profiles'");
    if ($result->num_rows > 0) {
        echo "✅ resident_profiles table exists<br>";
        
        $result = $conn->query("SHOW COLUMNS FROM resident_profiles");
        echo "Columns in resident_profiles table:<br>";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
        }
    } else {
        echo "❌ resident_profiles table does NOT exist!<br>";
        echo "Please create it using the SQL above.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>