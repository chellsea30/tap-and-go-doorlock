<?php
/**
 * Tap-and-Go Doorlock - Admin Approve/Reject Handler
 * Handles student registration approval
 * Location: frontend/pages/approve-resident.php
 */

session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// ============================================================
// HANDLE APPROVE
// ============================================================
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $user_id = (int)$_GET['approve'];
    
    // Get student info for logging
    $infoStmt = $conn->prepare("SELECT full_name, student_id FROM users WHERE user_id = ?");
    $infoStmt->bind_param("i", $user_id);
    $infoStmt->execute();
    $infoResult = $infoStmt->get_result();
    $studentInfo = $infoResult->fetch_assoc();
    $infoStmt->close();
    
    if (!$studentInfo) {
        header('Location: residents.php?msg=error&status=pending');
        exit();
    }
    
    // Update user
    $stmt = $conn->prepare("
        UPDATE users 
        SET approval_status = 'approved', 
            approved_by = ?, 
            approved_at = NOW(),
            status = 'active'
        WHERE user_id = ?
    ");
    $stmt->bind_param("ii", $_SESSION['admin_id'], $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log to student_registration_logs
        $logStmt = $conn->prepare("
            INSERT INTO student_registration_logs (user_id, action, details, performed_by)
            VALUES (?, 'approved', ?, ?)
        ");
        $adminName = $_SESSION['full_name'] ?? 'Admin';
        $details = "Registration approved: {$studentInfo['full_name']} ({$studentInfo['student_id']})";
        $logStmt->bind_param("iss", $user_id, $details, $adminName);
        $logStmt->execute();
        $logStmt->close();
        
        // Log to admin audit
        logAudit($_SESSION['admin_id'], 'Approve Student', "Approved student: {$studentInfo['full_name']} ({$studentInfo['student_id']})");
        
        header('Location: residents.php?msg=approved&status=pending');
        exit();
    } else {
        header('Location: residents.php?msg=error&status=pending');
        exit();
    }
}

// ============================================================
// HANDLE REJECT
// ============================================================
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $user_id = (int)$_GET['reject'];
    $reason = isset($_GET['reason']) ? trim($_GET['reason']) : 'Requirements not complete';
    
    // Get student info
    $infoStmt = $conn->prepare("SELECT full_name, student_id FROM users WHERE user_id = ?");
    $infoStmt->bind_param("i", $user_id);
    $infoStmt->execute();
    $infoResult = $infoStmt->get_result();
    $studentInfo = $infoResult->fetch_assoc();
    $infoStmt->close();
    
    if (!$studentInfo) {
        header('Location: residents.php?msg=error&status=pending');
        exit();
    }
    
    // Update user
    $stmt = $conn->prepare("
        UPDATE users 
        SET approval_status = 'rejected', 
            approved_by = ?, 
            approved_at = NOW(),
            rejection_reason = ?,
            status = 'inactive'
        WHERE user_id = ?
    ");
    $stmt->bind_param("isi", $_SESSION['admin_id'], $reason, $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log to student_registration_logs
        $logStmt = $conn->prepare("
            INSERT INTO student_registration_logs (user_id, action, details, performed_by)
            VALUES (?, 'rejected', ?, ?)
        ");
        $adminName = $_SESSION['full_name'] ?? 'Admin';
        $details = "Registration rejected: {$studentInfo['full_name']} ({$studentInfo['student_id']}) - Reason: {$reason}";
        $logStmt->bind_param("iss", $user_id, $details, $adminName);
        $logStmt->execute();
        $logStmt->close();
        
        // Log to admin audit
        logAudit($_SESSION['admin_id'], 'Reject Student', "Rejected student: {$studentInfo['full_name']} ({$studentInfo['student_id']})");
        
        header('Location: residents.php?msg=rejected&status=pending');
        exit();
    } else {
        header('Location: residents.php?msg=error&status=pending');
        exit();
    }
}

// ============================================================
// DEFAULT: Redirect to residents
// ============================================================
header('Location: residents.php');
exit();
?>
