<?php
/**
 * Tap-and-Go Doorlock - Login Processor
 * Handles Admin, Staff, and Student Login
 * COMPLETE VERSION - UPDATED FOR RAILWAY
 */

session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

$error = '';
$role = isset($_POST['role']) ? $_POST['role'] : 'admin';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Check if username and password are provided
if (empty($username) || empty($password)) {
    header('Location: login.php?error=Please enter username and password');
    exit();
}

try {
    $conn = getDBConnection();
    
    // ============================================================
    // ROUTE BASED ON ROLE
    // ============================================================
    switch ($role) {
        
        // ============================================================
        // CASE 1: ADMIN LOGIN
        // ============================================================
        case 'admin':
            $stmt = $conn->prepare("SELECT admin_id, username, password_hash, full_name, role, is_active FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password_hash'])) {
                    $_SESSION['admin_id'] = $row['admin_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['user_type'] = 'admin';  // ADD THIS
                    
                    updateLastLogin($row['admin_id']);
                    logAudit($row['admin_id'], 'Login', 'Admin logged in from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
                    
                    // CHANGED: Use absolute path
                    header('Location: /frontend/pages/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Admin account not found.';
            }
            $stmt->close();
            break;
        
        // ============================================================
        // CASE 2: STAFF LOGIN
        // ============================================================
        case 'staff':
            $stmt = $conn->prepare("SELECT staff_id, staff_id_number, full_name, email, password_hash, department, is_active FROM staff_users WHERE (staff_id_number = ? OR email = ?) AND is_active = 1");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password_hash'])) {
                    $_SESSION['staff_id'] = $row['staff_id'];
                    $_SESSION['staff_id_number'] = $row['staff_id_number'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['department'] = $row['department'];
                    $_SESSION['role'] = 'staff';
                    $_SESSION['login_time'] = time();
                    $_SESSION['user_type'] = 'staff';  // ADD THIS
                    
                    logStaffAudit($row['staff_id'], 'Login', 'Staff logged in from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
                    
                    // CHANGED: Use absolute path
                    header('Location: /frontend/pages/staff/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Staff account not found. Please check your Staff ID.';
            }
            $stmt->close();
            break;
        
        // ============================================================
        // CASE 3: STUDENT LOGIN
        // ============================================================
        case 'student':
            // Students can login using student_id_number OR username
            $stmt = $conn->prepare("SELECT student_id, student_id_number, username, full_name, course, year_level, email, password_hash, room_number, is_active FROM student_users WHERE (student_id_number = ? OR username = ? OR email = ?) AND is_active = 1");
            $stmt->bind_param("sss", $username, $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password_hash'])) {
                    $_SESSION['student_id'] = $row['student_id'];
                    $_SESSION['student_id_number'] = $row['student_id_number'];
                    $_SESSION['username'] = $row['username'] ?? $row['student_id_number'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['course'] = $row['course'];
                    $_SESSION['year_level'] = $row['year_level'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['room_number'] = $row['room_number'];
                    $_SESSION['role'] = 'student';
                    $_SESSION['login_time'] = time();
                    $_SESSION['user_type'] = 'student';  // ADD THIS
                    
                    logStudentAudit($row['student_id'], 'Login', 'Student logged in from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
                    
                    // CHANGED: Use absolute path
                    header('Location: /frontend/pages/student/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Student account not found. Please check your Student ID or Username.';
            }
            $stmt->close();
            break;
        
        // ============================================================
        // DEFAULT: INVALID ROLE
        // ============================================================
        default:
            $error = 'Invalid role selected. Please choose Admin, Staff, or Student.';
            break;
    }
    
} catch (Exception $e) {
    $error = 'Login error: ' . $e->getMessage();
}

// ============================================================
// IF ERROR, REDIRECT BACK TO LOGIN WITH ERROR MESSAGE
// ============================================================
if (!empty($error)) {
    // Determine which login page to redirect to based on role
    $redirect_page = '/frontend/pages/login.php';  // CHANGED: Use absolute path
    if ($role === 'staff') {
        $redirect_page = '/frontend/pages/staff/login.php';
    } elseif ($role === 'student') {
        $redirect_page = '/frontend/pages/student/login.php';
    }
    
    header('Location: ' . $redirect_page . '?error=' . urlencode($error));
    exit();
}
?>
