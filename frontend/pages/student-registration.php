<?php
/**
 * Tap-and-Go Doorlock - Student Portal Registration
 * DARK MODE - NO WHITE BACKGROUNDS
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 */

session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication (Admin only)
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php'; 

$conn = getDBConnection();
$error = '';
$success = '';
$password_success = '';
$studentData = [];
$residentData = null;

// ============================================================
// HELPER FUNCTION - SAFE ESCAPE (with type hints)
// ============================================================
function safe(string $value, string $default = ''): string {
    if ($value === null || $value === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// CREATE STUDENT USERS TABLE IF NOT EXISTS
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS student_users (
        student_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id_number VARCHAR(50) UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        course VARCHAR(50) NOT NULL,
        year_level VARCHAR(20) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        room_number VARCHAR(20),
        resident_id INT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student_id (student_id_number),
        INDEX idx_username (username),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ============================================================
// GET RESIDENT DATA FOR AUTO-FILL
// ============================================================
if (isset($_GET['resident_id']) && is_numeric($_GET['resident_id'])) {
    $resident_id = (int)$_GET['resident_id'];
    $stmt = $conn->prepare("
        SELECT u.*, rp.course, rp.year_level
        FROM users u
        LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
        WHERE u.user_id = ? AND u.status != 'deleted'
    ");
    $stmt->bind_param("i", $resident_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $residentData = $result->fetch_assoc();
    $stmt->close();
    
    if ($residentData) {
        $studentData = [
            'full_name' => $residentData['full_name'] ?? '',
            'course' => $residentData['course'] ?? '',
            'year_level' => $residentData['year_level'] ?? '',
            'phone' => $residentData['cp_no'] ?? $residentData['contact_number'] ?? '',
            'room_number' => $residentData['room_number'] ?? '',
            'email' => strtolower(str_replace(' ', '.', $residentData['full_name'] ?? 'student')) . '@isu.edu.ph',
            'student_id_number' => 'STU-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT)
        ];
    }
}

// ============================================================
// GET RESIDENTS LIST FOR DROPDOWN
// ============================================================
$residentsList = [];
$result = $conn->query("
    SELECT u.user_id, u.full_name, u.student_id, u.room_number, rp.course, rp.year_level
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status = 'active' 
    AND u.user_id NOT IN (SELECT resident_id FROM student_users WHERE resident_id IS NOT NULL)
    ORDER BY u.full_name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsList[] = $row;
    }
}

// ============================================================
// HANDLE REGISTRATION FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $resident_id = isset($_POST['resident_id']) ? (int)$_POST['resident_id'] : 0;
    $full_name = trim($_POST['full_name'] ?? '');
    $student_id_number = trim($_POST['student_id_number'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($full_name) || empty($student_id_number) || empty($username) || empty($course) || empty($year_level) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $check = $conn->prepare("SELECT student_id FROM student_users WHERE student_id_number = ? OR email = ? OR username = ?");
        $check->bind_param("sss", $student_id_number, $email, $username);
        $check->execute();
        $checkResult = $check->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = 'Student ID, Email, or Username already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO student_users (
                    student_id_number, full_name, username, course, year_level, email, 
                    password_hash, phone, room_number, resident_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssssssi", 
                $student_id_number, $full_name, $username, $course, $year_level, $email,
                $password_hash, $phone, $room_number, $resident_id
            );
            
            if ($stmt->execute()) {
                $student_id = $conn->insert_id;
                logAudit($_SESSION['admin_id'], 'Student Registration', "Registered student: $full_name ($student_id_number)");
                
                $success = "✅ Student registered successfully!<br><br>
                            <div class='alert alert-info'>
                                <strong>📋 Login Credentials</strong><br>
                                <strong>Student ID:</strong> " . safe($student_id_number) . "<br>
                                <strong>Username:</strong> " . safe($username) . "<br>
                                <strong>Password:</strong> " . safe($password) . "<br><br>
                                <small>📌 Student can login at the <a href='student/login.php' target='_blank'>Student Login Page</a></small>
                            </div>";
                
                $studentData = [];
                $_POST = array();
            } else {
                $error = "Failed to register student: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// HANDLE PASSWORD UPDATE - ADMIN ONLY
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password_admin'])) {
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($student_id)) {
        $error = 'Please select a student.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE student_users SET password_hash = ? WHERE student_id = ?");
        $stmt->bind_param("si", $new_hash, $student_id);
        
        if ($stmt->execute()) {
            logAudit($_SESSION['admin_id'], 'Student Password Update', "Updated password for student ID: $student_id");
            $password_success = "✅ Password updated successfully!";
        } else {
            $error = "Failed to update password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================================
// GET EXISTING STUDENTS
// ============================================================
$students = [];
$result = $conn->query("
    SELECT s.*, u.full_name as resident_name 
    FROM student_users s
    LEFT JOIN users u ON s.resident_id = u.user_id
    ORDER BY s.full_name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Registration - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           RESET & BASE
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e5e7eb !important;
        }
        
        /* ============================================================
           FIXED NAVBAR
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 56px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           SIDEBAR - FIXED POSITION
           ============================================================ */
        .sidebar {
            position: fixed !important;
            top: 56px !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 220px !important;
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            overflow-y: auto !important;
            z-index: 1040 !important;
            padding-top: 10px !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            margin: 2px 10px !important;
            font-size: 13px !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar .nav-link i {
            width: 18px;
            text-align: center;
        }
        .sidebar-footer { 
            border-top-color: #1a2a4a !important;
            padding: 12px 16px !important;
            margin-top: 10px !important;
        }
        .sidebar-footer .text-muted { color: #606070 !important; font-size: 11px !important; }
        
        /* ============================================================
           PAGE WRAPPER - FLEX LAYOUT
           ============================================================ */
        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .content-wrapper {
            display: flex;
            flex: 1;
        }
        
        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 15px 25px !important;
            flex: 1;
            min-height: calc(100vh - 56px - 50px) !important;
            background: #0a0e1a !important;
        }
        
        /* ============================================================
           FOOTER - STICKY BOTTOM
           ============================================================ */
        .footer {
            margin-left: 220px !important;
            padding: 10px 25px !important;
            background: #0d1528 !important;
            border-top: 1px solid #1a2a4a !important;
            color: #606070 !important;
            font-size: 12px !important;
            text-align: center !important;
            flex-shrink: 0;
            width: calc(100% - 220px) !important;
        }
        
        /* ============================================================
           FORM SECTION
           ============================================================ */
        .form-section {
            background: #131926 !important;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 18px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            border: 1px solid #1e2a3a;
        }
        
        .form-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 10px;
            margin-bottom: 18px;
            font-size: 16px;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 12px;
            color: #d1d5db !important;
        }
        
        .form-control,
        .form-select {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            height: 38px;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        
        .form-control::placeholder {
            color: #6b7280 !important;
        }
        
        .form-control[readonly] {
            background: #0a0e1a !important;
            color: #6b7280 !important;
            cursor: not-allowed;
        }
        
        .form-select option {
            background: #131926 !important;
            color: #e5e7eb !important;
        }
        
        .required {
            color: #ef4444 !important;
            margin-left: 2px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            color: #0a0e1a !important;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3) !important;
            color: #0a0e1a !important;
        }
        
        .btn-submit i {
            margin-right: 6px;
        }
        
        .btn-outline-secondary {
            color: #9ca3af !important;
            border-color: #1e2a3a !important;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 10px;
        }
        
        .btn-outline-secondary:hover {
            background: #1a1f2e !important;
            color: #e5e7eb !important;
        }
        
        .btn-danger-custom {
            background: #dc3545 !important;
            border: none;
            color: white !important;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .btn-danger-custom:hover {
            background: #c82333 !important;
            color: white !important;
        }
        
        .student-card {
            background: #131926 !important;
            border-radius: 12px;
            padding: 15px 18px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            margin-bottom: 12px;
            border-left: 3px solid #10b981;
            border: 1px solid #1e2a3a;
            transition: all 0.3s ease;
        }
        
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }
        
        .student-card .name {
            font-weight: 600;
            color: #ffd700 !important;
            font-size: 14px;
        }
        
        .student-card .detail {
            font-size: 12px;
            color: #9ca3af !important;
        }
        
        .student-card .detail i {
            color: #6b7280;
            width: 16px;
        }
        
        .student-card .btn-edit-password {
            background: rgba(255, 215, 0, 0.15) !important;
            color: #ffd700 !important;
            border: 1px solid rgba(255, 215, 0, 0.3) !important;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .student-card .btn-edit-password:hover {
            background: rgba(255, 215, 0, 0.25) !important;
        }
        
        .badge.bg-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
            font-size: 10px;
        }
        
        .badge.bg-info {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            font-size: 10px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.15) !important;
            border-color: #3b82f6 !important;
            color: #93c5fd !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        
        .alert-info a {
            color: #ffd700 !important;
        }
        
        .btn-close {
            filter: invert(1) !important;
        }
        
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 {
            color: #e5e7eb !important;
        }
        
        .border-bottom {
            border-color: #1e2a3a !important;
        }
        
        .text-muted {
            color: #6b7280 !important;
        }
        
        .text-primary {
            color: #ffd700 !important;
        }
        
        .info-box {
            background: #0d1220 !important;
            border-radius: 10px;
            padding: 12px 18px;
            border-left: 3px solid #ffd700;
            border: 1px solid #1e2a3a;
        }
        
        .info-box .label {
            font-size: 10px;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-box .value {
            font-size: 13px;
            color: #ffd700;
            font-weight: 600;
        }
        
        .password-requirements {
            font-size: 11px;
            color: #6b7280 !important;
            margin-top: 3px;
        }
        
        .password-requirements .text-success {
            color: #6ee7b7 !important;
        }
        
        .password-requirements .text-warning {
            color: #fbbf24 !important;
        }
        
        hr {
            border-color: #1e2a3a !important;
            margin: 15px 0 !important;
        }
        
        /* ============================================================
           MODAL DARK MODE
           ============================================================ */
        .password-modal .modal-content {
            background: #131926 !important;
            border: 1px solid #1e2a3a;
            border-radius: 12px;
        }
        
        .password-modal .modal-header {
            border-bottom: 1px solid #1e2a3a;
            padding: 12px 18px;
        }
        
        .password-modal .modal-footer {
            border-top: 1px solid #1e2a3a;
            padding: 12px 18px;
        }
        
        .password-modal .modal-title {
            color: #ffd700 !important;
            font-size: 16px;
        }
        
        .password-modal .btn-secondary {
            background: #1e2a3a !important;
            border: none;
            color: #e5e7eb !important;
            font-size: 13px;
            padding: 6px 16px;
            border-radius: 8px;
        }
        
        .password-modal .btn-secondary:hover {
            background: #2d3548 !important;
        }
        
        .password-modal .form-control {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .password-modal .form-control:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
        }
        
        /* ============================================================
           PAGE HEADER
           ============================================================ */
        .page-header {
            padding-bottom: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1e2a3a;
        }
        .page-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #e5e7eb;
        }
        .page-header h1 i {
            color: #ffd700;
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed !important;
                top: 56px !important;
                bottom: 0 !important;
                left: -260px !important;
                width: 260px !important;
                transition: left 0.3s ease !important;
                z-index: 1040 !important;
            }
            .sidebar.show { left: 0 !important; }
            .main-content {
                margin-left: 0 !important;
                padding: 12px 15px !important;
                min-height: calc(100vh - 56px - 40px) !important;
            }
            .footer {
                margin-left: 0 !important;
                padding: 8px 15px !important;
                width: 100% !important;
            }
            .form-section { padding: 15px; }
            .info-box .value { font-size: 12px; }
        }
        
        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0e1a;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e2a3a;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #ffd700;
        }
        
        @media print {
            .no-print { display: none !important; }
            .form-section { 
                box-shadow: none !important; 
                border: 1px solid #333 !important;
                background: #fff !important;
            }
            .form-section h5 { color: #1a3a6a !important; border-bottom-color: #1a3a6a !important; }
            body { background: #fff !important; color: #000 !important; }
            .form-control, .form-select { background: #fff !important; color: #000 !important; border-color: #ddd !important; }
            .form-label { color: #333 !important; }
            .student-card { background: #f8f9fa !important; border-color: #ddd !important; }
            .student-card .name { color: #1a3a6a !important; }
            .student-card .detail { color: #666 !important; }
            .info-box { background: #f8f9fa !important; border-color: #1a3a6a !important; }
            .info-box .value { color: #1a3a6a !important; }
            .footer { display: none !important; }
            .navbar { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="content-wrapper">
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- MAIN CONTENT -->
            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
                    <h1><i class="fas fa-user-graduate me-2"></i>Student Portal Registration</h1>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo safe($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($password_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $password_success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ============================================================
                REGISTRATION FORM
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-user-plus me-2"></i>Register New Student</h5>
                    
                    <div class="info-box mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="label">Student ID Format</div>
                                <div class="value">STU-2025-0001</div>
                            </div>
                            <div class="col-md-4">
                                <div class="label">Default Email</div>
                                <div class="value">first.last@isu.edu.ph</div>
                            </div>
                            <div class="col-md-4">
                                <div class="label">Username</div>
                                <div class="value">Auto-generated</div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="" id="studentForm">
                        <div class="row g-2 mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Select Resident for Auto-Fill</label>
                                <select class="form-select" id="residentSelect" onchange="autoFillResident()">
                                    <option value="">-- Select Resident --</option>
                                    <?php foreach ($residentsList as $resident): ?>
                                        <option value="<?php echo safe($resident['user_id']); ?>">
                                            <?php echo safe($resident['full_name']); ?> 
                                            (<?php echo safe($resident['student_id'] ?? 'N/A'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select a resident to auto-fill their information</small>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="full_name" id="full_name" value="<?php echo safe($studentData['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Student ID <span class="required">*</span></label>
                                <input type="text" class="form-control" name="student_id_number" value="<?php echo safe($studentData['student_id_number'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="required">*</span></label>
                                <input type="text" class="form-control" name="username" id="username" placeholder="Choose username" value="<?php echo safe($studentData['username'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo safe($studentData['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Course <span class="required">*</span></label>
                                <input type="text" class="form-control" name="course" value="<?php echo safe($studentData['course'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year Level <span class="required">*</span></label>
                                <select class="form-select" name="year_level" required>
                                    <option value="">Select</option>
                                    <option value="1st Year" <?php echo (isset($studentData['year_level']) && $studentData['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (isset($studentData['year_level']) && $studentData['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (isset($studentData['year_level']) && $studentData['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (isset($studentData['year_level']) && $studentData['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="5th Year" <?php echo (isset($studentData['year_level']) && $studentData['year_level'] == '5th Year') ? 'selected' : ''; ?>>5th Year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo safe($studentData['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Room Number</label>
                                <input type="text" class="form-control" name="room_number" value="<?php echo safe($studentData['room_number'] ?? ''); ?>">
                            </div>
                            <input type="hidden" name="resident_id" value="<?php echo isset($_GET['resident_id']) ? (int)$_GET['resident_id'] : 0; ?>">

                            <div class="col-md-12 mt-2">
                                <hr>
                                <h6 class="text-primary" style="font-size: 14px; margin-bottom: 8px;"><i class="fas fa-key me-2"></i>Set Password</h6>
                                <p class="text-muted small">This password will be used by the student to login</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="required">*</span></label>
                                <input type="password" class="form-control" name="password" id="password" placeholder="Enter password" required>
                                <div class="password-requirements">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Minimum 8 characters with uppercase, lowercase, and number
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password <span class="required">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm password" required>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" name="submit" class="btn btn-submit">
                                <i class="fas fa-save"></i> Register Student
                            </button>
                            <button type="reset" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ============================================================
                REGISTERED STUDENTS LIST WITH PASSWORD UPDATE
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-list me-2"></i>Registered Students</h5>
                    <?php if (empty($students)): ?>
                        <p class="text-muted text-center py-2" style="font-size: 13px;">No students registered yet</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($students as $student): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="student-card">
                                        <div class="name">
                                            <i class="fas fa-user-graduate me-2" style="color: #ffd700;"></i>
                                            <?php echo safe($student['full_name']); ?>
                                        </div>
                                        <div class="detail">
                                            <i class="fas fa-id-card me-1"></i>
                                            <?php echo safe($student['student_id_number']); ?>
                                        </div>
                                        <div class="detail">
                                            <i class="fas fa-user me-1"></i>
                                            Username: <?php echo safe($student['username']); ?>
                                        </div>
                                        <div class="detail">
                                            <i class="fas fa-graduation-cap me-1"></i>
                                            <?php echo safe($student['course']); ?> - <?php echo safe($student['year_level']); ?>
                                        </div>
                                        <div class="detail">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo safe($student['email']); ?>
                                        </div>
                                        <div class="mt-2 d-flex flex-wrap gap-1 align-items-center">
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                                            <?php if ($student['resident_id']): ?>
                                                <span class="badge bg-info"><i class="fas fa-user me-1"></i> Resident</span>
                                            <?php endif; ?>
                                            <button class="btn-edit-password" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#passwordModal"
                                                    data-student-id="<?php echo $student['student_id']; ?>"
                                                    data-student-name="<?php echo safe($student['full_name']); ?>"
                                                    data-student-username="<?php echo safe($student['username']); ?>">
                                                <i class="fas fa-key me-1"></i> Reset Password
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
        
        <!-- ============================================================
        FOOTER - STICKY BOTTOM
        ============================================================ -->
        <footer class="footer">
            &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System - ISU-Echague Dormitory. All rights reserved.
        </footer>
    </div>

    <!-- ============================================================
    PASSWORD UPDATE MODAL - ADMIN ONLY
    ============================================================ -->
    <div class="modal fade password-modal" id="passwordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2" style="color: #ffd700;"></i>
                        Reset Student Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Student:</strong> <span id="modalStudentName">-</span><br>
                            <strong>Username:</strong> <span id="modalStudentUsername">-</span>
                        </div>
                        
                        <input type="hidden" name="student_id" id="modalStudentId">
                        
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="required">*</span></label>
                            <input type="password" class="form-control" name="new_password" placeholder="Enter new password" required>
                            <small class="text-muted" style="font-size: 11px;">Minimum 8 characters with uppercase, lowercase, and number</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password <span class="required">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_password_admin" class="btn btn-danger-custom">
                            <i class="fas fa-save me-1"></i> Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // AUTO-FILL FROM RESIDENT SELECTION
        // ============================================================
        function autoFillResident() {
            const select = document.getElementById('residentSelect');
            const residentId = select.value;
            if (!residentId) return;
            window.location.href = `student-registration.php?resident_id=${residentId}`;
        }

        // ============================================================
        // GENERATE USERNAME FROM FULL NAME
        // ============================================================
        document.getElementById('full_name').addEventListener('input', function() {
            const usernameField = document.getElementById('username');
            if (!usernameField.value || usernameField.dataset.auto === 'true') {
                const name = this.value.trim();
                if (name) {
                    const parts = name.toLowerCase().split(' ');
                    let username = parts[0];
                    if (parts.length > 1) {
                        username += parts[parts.length - 1][0];
                    }
                    username += Math.floor(Math.random() * 100);
                    usernameField.value = username;
                    usernameField.dataset.auto = 'true';
                }
            }
        });

        // ============================================================
        // PASSWORD STRENGTH INDICATOR
        // ============================================================
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const requirements = document.querySelector('.password-requirements');
            
            let valid = password.length >= 8;
            valid = valid && /[A-Z]/.test(password);
            valid = valid && /[a-z]/.test(password);
            valid = valid && /[0-9]/.test(password);
            
            if (password.length > 0) {
                requirements.innerHTML = valid ? 
                    '<i class="fas fa-check-circle text-success me-1"></i> Strong password' :
                    '<i class="fas fa-exclamation-circle text-warning me-1"></i> Password must be at least 8 characters with uppercase, lowercase, and number';
            }
        });

        // ============================================================
        // PASSWORD MODAL - PASS STUDENT DATA
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const passwordModal = document.getElementById('passwordModal');
            passwordModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const studentId = button.getAttribute('data-student-id');
                const studentName = button.getAttribute('data-student-name');
                const studentUsername = button.getAttribute('data-student-username');
                
                document.getElementById('modalStudentId').value = studentId;
                document.getElementById('modalStudentName').textContent = studentName;
                document.getElementById('modalStudentUsername').textContent = studentUsername;
            });
        });

        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>