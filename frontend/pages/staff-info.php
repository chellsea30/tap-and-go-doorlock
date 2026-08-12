<?php
/**
 * Tap-and-Go Doorlock - Staff Information
 * DARK MODE - AUTO STAFF ID - FIXED LAYOUT SAME AS DASHBOARD
 */

session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php'; 

$conn = getDBConnection();
$error = '';
$success = '';

// ============================================================
// GET NEXT STAFF ID NUMBER
// ============================================================
function getNextStaffId($conn) {
    $result = $conn->query("SELECT staff_id_number FROM staff_users ORDER BY staff_id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $lastId = $row['staff_id_number'];
        $num = (int)substr($lastId, 6);
        $nextNum = $num + 1;
        return 'STAFF-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }
    return 'STAFF-001';
}

// ============================================================
// HANDLE PROFILE PHOTO UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo']) && isset($_POST['staff_id'])) {
    $staff_id = (int)$_POST['staff_id'];
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/staff_photos/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $file_name = 'staff_' . $staff_id . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;
        
        $image_info = getimagesize($_FILES['profile_photo']['tmp_name']);
        if ($image_info !== false) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($image_info['mime'], $allowed_types)) {
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    $photo_path = 'uploads/staff_photos/' . $file_name;
                    
                    $tableCheck = $conn->query("SHOW COLUMNS FROM staff_users LIKE 'avatar'");
                    if ($tableCheck && $tableCheck->num_rows > 0) {
                        $stmt = $conn->prepare("UPDATE staff_users SET avatar = ? WHERE staff_id = ?");
                        $stmt->bind_param("si", $photo_path, $staff_id);
                    } else {
                        $conn->query("ALTER TABLE staff_users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
                        $stmt = $conn->prepare("UPDATE staff_users SET avatar = ? WHERE staff_id = ?");
                        $stmt->bind_param("si", $photo_path, $staff_id);
                    }
                    
                    if ($stmt->execute()) {
                        $success = "Profile photo uploaded successfully!";
                    } else {
                        $error = "Failed to update database: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to upload photo. Please check folder permissions.";
                }
            } else {
                $error = "Invalid file type. Please upload JPEG, PNG, GIF, or WEBP.";
            }
        } else {
            $error = "Uploaded file is not a valid image.";
        }
    } else {
        $error = "Please select a photo to upload.";
    }
}

// ============================================================
// HANDLE PHOTO REMOVE
// ============================================================
if (isset($_GET['remove_photo']) && is_numeric($_GET['remove_photo'])) {
    $staff_id = (int)$_GET['remove_photo'];
    
    try {
        $stmt = $conn->prepare("SELECT avatar FROM staff_users WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!empty($row['avatar'])) {
            $file_path = '../../' . $row['avatar'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $stmt = $conn->prepare("UPDATE staff_users SET avatar = NULL WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        
        if ($stmt->execute()) {
            $success = "Profile photo removed successfully!";
        } else {
            $error = "Failed to remove photo: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// HANDLE DELETE STAFF
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM staff_users WHERE staff_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success = "Staff deleted successfully!";
        logAudit($_SESSION['admin_id'], 'Delete Staff', "Deleted staff ID: $delete_id");
    } else {
        $error = "Failed to delete staff.";
    }
    $stmt->close();
}

// ============================================================
// HANDLE SET/RESET PASSWORD
// ============================================================
if (isset($_GET['set_password']) && is_numeric($_GET['set_password'])) {
    $staff_id = (int)$_GET['set_password'];
    
    try {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $new_password = '';
        for ($i = 0; $i < 10; $i++) {
            $new_password .= $chars[rand(0, strlen($chars) - 1)];
        }
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $tableCheck = $conn->query("SHOW COLUMNS FROM staff_users LIKE 'password_hash'");
        if (!$tableCheck || $tableCheck->num_rows == 0) {
            $conn->query("ALTER TABLE staff_users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        }
        
        $stmt = $conn->prepare("UPDATE staff_users SET password_hash = ? WHERE staff_id = ?");
        $stmt->bind_param("si", $password_hash, $staff_id);
        
        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("SELECT full_name, email, staff_id_number FROM staff_users WHERE staff_id = ?");
            $stmt2->bind_param("i", $staff_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $staff = $result->fetch_assoc();
            $stmt2->close();
            
            $success = "Password set successfully!<br>";
            $success .= "<strong>Staff ID:</strong> " . $staff['staff_id_number'] . "<br>";
            $success .= "<strong>Email:</strong> " . $staff['email'] . "<br>";
            $success .= "<strong>New Password:</strong> <code class='bg-dark text-warning p-1 px-2 rounded' style='font-size:16px;'>$new_password</code><br>";
            $success .= "<small><i class='fas fa-info-circle me-1'></i>Please copy this password and share it with the staff member securely.</small>";
            
            logAudit($_SESSION['admin_id'], 'Set Staff Password', "Set password for staff: {$staff['full_name']}");
        } else {
            $error = "Failed to set password: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// GET STAFF LIST
// ============================================================
$staffList = [];
$tableCheck = $conn->query("SHOW COLUMNS FROM staff_users LIKE 'avatar'");
$hasAvatar = $tableCheck && $tableCheck->num_rows > 0;

if ($hasAvatar) {
    $result = $conn->query("SELECT * FROM staff_users ORDER BY full_name");
} else {
    $result = $conn->query("SELECT *, NULL as avatar FROM staff_users ORDER BY full_name");
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

$totalStaff = count($staffList);
$managementCount = 0;
$staffCount = 0;

foreach ($staffList as $staff) {
    if (strpos($staff['department'] ?? '', 'Management') !== false) {
        $managementCount++;
    } else {
        $staffCount++;
    }
}

$nextStaffId = getNextStaffId($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Info - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME - SAME AS DASHBOARD
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        
        /* ============================================================
           FIX: MAIN CONTENT OFFSET FOR FIXED NAVBAR
           ============================================================ */
        .container-fluid {
            padding-top: 10px !important;
        }
        
        main {
            padding-top: 10px !important;
            margin-top: 0 !important;
        }
        
        /* ============================================================
           DARK NAVBAR OVERRIDE - SAME AS DASHBOARD
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           DARK SIDEBAR - SAME AS DASHBOARD
           ============================================================ */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar-footer { border-top-color: #1a2a4a !important; }
        .sidebar-footer .text-muted { color: #606070 !important; }
        
        /* ============================================================
           STAFF CARD - DARK
           ============================================================ */
        .staff-card {
            background: #111827 !important;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: all 0.3s ease;
            text-align: center;
            height: 100%;
            position: relative;
            border: 1px solid #1a2a4a;
        }
        .staff-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important;
        }
        .staff-card .name {
            font-weight: 700;
            color: #ffd700 !important;
            font-size: 18px;
        }
        .staff-card .department {
            color: #9ca3af !important;
            font-size: 14px;
        }
        .staff-card .staff-id {
            color: #6b7280 !important;
            font-size: 12px;
        }
        .staff-card .badge-active {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
            font-size: 11px;
            padding: 3px 12px;
            border-radius: 20px;
        }
        .staff-card .text-muted { color: #6b7280 !important; }
        
        /* ============================================================
           STAFF AVATAR
           ============================================================ */
        .staff-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin: 0 auto 15px;
            font-weight: 700;
            overflow: hidden;
            border: 3px solid #1a2a4a;
            position: relative;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        .staff-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        .staff-avatar .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.8);
            color: white;
            text-align: center;
            font-size: 9px;
            padding: 2px 0;
            opacity: 0;
            transition: all 0.3s ease;
        }
        .staff-avatar:hover .upload-overlay {
            opacity: 1;
        }
        .staff-avatar .has-photo-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #111827;
        }
        
        /* ============================================================
           STAT CARDS - SAME AS DASHBOARD
           ============================================================ */
        .staff-stat {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            text-align: center;
        }
        .staff-stat:hover { transform: translateY(-4px); }
        .staff-stat .number {
            font-size: 32px;
            font-weight: 700;
            color: #ffd700 !important;
        }
        .staff-stat .label {
            font-size: 13px;
            color: #6b7280 !important;
        }
        
        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-add {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            color: #0a0e1a !important;
            padding: 10px 25px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3) !important;
            color: #0a0e1a !important;
        }
        .btn-add i { margin-right: 8px; }
        
        .btn-password {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-password:hover {
            background: rgba(245, 158, 11, 0.3) !important;
            color: #fbbf24 !important;
        }
        .btn-password-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
            border: 1px solid rgba(16, 185, 129, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-password-success:hover {
            background: rgba(16, 185, 129, 0.3) !important;
            color: #6ee7b7 !important;
        }
        
        .btn-upload-photo {
            background: rgba(139, 92, 246, 0.2) !important;
            color: #a78bfa !important;
            border: 1px solid rgba(139, 92, 246, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        .btn-upload-photo:hover {
            background: rgba(139, 92, 246, 0.3) !important;
            color: #a78bfa !important;
        }
        
        .btn-remove-photo {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
            border: 1px solid rgba(239, 68, 68, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        .btn-remove-photo:hover {
            background: rgba(239, 68, 68, 0.3) !important;
            color: #fca5a5 !important;
        }
        
        .btn-outline-primary {
            color: #ffd700 !important;
            border-color: rgba(255, 215, 0, 0.3) !important;
        }
        .btn-outline-primary:hover {
            background: rgba(255, 215, 0, 0.15) !important;
            color: #ffd700 !important;
        }
        .btn-outline-danger {
            color: #fca5a5 !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
        }
        .btn-outline-danger:hover {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #fca5a5 !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            color: #0a0e1a !important;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: #0a0e1a !important;
        }
        
        .btn-secondary {
            background: #1a2a4a !important;
            border: none !important;
            color: #e5e7eb !important;
        }
        .btn-secondary:hover {
            background: #2d3548 !important;
            color: #e5e7eb !important;
        }
        
        .staff-actions {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .has-password { color: #6ee7b7 !important; }
        .no-password { color: #fca5a5 !important; }
        
        .staff-id-badge {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            border: 1px solid rgba(255, 215, 0, 0.15);
        }
        
        /* ============================================================
           MODAL - DARK
           ============================================================ */
        .modal-content {
            background: #131926 !important;
            border-radius: 16px;
            border: 1px solid #1a2a4a;
        }
        .modal-header { border-bottom: 1px solid #1a2a4a; }
        .modal-footer { border-top: 1px solid #1a2a4a; }
        .modal-title { color: #ffd700 !important; }
        .modal-title i { color: #ffd700 !important; }
        
        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1a2a4a;
        }
        
        /* ============================================================
           FORM ELEMENTS
           ============================================================ */
        .form-control {
            background: #0d1220 !important;
            border: 1px solid #1a2a4a !important;
            color: #e5e7eb !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        .form-control::placeholder { color: #6b7280 !important; }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #d1d5db !important;
        }
        .form-text { color: #6b7280 !important; }
        
        /* ============================================================
           ALERTS
           ============================================================ */
        .alert-success {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
        }
        .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #6b7280 !important; }
        
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .card .card-body { background: transparent !important; }
        .card .text-muted { color: #6b7280 !important; }
        .card h5 { color: #9ca3af !important; }
        
        .section-header h5 {
            margin: 0;
            color: #ffd700 !important;
            font-weight: 700;
        }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            margin-right: 4px;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1a2a4a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
        /* ============================================================
           RESPONSIVE - SAME AS DASHBOARD
           ============================================================ */
        @media (max-width: 768px) {
            body {
                padding-top: 60px !important;
            }
            
            .navbar {
                height: 60px !important;
            }
            
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            
            .staff-card { padding: 15px; }
            .staff-card .name { font-size: 16px; }
            .staff-avatar { width: 60px; height: 60px; font-size: 24px; }
        }
        
        @media (max-width: 576px) {
            .staff-actions {
                flex-direction: column;
                align-items: center;
            }
            .staff-actions .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ============================================================
                HEADER - SAME AS DASHBOARD
                ============================================================ -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-tie me-2" style="color: #ffd700;"></i>
                        Staff Information
                        <span class="badge bg-secondary ms-2"><?php echo $totalStaff; ?> total</span>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="add-staff.php" class="btn btn-primary btn-sm ms-1">
                            <i class="fas fa-user-plus me-1"></i> Add Staff
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> 
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ============================================================
                STATISTICS - SAME AS DASHBOARD
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $totalStaff; ?></div>
                            <div class="label">Total Staff</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $totalStaff; ?></div>
                            <div class="label">Active Staff</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $managementCount; ?></div>
                            <div class="label">Management</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $staffCount; ?></div>
                            <div class="label">Staff</div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                STAFF LIST
                ============================================================ -->
                <div class="section-header mb-3">
                    <h5><i class="fas fa-list me-2"></i>Staff List</h5>
                    <small class="text-muted ms-2">
                        Next ID: <strong class="text-warning"><?php echo $nextStaffId; ?></strong>
                    </small>
                </div>

                <?php if (empty($staffList)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No staff members found</h5>
                            <p class="text-muted">Click "Add Staff" to add a staff member</p>
                            <a href="add-staff.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-user-plus me-1"></i> Add Staff
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($staffList as $staff): ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="staff-card">
                                    <!-- Staff Avatar -->
                                    <div class="staff-avatar" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#photoModal<?php echo $staff['staff_id']; ?>"
                                         title="Click to upload/change photo">
                                        <?php 
                                            $photoPath = $staff['avatar'] ?? '';
                                            $fullPath = '../../' . $photoPath;
                                            
                                            if (!empty($photoPath) && file_exists($fullPath)):
                                        ?>
                                            <img src="<?php echo $fullPath; ?>" 
                                                 alt="Staff Photo"
                                                 onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                                            <span class="has-photo-badge">
                                                <i class="fas fa-check-circle"></i>
                                            </span>
                                        <?php else: 
                                            $name = $staff['full_name'] ?? 'Staff';
                                            $initials = '';
                                            $parts = explode(' ', $name);
                                            foreach ($parts as $p) {
                                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                            }
                                            echo '<div class="no-photo">' . substr($initials, 0, 2) . '</div>';
                                        endif; 
                                        ?>
                                        <div class="upload-overlay">
                                            <i class="fas fa-camera me-1"></i> Upload
                                        </div>
                                    </div>
                                    
                                    <div class="name"><?php echo htmlspecialchars($staff['full_name']); ?></div>
                                    <div class="department"><?php echo htmlspecialchars($staff['department'] ?? 'Staff'); ?></div>
                                    <div class="staff-id">
                                        <span class="staff-id-badge">
                                            <?php echo htmlspecialchars($staff['staff_id_number']); ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <span class="badge-active"><i class="fas fa-check-circle me-1"></i> Active</span>
                                    </div>
                                    <div class="mt-2">
                                        <span class="text-muted small">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($staff['email']); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Password Status -->
                                    <div class="mt-1">
                                        <?php if (!empty($staff['password_hash']) && $staff['password_hash'] != ''): ?>
                                            <span class="has-password small">
                                                <i class="fas fa-check-circle me-1"></i> Password Set
                                            </span>
                                        <?php else: ?>
                                            <span class="no-password small">
                                                <i class="fas fa-exclamation-triangle me-1"></i> No Password
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Staff Actions -->
                                    <div class="staff-actions">
                                        <button type="button" 
                                                class="btn btn-sm btn-upload-photo"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#photoModal<?php echo $staff['staff_id']; ?>">
                                            <i class="fas fa-camera"></i>
                                        </button>
                                        
                                        <?php if (empty($staff['password_hash']) || $staff['password_hash'] == ''): ?>
                                            <a href="?set_password=<?php echo $staff['staff_id']; ?>" 
                                               class="btn btn-sm btn-password"
                                               onclick="return confirm('Set password for <?php echo $staff['full_name']; ?>?')">
                                                <i class="fas fa-key me-1"></i> Set Password
                                            </a>
                                        <?php else: ?>
                                            <a href="?set_password=<?php echo $staff['staff_id']; ?>" 
                                               class="btn btn-sm btn-password-success"
                                               onclick="return confirm('Reset password for <?php echo $staff['full_name']; ?>?')">
                                                <i class="fas fa-sync-alt me-1"></i> Reset
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="edit-staff.php?id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this staff?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- PHOTO UPLOAD MODAL -->
                            <div class="modal fade" id="photoModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-camera me-2"></i>
                                                Profile Photo - <?php echo htmlspecialchars($staff['full_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <div class="mb-3">
                                                <?php 
                                                    $photoPath = $staff['avatar'] ?? '';
                                                    $fullPath = '../../' . $photoPath;
                                                    if (!empty($photoPath) && file_exists($fullPath)):
                                                ?>
                                                    <img src="<?php echo $fullPath; ?>" 
                                                         alt="Current Photo"
                                                         class="photo-preview">
                                                    <div class="mt-2">
                                                        <a href="?remove_photo=<?php echo $staff['staff_id']; ?>" 
                                                           class="btn btn-sm btn-remove-photo"
                                                           onclick="return confirm('Remove this photo?')">
                                                            <i class="fas fa-trash me-1"></i> Remove Photo
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:48px;font-weight:700;color:white;">
                                                        <?php 
                                                            $name = $staff['full_name'] ?? 'Staff';
                                                            $initials = '';
                                                            $parts = explode(' ', $name);
                                                            foreach ($parts as $p) {
                                                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                                            }
                                                            echo substr($initials, 0, 2);
                                                        ?>
                                                    </div>
                                                    <div class="mt-2 text-muted small">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        No photo uploaded yet
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <hr>
                                            
                                            <form method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Upload New Photo</label>
                                                    <input type="file" 
                                                           class="form-control" 
                                                           name="profile_photo" 
                                                           accept="image/*"
                                                           required>
                                                    <div class="form-text text-muted small">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Max size: 2MB. Allowed: JPG, PNG, GIF, WEBP
                                                    </div>
                                                </div>
                                                <button type="submit" name="upload_photo" class="btn btn-primary">
                                                    <i class="fas fa-upload me-1"></i> Upload Photo
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- ============================================================
                FOOTER - SAME AS DASHBOARD
                ============================================================ -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span>Total: <?php echo $totalStaff; ?> staff members</span>
                    <span class="mx-2">|</span>
                    <span class="text-warning"><i class="fas fa-id-card me-1"></i>Next ID: <?php echo $nextStaffId; ?></span>
                </footer>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // UPDATE TIME - SAME AS DASHBOARD
        // ============================================================
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            const updateElement = document.getElementById('lastUpdate');
            if (updateElement) {
                updateElement.textContent = 'Updated: ' + timeString;
            }
            const serverTimeElement = document.getElementById('serverTime');
            if (serverTimeElement) {
                const dateString = now.toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
                serverTimeElement.textContent = 'Server Time: ' + dateString + ' ' + timeString;
            }
        }

        setInterval(updateLastUpdateTime, 10000);
        document.addEventListener('DOMContentLoaded', updateLastUpdateTime);
        
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>