<?php
/**
 * Tap-and-Go Doorlock - Visitors Management
 * DARK MODE - FULLY READABLE
 * WITH EXPIRY DATE AND CARD STATUS
 * WITH COUNTDOWN TIMER FOR EXPIRING CARDS & VISITS
 * FIXED LAYOUT SAME AS DASHBOARD
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php'; 

$conn = getDBConnection();

// ============================================================
// AUTO-CHECK EXPIRED CARDS ON PAGE LOAD
// ============================================================
$expired_deactivated = checkExpiredVisitorCards();
if ($expired_deactivated > 0) {
    $success = "✅ $expired_deactivated expired visitor card(s) have been automatically deactivated.";
}

// ============================================================
// HANDLE DELETE
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM visitor_logs WHERE visitor_log_id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $success = "Visitor record deleted successfully!";
        logAudit($_SESSION['admin_id'], 'Delete Visitor', "Deleted visitor record ID: $delete_id");
    } else {
        $error = "Failed to delete: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// HANDLE CHECK-IN / CHECK-OUT
// ============================================================
if (isset($_GET['checkin']) && is_numeric($_GET['checkin'])) {
    $visitor_id = (int)$_GET['checkin'];
    
    $checkStmt = $conn->prepare("
        SELECT v.*, c.expiry_date, c.status as card_status 
        FROM visitor_logs v
        LEFT JOIN rfid_cards c ON v.temporary_card_uid = c.card_uid
        WHERE v.visitor_log_id = ?
    ");
    $checkStmt->bind_param("i", $visitor_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $visitorData = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($visitorData && $visitorData['card_status'] == 'expired') {
        $error = "❌ Cannot check in. Visitor card has expired.";
    } elseif ($visitorData && $visitorData['expiry_date'] && strtotime($visitorData['expiry_date']) < time()) {
        $error = "❌ Cannot check in. Visitor card expired on " . date('M d, Y', strtotime($visitorData['expiry_date']));
    } else {
        $stmt = $conn->prepare("UPDATE visitor_logs SET entry_timestamp = NOW(), access_status = 'granted' WHERE visitor_log_id = ?");
        $stmt->bind_param("i", $visitor_id);
        
        if ($stmt->execute()) {
            $success = "Visitor checked in successfully!";
            logAudit($_SESSION['admin_id'], 'Visitor Check In', "Checked in visitor ID: $visitor_id");
        } else {
            $error = "Failed to check in: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['checkout']) && is_numeric($_GET['checkout'])) {
    $visitor_id = (int)$_GET['checkout'];
    
    $stmt = $conn->prepare("UPDATE visitor_logs SET exit_timestamp = NOW() WHERE visitor_log_id = ?");
    $stmt->bind_param("i", $visitor_id);
    
    if ($stmt->execute()) {
        $success = "Visitor checked out successfully!";
        logAudit($_SESSION['admin_id'], 'Visitor Check Out', "Checked out visitor ID: $visitor_id");
    } else {
        $error = "Failed to check out: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// HANDLE RENEW CARD
// ============================================================
if (isset($_GET['renew']) && !empty($_GET['renew'])) {
    $card_uid = $_GET['renew'];
    $new_expiry = date('Y-m-d', strtotime('+1 year'));
    
    $stmt = $conn->prepare("UPDATE rfid_cards SET expiry_date = ?, status = 'active' WHERE card_uid = ?");
    $stmt->bind_param("ss", $new_expiry, $card_uid);
    
    if ($stmt->execute()) {
        $success = "✅ Card renewed successfully! New expiry: " . date('M d, Y', strtotime($new_expiry));
        logAudit($_SESSION['admin_id'], 'Renew Card', "Renewed card: $card_uid");
    } else {
        $error = "Failed to renew card: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// HANDLE ADD/EDIT VISITOR
// ============================================================
$visitor = null;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM visitor_logs WHERE visitor_log_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $visitor = $result->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $resident_visited = (int)($_POST['resident_visited'] ?? 0);
    $purpose = trim($_POST['purpose_of_visit'] ?? '');
    $validity_start = $_POST['validity_start'] ?? date('Y-m-d');
    $validity_end = $_POST['validity_end'] ?? date('Y-m-d', strtotime('+1 week'));
    $access_status = $_POST['access_status'] ?? 'pending';
    $temporary_card_uid = trim($_POST['temporary_card_uid'] ?? '');
    
    if (empty($visitor_name) || empty($resident_visited) || empty($purpose)) {
        $error = 'Please fill in all required fields.';
    } else {
        if (!empty($temporary_card_uid)) {
            $cardCheck = $conn->prepare("SELECT status, expiry_date FROM rfid_cards WHERE card_uid = ?");
            $cardCheck->bind_param("s", $temporary_card_uid);
            $cardCheck->execute();
            $cardResult = $cardCheck->get_result();
            $cardData = $cardResult->fetch_assoc();
            $cardCheck->close();
            
            if ($cardData && $cardData['status'] == 'expired') {
                $error = "❌ Cannot assign expired card. Please activate a new card.";
            }
        }
        
        if (empty($error)) {
            if ($edit_id > 0) {
                $stmt = $conn->prepare("
                    UPDATE visitor_logs SET 
                        visitor_name = ?, 
                        resident_visited = ?, 
                        purpose_of_visit = ?, 
                        validity_start = ?, 
                        validity_end = ?, 
                        access_status = ?,
                        temporary_card_uid = ?
                    WHERE visitor_log_id = ?
                ");
                $stmt->bind_param("sisssssi", $visitor_name, $resident_visited, $purpose, $validity_start, $validity_end, $access_status, $temporary_card_uid, $edit_id);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO visitor_logs (
                        visitor_name, resident_visited, purpose_of_visit, 
                        validity_start, validity_end, access_status, 
                        temporary_card_uid, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("sisssss", $visitor_name, $resident_visited, $purpose, $validity_start, $validity_end, $access_status, $temporary_card_uid);
            }
            
            if ($stmt->execute()) {
                $success = $edit_id > 0 ? "Visitor updated successfully!" : "Visitor registered successfully!";
                logAudit($_SESSION['admin_id'], $edit_id > 0 ? 'Update Visitor' : 'Register Visitor', 
                         ($edit_id > 0 ? "Updated" : "Registered") . " visitor: $visitor_name");
                $edit_id = 0;
                $visitor = null;
            } else {
                $error = "Failed to save: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ============================================================
// GET RESIDENTS LIST FOR DROPDOWN
// ============================================================
$residentsList = [];
$result = $conn->query("SELECT user_id, full_name, room_number FROM users WHERE status = 'active' ORDER BY full_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsList[] = $row;
    }
}

// ============================================================
// GET AVAILABLE CARDS FOR DROPDOWN
// ============================================================
$availableCards = [];
$result = $conn->query("
    SELECT card_uid 
    FROM rfid_cards 
    WHERE status = 'active' 
    AND card_type = 'visitor'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ORDER BY card_uid
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $availableCards[] = $row['card_uid'];
    }
}

// ============================================================
// GET VISITORS LIST WITH CARD INFO
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchQuery = '';
if (!empty($search)) {
    $searchQuery = " AND (v.visitor_name LIKE '%$search%' OR u.full_name LIKE '%$search%' OR v.temporary_card_uid LIKE '%$search%')";
}

$countResult = $conn->query("
    SELECT COUNT(*) as total 
    FROM visitor_logs v
    LEFT JOIN users u ON v.resident_visited = u.user_id
    WHERE 1=1 $searchQuery
");
$totalVisitors = $countResult->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalVisitors / $perPage);
if ($totalPages == 0) $totalPages = 1;

$visitors = [];
$result = $conn->query("
    SELECT 
        v.*, 
        u.full_name as resident_name, 
        u.room_number,
        c.expiry_date as card_expiry,
        c.status as card_status
    FROM visitor_logs v
    LEFT JOIN users u ON v.resident_visited = u.user_id
    LEFT JOIN rfid_cards c ON v.temporary_card_uid = c.card_uid
    WHERE 1=1 $searchQuery
    ORDER BY v.created_at DESC
    LIMIT $offset, $perPage
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $visitors[] = $row;
    }
}

// ============================================================
// GET STATS
// ============================================================
$stats = [
    'total' => 0,
    'pending' => 0,
    'granted' => 0,
    'exited' => 0,
    'denied' => 0,
    'expired_cards' => 0,
    'expiring_soon' => 0,
    'visits_expiring_soon' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'granted'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['granted'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'exited'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['exited'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'denied'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['denied'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM rfid_cards 
    WHERE card_type = 'visitor' 
    AND status = 'expired'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['expired_cards'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM rfid_cards 
    WHERE card_type = 'visitor' 
    AND status = 'active'
    AND expiry_date IS NOT NULL
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['expiring_soon'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM visitor_logs 
    WHERE validity_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND access_status != 'exited'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['visits_expiring_soon'] = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitors - Tap-and-Go Doorlock</title>
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
           VISITOR CARD - DARK
           ============================================================ */
        .visitor-card {
            background: #131926 !important;
            border-radius: 16px;
            padding: 18px 22px;
            margin-bottom: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
            border-left: 4px solid #8b5cf6;
            border: 1px solid #1e2a3a;
        }
        .visitor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }
        .visitor-card.checked-in { border-left-color: #10b981; }
        .visitor-card.checked-out { border-left-color: #6b7280; opacity: 0.7; }
        .visitor-card.pending { border-left-color: #f59e0b; }
        .visitor-card.denied { border-left-color: #ef4444; }
        .visitor-card.card-expired { border-left-color: #ef4444; background: #1a0a0a !important; }
        .visitor-card.visit-expired { border-left-color: #ef4444; background: #1a0a0a !important; }
        .visitor-card h6 { color: #ffd700 !important; }
        .visitor-card .text-muted { color: #6b7280 !important; }
        .visitor-card .small { color: #9ca3af !important; }
        .visitor-card span { color: #d1d5db !important; }
        
        .resident-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9) !important;
            color: white;
            flex-shrink: 0;
        }
        
        /* ============================================================
           STAT CARDS - SAME AS DASHBOARD
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
        .stat-number.text-danger { color: #f87171 !important; }
        .stat-number.text-success { color: #34d399 !important; }
        .stat-number.text-warning { color: #fbbf24 !important; }
        
        .pulse-badge {
            animation: pulseBadge 1s infinite;
            margin-left: 5px;
        }
        @keyframes pulseBadge {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        /* ============================================================
           BUTTONS - DARK
           ============================================================ */
        .btn-action {
            border-radius: 10px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        .btn-action:hover { transform: translateY(-1px); }
        
        .btn-checkin {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
            border: 1px solid rgba(16, 185, 129, 0.3) !important;
        }
        .btn-checkin:hover { background: rgba(16, 185, 129, 0.3) !important; color: #6ee7b7 !important; }
        
        .btn-checkout {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
        }
        .btn-checkout:hover { background: rgba(245, 158, 11, 0.3) !important; color: #fbbf24 !important; }
        
        .btn-edit-visitor {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
        }
        .btn-edit-visitor:hover { background: rgba(59, 130, 246, 0.3) !important; color: #93c5fd !important; }
        
        .btn-delete-visitor {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
            border: 1px solid rgba(239, 68, 68, 0.3) !important;
        }
        .btn-delete-visitor:hover { background: rgba(239, 68, 68, 0.3) !important; color: #fca5a5 !important; }
        
        .btn-renew-card {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
        }
        .btn-renew-card:hover { background: rgba(245, 158, 11, 0.3) !important; color: #fbbf24 !important; }
        
        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            color: #0a0e1a !important;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: #0a0e1a !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: #0a0e1a !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3) !important;
            color: #0a0e1a !important;
        }
        
        .btn-secondary {
            background: #1e2a3a !important;
            border: none !important;
            color: #e5e7eb !important;
        }
        .btn-secondary:hover { background: #2d3548 !important; color: #e5e7eb !important; }
        
        /* ============================================================
           BADGES - DARK
           ============================================================ */
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-checked-in { background: rgba(16, 185, 129, 0.2) !important; color: #6ee7b7 !important; }
        .badge-checked-out { background: rgba(107, 114, 128, 0.2) !important; color: #9ca3af !important; }
        .badge-pending { background: rgba(245, 158, 11, 0.2) !important; color: #fbbf24 !important; }
        .badge-denied { background: rgba(239, 68, 68, 0.2) !important; color: #f87171 !important; }
        .badge-card-expired { background: rgba(239, 68, 68, 0.2) !important; color: #f87171 !important; }
        .badge-card-expiring { background: rgba(245, 158, 11, 0.2) !important; color: #fbbf24 !important; }
        .badge-card-active { background: rgba(16, 185, 129, 0.2) !important; color: #6ee7b7 !important; }
        .badge-visit-expired { background: rgba(239, 68, 68, 0.2) !important; color: #f87171 !important; }
        
        /* ============================================================
           COUNTDOWN TIMER
           ============================================================ */
        .countdown-timer {
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            margin-top: 2px;
            background: rgba(0,0,0,0.3);
        }
        .countdown-timer.countdown-urgent {
            color: #fbbf24 !important;
            animation: pulse-urgent 1.5s ease-in-out infinite;
            background: rgba(245, 158, 11, 0.15);
        }
        .countdown-timer.countdown-expired {
            color: #f87171 !important;
            background: rgba(239, 68, 68, 0.15);
        }
        .countdown-timer.countdown-normal {
            color: #6ee7b7 !important;
            background: rgba(16, 185, 129, 0.1);
        }
        @keyframes pulse-urgent {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.98); }
        }
        
        /* ============================================================
           SEARCH BOX - DARK
           ============================================================ */
        .search-box { max-width: 350px; }
        .search-box .form-control {
            border-radius: 12px 0 0 12px;
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            padding: 10px 16px;
        }
        .search-box .form-control:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
        }
        .search-box .form-control::placeholder { color: #6b7280 !important; }
        .search-box .btn {
            border-radius: 0 12px 12px 0;
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            color: #0a0e1a !important;
            border: none;
            font-weight: 600;
        }
        .search-box .btn:hover { background: linear-gradient(135deg, #f59e0b, #d97706) !important; }
        
        /* ============================================================
           ALERTS - DARK
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
        .alert-warning {
            background: rgba(245, 158, 11, 0.15) !important;
            border-color: #f59e0b !important;
            color: #fbbf24 !important;
        }
        .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           FORMS - DARK
           ============================================================ */
        .form-control, .form-select {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
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
        .form-select option { background: #131926 !important; color: #e5e7eb !important; }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #d1d5db !important;
        }
        .required { color: #ef4444 !important; margin-left: 2px; }
        
        /* ============================================================
           MODAL - DARK
           ============================================================ */
        .modal-content {
            background: #131926 !important;
            border-radius: 16px;
            border: 1px solid #1e2a3a;
        }
        .modal-header { border-bottom: 1px solid #1e2a3a; }
        .modal-footer { border-top: 1px solid #1e2a3a; }
        .modal-title { color: #ffd700 !important; }
        .modal-title i { color: #ffd700 !important; }
        
        /* ============================================================
           PAGINATION - DARK
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
        
        /* ============================================================
           CARDS - DARK
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .card-body { background: #111827 !important; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        
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
            
            .stat-card { padding: 15px; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            
            .visitor-card { padding: 15px; }
            .btn-action { font-size: 11px; padding: 4px 10px; }
            .search-box { max-width: 100%; margin-bottom: 10px; }
            .countdown-timer { font-size: 10px; padding: 1px 8px; }
            
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
        }
        
        @media (max-width: 576px) {
            .visitor-card .row .col-md-4,
            .visitor-card .row .col-md-3,
            .visitor-card .row .col-md-2 {
                margin-bottom: 8px;
            }
            .visitor-card .d-flex.gap-1 {
                justify-content: center;
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
                        <i class="fas fa-user-plus me-2" style="color: #ffd700;"></i>
                        Visitors Management
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
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($stats['expiring_soon'] > 0 || $stats['visits_expiring_soon'] > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-clock me-2"></i>
                        <?php if ($stats['expiring_soon'] > 0): ?>
                            <strong><?php echo $stats['expiring_soon']; ?> visitor card(s)</strong> will expire within 3 days.
                        <?php endif; ?>
                        <?php if ($stats['expiring_soon'] > 0 && $stats['visits_expiring_soon'] > 0): ?>
                            <br>
                        <?php endif; ?>
                        <?php if ($stats['visits_expiring_soon'] > 0): ?>
                            <strong><?php echo $stats['visits_expiring_soon']; ?> visit(s)</strong> will end within 3 days.
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ============================================================
                STATS CARDS - SAME AS DASHBOARD
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
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
                                <span class="badge bg-danger pulse-badge"><?php echo $stats['pending']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['granted']; ?></div>
                                <div class="stat-label">Checked In</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-sign-out-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['exited']; ?></div>
                                <div class="stat-label">Checked Out</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $stats['expired_cards'] > 0 ? '#ef4444' : '#6b7280'; ?>;">
                                <i class="fas fa-hourglass-end"></i>
                            </div>
                            <div>
                                <div class="stat-number <?php echo $stats['expired_cards'] > 0 ? 'text-danger' : ''; ?>">
                                    <?php echo $stats['expired_cards']; ?>
                                </div>
                                <div class="stat-label">Expired Cards</div>
                            </div>
                            <?php if ($stats['expired_cards'] > 0): ?>
                                <span class="badge bg-danger pulse-badge">⚠️</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo ($stats['expiring_soon'] > 0 || $stats['visits_expiring_soon'] > 0) ? '#f59e0b' : '#10b981'; ?>;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div class="stat-number <?php echo ($stats['expiring_soon'] > 0 || $stats['visits_expiring_soon'] > 0) ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo $stats['expiring_soon'] + $stats['visits_expiring_soon']; ?>
                                </div>
                                <div class="stat-label">Expiring Soon</div>
                            </div>
                            <?php if ($stats['expiring_soon'] > 0 || $stats['visits_expiring_soon'] > 0): ?>
                                <span class="badge bg-warning pulse-badge">⏰</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                SEARCH BAR
                ============================================================ -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" action="" class="search-box d-flex">
                            <input type="text" class="form-control" name="search" placeholder="Search visitor or resident..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <button type="submit" class="btn"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-muted small">
                            <i class="fas fa-users me-1"></i>
                            <?php echo count($visitors); ?> visitors found
                        </span>
                        <button type="button" class="btn btn-primary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#visitorModal">
                            <i class="fas fa-plus me-1"></i> Quick Add
                        </button>
                    </div>
                </div>

                <!-- ============================================================
                VISITORS LIST
                ============================================================ -->
                <?php if (empty($visitors)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No visitors found</h5>
                            <p class="text-muted">Click "Quick Add" to register a visitor</p>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#visitorModal">
                                <i class="fas fa-plus me-1"></i> Register Visitor
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($visitors as $visitor): 
                        $status = $visitor['access_status'] ?? 'pending';
                        $card_expired = $visitor['card_status'] == 'expired';
                        $card_expiring = false;
                        $visit_expired = false;
                        $visit_expiring = false;
                        $days_left = 0;
                        $expiry_date_str = $visitor['card_expiry'] ?? null;
                        $countdown_display = '';
                        $countdown_class = 'countdown-normal';
                        $show_countdown = false;
                        $countdown_label = 'Card Expiry';
                        $is_visit_countdown = false;
                        
                        // CHECK CARD EXPIRY
                        if ($expiry_date_str) {
                            $now = new DateTime();
                            $expiry = new DateTime($expiry_date_str);
                            $diff = $now->diff($expiry);
                            $days_left = $diff->days;
                            $is_expired = $diff->invert == 1;
                            $show_countdown = true;
                            $countdown_label = 'Card Expiry';
                            
                            if (!$is_expired && $days_left <= 3 && $visitor['card_status'] == 'active') {
                                $card_expiring = true;
                                $countdown_class = 'countdown-urgent';
                                $hours_left = $diff->h + ($diff->days * 24);
                                $minutes_left = $diff->i;
                                
                                if ($days_left >= 1) {
                                    $countdown_display = "📅 {$days_left} day" . ($days_left > 1 ? 's' : '') . " left";
                                } else if ($hours_left >= 1) {
                                    $countdown_display = "⏰ {$hours_left} hour" . ($hours_left > 1 ? 's' : '') . " left";
                                } else if ($minutes_left > 0) {
                                    $countdown_display = "⏱️ {$minutes_left} minute" . ($minutes_left > 1 ? 's' : '') . " left";
                                } else {
                                    $countdown_display = "⚠️ Expiring today!";
                                }
                            } elseif ($is_expired) {
                                $card_expired = true;
                                $countdown_class = 'countdown-expired';
                                $countdown_display = "⛔ EXPIRED";
                            } else {
                                $countdown_class = 'countdown-normal';
                                $countdown_display = "📅 {$days_left} day" . ($days_left > 1 ? 's' : '') . " remaining";
                            }
                        } 
                        // CHECK VISIT VALIDITY
                        else if (!empty($visitor['validity_end']) && $status != 'exited') {
                            $now = new DateTime();
                            $validity_end = new DateTime($visitor['validity_end']);
                            $diff = $now->diff($validity_end);
                            $days_left = $diff->days;
                            $is_expired = $diff->invert == 1;
                            $show_countdown = true;
                            $countdown_label = 'Visit Validity';
                            $is_visit_countdown = true;
                            
                            if (!$is_expired && $days_left <= 3) {
                                $visit_expiring = true;
                                $countdown_class = 'countdown-urgent';
                                $hours_left = $diff->h + ($diff->days * 24);
                                $minutes_left = $diff->i;
                                
                                if ($days_left >= 1) {
                                    $countdown_display = "📅 Visit ends in {$days_left} day" . ($days_left > 1 ? 's' : '');
                                } else if ($hours_left >= 1) {
                                    $countdown_display = "⏰ Visit ends in {$hours_left} hour" . ($hours_left > 1 ? 's' : '');
                                } else if ($minutes_left > 0) {
                                    $countdown_display = "⏱️ Visit ends in {$minutes_left} minute" . ($minutes_left > 1 ? 's' : '');
                                } else {
                                    $countdown_display = "⚠️ Visit ends today!";
                                }
                            } elseif ($is_expired) {
                                $visit_expired = true;
                                $countdown_class = 'countdown-expired';
                                $countdown_display = "⛔ Visit period expired";
                            } else {
                                $countdown_class = 'countdown-normal';
                                $countdown_display = "📅 {$days_left} day" . ($days_left > 1 ? 's' : '') . " remaining";
                            }
                        }
                        
                        $card_class = '';
                        if ($card_expired || $visit_expired) {
                            $card_class = 'card-expired';
                        } elseif ($status == 'granted' && empty($visitor['exit_timestamp'])) {
                            $card_class = 'checked-in';
                        } elseif ($status == 'exited') {
                            $card_class = 'checked-out';
                        } elseif ($status == 'pending') {
                            $card_class = 'pending';
                        } elseif ($status == 'denied') {
                            $card_class = 'denied';
                        }
                    ?>
                        <div class="visitor-card <?php echo $card_class; ?>">
                            <div class="row align-items-center">
                                <!-- Visitor Info -->
                                <div class="col-md-4 col-lg-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="resident-avatar">
                                            <?php 
                                                $nameParts = explode(' ', $visitor['visitor_name'] ?? '');
                                                $initials = '';
                                                foreach ($nameParts as $part) {
                                                    if (!empty($part)) {
                                                        $initials .= strtoupper($part[0]);
                                                    }
                                                }
                                                echo substr($initials, 0, 2) ?: '?';
                                            ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($visitor['visitor_name']); ?></h6>
                                            <span class="text-muted small">
                                                <i class="fas fa-user me-1"></i>
                                                Visiting: <?php echo htmlspecialchars($visitor['resident_name'] ?? 'N/A'); ?>
                                                (Room <?php echo htmlspecialchars($visitor['room_number'] ?? 'N/A'); ?>)
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visit Details -->
                                <div class="col-md-3 col-lg-3">
                                    <div>
                                        <span class="text-muted small">Purpose</span>
                                        <br>
                                        <span><?php echo htmlspecialchars($visitor['purpose_of_visit'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="mt-1">
                                        <span class="text-muted small">Validity</span>
                                        <br>
                                        <span class="small"><?php echo date('M d', strtotime($visitor['validity_start'] ?? 'now')); ?> - <?php echo date('M d', strtotime($visitor['validity_end'] ?? 'now')); ?></span>
                                    </div>
                                </div>

                                <!-- Status & Times -->
                                <div class="col-md-3 col-lg-3">
                                    <div>
                                        <span class="text-muted small">Status</span>
                                        <br>
                                        <?php if ($status == 'granted'): ?>
                                            <span class="badge-status badge-checked-in">
                                                <i class="fas fa-check-circle me-1"></i> Checked In
                                            </span>
                                        <?php elseif ($status == 'pending'): ?>
                                            <span class="badge-status badge-pending">
                                                <i class="fas fa-clock me-1"></i> Pending
                                            </span>
                                        <?php elseif ($status == 'denied'): ?>
                                            <span class="badge-status badge-denied">
                                                <i class="fas fa-times-circle me-1"></i> Denied
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status badge-checked-out">
                                                <i class="fas fa-check-circle me-1"></i> Checked Out
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($visitor['temporary_card_uid']): ?>
                                            <br>
                                            <span class="text-muted small">Card:</span>
                                            <?php if ($card_expired): ?>
                                                <span class="badge-status badge-card-expired">
                                                    <i class="fas fa-exclamation-triangle me-1"></i> Expired
                                                </span>
                                            <?php elseif ($card_expiring): ?>
                                                <span class="badge-status badge-card-expiring">
                                                    <i class="fas fa-clock me-1"></i> <?php echo $days_left; ?> day(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-status badge-card-active">
                                                    <i class="fas fa-check-circle me-1"></i> Active
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($visit_expired && $status != 'exited'): ?>
                                            <br>
                                            <span class="badge-status badge-visit-expired">
                                                <i class="fas fa-exclamation-triangle me-1"></i> Visit Expired
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="text-muted small">Entry</span>
                                        <br>
                                        <span class="small"><?php echo $visitor['entry_timestamp'] ? date('M d, h:i A', strtotime($visitor['entry_timestamp'])) : 'N/A'; ?></span>
                                    </div>
                                    <div class="mt-1">
                                        <span class="text-muted small">Exit</span>
                                        <br>
                                        <span class="small"><?php echo $visitor['exit_timestamp'] ? date('M d, h:i A', strtotime($visitor['exit_timestamp'])) : 'N/A'; ?></span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="col-md-2 col-lg-3">
                                    <?php if ($show_countdown): ?>
                                        <div class="mb-2">
                                            <span class="text-muted small"><?php echo $countdown_label; ?></span>
                                            <br>
                                            <?php if ($visitor['card_expiry'] && !$is_visit_countdown): ?>
                                                <span class="small <?php echo $card_expired ? 'text-danger' : ($card_expiring ? 'text-warning' : ''); ?>">
                                                    <?php echo date('M d, Y', strtotime($visitor['card_expiry'])); ?>
                                                </span>
                                            <?php elseif ($is_visit_countdown): ?>
                                                <span class="small <?php echo $visit_expired ? 'text-danger' : ($visit_expiring ? 'text-warning' : ''); ?>">
                                                    <?php echo date('M d, Y', strtotime($visitor['validity_end'])); ?>
                                                </span>
                                            <?php endif; ?>
                                            <br>
                                            <span class="countdown-timer <?php echo $countdown_class; ?>">
                                                <?php echo $countdown_display; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        <?php if ($status == 'pending' && !$card_expired && !$visit_expired): ?>
                                            <a href="?checkin=<?php echo $visitor['visitor_log_id']; ?>" class="btn btn-action btn-checkin">
                                                <i class="fas fa-sign-in-alt me-1"></i> Check In
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($status == 'granted' && empty($visitor['exit_timestamp'])): ?>
                                            <a href="?checkout=<?php echo $visitor['visitor_log_id']; ?>" class="btn btn-action btn-checkout">
                                                <i class="fas fa-sign-out-alt me-1"></i> Check Out
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($card_expired && $visitor['temporary_card_uid']): ?>
                                            <a href="?renew=<?php echo $visitor['temporary_card_uid']; ?>" class="btn btn-action btn-renew-card" onclick="return confirm('Renew this card for another year?')">
                                                <i class="fas fa-sync me-1"></i> Renew Card
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?edit=<?php echo $visitor['visitor_log_id']; ?>" class="btn btn-action btn-edit-visitor" data-bs-toggle="modal" data-bs-target="#visitorModal">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                        
                                        <a href="?delete=<?php echo $visitor['visitor_log_id']; ?>" class="btn btn-action btn-delete-visitor" onclick="return confirm('Are you sure you want to delete this visitor record?')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- ============================================================
                PAGINATION - SAME AS DASHBOARD
                ============================================================ -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <span class="text-muted small">
                                Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
                                <span class="mx-1 text-muted">|</span>
                                Total: <?php echo $totalVisitors; ?> visitors
                            </span>
                        </div>
                        <div class="col-md-6">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-end mb-0">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">First</a>
                                    </li>
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Prev</a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                    </li>
                                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Last</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                VISITOR MODAL (Add/Edit)
                ============================================================ -->
                <div class="modal fade" id="visitorModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-user-plus me-2"></i>
                                    <?php echo $edit_id > 0 ? 'Edit Visitor' : 'New Visitor Registration'; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Visitor Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" name="visitor_name" placeholder="Full name" value="<?php echo htmlspecialchars($visitor['visitor_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Resident to Visit <span class="required">*</span></label>
                                            <select class="form-select" name="resident_visited" required>
                                                <option value="">Select Resident</option>
                                                <?php foreach ($residentsList as $res): ?>
                                                    <option value="<?php echo $res['user_id']; ?>" <?php echo (isset($visitor['resident_visited']) && $visitor['resident_visited'] == $res['user_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($res['full_name']); ?> (Room <?php echo htmlspecialchars($res['room_number'] ?? 'N/A'); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Purpose of Visit <span class="required">*</span></label>
                                            <input type="text" class="form-control" name="purpose_of_visit" placeholder="e.g., Visit friend, Meeting, etc." value="<?php echo htmlspecialchars($visitor['purpose_of_visit'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Validity Start <span class="required">*</span></label>
                                            <input type="date" class="form-control" name="validity_start" value="<?php echo htmlspecialchars($visitor['validity_start'] ?? date('Y-m-d')); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Validity End <span class="required">*</span></label>
                                            <input type="date" class="form-control" name="validity_end" value="<?php echo htmlspecialchars($visitor['validity_end'] ?? date('Y-m-d', strtotime('+1 week'))); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Access Status</label>
                                            <select class="form-select" name="access_status">
                                                <option value="pending" <?php echo (isset($visitor['access_status']) && $visitor['access_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="granted" <?php echo (isset($visitor['access_status']) && $visitor['access_status'] == 'granted') ? 'selected' : ''; ?>>Granted</option>
                                                <option value="denied" <?php echo (isset($visitor['access_status']) && $visitor['access_status'] == 'denied') ? 'selected' : ''; ?>>Denied</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Temporary Card UID</label>
                                            <select class="form-select" name="temporary_card_uid">
                                                <option value="">-- No Card --</option>
                                                <?php foreach ($availableCards as $card): ?>
                                                    <option value="<?php echo htmlspecialchars($card); ?>" <?php echo (isset($visitor['temporary_card_uid']) && $visitor['temporary_card_uid'] == $card) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($card); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Assign an available visitor RFID card</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="submit" class="btn btn-submit">
                                        <i class="fas fa-save me-1"></i> <?php echo $edit_id > 0 ? 'Update Visitor' : 'Register Visitor'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                FOOTER - SAME AS DASHBOARD
                ============================================================ -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span>Total: <?php echo $totalVisitors; ?> visitors</span>
                    <?php if ($stats['expired_cards'] > 0): ?>
                        <span class="text-danger ms-3">
                            <i class="fas fa-hourglass-end me-1"></i>
                            <?php echo $stats['expired_cards']; ?> expired cards
                        </span>
                    <?php endif; ?>
                    <?php if ($stats['expiring_soon'] > 0): ?>
                        <span class="text-warning ms-3">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $stats['expiring_soon']; ?> cards expiring
                        </span>
                    <?php endif; ?>
                    <?php if ($stats['visits_expiring_soon'] > 0): ?>
                        <span class="text-warning ms-3">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo $stats['visits_expiring_soon']; ?> visits ending
                        </span>
                    <?php endif; ?>
                </footer>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // AUTO-OPEN MODAL WHEN EDITING
        // ============================================================
        <?php if ($edit_id > 0): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('visitorModal'));
                modal.show();
            });
        <?php endif; ?>
        
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