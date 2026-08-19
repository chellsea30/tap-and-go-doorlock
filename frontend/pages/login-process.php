<?php
/**
 * Tap-and-Go Doorlock - Login Processor
 * UPDATED FOR RAILWAY - WITH DEBUGGING
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
    
    // Debug: Log the login attempt
    error_log("Login attempt - Username: $username, Role: $role");
    
    switch ($role) {
        
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
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['user_id'] = $row['admin_id'];
                    
                    updateLastLogin($row['admin_id']);
                    logAudit($row['admin_id'], 'Login', 'Admin logged in');
                    
                    // Try multiple paths
                    $redirect_paths = [
                        '/frontend/pages/dashboard.php',
                        '/frontend/dashboard.php',
                        '/dashboard.php'
                    ];
                    
                    foreach ($redirect_paths as $path) {
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
                            error_log("Admin redirecting to: " . $path);
                            header('Location: ' . $path);
                            exit();
                        }
                    }
                    
                    // Fallback
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
                    $_SESSION['user_type'] = 'staff';
                    $_SESSION['user_id'] = $row['staff_id'];
                    
                    logStaffAudit($row['staff_id'], 'Login', 'Staff logged in');
                    
                    // Try multiple paths
                    $redirect_paths = [
                        '/frontend/pages/staff/dashboard.php',
                        '/frontend/staff/dashboard.php',
                        '/frontend/pages/dashboard.php'
                    ];
                    
                    foreach ($redirect_paths as $path) {
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
                            error_log("Staff redirecting to: " . $path);
                            header('Location: ' . $path);
                            exit();
                        }
                    }
                    
                    header('Location: /frontend/pages/staff/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Staff account not found.';
            }
            $stmt->close();
            break;
        
        case 'student':
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
                    $_SESSION['user_type'] = 'student';
                    $_SESSION['user_id'] = $row['student_id'];
                    
                    logStudentAudit($row['student_id'], 'Login', 'Student logged in');
                    
                    // Try multiple paths
                    $redirect_paths = [
                        '/frontend/pages/student/dashboard.php',
                        '/frontend/student/dashboard.php',
                        '/frontend/pages/dashboard.php'
                    ];
                    
                    foreach ($redirect_paths as $path) {
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
                            error_log("Student redirecting to: " . $path);
                            header('Location: ' . $path);
                            exit();
                        }
                    }
                    
                    header('Location: /frontend/pages/student/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Student account not found.';
            }
            $stmt->close();
            break;
        
        default:
            $error = 'Invalid role selected.';
            break;
    }
    
} catch (Exception $e) {
    $error = 'Login error: ' . $e->getMessage();
    error_log("Login exception: " . $e->getMessage());
}

// If error, redirect back to login
if (!empty($error)) {
    $redirect_page = '/frontend/pages/login.php';
    if ($role === 'staff') {
        $redirect_page = '/frontend/pages/staff/login.php';
    } elseif ($role === 'student') {
        $redirect_page = '/frontend/pages/student/login.php';
    }
    
    header('Location: ' . $redirect_page . '?error=' . urlencode($error));
    exit();
}
?>
