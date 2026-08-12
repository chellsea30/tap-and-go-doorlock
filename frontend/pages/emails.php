<?php
/**
 * Tap-and-Go Doorlock - Email Management
 * View, Update, and Send Emails to Staff & Residents
 * PURE DARK MODE - FIXED LAYOUT SAME AS DASHBOARD
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
$success_email = '';

// ============================================================
// PAGINATION SETTINGS
// ============================================================
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

// ============================================================
// GET ACTIVE TAB
// ============================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'view';
$allowed_tabs = ['view', 'staff', 'residents', 'update-staff', 'update-resident', 'send-email'];
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'view';
}

// ============================================================
// HANDLE UPDATE STAFF EMAIL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff_email'])) {
    $staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $department = trim($_POST['department'] ?? 'Dormitory Staff');
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        if ($staff_id > 0) {
            $stmt = $conn->prepare("UPDATE staff_users SET full_name = ?, email = ?, department = ?, is_active = ? WHERE staff_id = ?");
            $stmt->bind_param("sssii", $full_name, $email, $department, $is_active, $staff_id);
        } else {
            $check = $conn->prepare("SELECT staff_id FROM staff_users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'Staff with this email already exists.';
            } else {
                $stmt = $conn->prepare("INSERT INTO staff_users (full_name, email, department, is_active) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $full_name, $email, $department, $is_active);
            }
            $check->close();
        }
        
        if (isset($stmt) && $stmt->execute()) {
            $success = "✅ Staff " . ($staff_id > 0 ? "updated" : "added") . " successfully!";
            logAudit($_SESSION['admin_id'], 'Update Staff Email', "Updated staff: $full_name");
            header('Location: emails.php?tab=staff&msg=updated');
            exit();
        } else {
            $error = "Failed to update staff: " . $conn->error;
        }
        if (isset($stmt)) $stmt->close();
    }
}

// ============================================================
// HANDLE UPDATE RESIDENT EMAIL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_resident_email'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $course = trim($_POST['course'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Another resident already uses this email.';
        } else {
            if ($user_id > 0) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, room_number = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $full_name, $email, $room_number, $status, $user_id);
            } else {
                $student_id = 'STU-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, room_number, status, student_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $full_name, $email, $room_number, $status, $student_id);
            }
            
            if ($stmt->execute()) {
                $success = "✅ Resident " . ($user_id > 0 ? "updated" : "added") . " successfully!";
                logAudit($_SESSION['admin_id'], 'Update Resident Email', "Updated resident: $full_name");
                header('Location: emails.php?tab=residents&msg=updated');
                exit();
            } else {
                $error = "Failed to update resident: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// HANDLE SEND EMAIL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $recipient_type = $_POST['recipient_type'] ?? 'staff';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $recipient_ids = isset($_POST['recipients']) ? $_POST['recipients'] : [];
    $send_to_all = isset($_POST['send_to_all']) ? true : false;
    
    if (empty($subject) || empty($message)) {
        $error = 'Please fill in subject and message.';
    } elseif (empty($recipient_ids) && !$send_to_all) {
        $error = 'Please select at least one recipient.';
    } else {
        $recipients = [];
        if ($send_to_all) {
            if ($recipient_type === 'staff') {
                $result = $conn->query("SELECT staff_id, full_name, email FROM staff_users WHERE is_active = 1");
                while ($row = $result->fetch_assoc()) {
                    $recipients[] = ['id' => $row['staff_id'], 'name' => $row['full_name'], 'email' => $row['email']];
                }
            } else {
                $result = $conn->query("SELECT user_id, full_name, email FROM users WHERE status = 'active'");
                while ($row = $result->fetch_assoc()) {
                    $recipients[] = ['id' => $row['user_id'], 'name' => $row['full_name'], 'email' => $row['email']];
                }
            }
        } else {
            foreach ($recipient_ids as $id) {
                if ($recipient_type === 'staff') {
                    $stmt = $conn->prepare("SELECT staff_id, full_name, email FROM staff_users WHERE staff_id = ?");
                } else {
                    $stmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE user_id = ?");
                }
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $recipients[] = ['id' => $row['staff_id'] ?? $row['user_id'], 'name' => $row['full_name'], 'email' => $row['email']];
                }
                $stmt->close();
            }
        }
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($recipients as $recipient) {
            $stmt = $conn->prepare("
                INSERT INTO email_logs (
                    recipient_type, recipient_id, recipient_email, recipient_name, 
                    subject, message, sent_by, sent_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $sent_by = $_SESSION['admin_id'];
            $stmt->bind_param("sissssi", 
                $recipient_type, 
                $recipient['id'], 
                $recipient['email'], 
                $recipient['name'],
                $subject, 
                $message, 
                $sent_by
            );
            
            if ($stmt->execute()) {
                $sent_count++;
            } else {
                $failed_count++;
            }
            $stmt->close();
        }
        
        $success_email = "✅ Email sent to $sent_count recipient(s).";
        if ($failed_count > 0) {
            $success_email .= " Failed: $failed_count";
        }
        
        logAudit($_SESSION['admin_id'], 'Send Email', "Sent email to $sent_count $recipient_type(s)");
        header('Location: emails.php?tab=view&msg=sent');
        exit();
    }
}

// ============================================================
// GET STAFF LIST (with pagination)
// ============================================================
$staffList = [];
$staffTotal = 0;

$staffCountResult = $conn->query("SELECT COUNT(*) as total FROM staff_users");
if ($staffCountResult && $row = $staffCountResult->fetch_assoc()) {
    $staffTotal = (int)$row['total'];
}
$staffPages = ceil($staffTotal / $perPage);
if ($staffPages < 1) $staffPages = 1;
$staffOffset = ($page - 1) * $perPage;

$result = $conn->query("SELECT staff_id, full_name, email, department, is_active FROM staff_users ORDER BY full_name LIMIT $perPage OFFSET $staffOffset");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

// ============================================================
// GET RESIDENT LIST (with pagination)
// ============================================================
$residentsList = [];
$residentsTotal = 0;

$resCountResult = $conn->query("SELECT COUNT(*) as total FROM users WHERE status != 'deleted'");
if ($resCountResult && $row = $resCountResult->fetch_assoc()) {
    $residentsTotal = (int)$row['total'];
}
$resPages = ceil($residentsTotal / $perPage);
if ($resPages < 1) $resPages = 1;
$resOffset = ($page - 1) * $perPage;

$result = $conn->query("
    SELECT u.user_id, u.full_name, u.email, u.room_number, u.status, rp.course, rp.year_level
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status != 'deleted'
    ORDER BY u.full_name
    LIMIT $perPage OFFSET $resOffset
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsList[] = $row;
    }
}

// ============================================================
// GET EMAIL LOGS (with pagination)
// ============================================================
$emailLogs = [];
$logsTotal = 0;

$logsCountResult = $conn->query("SELECT COUNT(*) as total FROM email_logs");
if ($logsCountResult && $row = $logsCountResult->fetch_assoc()) {
    $logsTotal = (int)$row['total'];
}
$logsPages = ceil($logsTotal / $perPage);
if ($logsPages < 1) $logsPages = 1;
$logsOffset = ($page - 1) * $perPage;

$result = $conn->query("
    SELECT el.*, au.full_name as admin_name 
    FROM email_logs el
    LEFT JOIN admin_users au ON el.sent_by = au.admin_id
    ORDER BY el.sent_at DESC
    LIMIT $perPage OFFSET $logsOffset
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $emailLogs[] = $row;
    }
}

// ============================================================
// GET TOTAL COUNTS FOR STATS
// ============================================================
$totalStaff = $conn->query("SELECT COUNT(*) as count FROM staff_users")->fetch_assoc()['count'] ?? 0;
$totalResidents = $conn->query("SELECT COUNT(*) as count FROM users WHERE status != 'deleted'")->fetch_assoc()['count'] ?? 0;
$totalEmails = $conn->query("SELECT COUNT(*) as count FROM email_logs")->fetch_assoc()['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Management - Tap-and-Go Doorlock</title>
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
           DARK STAT CARDS - SAME AS DASHBOARD
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white; flex-shrink: 0;
        }
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        
        /* ============================================================
           DARK FORM SECTION
           ============================================================ */
        .form-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .form-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #b8960f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #b0b0c0 !important;
        }
        .form-control, .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #e0e0e0 !important;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .form-control::placeholder { color: #606070 !important; }
        .required { color: #f87171 !important; }
        .text-muted { color: #808090 !important; }
        
        /* ============================================================
           DARK BUTTONS
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,106,0.4);
            color: white !important;
        }
        .btn-success-custom {
            background: #065f46 !important;
            border: none !important;
            color: #34d399 !important;
            padding: 8px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-success-custom:hover {
            background: #0a7a5a !important;
            color: white !important;
        }
        .btn-primary {
            background: #1a3a6a !important;
            border-color: #1a3a6a !important;
            color: white !important;
        }
        .btn-primary:hover {
            background: #2a5a9a !important;
            border-color: #2a5a9a !important;
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .edit-btn {
            background: #2a2a4a !important;
            color: #fcd34d !important;
            border: 1px solid #92400e !important;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        .edit-btn:hover {
            background: #3a3a5a !important;
            color: #fde68a !important;
        }
        
        /* ============================================================
           DARK TABS
           ============================================================ */
        .nav-tabs {
            border-bottom-color: #1a2a4a !important;
        }
        .nav-tabs .nav-link {
            color: #808090 !important;
            font-weight: 500;
            border: none;
        }
        .nav-tabs .nav-link:hover {
            color: #e0e0e0 !important;
        }
        .nav-tabs .nav-link.active {
            color: #93c5fd !important;
            font-weight: 600;
            border-bottom: 2px solid #93c5fd !important;
            background: transparent !important;
        }
        .nav-tabs .nav-link .badge {
            font-size: 10px;
            padding: 2px 8px;
        }
        
        /* ============================================================
           DARK EMAIL ITEMS
           ============================================================ */
        .email-item {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 12px !important;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
        }
        .email-item:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .email-item .subject {
            font-weight: 600;
            color: #e0e0e0 !important;
            font-size: 15px;
        }
        .email-item .recipient {
            font-size: 13px;
            color: #808090 !important;
        }
        .email-item .message-preview {
            font-size: 13px;
            color: #b0b0c0 !important;
            margin: 5px 0;
        }
        .email-item .date {
            font-size: 12px;
            color: #606070 !important;
        }
        .badge-staff { background: #1a2a4a !important; color: #93c5fd !important; }
        .badge-resident { background: #065f46 !important; color: #34d399 !important; }
        
        /* ============================================================
           DARK TABLE
           ============================================================ */
        .table-email {
            color: #e0e0e0 !important;
        }
        .table-email th {
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
        }
        .table-email td {
            border-bottom: 1px solid #1a2a4a !important;
            color: #e0e0e0 !important;
        }
        .table-email tbody tr:hover td {
            background: rgba(255,255,255,0.02) !important;
        }
        
        /* ============================================================
           DARK RECIPIENT GRID
           ============================================================ */
        .recipient-grid {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            background: #1a1a2e !important;
        }
        .recipient-grid .form-check { padding: 4px 0; }
        .recipient-grid .form-check:hover { background: rgba(255,255,255,0.03) !important; border-radius: 4px; }
        .recipient-grid .form-check-label { color: #b0b0c0 !important; }
        .recipient-grid .form-check-input {
            background-color: #1a1a2e !important;
            border-color: #2a2a4a !important;
        }
        .recipient-grid .form-check-input:checked {
            background-color: #1a3a6a !important;
            border-color: #1a3a6a !important;
        }
        
        /* ============================================================
           DARK AVATARS
           ============================================================ */
        .staff-avatar, .resident-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white !important;
            font-weight: 700;
            font-size: 12px;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .staff-avatar { background: linear-gradient(135deg, #4a5a8a, #5a3a7a) !important; }
        .resident-avatar { background: linear-gradient(135deg, #065f46, #0a7a5a) !important; }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge.bg-success { background: #065f46 !important; color: #34d399 !important; }
        .badge.bg-secondary { background: #2a2a3a !important; color: #808090 !important; }
        .badge.bg-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge.bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge.bg-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        
        /* ============================================================
           DARK PAGINATION - SAME AS DASHBOARD
           ============================================================ */
        .pagination-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-top: 20px;
        }
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 3px;
            border: none;
            color: #9090a0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }
        .pagination .page-link:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        .pagination .page-item.disabled .page-link {
            color: #4a4a5a !important;
        }
        .page-info { color: #808090 !important; font-size: 14px; }
        .page-info strong { color: #93c5fd !important; }
        
        /* ============================================================
           PER PAGE SELECTOR - DARK
           ============================================================ */
        .per-page-selector select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
        }
        .per-page-selector select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .per-page-selector label { color: #808090 !important; font-size: 13px; margin: 0; }
        
        /* ============================================================
           DARK ALERTS
           ============================================================ */
        .alert-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
        }
        .alert-info {
            background: #1a2a4a !important;
            border-color: #1a3a6a !important;
            color: #93c5fd !important;
        }
        .alert .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        
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
        
        .pulse-badge {
            animation: pulseBadge 1s infinite;
        }
        @keyframes pulseBadge {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
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
            
            .form-section { padding: 20px; }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            
            .pagination-container .row {
                flex-direction: column;
                gap: 10px;
            }
            .pagination-container .col-md-6 {
                width: 100%;
                text-align: center !important;
            }
            .pagination {
                justify-content: center !important;
            }
            
            .nav-tabs { flex-wrap: nowrap; overflow-x: auto; }
            .nav-tabs .nav-link { font-size: 12px; padding: 8px 12px; white-space: nowrap; }
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
                        <i class="fas fa-envelope me-2" style="color: #1a3a6a;"></i>
                        Email Management
                        <?php if ($totalEmails > 0): ?>
                            <span class="badge bg-secondary ms-2"><?php echo $totalEmails; ?> sent</span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_email)): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-envelope me-2"></i> <?php echo $success_email; ?>
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
                STATS CARDS - SAME AS DASHBOARD
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-user-tie"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalStaff; ?></div>
                                <div class="stat-label">Staff</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalResidents; ?></div>
                                <div class="stat-label">Residents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-envelope"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalEmails; ?></div>
                                <div class="stat-label">Emails Sent</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number"><?php echo date('M d'); ?></div>
                                <div class="stat-label">Today</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                TABS
                ============================================================ -->
                <ul class="nav nav-tabs mb-3" id="emailTabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'view' ? 'active' : ''; ?>" href="?tab=view">
                            <i class="fas fa-inbox me-1"></i> All Emails
                            <span class="badge bg-primary ms-1"><?php echo $totalEmails; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'staff' ? 'active' : ''; ?>" href="?tab=staff">
                            <i class="fas fa-user-tie me-1"></i> Staff
                            <span class="badge bg-primary ms-1"><?php echo $totalStaff; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'residents' ? 'active' : ''; ?>" href="?tab=residents">
                            <i class="fas fa-users me-1"></i> Residents
                            <span class="badge bg-success ms-1"><?php echo $totalResidents; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'send-email' ? 'active' : ''; ?>" href="?tab=send-email">
                            <i class="fas fa-paper-plane me-1"></i> Send Email
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'update-staff' ? 'active' : ''; ?>" href="?tab=update-staff">
                            <i class="fas fa-edit me-1"></i> Update Staff
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'update-resident' ? 'active' : ''; ?>" href="?tab=update-resident">
                            <i class="fas fa-edit me-1"></i> Update Residents
                        </a>
                    </li>
                </ul>

                <!-- ============================================================
                TAB 1: VIEW ALL EMAILS (with pagination)
                ============================================================ -->
                <?php if ($active_tab == 'view'): ?>
                <div class="form-section">
                    <h5><i class="fas fa-history me-2"></i>Email History</h5>
                    <?php if (empty($emailLogs)): ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                            No emails sent yet
                        </p>
                    <?php else: ?>
                        <?php foreach ($emailLogs as $log): ?>
                            <div class="email-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="subject">
                                            <?php echo htmlspecialchars($log['subject']); ?>
                                            <span class="badge <?php echo $log['recipient_type'] == 'staff' ? 'badge-staff' : 'badge-resident'; ?> ms-1">
                                                <?php echo ucfirst($log['recipient_type']); ?>
                                            </span>
                                        </div>
                                        <div class="recipient">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($log['recipient_name']); ?>
                                            <span class="mx-1">•</span>
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($log['recipient_email']); ?>
                                        </div>
                                        <div class="message-preview">
                                            <?php echo nl2br(htmlspecialchars(substr($log['message'], 0, 150))); ?>
                                            <?php if (strlen($log['message']) > 150): ?>...<?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0 ms-3">
                                        <div class="date">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($log['sent_at'])); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="fas fa-user-cog me-1"></i>
                                            <?php echo htmlspecialchars($log['admin_name'] ?? 'Admin'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Pagination for logs -->
                    <?php if ($logsTotal > $perPage): ?>
                    <div class="pagination-container">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?php echo $logsOffset + 1; ?> to <?php echo min($logsOffset + $perPage, $logsTotal); ?> of <?php echo $logsTotal; ?> emails
                                    <span class="mx-1 text-muted">|</span>
                                    <span class="text-muted">Page <?php echo $page; ?> of <?php echo $logsPages; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
                                    <div class="per-page-selector d-flex align-items-center gap-2">
                                        <label>Show:</label>
                                        <select onchange="changePerPageAndTab(this.value, 'view')">
                                            <?php foreach ($perPageOptions as $option): ?>
                                                <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                                                    <?php echo $option; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-end mb-0">
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=view&page=1&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-double-left"></i></a>
                                            </li>
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=view&page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-left"></i></a>
                                            </li>
                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($logsPages, $page + 2);
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?tab=view&page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo ($page >= $logsPages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=view&page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-right"></i></a>
                                            </li>
                                            <li class="page-item <?php echo ($page >= $logsPages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=view&page=<?php echo $logsPages; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-double-right"></i></a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                TAB 2: STAFF LIST (with pagination)
                ============================================================ -->
                <?php if ($active_tab == 'staff'): ?>
                <div class="form-section">
                    <h5><i class="fas fa-user-tie me-2"></i>Staff List</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-email">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staffList)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3">No staff found</td></tr>
                                <?php else: ?>
                                    <?php $i = $staffOffset + 1; foreach ($staffList as $staff): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td>
                                                <span class="staff-avatar">
                                                    <?php 
                                                        $initials = '';
                                                        $parts = explode(' ', $staff['full_name']);
                                                        foreach ($parts as $p) {
                                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                                        }
                                                        echo substr($initials, 0, 2);
                                                    ?>
                                                </span>
                                                <?php echo htmlspecialchars($staff['full_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $staff['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?tab=update-staff&id=<?php echo $staff['staff_id']; ?>" class="edit-btn">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination for staff -->
                    <?php if ($staffTotal > $perPage): ?>
                    <div class="pagination-container">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?php echo $staffOffset + 1; ?> to <?php echo min($staffOffset + $perPage, $staffTotal); ?> of <?php echo $staffTotal; ?> staff
                                    <span class="mx-1 text-muted">|</span>
                                    <span class="text-muted">Page <?php echo $page; ?> of <?php echo $staffPages; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
                                    <div class="per-page-selector d-flex align-items-center gap-2">
                                        <label>Show:</label>
                                        <select onchange="changePerPageAndTab(this.value, 'staff')">
                                            <?php foreach ($perPageOptions as $option): ?>
                                                <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                                                    <?php echo $option; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-end mb-0">
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=staff&page=1&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-double-left"></i></a>
                                            </li>
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=staff&page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-left"></i></a>
                                            </li>
                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($staffPages, $page + 2);
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?tab=staff&page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo ($page >= $staffPages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=staff&page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-right"></i></a>
                                            </li>
                                            <li class="page-item <?php echo ($page >= $staffPages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=staff&page=<?php echo $staffPages; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-double-right"></i></a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                TAB 3: RESIDENT LIST (with pagination)
                ============================================================ -->
                <?php if ($active_tab == 'residents'): ?>
                <div class="form-section">
                    <h5><i class="fas fa-users me-2"></i>Resident List</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-email">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Room</th>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($residentsList)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">No residents found</td></tr>
                                <?php else: ?>
                                    <?php $i = $resOffset + 1; foreach ($residentsList as $resident): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td>
                                                <span class="resident-avatar">
                                                    <?php 
                                                        $initials = '';
                                                        $parts = explode(' ', $resident['full_name']);
                                                        foreach ($parts as $p) {
                                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                                        }
                                                        echo substr($initials, 0, 2);
                                                    ?>
                                                </span>
                                                <?php echo htmlspecialchars($resident['full_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($resident['email']); ?></td>
                                            <td><?php echo htmlspecialchars($resident['room_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $resident['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($resident['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?tab=update-resident&id=<?php echo $resident['user_id']; ?>" class="edit-btn">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination for residents -->
                    <?php if ($residentsTotal > $perPage): ?>
                    <div class="pagination-container">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?php echo $resOffset + 1; ?> to <?php echo min($resOffset + $perPage, $residentsTotal); ?> of <?php echo $residentsTotal; ?> residents
                                    <span class="mx-1 text-muted">|</span>
                                    <span class="text-muted">Page <?php echo $page; ?> of <?php echo $resPages; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
                                    <div class="per-page-selector d-flex align-items-center gap-2">
                                        <label>Show:</label>
                                        <select onchange="changePerPageAndTab(this.value, 'residents')">
                                            <?php foreach ($perPageOptions as $option): ?>
                                                <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                                                    <?php echo $option; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-end mb-0">
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=residents&page=1&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-double-left"></i></a>
                                            </li>
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=residents&page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-left"></i></a>
                                            </li>
                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($resPages, $page + 2);
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?tab=residents&page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo ($page >= $resPages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=residents&page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-right"></i></a>
                                            </li>
                                            <li class="page-item <?php echo ($page >= $resPages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?tab=residents&page=<?php echo $resPages; ?>&per_page=<?php echo $perPage; ?>"><i class="fas fa-angle-double-right"></i></a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                TAB 4: SEND EMAIL
                ============================================================ -->
                <?php if ($active_tab == 'send-email'): ?>
                <div class="form-section">
                    <h5><i class="fas fa-paper-plane me-2"></i>Send Email</h5>
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Recipient Type <span class="required">*</span></label>
                                <select class="form-select" name="recipient_type" id="recipientType" required>
                                    <option value="staff">Staff</option>
                                    <option value="resident">Residents</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject <span class="required">*</span></label>
                                <input type="text" class="form-control" name="subject" placeholder="Email subject" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Message <span class="required">*</span></label>
                                <textarea class="form-control" name="message" rows="5" placeholder="Type your message here..." required></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="send_to_all" id="sendToAll">
                                    <label class="form-check-label" for="sendToAll" style="color:#e0e0e0 !important;">
                                        <strong>Send to All</strong> <span class="text-muted">(Send to all active recipients)</span>
                                    </label>
                                </div>
                                <div id="recipientSelection">
                                    <label class="form-label">Select Recipients</label>
                                    <div class="recipient-grid" id="recipientGrid">
                                        <!-- Will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="send_email" class="btn btn-success-custom">
                                <i class="fas fa-paper-plane me-1"></i> Send Email
                            </button>
                            <a href="?tab=view" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                TAB 5: UPDATE STAFF EMAIL
                ============================================================ -->
                <?php if ($active_tab == 'update-staff'): ?>
                <?php
                    $editStaff = null;
                    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                        $edit_id = (int)$_GET['id'];
                        $stmt = $conn->prepare("SELECT * FROM staff_users WHERE staff_id = ?");
                        $stmt->bind_param("i", $edit_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $editStaff = $result->fetch_assoc();
                        $stmt->close();
                    }
                ?>
                <div class="form-section">
                    <h5><i class="fas fa-user-tie me-2"></i><?php echo $editStaff ? 'Edit Staff' : 'Add New Staff'; ?></h5>
                    <form method="POST" action="">
                        <?php if ($editStaff): ?>
                            <input type="hidden" name="staff_id" value="<?php echo $editStaff['staff_id']; ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($editStaff['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="required">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($editStaff['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($editStaff['department'] ?? 'Dormitory Staff'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="is_active">
                                    <option value="1" <?php echo (isset($editStaff['is_active']) && $editStaff['is_active'] == 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo (isset($editStaff['is_active']) && $editStaff['is_active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update_staff_email" class="btn btn-submit">
                                <i class="fas fa-save me-1"></i> <?php echo $editStaff ? 'Update Staff' : 'Add Staff'; ?>
                            </button>
                            <a href="?tab=staff" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i> Back to Staff
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                TAB 6: UPDATE RESIDENT EMAIL
                ============================================================ -->
                <?php if ($active_tab == 'update-resident'): ?>
                <?php
                    $editResident = null;
                    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                        $edit_id = (int)$_GET['id'];
                        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                        $stmt->bind_param("i", $edit_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $editResident = $result->fetch_assoc();
                        $stmt->close();
                    }
                ?>
                <div class="form-section">
                    <h5><i class="fas fa-users me-2"></i><?php echo $editResident ? 'Edit Resident' : 'Add New Resident'; ?></h5>
                    <form method="POST" action="">
                        <?php if ($editResident): ?>
                            <input type="hidden" name="user_id" value="<?php echo $editResident['user_id']; ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($editResident['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="required">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($editResident['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Room Number</label>
                                <input type="text" class="form-control" name="room_number" value="<?php echo htmlspecialchars($editResident['room_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo (isset($editResident['status']) && $editResident['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (isset($editResident['status']) && $editResident['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="pending" <?php echo (isset($editResident['status']) && $editResident['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="suspended" <?php echo (isset($editResident['status']) && $editResident['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Course</label>
                                <input type="text" class="form-control" name="course" value="<?php echo htmlspecialchars($editResident['course'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update_resident_email" class="btn btn-success-custom">
                                <i class="fas fa-save me-1"></i> <?php echo $editResident ? 'Update Resident' : 'Add Resident'; ?>
                            </button>
                            <a href="?tab=residents" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i> Back to Residents
                            </a>
                        </div>
                    </form>
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
                    <span>Total: <?php echo $totalEmails; ?> emails sent</span>
                    <span class="mx-2">|</span>
                    <span class="text-primary"><i class="fas fa-user-tie me-1"></i><?php echo $totalStaff; ?> staff</span>
                    <span class="mx-2">|</span>
                    <span class="text-success"><i class="fas fa-users me-1"></i><?php echo $totalResidents; ?> residents</span>
                </footer>

            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // CHANGE PER PAGE AND TAB
        // ============================================================
        function changePerPageAndTab(value, tab) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            urlParams.set('tab', tab);
            window.location.href = '?' + urlParams.toString();
        }
        
        // ============================================================
        // LOAD RECIPIENTS FOR SEND EMAIL
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const recipientType = document.getElementById('recipientType');
            const sendToAll = document.getElementById('sendToAll');
            const recipientGrid = document.getElementById('recipientGrid');
            
            if (recipientType && recipientGrid) {
                function loadRecipients() {
                    const type = recipientType.value;
                    fetch('api/get_recipients.php?type=' + type)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                recipientGrid.innerHTML = '';
                                data.recipients.forEach(function(recipient) {
                                    const div = document.createElement('div');
                                    div.className = 'form-check';
                                    div.innerHTML = `
                                        <input class="form-check-input" type="checkbox" name="recipients[]" value="${recipient.id}" id="rec_${recipient.id}">
                                        <label class="form-check-label" for="rec_${recipient.id}">
                                            ${recipient.name} <span class="text-muted">(${recipient.email})</span>
                                        </label>
                                    `;
                                    recipientGrid.appendChild(div);
                                });
                            }
                        })
                        .catch(err => console.log('Error loading recipients:', err));
                }
                
                loadRecipients();
                recipientType.addEventListener('change', loadRecipients);
                
                if (sendToAll) {
                    sendToAll.addEventListener('change', function() {
                        const selection = document.getElementById('recipientSelection');
                        if (this.checked) {
                            selection.style.display = 'none';
                        } else {
                            selection.style.display = 'block';
                        }
                    });
                }
            }
        });
        
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