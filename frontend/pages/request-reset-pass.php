<?php
/**
 * Tap-and-Go Doorlock - Password Reset Requests (Admin)
 * FIXED: Proper URL generation for Railway
 * ✅ PERMANENT HISTORY (hindi nabubura)
 * ✅ AUTO-EXPIRE DETECTION
 * ✅ DELETE BUTTON sa history
 * ✅ AUTO-FIX: Ensures 'expired' in enum
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

include '../includes/header.php';

$conn = getDBConnection();
$success = '';
$error = '';
$generated_link = '';

// ============================================================
// ✅ AUTO-FIX: Ensure 'expired' is in status enum
// ============================================================
try {
    $checkEnum = $conn->query("
        SELECT COLUMN_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'password_reset_requests' 
        AND COLUMN_NAME = 'status'
    ");
    
    if ($checkEnum && $row = $checkEnum->fetch_assoc()) {
        $columnType = $row['COLUMN_TYPE'];
        
        if (strpos($columnType, "'expired'") === false) {
            $conn->query("
                ALTER TABLE password_reset_requests 
                MODIFY COLUMN status ENUM('pending', 'approved', 'denied', 'completed', 'expired') 
                DEFAULT 'pending'
            ");
            error_log("✅ Added 'expired' to password_reset_requests.status enum");
        }
    }
} catch (Exception $e) {
    error_log("⚠️ Enum check failed: " . $e->getMessage());
}

// ============================================================
// AUTO-EXPIRE: Mark approved requests as expired
// ============================================================
try {
    $conn->query("
        UPDATE password_reset_requests 
        SET status = 'expired'
        WHERE status = 'approved' 
        AND token_expires_at IS NOT NULL 
        AND token_expires_at < NOW()
    ");
} catch (Exception $e) {
    error_log("⚠️ Auto-expire failed: " . $e->getMessage());
}

// ============================================================
// FIXED: Get proper base URL for Railway
// ============================================================
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $host = preg_replace('/:\d+$/', '', $host);
    return $protocol . $host;
}

// ============================================================
// HANDLE APPROVE REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $request_id = (int)$_POST['request_id'];
    $admin_response = trim($_POST['admin_response'] ?? '');
    
    $reset_token = bin2hex(random_bytes(32));
    $token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $conn->prepare("
        SELECT s.*, r.student_id 
        FROM password_reset_requests r
        JOIN student_users s ON r.student_id = s.student_id
        WHERE r.request_id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if ($student) {
        $stmt = $conn->prepare("
            UPDATE password_reset_requests 
            SET status = 'approved', 
                reset_token = ?, 
                token_expires_at = ?,
                admin_response = ?, 
                responded_at = NOW() 
            WHERE request_id = ?
        ");
        $stmt->bind_param("sssi", $reset_token, $token_expires, $admin_response, $request_id);
        $stmt->execute();
        $stmt->close();
        
        $base_url = getBaseUrl();
        $reset_link = $base_url . '/frontend/pages/student/reset-password.php?token=' . $reset_token;
        
        logAudit($_SESSION['admin_id'], 'Password Reset Approved', "Approved reset for: {$student['full_name']}");
        $success = "✅ Reset request approved!";
        $generated_link = $reset_link;
        
        $_SESSION['generated_link'] = $reset_link;
        $_SESSION['generated_student'] = $student['full_name'];
    }
}

// ============================================================
// HANDLE DENY REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deny'])) {
    $request_id = (int)$_POST['request_id'];
    $admin_response = trim($_POST['admin_response'] ?? '');
    
    $stmt = $conn->prepare("
        UPDATE password_reset_requests 
        SET status = 'denied', admin_response = ?, responded_at = NOW() 
        WHERE request_id = ?
    ");
    $stmt->bind_param("si", $admin_response, $request_id);
    $stmt->execute();
    $stmt->close();
    
    logAudit($_SESSION['admin_id'], 'Password Reset Denied', "Denied reset for request ID: $request_id");
    $success = "✅ Request denied successfully.";
}

// ============================================================
// HANDLE DELETE SINGLE HISTORY
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $check = $conn->prepare("SELECT status FROM password_reset_requests WHERE request_id = ?");
    $check->bind_param("i", $delete_id);
    $check->execute();
    $checkResult = $check->get_result();
    $row = $checkResult->fetch_assoc();
    $check->close();
    
    if ($row && $row['status'] != 'pending') {
        $stmt = $conn->prepare("DELETE FROM password_reset_requests WHERE request_id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $success = "✅ Request deleted from history.";
            logAudit($_SESSION['admin_id'], 'Delete Reset Request', "Deleted request ID: $delete_id");
        } else {
            $error = "Failed to delete: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Cannot delete pending requests. Process them first.";
    }
    header('Location: request-reset-pass.php?deleted=1');
    exit();
}

// ============================================================
// HANDLE CLEAR ALL HISTORY
// ============================================================
if (isset($_GET['clear_history']) && $_GET['clear_history'] === '1') {
    $stmt = $conn->prepare("
        DELETE FROM password_reset_requests 
        WHERE status IN ('approved', 'denied', 'completed', 'expired')
    ");
    if ($stmt->execute()) {
        $count = $stmt->affected_rows;
        $success = "✅ Cleared $count history record(s).";
        logAudit($_SESSION['admin_id'], 'Clear Reset History', "Cleared $count records");
    } else {
        $error = "Failed to clear: " . $stmt->error;
    }
    $stmt->close();
    header('Location: request-reset-pass.php?cleared=1');
    exit();
}

// ============================================================
// HANDLE REGENERATE TOKEN
// ============================================================
if (isset($_GET['regenerate']) && !empty($_GET['regenerate'])) {
    $request_id = (int)$_GET['regenerate'];
    
    $reset_token = bin2hex(random_bytes(32));
    $token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $conn->prepare("
        UPDATE password_reset_requests 
        SET status = 'approved',
            reset_token = ?, 
            token_expires_at = ?
        WHERE request_id = ?
    ");
    $stmt->bind_param("ssi", $reset_token, $token_expires, $request_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("
        SELECT s.full_name 
        FROM password_reset_requests r
        JOIN student_users s ON r.student_id = s.student_id
        WHERE r.request_id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    $base_url = getBaseUrl();
    $reset_link = $base_url . '/frontend/pages/student/reset-password.php?token=' . $reset_token;
    
    $success = "✅ New reset link generated!";
    $generated_link = $reset_link;
    $_SESSION['generated_link'] = $reset_link;
    $_SESSION['generated_student'] = $student['full_name'] ?? 'Student';
}

// ============================================================
// GET ALL REQUESTS
// ============================================================
$pendingRequests = [];
$historyRequests = [];

$result = $conn->query("
    SELECT r.*, s.full_name, s.username, s.email, s.student_id_number
    FROM password_reset_requests r
    JOIN student_users s ON r.student_id = s.student_id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingRequests[] = $row;
    }
}

$result = $conn->query("
    SELECT r.*, s.full_name, s.username, s.email, s.student_id_number
    FROM password_reset_requests r
    JOIN student_users s ON r.student_id = s.student_id
    WHERE r.status != 'pending'
    ORDER BY 
        CASE 
            WHEN r.status = 'completed' THEN 0
            WHEN r.status = 'expired' THEN 1
            WHEN r.status = 'approved' THEN 2
            WHEN r.status = 'denied' THEN 3
            ELSE 4
        END,
        r.responded_at DESC
    LIMIT 200
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $historyRequests[] = $row;
    }
}

$stats = [
    'total' => count($pendingRequests) + count($historyRequests),
    'pending' => count($pendingRequests),
    'approved' => 0,
    'denied' => 0,
    'completed' => 0,
    'expired' => 0
];

foreach ($historyRequests as $req) {
    if ($req['status'] == 'approved') $stats['approved']++;
    elseif ($req['status'] == 'denied') $stats['denied']++;
    elseif ($req['status'] == 'completed') $stats['completed']++;
    elseif ($req['status'] == 'expired') $stats['expired']++;
}

if (isset($_SESSION['generated_link']) && empty($generated_link)) {
    $generated_link = $_SESSION['generated_link'];
    $generated_student = $_SESSION['generated_student'] ?? 'Student';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Requests - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
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
        
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important; left: 0 !important; right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
        }
        .sidebar .nav-link { color: #9090a0 !important; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.05) !important; color: #e0e0e0 !important; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important; color: white !important; }
        .sidebar-footer { border-top-color: #1a2a4a !important; }
        .sidebar-footer .text-muted { color: #606070 !important; }
        
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
        .stat-number.text-warning { color: #fbbf24 !important; }
        .stat-number.text-success { color: #34d399 !important; }
        .stat-number.text-danger { color: #f87171 !important; }
        .stat-number.text-secondary { color: #9ca3af !important; }
        
        .pulse-badge { animation: pulseBadge 1s infinite; }
        @keyframes pulseBadge {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .card-header {
            background: #111827 !important;
            border-bottom: 1px solid #1a2a4a !important;
            border-radius: 16px 16px 0 0 !important;
            padding: 14px 20px;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #e0e0e0; font-size: 16px; }
        .card-body { padding: 20px; background: #111827 !important; }
        
        .request-card {
            background: #1a1a2e !important;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 12px;
            border-left: 4px solid #f59e0b;
            border: 1px solid #1a2a4a;
            transition: all 0.3s ease;
        }
        .request-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .request-card.history-approved { border-left-color: #ffd700; }
        .request-card.history-denied { border-left-color: #ef4444; }
        .request-card.history-completed { border-left-color: #10b981; }
        .request-card.history-expired { border-left-color: #6b7280; opacity: 0.85; }
        
        .request-card .student-name {
            font-weight: 600;
            color: #ffd700 !important;
            font-size: 15px;
        }
        .request-card .student-name i { color: #8b5cf6 !important; }
        .request-card .detail { font-size: 13px; color: #9ca3af !important; }
        .request-card .detail i { color: #6b7280 !important; }
        .request-card .reason { color: #d1d5db !important; margin: 6px 0; font-size: 13px; }
        .request-card .reason i { color: #8b5cf6 !important; }
        .request-card .meta { font-size: 12px; color: #6b7280 !important; }
        .request-card .meta i { color: #6b7280 !important; }
        
        .request-card .reset-link-box {
            background: #0a0e1a !important;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 8px;
            border: 1px solid #1a2a4a;
            word-break: break-all;
        }
        .request-card .reset-link-box.expired {
            border-color: #7a2a2a;
            background: #1a0a0a !important;
        }
        .request-card .reset-link-box .link { color: #93c5fd !important; font-size: 13px; }
        .request-card .reset-link-box.expired .link { color: #6b7280 !important; text-decoration: line-through; }
        .request-card .reset-link-box .copy-btn {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 2px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .request-card .reset-link-box .copy-btn:hover { background: rgba(59, 130, 246, 0.3); }
        
        .badge-pending { background: rgba(245, 158, 11, 0.2) !important; color: #fbbf24 !important; }
        .badge-approved { background: rgba(255, 215, 0, 0.2) !important; color: #ffd700 !important; }
        .badge-denied { background: rgba(239, 68, 68, 0.2) !important; color: #fca5a5 !important; }
        .badge-completed { background: rgba(16, 185, 129, 0.2) !important; color: #6ee7b7 !important; }
        .badge-expired { background: rgba(107, 114, 128, 0.3) !important; color: #9ca3af !important; }
        
        .btn-approve {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
            border: 1px solid rgba(16, 185, 129, 0.3) !important;
            padding: 6px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-approve:hover { background: rgba(16, 185, 129, 0.3) !important; color: #6ee7b7 !important; transform: translateY(-1px); }
        
        .btn-deny {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
            border: 1px solid rgba(239, 68, 68, 0.3) !important;
            padding: 6px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-deny:hover { background: rgba(239, 68, 68, 0.3) !important; color: #fca5a5 !important; transform: translateY(-1px); }
        
        .btn-regenerate {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
            padding: 4px 14px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-regenerate:hover { background: rgba(59, 130, 246, 0.3) !important; color: #93c5fd !important; }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #f87171 !important;
            border: 1px solid rgba(239, 68, 68, 0.3) !important;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.3) !important; color: #fca5a5 !important; transform: scale(1.05); }
        
        .expired-badge {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
            border: 1px solid rgba(239, 68, 68, 0.4) !important;
            padding: 2px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            animation: pulseRed 1.5s infinite;
        }
        @keyframes pulseRed {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .form-control {
            background: #0d1220 !important;
            border: 1px solid #1a2a4a !important;
            color: #e5e7eb !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
        }
        .form-control:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        .form-control::placeholder { color: #6b7280 !important; }
        
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
        
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #6b7280 !important; }
        .text-success { color: #34d399 !important; }
        .text-danger { color: #f87171 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-primary { color: #93c5fd !important; }
        .text-secondary { color: #9ca3af !important; }
        
        .live-indicator {
            display: inline-block;
            width: 8px; height: 8px;
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
        
        .btn-clear-history {
            background: rgba(107, 114, 128, 0.2) !important;
            color: #9ca3af !important;
            border: 1px solid rgba(107, 114, 128, 0.3) !important;
            padding: 5px 14px;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-clear-history:hover {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1a2a4a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px; bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-key me-2" style="color: #ffd700;"></i>
                        Password Reset Requests
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge bg-danger ms-2 pulse-badge">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?php echo $stats['pending']; ?> pending
                            </span>
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
                        <?php if (!empty($generated_link)): ?>
                            <div class="mt-2">
                                <strong>Reset Link:</strong>
                                <div class="bg-dark p-2 rounded mt-1" style="word-break: break-all; background: #0a0e1a !important; border: 1px solid #1a2a4a;">
                                    <code style="color: #93c5fd;"><?php echo htmlspecialchars($generated_link); ?></code>
                                </div>
                                <button class="btn btn-sm btn-primary mt-2" onclick="copyLink('<?php echo htmlspecialchars($generated_link); ?>')">
                                    <i class="fas fa-copy me-1"></i> Copy Link
                                </button>
                                <small class="text-muted ms-2">Share this link with the student</small>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- STATS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-list"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['pending'] > 0 ? 'text-warning' : ''; ?>">
                                    <?php echo $stats['pending']; ?>
                                </div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <?php if ($stats['pending'] > 0): ?>
                                <span class="badge bg-danger pulse-badge" style="position:absolute; top:8px; right:8px;"><?php echo $stats['pending']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ffd700;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['approved']; ?></div>
                                <div class="stat-label">Approved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-double"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['completed']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-hourglass-end"></i></div>
                            <div>
                                <div class="stat-number text-secondary"><?php echo $stats['expired']; ?></div>
                                <div class="stat-label">Expired</div>
                            </div>
                            <?php if ($stats['expired'] > 0): ?>
                                <span class="badge bg-secondary pulse-badge" style="position:absolute; top:8px; right:8px;"><?php echo $stats['expired']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-times-circle"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['denied'] > 0 ? 'text-danger' : ''; ?>">
                                    <?php echo $stats['denied']; ?>
                                </div>
                                <div class="stat-label">Denied</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PENDING REQUESTS -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5><i class="fas fa-clock me-2"></i>Pending Requests</h5>
                            <span class="text-muted small"><?php echo $stats['pending']; ?> requests waiting</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingRequests)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5 class="text-muted">No pending requests</h5>
                                <p class="text-muted small">All password reset requests have been processed</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingRequests as $request): ?>
                                <div class="request-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="student-name">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($request['full_name']); ?>
                                                <span class="badge badge-pending ms-2">Pending</span>
                                            </div>
                                            <div class="detail">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($request['student_id_number']); ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($request['username']); ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($request['email']); ?>
                                            </div>
                                            <div class="reason">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php echo nl2br(htmlspecialchars($request['reason'] ?: 'No reason provided')); ?>
                                            </div>
                                            <div class="meta">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                Requested: <?php echo date('M d, Y h:i A', strtotime($request['requested_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <form method="POST" class="mt-2 mt-md-0">
                                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                <div class="row g-2">
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="admin_response" placeholder="Response (optional)">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex gap-1">
                                                            <button type="submit" name="approve" class="btn-approve" onclick="return confirm('Approve reset request for <?php echo htmlspecialchars($request['full_name']); ?>?')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="submit" name="deny" class="btn-deny" onclick="return confirm('Deny reset request for <?php echo htmlspecialchars($request['full_name']); ?>?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Approve = Generate reset link</small>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- HISTORY -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5><i class="fas fa-history me-2"></i>Request History 
                                    <span class="badge bg-secondary ms-2"><?php echo count($historyRequests); ?></span>
                                </h5>
                                <small class="text-muted">Permanent record - hindi nabubura</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">
                                    <?php echo $stats['approved']; ?> approved | 
                                    <?php echo $stats['completed']; ?> completed | 
                                    <?php echo $stats['expired']; ?> expired | 
                                    <?php echo $stats['denied']; ?> denied
                                </span>
                                <?php if (!empty($historyRequests)): ?>
                                    <a href="?clear_history=1" 
                                       class="btn-clear-history"
                                       onclick="return confirm('⚠️ Are you sure you want to CLEAR ALL history?\n\nThis will delete ALL approved, denied, completed, and expired records.\nThis action CANNOT be undone!')">
                                        <i class="fas fa-trash-alt me-1"></i> Clear All History
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historyRequests)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No history available</h5>
                                <p class="text-muted small">Processed requests will appear here</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($historyRequests as $request): 
                                $isApproved = $request['status'] == 'approved';
                                $isCompleted = $request['status'] == 'completed';
                                $isDenied = $request['status'] == 'denied';
                                $isExpired = $request['status'] == 'expired';
                                
                                $isActuallyExpired = false;
                                if ($isApproved && !empty($request['token_expires_at'])) {
                                    $isActuallyExpired = strtotime($request['token_expires_at']) < time();
                                }
                                
                                $badgeClass = $isApproved ? 'badge-approved' : 
                                              ($isCompleted ? 'badge-completed' : 
                                              ($isExpired || $isActuallyExpired ? 'badge-expired' : 'badge-denied'));
                                
                                $cardClass = $isApproved ? 'history-approved' : 
                                             ($isCompleted ? 'history-completed' : 
                                             ($isExpired || $isActuallyExpired ? 'history-expired' : 'history-denied'));
                                
                                $statusIcon = $isApproved ? 'fa-clock' : 
                                              ($isCompleted ? 'fa-check-double' : 
                                              ($isExpired || $isActuallyExpired ? 'fa-hourglass-end' : 'fa-times-circle'));
                                
                                $statusText = $isApproved ? ($isActuallyExpired ? 'Expired' : 'Approved - Waiting') : 
                                              ($isCompleted ? 'Completed' : 
                                              ($isExpired ? 'Expired' : 'Denied'));
                            ?>
                                <div class="request-card <?php echo $cardClass; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-9">
                                            <div class="student-name">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($request['full_name']); ?>
                                                <span class="badge <?php echo $badgeClass; ?> ms-2">
                                                    <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                    <?php echo $statusText; ?>
                                                </span>
                                                <?php if ($isActuallyExpired): ?>
                                                    <span class="expired-badge ms-2">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>EXPIRED
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="detail">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($request['student_id_number']); ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($request['email']); ?>
                                            </div>
                                            <div class="reason">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php echo nl2br(htmlspecialchars($request['reason'] ?: 'No reason provided')); ?>
                                            </div>
                                            
                                            <?php if (($isApproved || $isExpired || $isActuallyExpired) && !empty($request['reset_token'])): 
                                                $base_url = getBaseUrl();
                                                $reset_link = $base_url . '/frontend/pages/student/reset-password.php?token=' . $request['reset_token'];
                                                $linkExpired = $isActuallyExpired || $isExpired;
                                            ?>
                                                <div class="reset-link-box <?php echo $linkExpired ? 'expired' : ''; ?>">
                                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                        <span class="link">
                                                            <i class="fas fa-link me-1"></i> 
                                                            <?php echo htmlspecialchars($reset_link); ?>
                                                        </span>
                                                        <div class="d-flex gap-1">
                                                            <?php if (!$linkExpired): ?>
                                                                <button class="copy-btn" onclick="copyLink('<?php echo htmlspecialchars($reset_link); ?>')">
                                                                    <i class="fas fa-copy me-1"></i> Copy
                                                                </button>
                                                            <?php endif; ?>
                                                            <a href="?regenerate=<?php echo $request['request_id']; ?>" 
                                                               class="btn-regenerate" 
                                                               onclick="return confirm('Generate NEW reset link? The old link will stop working.')">
                                                                <i class="fas fa-sync-alt me-1"></i> <?php echo $linkExpired ? 'Regenerate' : 'New'; ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="text-muted small mt-1">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Expires: <?php echo date('M d, Y h:i A', strtotime($request['token_expires_at'])); ?>
                                                        <?php if ($linkExpired): ?>
                                                            <span class="expired-badge ms-2">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>EXPIRED
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($request['admin_response'])): ?>
                                                <div class="reset-link-box" style="border-color: #ffd700;">
                                                    <strong style="color: #ffd700;"><i class="fas fa-reply me-1"></i> Admin:</strong>
                                                    <?php echo nl2br(htmlspecialchars($request['admin_response'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="meta">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                <?php if ($request['responded_at']): ?>
                                                    Processed: <?php echo date('M d, Y h:i A', strtotime($request['responded_at'])); ?>
                                                <?php else: ?>
                                                    Requested: <?php echo date('M d, Y h:i A', strtotime($request['requested_at'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-md-end">
                                            <div class="d-flex flex-column gap-2 align-items-end">
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                    <?php echo $isApproved ? ($isActuallyExpired ? 'Expired' : 'Approved') : 
                                                        ($isCompleted ? 'Completed' : 
                                                        ($isExpired ? 'Expired' : 'Denied')); ?>
                                                </span>
                                                <a href="?delete=<?php echo $request['request_id']; ?>" 
                                                   class="btn-delete"
                                                   onclick="return confirm('⚠️ Delete this history record?\n\nStudent: <?php echo htmlspecialchars($request['full_name']); ?>\nStatus: <?php echo $statusText; ?>\n\nThis action CANNOT be undone!')">
                                                    <i class="fas fa-trash me-1"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- FOOTER -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span>Total: <?php echo $stats['total']; ?> reset requests</span>
                    <?php if ($stats['pending'] > 0): ?>
                        <span class="text-warning ms-3">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $stats['pending']; ?> pending
                        </span>
                    <?php endif; ?>
                    <?php if ($stats['expired'] > 0): ?>
                        <span class="text-secondary ms-3">
                            <i class="fas fa-hourglass-end me-1"></i>
                            <?php echo $stats['expired']; ?> expired
                        </span>
                    <?php endif; ?>
                </footer>

            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyLink(link) {
            navigator.clipboard.writeText(link).then(function() {
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
                setTimeout(function() {
                    btn.innerHTML = originalText;
                }, 2000);
            }).catch(function() {
                const input = document.createElement('input');
                input.value = link;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                alert('Link copied to clipboard!');
            });
        }

        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            const updateElement = document.getElementById('lastUpdate');
            if (updateElement) updateElement.textContent = 'Updated: ' + timeString;
            
            const serverTimeElement = document.getElementById('serverTime');
            if (serverTimeElement) {
                const dateString = now.toLocaleDateString('en-US', { 
                    month: 'long', day: 'numeric', year: 'numeric' 
                });
                serverTimeElement.textContent = 'Server Time: ' + dateString + ' ' + timeString;
            }
        }

        setInterval(updateLastUpdateTime, 10000);
        document.addEventListener('DOMContentLoaded', updateLastUpdateTime);
        
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
