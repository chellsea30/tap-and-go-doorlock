<?php
echo "<h1>Debug Resident Profiles</h1>";

require_once 'backend/config/config.php';

try {
    $conn = getDBConnection();
    
    // Check users
    echo "<h2>Users Table</h2>";
    $result = $conn->query("SELECT user_id, full_name, student_id FROM users LIMIT 5");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['user_id'] . " - Name: " . $row['full_name'] . " - Student: " . $row['student_id'] . "<br>";
            
            // Check if profile exists
            $stmt = $conn->prepare("SELECT * FROM resident_profiles WHERE user_id = ?");
            $stmt->bind_param("i", $row['user_id']);
            $stmt->execute();
            $profResult = $stmt->get_result();
            if ($profResult->num_rows > 0) {
                $prof = $profResult->fetch_assoc();
                echo "✅ Profile found! Course: " . ($prof['course'] ?? 'N/A') . "<br>";
                echo "<pre>";
                print_r($prof);
                echo "</pre>";
            } else {
                echo "❌ No profile found for user ID: " . $row['user_id'] . "<br>";
            }
            $stmt->close();
            echo "<hr>";
        }
    } else {
        echo "No users found.<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>