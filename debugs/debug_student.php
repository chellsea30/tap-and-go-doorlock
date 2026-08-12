<?php
echo "<h1>Student Database Debug</h1>";

require_once 'backend/config/config.php';

try {
    $conn = getDBConnection();
    
    // Check if student_users table exists
    $result = $conn->query("SHOW TABLES LIKE 'student_users'");
    if ($result->num_rows > 0) {
        echo "✅ student_users table exists<br><br>";
        
        // Show table structure
        echo "<h2>Table Structure</h2>";
        $result = $conn->query("DESCRIBE student_users");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Show all students
        echo "<h2>Students in Database</h2>";
        $result = $conn->query("SELECT * FROM student_users");
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Student ID</th><th>Username</th><th>Full Name</th><th>Course</th><th>Year</th><th>Email</th><th>Active</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['student_id'] . "</td>";
                echo "<td>" . $row['student_id_number'] . "</td>";
                echo "<td>" . $row['username'] . "</td>";
                echo "<td>" . $row['full_name'] . "</td>";
                echo "<td>" . $row['course'] . "</td>";
                echo "<td>" . $row['year_level'] . "</td>";
                echo "<td>" . $row['email'] . "</td>";
                echo "<td>" . ($row['is_active'] ? '✅' : '❌') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "❌ No students found in database!<br>";
            echo "Please insert sample data using the SQL above.<br>";
        }
        
    } else {
        echo "❌ student_users table does NOT exist!<br>";
        echo "Please run the CREATE TABLE SQL above.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='frontend/pages/student/login.php'>Go to Student Login</a>";
echo " | ";
echo "<a href='frontend/pages/student-registration.php'>Go to Student Registration</a>";
?>