<?php
echo "<h1>Residents Database Debug</h1>";

require_once 'backend/config/config.php';

try {
    $conn = getDBConnection();
    
    // Check users table
    echo "<h2>Users Table</h2>";
    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10");
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Student ID</th><th>Room</th><th>Status</th><th>Created</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['full_name'] . "</td>";
            echo "<td>" . $row['student_id'] . "</td>";
            echo "<td>" . $row['room_number'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "Total users: " . $result->num_rows . "<br>";
    } else {
        echo "❌ No users found in database!<br>";
        echo "Error: " . ($conn->error ?? 'No error') . "<br>";
    }
    
    // Check resident_profiles table
    echo "<h2>Resident Profiles Table</h2>";
    $result = $conn->query("SELECT * FROM resident_profiles ORDER BY created_at DESC LIMIT 10");
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Profile ID</th><th>User ID</th><th>Course</th><th>Year Level</th><th>Gender</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['profile_id'] . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['course'] . "</td>";
            echo "<td>" . $row['year_level'] . "</td>";
            echo "<td>" . $row['gender'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "Total profiles: " . $result->num_rows . "<br>";
    } else {
        echo "❌ No profiles found in database!<br>";
        echo "Error: " . ($conn->error ?? 'No error') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='frontend/pages/residents.php'>Go to Residents List</a>";