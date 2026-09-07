<?php
/**
 * Tap-and-Go Doorlock - Staff Management
 * DARK MODE - WITH CARD UID - WITH PROFILE PHOTO - WITH PRINT ID
 * WITH EDIT, DELETE, ADD, PORTAL REGISTRATION, AND PHOTO UPLOAD
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
// GET STAFF DATA FOR EDIT
// ============================================================
$editStaff = null;
$editMode = false;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM staff_users WHERE staff_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editStaff = $result->fetch_assoc();
    $stmt->close();
    if ($editStaff) {
        $editMode = true;
    } else {
        $error = "Staff not found.";
    }
}

// ============================================================
// HANDLE ADD STAFF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? 'Staff');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields (Name and Email).';
    } else {
        $check = $conn->prepare("SELECT staff_id FROM staff_users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already exists in the system.';
        } else {
            $staff_id_number = getNextStaffId($conn);
            
            $stmt = $conn->prepare("
                INSERT INTO staff_users (staff_id_number, full_name, email, department, password_hash, created_at)
                VALUES (?, ?, ?, ?, NULL, NOW())
            ");
            $stmt->bind_param("ssss", $staff_id_number, $full_name, $email, $department);
            
            if ($stmt->execute()) {
                $success = "✅ Staff added successfully! Staff ID: $staff_id_number";
                logAudit($_SESSION['admin_id'], 'Add Staff', "Added staff: $full_name ($staff_id_number)");
                header('Location: staff-info.php?added=1');
                exit();
            } else {
                $error = "Failed to add staff: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// HANDLE UPDATE STAFF (EDIT)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $staff_id = (int)$_POST['staff_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? 'Staff');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields (Name and Email).';
    } else {
        $check = $conn->prepare("SELECT staff_id FROM staff_users WHERE email = ? AND staff_id != ?");
        $check->bind_param("si", $email, $staff_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already exists in the system.';
        } else {
            $stmt = $conn->prepare("
                UPDATE staff_users 
                SET full_name = ?, email = ?, department = ?, updated_at = NOW()
                WHERE staff_id = ?
            ");
            $stmt->bind_param("sssi", $full_name, $email, $department, $staff_id);
            
            if ($stmt->execute()) {
                $success = "✅ Staff updated successfully!";
                logAudit($_SESSION['admin_id'], 'Update Staff', "Updated staff: $full_name (ID: $staff_id)");
                header('Location: staff-info.php?updated=1');
                exit();
            } else {
                $error = "Failed to update staff: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// HANDLE PHOTO UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    $staff_id = (int)$_POST['staff_id'];
    
    if (isset($_FILES['staff_photo']) && $_FILES['staff_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['staff_photo'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        
        // Validate file type
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($file_tmp);
        
        if (!in_array($file_type, $allowed)) {
            $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB max
            $error = 'File size too large. Maximum 5MB allowed.';
        } else {
            // Create upload directory if not exists
            $upload_dir = '../../uploads/staff_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = 'staff_' . $staff_id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old photo if exists
                $stmt = $conn->prepare("SELECT avatar FROM staff_users WHERE staff_id = ?");
                $stmt->bind_param("i", $staff_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                if (!empty($row['avatar'])) {
                    $old_file = '../../' . $row['avatar'];
                    if (file_exists($old_file) && is_file($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Update database with new photo path
                $photo_path = 'uploads/staff_photos/' . $new_filename;
                $stmt = $conn->prepare("UPDATE staff_users SET avatar = ? WHERE staff_id = ?");
                $stmt->bind_param("si", $photo_path, $staff_id);
                
                if ($stmt->execute()) {
                    $success = "✅ Profile photo uploaded successfully!";
                    logAudit($_SESSION['admin_id'], 'Upload Staff Photo', "Uploaded photo for staff ID: $staff_id");
                    header('Location: staff-info.php?photo_uploaded=1');
                    exit();
                } else {
                    $error = "Failed to update database: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Failed to upload file.";
            }
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// ============================================================
// HANDLE REGISTER CARD FOR STAFF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_card'])) {
    $staff_id = (int)$_POST['staff_id'];
    $card_uid = strtoupper(trim($_POST['card_uid'] ?? ''));
    
    if (empty($card_uid)) {
        $error = 'Please enter a Card UID.';
    } else {
        $check = $conn->prepare("
            SELECT card_uid FROM rfid_cards 
            WHERE card_uid = ? AND status = 'active'
        ");
        $check->bind_param("s", $card_uid);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Card UID is already assigned to someone else.';
        } else {
            $staffCheck = $conn->prepare("SELECT card_uid FROM staff_users WHERE staff_id = ?");
            $staffCheck->bind_param("i", $staff_id);
            $staffCheck->execute();
            $staffResult = $staffCheck->get_result();
            $staffData = $staffResult->fetch_assoc();
            $staffCheck->close();
            
            if (!empty($staffData['card_uid'])) {
                $error = 'This staff already has a card. Please remove the existing card first.';
            } else {
                $stmt = $conn->prepare("UPDATE staff_users SET card_uid = ? WHERE staff_id = ?");
                $stmt->bind_param("si", $card_uid, $staff_id);
                
                if ($stmt->execute()) {
                    $rfidStmt = $conn->prepare("
                        INSERT INTO rfid_cards (card_uid, user_id, card_type, issued_date, expiry_date, status)
                        VALUES (?, ?, 'staff', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active')
                    ");
                    $rfidStmt->bind_param("si", $card_uid, $staff_id);
                    $rfidStmt->execute();
                    $rfidStmt->close();
                    
                    $success = "✅ Card UID registered successfully!";
                    logAudit($_SESSION['admin_id'], 'Register Staff Card', "Registered card $card_uid for staff ID: $staff_id");
                    header('Location: staff-info.php?card_registered=1');
                    exit();
                } else {
                    $error = "Failed to register card: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        $check->close();
    }
}

// ============================================================
// HANDLE REMOVE CARD UID
// ============================================================
if (isset($_GET['remove_card']) && is_numeric($_GET['remove_card'])) {
    $staff_id = (int)$_GET['remove_card'];
    
    $stmt = $conn->prepare("UPDATE staff_users SET card_uid = NULL WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    if ($stmt->execute()) {
        $rfidStmt = $conn->prepare("UPDATE rfid_cards SET status = 'deactivated' WHERE user_id = ? AND card_type = 'staff'");
        $rfidStmt->bind_param("i", $staff_id);
        $rfidStmt->execute();
        $rfidStmt->close();
        
        $success = "✅ Card UID removed successfully!";
        logAudit($_SESSION['admin_id'], 'Remove Staff Card', "Removed card for staff ID: $staff_id");
        header('Location: staff-info.php?card_removed=1');
        exit();
    } else {
        $error = "Failed to remove card: " . $stmt->error;
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
// HANDLE DELETE STAFF
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $getCard = $conn->prepare("SELECT card_uid, avatar FROM staff_users WHERE staff_id = ?");
    $getCard->bind_param("i", $delete_id);
    $getCard->execute();
    $cardResult = $getCard->get_result();
    $staffData = $cardResult->fetch_assoc();
    $getCard->close();
    
    // Delete avatar file
    if (!empty($staffData['avatar'])) {
        $avatar_path = '../../' . $staffData['avatar'];
        if (file_exists($avatar_path) && is_file($avatar_path)) {
            unlink($avatar_path);
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM staff_users WHERE staff_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        if (!empty($staffData['card_uid'])) {
            $rfidStmt = $conn->prepare("UPDATE rfid_cards SET status = 'deactivated' WHERE card_uid = ?");
            $rfidStmt->bind_param("s", $staffData['card_uid']);
            $rfidStmt->execute();
            $rfidStmt->close();
        }
        $success = "Staff deleted successfully!";
        logAudit($_SESSION['admin_id'], 'Delete Staff', "Deleted staff ID: $delete_id");
        header('Location: staff-info.php?deleted=1');
        exit();
    } else {
        $error = "Failed to delete staff.";
    }
    $stmt->close();
}

// ============================================================
// HANDLE PORTAL REGISTRATION (STAFF PORTAL LOGIN) - FIXED
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_portal'])) {
    $staff_id = (int)$_POST['staff_id'];
    $portal_password = $_POST['portal_password'] ?? '';
    $portal_confirm = $_POST['portal_confirm'] ?? '';
    
    if (empty($staff_id) || empty($portal_password) || empty($portal_confirm)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($portal_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($portal_password !== $portal_confirm) {
        $error = 'Passwords do not match.';
    } else {
        // ✅ FIXED: Include full_name and email in SELECT
        $check = $conn->prepare("SELECT staff_id, full_name, email, password_hash FROM staff_users WHERE staff_id = ?");
        $check->bind_param("i", $staff_id);
        $check->execute();
        $result = $check->get_result();
        $staffData = $result->fetch_assoc();
        $check->close();
        
        if (!$staffData) {
            $error = 'Staff not found.';
        } else {
            if (!empty($staffData['password_hash']) && $staffData['password_hash'] != '') {
                $error = 'This staff already has a portal account. You can reset the password instead.';
            } else {
                $password_hash = password_hash($portal_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE staff_users SET password_hash = ? WHERE staff_id = ?");
                $stmt->bind_param("si", $password_hash, $staff_id);
                
                if ($stmt->execute()) {
                    $success = "✅ Portal account registered successfully!<br>";
                    // ✅ FIXED: Use isset() to check if keys exist
                    if (isset($staffData['full_name'])) {
                        $success .= "<strong>Staff:</strong> " . htmlspecialchars($staffData['full_name']) . "<br>";
                    }
                    if (isset($staffData['email'])) {
                        $success .= "<strong>Email:</strong> " . htmlspecialchars($staffData['email']) . "<br>";
                    }
                    $success .= "<small><i class='fas fa-info-circle me-1'></i>Staff can now login to the staff portal.</small>";
                    logAudit($_SESSION['admin_id'], 'Register Staff Portal', "Registered portal account for staff ID: $staff_id");
                    header('Location: staff-info.php?portal_registered=1');
                    exit();
                } else {
                    $error = "Failed to register portal account: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// ============================================================
// GET AVAILABLE CARDS FOR DROPDOWN
// ============================================================
$availableCards = [];
$result = $conn->query("
    SELECT card_id, card_uid, card_type 
    FROM available_rfid_cards 
    WHERE status = 'available'
    ORDER BY card_uid
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $availableCards[] = $row;
    }
}

// ============================================================
// GET STAFF LIST WITH CARD UID AND PROFILE PHOTO
// ============================================================
$staffList = [];

$tableCheck = $conn->query("SHOW COLUMNS FROM staff_users LIKE 'avatar'");
$hasAvatar = $tableCheck && $tableCheck->num_rows > 0;

if ($hasAvatar) {
    $result = $conn->query("
        SELECT staff_id, staff_id_number, full_name, email, department, card_uid, avatar, password_hash, created_at
        FROM staff_users
        ORDER BY full_name
    ");
} else {
    $result = $conn->query("
        SELECT staff_id, staff_id_number, full_name, email, department, card_uid, NULL as avatar, password_hash, created_at
        FROM staff_users
        ORDER BY full_name
    ");
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

$totalStaff = count($staffList);
$hasCardCount = 0;
$noCardCount = 0;
$hasPortalCount = 0;
$noPortalCount = 0;
$hasPhotoCount = 0;

foreach ($staffList as $staff) {
    if (!empty($staff['card_uid'])) {
        $hasCardCount++;
    } else {
        $noCardCount++;
    }
    if (!empty($staff['password_hash']) && $staff['password_hash'] != '') {
        $hasPortalCount++;
    } else {
        $noPortalCount++;
    }
    if (!empty($staff['avatar'])) {
        $hasPhotoCount++;
    }
}

$nextStaffId = getNextStaffId($conn);

// Get staff without card for the card registration section
$staffWithoutCard = [];
foreach ($staffList as $staff) {
    if (empty($staff['card_uid'])) {
        $staffWithoutCard[] = $staff;
    }
}

// Get staff without portal account
$staffWithoutPortal = [];
foreach ($staffList as $staff) {
    if (empty($staff['password_hash']) || $staff['password_hash'] == '') {
        $staffWithoutPortal[] = $staff;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        
        .container-fluid { padding-top: 10px !important; }
        main { padding-top: 10px !important; margin-top: 0 !important; }
        
        /* NAVBAR */
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
        
        /* SIDEBAR */
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
        
        /* STAFF CARD */
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
        
        /* STAFF AVATAR */
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
        .staff-avatar .has-photo-badge {
            position: absolute;
            bottom: -2px;
            right: -2px;
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
        .staff-avatar-wrapper {
            position: relative;
            display: inline-block;
        }
        
        /* CARD UID BADGE */
        .card-uid-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        .card-uid-badge.has-card {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .card-uid-badge.no-card {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #f87171 !important;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        /* PORTAL BADGE */
        .portal-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .portal-badge.has-portal {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .portal-badge.no-portal {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #f87171 !important;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        /* STAT CARDS */
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
        
        /* BUTTONS */
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
        
        .btn-card {
            background: rgba(139, 92, 246, 0.2) !important;
            color: #a78bfa !important;
            border: 1px solid rgba(139, 92, 246, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-card:hover {
            background: rgba(139, 92, 246, 0.3) !important;
            color: #a78bfa !important;
        }
        
        .btn-card-remove {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
            border: 1px solid rgba(239, 68, 68, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-card-remove:hover {
            background: rgba(239, 68, 68, 0.3) !important;
            color: #fca5a5 !important;
        }
        
        .btn-print-id {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print-id:hover {
            background: rgba(59, 130, 246, 0.3) !important;
            color: #93c5fd !important;
        }
        
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
        
        .btn-success-custom {
            background: #065f46 !important;
            border: none !important;
            color: #34d399 !important;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-success-custom:hover {
            background: #0a7a5a !important;
            color: #6ee7b7 !important;
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
        
        .btn-upload-photo {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        .btn-upload-photo:hover {
            background: rgba(59, 130, 246, 0.3) !important;
            color: #93c5fd !important;
        }
        
        .staff-actions {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .staff-id-badge {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            border: 1px solid rgba(255, 215, 0, 0.15);
        }
        
        .has-password { color: #6ee7b7 !important; }
        .no-password { color: #fca5a5 !important; }
        
        /* MODAL */
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
        
        /* FORM */
        .form-control, .form-select {
            background: #0d1220 !important;
            border: 1px solid #1a2a4a !important;
            color: #e5e7eb !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
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
        
        /* AVAILABLE CARDS LIST */
        .available-card-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 8px 10px;
            background: #0d1220 !important;
            border-radius: 8px;
            border: 1px solid #1a2a4a !important;
            min-height: 36px;
        }
        .available-card-list .card-item-mini {
            display: inline-block;
            padding: 2px 10px;
            background: #0d1220 !important;
            border-radius: 6px;
            font-family: monospace;
            font-size: 11px;
            font-weight: 600;
            border: 2px solid #1a2a4a !important;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #e0e0e0 !important;
        }
        .available-card-list .card-item-mini:hover {
            border-color: #ffd700 !important;
            background: #1a2a4a !important;
            transform: scale(1.05);
        }
        .available-card-list .card-item-mini.selected {
            border-color: #ffd700 !important;
            background: #1a2a4a !important;
            color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15);
        }
        
        /* STAFF CARD REGISTRATION SECTION */
        .card-registration-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-registration-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        /* PORTAL REGISTRATION SECTION */
        .portal-registration-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .portal-registration-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        /* PHOTO UPLOAD MODAL */
        .photo-upload-modal .modal-content {
            background: #131926 !important;
            border-radius: 16px;
            border: 1px solid #1a2a4a;
        }
        .photo-upload-modal .modal-header {
            border-bottom: 1px solid #1a2a4a;
        }
        .photo-upload-modal .modal-footer {
            border-top: 1px solid #1a2a4a;
        }
        .photo-upload-modal .modal-title {
            color: #ffd700 !important;
        }
        .photo-upload-modal .form-control {
            background: #0d1220 !important;
            border: 1px solid #1a2a4a !important;
            color: #e5e7eb !important;
        }
        .photo-upload-modal .form-control:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
        }
        .photo-preview-upload {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1a2a4a;
            margin: 0 auto 15px;
            display: block;
        }
        
        /* ALERTS */
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
        .alert-info {
            background: rgba(59, 130, 246, 0.15) !important;
            border-color: #3b82f6 !important;
            color: #93c5fd !important;
        }
        .btn-close { filter: invert(1) !important; }
        
        /* BORDER & MISC */
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
        
        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1a2a4a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
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
                
                <!-- HEADER -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-tie me-2" style="color: #ffd700;"></i>
                        Staff Management
                        <span class="badge bg-secondary ms-2"><?php echo $totalStaff; ?> total</span>
                        <span class="badge bg-info ms-2"><?php echo $hasPhotoCount; ?> with photo</span>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button class="btn btn-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="fas fa-user-plus me-1"></i> Add Staff
                        </button>
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

                <!-- STATISTICS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $totalStaff; ?></div>
                            <div class="label">Total Staff</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $hasCardCount; ?></div>
                            <div class="label">With Card</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $noCardCount; ?></div>
                            <div class="label">No Card</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $hasPortalCount; ?></div>
                            <div class="label">Portal Access</div>
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
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                <i class="fas fa-user-plus me-1"></i> Add Staff
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($staffList as $staff): 
                            $name = $staff['full_name'] ?? 'Staff';
                            $initials = '';
                            $parts = explode(' ', $name);
                            foreach ($parts as $p) {
                                if (!empty($p)) $initials .= strtoupper($p[0]);
                            }
                            $initials = substr($initials, 0, 2) ?: 'ST';
                            
                            $photoPath = $staff['avatar'] ?? '';
                            $hasPhoto = false;
                            $fullPhotoPath = '';
                            
                            if (!empty($photoPath)) {
                                if (strpos($photoPath, 'uploads/') === 0) {
                                    $fullPhotoPath = '../../' . $photoPath;
                                } else {
                                    $fullPhotoPath = '../../uploads/staff_photos/' . $photoPath;
                                }
                                if (file_exists($fullPhotoPath)) {
                                    $hasPhoto = true;
                                }
                            }
                            
                            $hasPortal = !empty($staff['password_hash']) && $staff['password_hash'] != '';
                        ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="staff-card">
                                    <!-- Staff Avatar -->
                                    <div class="staff-avatar-wrapper">
                                        <div class="staff-avatar">
                                            <?php if ($hasPhoto): ?>
                                                <img src="<?php echo $fullPhotoPath; ?>" 
                                                     alt="<?php echo htmlspecialchars($staff['full_name']); ?>"
                                                     onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                                                <span class="has-photo-badge">
                                                    <i class="fas fa-camera"></i>
                                                </span>
                                            <?php else: ?>
                                                <div class="no-photo"><?php echo $initials; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="name"><?php echo htmlspecialchars($staff['full_name']); ?></div>
                                    <div class="department"><?php echo htmlspecialchars($staff['department'] ?? 'Staff'); ?></div>
                                    <div class="staff-id">
                                        <span class="staff-id-badge">
                                            <?php echo htmlspecialchars($staff['staff_id_number']); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Card UID -->
                                    <div class="mt-2">
                                        <?php if (!empty($staff['card_uid'])): ?>
                                            <span class="card-uid-badge has-card">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($staff['card_uid']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="card-uid-badge no-card">
                                                <i class="fas fa-times-circle me-1"></i>
                                                No Card
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Portal Status -->
                                    <div class="mt-1">
                                        <?php if ($hasPortal): ?>
                                            <span class="portal-badge has-portal">
                                                <i class="fas fa-check-circle me-1"></i> Portal Active
                                            </span>
                                        <?php else: ?>
                                            <span class="portal-badge no-portal">
                                                <i class="fas fa-times-circle me-1"></i> No Portal
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-1">
                                        <span class="text-muted small">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($staff['email']); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Staff Actions -->
                                    <div class="staff-actions">
                                        <button type="button" 
                                                class="btn-upload-photo"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#photoModal<?php echo $staff['staff_id']; ?>">
                                            <i class="fas fa-camera me-1"></i> Photo
                                        </button>
                                        
                                        <?php if (!empty($staff['card_uid'])): ?>
                                            <a href="print-staff-id.php?uid=<?php echo $staff['card_uid']; ?>" 
                                               target="_blank" 
                                               class="btn-print-id">
                                                <i class="fas fa-print me-1"></i> Print ID
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?edit=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <a href="?delete=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this staff?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- PHOTO UPLOAD MODAL -->
                            <div class="modal fade photo-upload-modal" id="photoModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-camera me-2"></i>
                                                Upload Photo - <?php echo htmlspecialchars($staff['full_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                <input type="hidden" name="upload_photo" value="1">
                                                
                                                <div class="text-center mb-3">
                                                    <?php if ($hasPhoto): ?>
                                                        <img src="<?php echo $fullPhotoPath; ?>" 
                                                             alt="<?php echo htmlspecialchars($staff['full_name']); ?>"
                                                             class="photo-preview-upload"
                                                             onerror="this.style.display='none';">
                                                        <p class="text-muted small">Current photo</p>
                                                    <?php else: ?>
                                                        <div class="photo-preview-upload" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#667eea,#764ba2);font-size:48px;color:white;">
                                                            <?php echo $initials; ?>
                                                        </div>
                                                        <p class="text-muted small">No photo yet</p>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <label class="form-label">Select Photo <span class="text-danger">*</span></label>
                                                    <input type="file" class="form-control" name="staff_photo" accept="image/*" required>
                                                    <div class="form-text text-muted small">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        JPG, PNG, GIF, WEBP only. Max 5MB.
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-upload me-1"></i> Upload Photo
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- ============================================================
                STAFF CARD REGISTRATION SECTION (BELOW STAFF LIST)
                ============================================================ -->
                <div class="card-registration-section mt-4">
                    <h5><i class="fas fa-id-card me-2"></i>Staff Card Registration</h5>
                    <p class="text-muted small">Register an RFID card to a staff member who doesn't have one yet.</p>
                    
                    <?php if (empty($staffWithoutCard)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            All staff members already have cards assigned!
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" class="row g-3 align-items-end">
                            <input type="hidden" name="register_card" value="1">
                            
                            <div class="col-md-4">
                                <label class="form-label">Select Staff <span class="text-danger">*</span></label>
                                <select class="form-select" name="staff_id" required>
                                    <option value="">-- Select Staff --</option>
                                    <?php foreach ($staffWithoutCard as $staff): ?>
                                        <option value="<?php echo $staff['staff_id']; ?>">
                                            <?php echo htmlspecialchars($staff['full_name']); ?> 
                                            (<?php echo htmlspecialchars($staff['staff_id_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if (!empty($availableCards)): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Available Cards (Click to auto-fill)</label>
                                    <div class="available-card-list" id="availableCardListRegister">
                                        <?php foreach ($availableCards as $card): ?>
                                            <span class="card-item-mini" data-uid="<?php echo $card['card_uid']; ?>" onclick="selectCard(this, 'cardUidRegister')">
                                                <?php echo $card['card_uid']; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">Click a card above to auto-fill the UID</small>
                                </div>
                            <?php else: ?>
                                <div class="col-md-4">
                                    <label class="form-label">Available Cards</label>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        No available cards in inventory.
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-4">
                                <label class="form-label">Card UID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="card_uid" id="cardUidRegister" placeholder="Enter card UID" required>
                                <div class="form-text text-muted small">Enter UID or click an available card above</div>
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success-custom">
                                    <i class="fas fa-id-card me-1"></i> Register Card
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                STAFF PORTAL REGISTRATION SECTION
                ============================================================ -->
                <div class="portal-registration-section mt-4">
                    <h5><i class="fas fa-user-lock me-2"></i>Staff Portal Registration</h5>
                    <p class="text-muted small">Create portal login account for staff members who don't have one yet.</p>
                    
                    <?php if (empty($staffWithoutPortal)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            All staff members already have portal accounts!
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" class="row g-3 align-items-end">
                            <input type="hidden" name="register_portal" value="1">
                            
                            <div class="col-md-4">
                                <label class="form-label">Select Staff <span class="text-danger">*</span></label>
                                <select class="form-select" name="staff_id" required>
                                    <option value="">-- Select Staff --</option>
                                    <?php foreach ($staffWithoutPortal as $staff): ?>
                                        <option value="<?php echo $staff['staff_id']; ?>">
                                            <?php echo htmlspecialchars($staff['full_name']); ?> 
                                            (<?php echo htmlspecialchars($staff['staff_id_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="portal_password" placeholder="Enter password" required>
                                <div class="form-text text-muted small">Minimum 8 characters</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="portal_confirm" placeholder="Confirm password" required>
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-1"></i> Register Portal Account
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                EDIT STAFF MODAL
                ============================================================ -->
                <?php if ($editMode && $editStaff): ?>
                <div class="modal fade show d-block" id="editModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.7);">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-edit me-2"></i>
                                    Edit Staff
                                </h5>
                                <a href="staff-info.php" class="btn-close"></a>
                            </div>
                            <div class="modal-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="update_staff" value="1">
                                    <input type="hidden" name="staff_id" value="<?php echo $editStaff['staff_id']; ?>">
                                    
                                    <div class="mb-2">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($editStaff['full_name']); ?>" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($editStaff['email']); ?>" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Department</label>
                                        <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($editStaff['department'] ?? 'Staff'); ?>">
                                    </div>
                                    
                                    <div class="mb-2">
                                        <label class="form-label">Staff ID</label>
                                        <div class="form-control" style="background:#0d1220 !important;color:#6b7280 !important;">
                                            <?php echo htmlspecialchars($editStaff['staff_id_number']); ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($editStaff['card_uid'])): ?>
                                        <div class="mb-2">
                                            <label class="form-label">Card UID</label>
                                            <div class="form-control" style="background:#0d1220 !important;color:#34d399 !important;">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($editStaff['card_uid']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-1"></i> Update Staff
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FOOTER -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span><?php echo $hasCardCount; ?> with card, <?php echo $noCardCount; ?> without card</span>
                    <span class="mx-2">|</span>
                    <span><?php echo $hasPortalCount; ?> portal access</span>
                    <span class="mx-2">|</span>
                    <span><?php echo $hasPhotoCount; ?> with photo</span>
                </footer>
            </main>
        </div>
    </div>

    <!-- ADD STAFF MODAL -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>
                        Add New Staff
                        <span class="badge bg-secondary ms-2"><?php echo $nextStaffId; ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="add_staff" value="1">
                        
                        <div class="mb-2">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" placeholder="Enter full name" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" placeholder="staff@example.com" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" placeholder="e.g., Security, Admin, Maintenance">
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            Staff will be added without a card and portal account. You can register them using the forms below.
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i> Add Staff
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // SELECT CARD FROM AVAILABLE LIST
        // ============================================================
        function selectCard(element, inputId) {
            const uid = element.dataset.uid;
            document.getElementById(inputId).value = uid;
            
            const parentList = element.closest('.available-card-list');
            parentList.querySelectorAll('.card-item-mini').forEach(el => {
                el.classList.remove('selected');
            });
            element.classList.add('selected');
        }

        // ============================================================
        // UPDATE TIME
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
        
        // ============================================================
        // PHOTO UPLOAD - AUTO CLOSE MODAL AFTER SUCCESS
        // ============================================================
        <?php if (isset($_GET['photo_uploaded']) && $_GET['photo_uploaded'] == 1): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Close any open modals
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const instance = bootstrap.Modal.getInstance(modal);
                if (instance) {
                    instance.hide();
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
