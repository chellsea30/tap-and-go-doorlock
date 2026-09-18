<?php
/**
 * Tap-and-Go Doorlock - Staff Card Management
 * DARK MODE - WITH CARD UID - FIXED LAYOUT SAME AS STAFF INFO
 * WITH PRINT ID BUTTON - WITH PROFILE PHOTO
 * ✅ WITH LIVE RFID SCAN AUTO-FILL (from Slave ESP32)
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
// HANDLE ADD STAFF WITH CARD UID
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? 'Staff');
    $card_uid = strtoupper(trim($_POST['card_uid'] ?? ''));
    $scan_id = isset($_POST['scan_id']) ? (int)$_POST['scan_id'] : 0;
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields (Name and Email).';
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT staff_id FROM staff_users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already exists in the system.';
        } else {
            // Check if card UID is available
            if (!empty($card_uid)) {
                $cardCheck = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ? AND status = 'active'");
                $cardCheck->bind_param("s", $card_uid);
                $cardCheck->execute();
                if ($cardCheck->get_result()->num_rows > 0) {
                    $error = 'Card UID is already assigned to someone else.';
                }
                $cardCheck->close();
            }
            
            if (empty($error)) {
                $staff_id_number = getNextStaffId($conn);
                
                $stmt = $conn->prepare("
                    INSERT INTO staff_users (staff_id_number, full_name, email, department, card_uid, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("sssss", $staff_id_number, $full_name, $email, $department, $card_uid);
                
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    
                    // If card_uid is provided, insert into rfid_cards
                    if (!empty($card_uid)) {
                        // Check if in available cards
                        $availCheck = $conn->prepare("SELECT card_id FROM available_rfid_cards WHERE card_uid = ? AND status = 'available'");
                        $availCheck->bind_param("s", $card_uid);
                        $availCheck->execute();
                        $availResult = $availCheck->get_result();
                        $isAvailable = $availResult->num_rows > 0;
                        $availCheck->close();
                        
                        // Insert into rfid_cards
                        $rfidStmt = $conn->prepare("
                            INSERT INTO rfid_cards (card_uid, user_id, card_type, issued_date, expiry_date, status)
                            VALUES (?, ?, 'staff', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active')
                        ");
                        $rfidStmt->bind_param("si", $card_uid, $new_id);
                        $rfidStmt->execute();
                        $rfidStmt->close();
                        
                        // Mark as assigned if from available cards
                        if ($isAvailable) {
                            $updateStmt = $conn->prepare("UPDATE available_rfid_cards SET status = 'assigned' WHERE card_uid = ?");
                            $updateStmt->bind_param("s", $card_uid);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }
                    }
                    
                    // ✅ Mark scan as used
                    if ($scan_id > 0) {
                        $stmtScan = $conn->prepare("UPDATE live_scan SET status = 'used' WHERE scan_id = ?");
                        $stmtScan->bind_param("i", $scan_id);
                        $stmtScan->execute();
                        $stmtScan->close();
                    }
                    
                    $success = "✅ Staff added successfully with Card UID: " . (!empty($card_uid) ? $card_uid : 'None');
                    logAudit($_SESSION['admin_id'], 'Add Staff', "Added staff: $full_name ($staff_id_number)");
                    header('Location: staff-card.php?success=1');
                    exit();
                } else {
                    $error = "Failed to add staff: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        $check->close();
    }
}

// ============================================================
// HANDLE UPDATE STAFF CARD UID
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_card'])) {
    $staff_id = (int)$_POST['staff_id'];
    $card_uid = strtoupper(trim($_POST['card_uid'] ?? ''));
    $scan_id = isset($_POST['scan_id']) ? (int)$_POST['scan_id'] : 0;
    
    if (empty($card_uid)) {
        $error = 'Please enter a Card UID.';
    } else {
        // Check if card already assigned to someone else
        $check = $conn->prepare("
            SELECT user_id FROM rfid_cards 
            WHERE card_uid = ? AND user_id != ? AND status = 'active'
        ");
        $check->bind_param("si", $card_uid, $staff_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Card UID is already assigned to someone else.';
        } else {
            // Update staff_users
            $stmt = $conn->prepare("UPDATE staff_users SET card_uid = ? WHERE staff_id = ?");
            $stmt->bind_param("si", $card_uid, $staff_id);
            
            if ($stmt->execute()) {
                // Update or insert into rfid_cards
                $cardCheck = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE user_id = ? AND card_type = 'staff'");
                $cardCheck->bind_param("i", $staff_id);
                $cardCheck->execute();
                $exists = $cardCheck->get_result()->num_rows > 0;
                $cardCheck->close();
                
                if ($exists) {
                    $rfidStmt = $conn->prepare("
                        UPDATE rfid_cards SET card_uid = ?, status = 'active' 
                        WHERE user_id = ? AND card_type = 'staff'
                    ");
                    $rfidStmt->bind_param("si", $card_uid, $staff_id);
                } else {
                    $rfidStmt = $conn->prepare("
                        INSERT INTO rfid_cards (card_uid, user_id, card_type, issued_date, expiry_date, status)
                        VALUES (?, ?, 'staff', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active')
                    ");
                    $rfidStmt->bind_param("si", $card_uid, $staff_id);
                }
                $rfidStmt->execute();
                $rfidStmt->close();
                
                // ✅ Mark scan as used
                if ($scan_id > 0) {
                    $stmtScan = $conn->prepare("UPDATE live_scan SET status = 'used' WHERE scan_id = ?");
                    $stmtScan->bind_param("i", $scan_id);
                    $stmtScan->execute();
                    $stmtScan->close();
                }
                
                $success = "✅ Card UID updated successfully!";
                logAudit($_SESSION['admin_id'], 'Update Staff Card', "Updated card for staff ID: $staff_id to $card_uid");
                header('Location: staff-card.php?card_updated=1');
                exit();
            } else {
                $error = "Failed to update card: " . $stmt->error;
            }
            $stmt->close();
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
        // Deactivate in rfid_cards
        $rfidStmt = $conn->prepare("UPDATE rfid_cards SET status = 'deactivated' WHERE user_id = ? AND card_type = 'staff'");
        $rfidStmt->bind_param("i", $staff_id);
        $rfidStmt->execute();
        $rfidStmt->close();
        
        $success = "✅ Card UID removed successfully!";
        logAudit($_SESSION['admin_id'], 'Remove Staff Card', "Removed card for staff ID: $staff_id");
        header('Location: staff-card.php?card_removed=1');
        exit();
    } else {
        $error = "Failed to remove card: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// HANDLE DELETE STAFF
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    $getCard = $conn->prepare("SELECT card_uid FROM staff_users WHERE staff_id = ?");
    $getCard->bind_param("i", $delete_id);
    $getCard->execute();
    $cardResult = $getCard->get_result();
    $staffData = $cardResult->fetch_assoc();
    $getCard->close();
    
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
        header('Location: staff-card.php?deleted=1');
        exit();
    } else {
        $error = "Failed to delete staff.";
    }
    $stmt->close();
}

// ============================================================
// GET STAFF LIST WITH CARD UID AND PROFILE PHOTO
// ============================================================
$staffList = [];

$tableCheck = $conn->query("SHOW COLUMNS FROM staff_users LIKE 'avatar'");
$hasAvatar = $tableCheck && $tableCheck->num_rows > 0;

if ($hasAvatar) {
    $result = $conn->query("
        SELECT staff_id, staff_id_number, full_name, email, department, card_uid, avatar, created_at
        FROM staff_users
        ORDER BY full_name
    ");
} else {
    $result = $conn->query("
        SELECT staff_id, staff_id_number, full_name, email, department, card_uid, NULL as avatar, created_at
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

foreach ($staffList as $staff) {
    if (!empty($staff['card_uid'])) {
        $hasCardCount++;
    } else {
        $noCardCount++;
    }
}

$nextStaffId = getNextStaffId($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Card - Tap-and-Go Doorlock</title>
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
        .staff-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .staff-card .name { font-weight: 700; color: #ffd700 !important; font-size: 18px; }
        .staff-card .department { color: #9ca3af !important; font-size: 14px; }
        .staff-card .staff-id { color: #6b7280 !important; font-size: 12px; }
        .staff-card .badge-active { background: rgba(16, 185, 129, 0.2) !important; color: #6ee7b7 !important; font-size: 11px; padding: 3px 12px; border-radius: 20px; }
        .staff-card .text-muted { color: #6b7280 !important; }
        
        .staff-avatar {
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; color: white; margin: 0 auto 15px;
            font-weight: 700; overflow: hidden;
            border: 3px solid #1a2a4a;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        .staff-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .staff-avatar .no-photo { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-size: 28px; font-weight: 700; color: white; }
        .staff-avatar .has-photo-badge {
            position: absolute; top: -5px; right: -5px;
            background: #10b981; color: white; border-radius: 50%;
            width: 20px; height: 20px; font-size: 10px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #111827;
        }
        .staff-avatar-wrapper { position: relative; display: inline-block; }
        
        .card-uid-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        .card-uid-badge.has-card { background: rgba(16, 185, 129, 0.2) !important; color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.3); }
        .card-uid-badge.no-card { background: rgba(239, 68, 68, 0.15) !important; color: #f87171 !important; border: 1px solid rgba(239, 68, 68, 0.3); }
        
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
        .staff-stat .number { font-size: 32px; font-weight: 700; color: #ffd700 !important; }
        .staff-stat .label { font-size: 13px; color: #6b7280 !important; }
        
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
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3) !important; color: #0a0e1a !important; }
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
        .btn-card:hover { background: rgba(139, 92, 246, 0.3) !important; color: #a78bfa !important; }
        
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
        .btn-card-remove:hover { background: rgba(239, 68, 68, 0.3) !important; color: #fca5a5 !important; }
        
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
        .btn-print-id:hover { background: rgba(59, 130, 246, 0.3) !important; color: #93c5fd !important; }
        
        .btn-outline-primary { color: #ffd700 !important; border-color: rgba(255, 215, 0, 0.3) !important; }
        .btn-outline-primary:hover { background: rgba(255, 215, 0, 0.15) !important; color: #ffd700 !important; }
        .btn-outline-danger { color: #fca5a5 !important; border-color: rgba(239, 68, 68, 0.3) !important; }
        .btn-outline-danger:hover { background: rgba(239, 68, 68, 0.15) !important; color: #fca5a5 !important; }
        
        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            color: #0a0e1a !important;
            font-weight: 600;
        }
        .btn-primary:hover { background: linear-gradient(135deg, #f59e0b, #d97706) !important; color: #0a0e1a !important; }
        
        .btn-secondary { background: #1a2a4a !important; border: none !important; color: #e5e7eb !important; }
        .btn-secondary:hover { background: #2d3548 !important; color: #e5e7eb !important; }
        
        .staff-actions { display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; margin-top: 8px; }
        
        .staff-id-badge {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            border: 1px solid rgba(255, 215, 0, 0.15);
        }
        
        .modal-content { background: #131926 !important; border-radius: 16px; border: 1px solid #1a2a4a; }
        .modal-header { border-bottom: 1px solid #1a2a4a; }
        .modal-footer { border-top: 1px solid #1a2a4a; }
        .modal-title { color: #ffd700 !important; }
        .modal-title i { color: #ffd700 !important; }
        
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
        .form-label { font-weight: 500; font-size: 13px; color: #d1d5db !important; }
        .form-text { color: #6b7280 !important; }
        
        .available-card-list {
            display: flex; flex-wrap: wrap; gap: 4px;
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
        
        .alert-success { background: rgba(16, 185, 129, 0.15) !important; border-color: #10b981 !important; color: #6ee7b7 !important; }
        .alert-danger { background: rgba(239, 68, 68, 0.15) !important; border-color: #ef4444 !important; color: #fca5a5 !important; }
        .btn-close { filter: invert(1) !important; }
        
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
        
        .section-header h5 { margin: 0; color: #ffd700 !important; font-weight: 700; }
        
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
        
        /* ✅ BAGO: Scan Button */
        .btn-scan {
            background: linear-gradient(135deg, #f59e0b, #dc2626) !important;
            border: none !important;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            color: white !important;
            transition: all 0.3s ease;
            animation: pulse-scan 2s infinite;
            width: 100%;
        }
        .btn-scan:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(245,158,11,0.5); color: white !important; }
        @keyframes pulse-scan {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0.7); }
            50% { box-shadow: 0 0 0 10px rgba(245,158,11,0); }
        }
        
        /* ✅ SCAN MODAL */
        .scan-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
        }
        .scan-modal-overlay.show { display: flex; }
        
        .scan-modal {
            background: linear-gradient(135deg, #0d1528, #1a2a4a);
            border: 2px solid #2a5a9a;
            border-radius: 24px;
            padding: 40px 50px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,0.8), 0 0 60px rgba(42,90,154,0.5);
            animation: modalPop 0.4s ease;
        }
        @keyframes modalPop {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .scan-modal .scan-icon {
            font-size: 80px;
            color: #f59e0b;
            margin-bottom: 20px;
            animation: scanPulse 1.5s infinite;
            display: inline-block;
        }
        @keyframes scanPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }
        
        .scan-modal h3 { color: #ffd700 !important; font-weight: 700; font-size: 22px; margin-bottom: 10px; }
        .scan-modal p { color: #b0b0c0 !important; font-size: 14px; margin-bottom: 20px; }
        
        .scan-modal .scan-status {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid #f59e0b;
            border-radius: 30px;
            color: #fbbf24 !important;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .scan-modal .scan-status.scanned {
            background: rgba(16, 185, 129, 0.15);
            border-color: #10b981;
            color: #34d399 !important;
        }
        
        .scan-modal .scanned-uid {
            font-family: monospace;
            font-size: 32px;
            font-weight: 700;
            color: #34d399 !important;
            background: rgba(16, 185, 129, 0.1);
            padding: 15px 30px;
            border-radius: 12px;
            letter-spacing: 4px;
            margin: 15px 0;
            display: none;
        }
        .scan-modal .scanned-uid.show { display: block; animation: uidReveal 0.5s ease; }
        @keyframes uidReveal {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .scan-modal .btn-cancel-scan {
            background: transparent;
            border: 1px solid #7a2a2a;
            color: #f87171;
            padding: 8px 24px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        .scan-modal .btn-cancel-scan:hover { background: #7a2a2a; color: white; }
        
        .pulse-reader {
            display: inline-block;
            width: 12px; height: 12px;
            background: #10b981;
            border-radius: 50%;
            margin-right: 6px;
            animation: readerPulse 1s infinite;
        }
        @keyframes readerPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
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
            .staff-card { padding: 15px; }
            .staff-card .name { font-size: 16px; }
            .staff-avatar { width: 60px; height: 60px; font-size: 24px; }
            .scan-modal { padding: 30px 25px; }
            .scan-modal .scan-icon { font-size: 60px; }
        }
        
        @media (max-width: 576px) {
            .staff-actions { flex-direction: column; align-items: center; }
            .staff-actions .btn { width: 100%; text-align: center; }
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
                        <i class="fas fa-id-card me-2" style="color: #ffd700;"></i>
                        Staff Cards
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
                        <button class="btn btn-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="fas fa-user-plus me-1"></i> Add Staff
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

                <!-- STATISTICS -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $totalStaff; ?></div>
                            <div class="label">Total Staff</div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $hasCardCount; ?></div>
                            <div class="label">With Card</div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="staff-stat">
                            <div class="number"><?php echo $noCardCount; ?></div>
                            <div class="label">No Card</div>
                        </div>
                    </div>
                </div>

                <!-- STAFF LIST -->
                <div class="section-header mb-3">
                    <h5><i class="fas fa-list me-2"></i>Staff Cards</h5>
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
                                if (file_exists($fullPhotoPath)) $hasPhoto = true;
                            }
                        ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="staff-card">
                                    <div class="staff-avatar-wrapper">
                                        <div class="staff-avatar">
                                            <?php if ($hasPhoto): ?>
                                                <img src="<?php echo $fullPhotoPath; ?>" 
                                                     alt="<?php echo htmlspecialchars($staff['full_name']); ?>"
                                                     onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                                                <span class="has-photo-badge"><i class="fas fa-check-circle"></i></span>
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
                                    
                                    <div class="mt-2">
                                        <span class="text-muted small">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($staff['email']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="staff-actions">
                                        <?php if (!empty($staff['card_uid'])): ?>
                                            <a href="print-staff-id.php?uid=<?php echo $staff['card_uid']; ?>" 
                                               target="_blank" 
                                               class="btn-print-id">
                                                <i class="fas fa-print me-1"></i> Print ID
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button type="button" 
                                                class="btn btn-card"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#cardModal<?php echo $staff['staff_id']; ?>">
                                            <i class="fas fa-id-card me-1"></i> Card
                                        </button>
                                        
                                        <?php if (!empty($staff['card_uid'])): ?>
                                            <a href="?remove_card=<?php echo $staff['staff_id']; ?>" 
                                               class="btn btn-card-remove"
                                               onclick="return confirm('Remove card from <?php echo $staff['full_name']; ?>?')">
                                                <i class="fas fa-times me-1"></i> Remove
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

                            <!-- ✅ CARD UID MODAL - WITH LIVE SCAN -->
                            <div class="modal fade" id="cardModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-id-card me-2"></i>
                                                Staff Card - <?php echo htmlspecialchars($staff['full_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3 text-center">
                                                <div style="width:80px;height:80px;border-radius:50%;margin:0 auto;overflow:hidden;border:3px solid #1a2a4a;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:white;">
                                                    <?php if ($hasPhoto): ?>
                                                        <img src="<?php echo $fullPhotoPath; ?>" 
                                                             alt="<?php echo htmlspecialchars($staff['full_name']); ?>"
                                                             style="width:100%;height:100%;object-fit:cover;"
                                                             onerror="this.style.display='none'; this.parentElement.textContent='<?php echo $initials; ?>';">
                                                    <?php else: ?>
                                                        <?php echo $initials; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars($staff['staff_id_number']); ?>
                                                </div>
                                            </div>
                                            
                                            <hr>
                                            
                                            <form method="POST" action="" id="cardForm<?php echo $staff['staff_id']; ?>">
                                                <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                <input type="hidden" name="update_card" value="1">
                                                <input type="hidden" name="scan_id" id="scanId_<?php echo $staff['staff_id']; ?>" value="">
                                                
                                                <div class="mb-2">
                                                    <label class="form-label">Current Card UID</label>
                                                    <div class="form-control" style="background:#0d1220 !important;border:1px solid #1a2a4a !important;border-radius:10px;padding:10px 14px;color:#e5e7eb !important;">
                                                        <?php if (!empty($staff['card_uid'])): ?>
                                                            <span class="text-success"><i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($staff['card_uid']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-danger"><i class="fas fa-times-circle me-1"></i> No card assigned</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- ✅ LIVE SCAN BUTTON -->
                                                <div class="mb-2">
                                                    <label class="form-label">Live RFID Scan</label>
                                                    <button type="button" 
                                                            class="btn btn-scan" 
                                                            onclick="startStaffScan(<?php echo $staff['staff_id']; ?>)">
                                                        <i class="fas fa-wifi me-2"></i> Scan Card
                                                    </button>
                                                    <small class="text-muted d-block mt-1">
                                                        <span class="pulse-reader"></span>Tap card on reader to auto-fill
                                                    </small>
                                                </div>
                                                
                                                <?php if (!empty($availableCards)): ?>
                                                    <div class="mb-2">
                                                        <label class="form-label">Available Cards (Click to auto-fill)</label>
                                                        <div class="available-card-list" id="availableCardList<?php echo $staff['staff_id']; ?>">
                                                            <?php foreach ($availableCards as $card): ?>
                                                                <span class="card-item-mini" data-uid="<?php echo $card['card_uid']; ?>" onclick="selectCard(this, 'cardUid<?php echo $staff['staff_id']; ?>')">
                                                                    <?php echo $card['card_uid']; ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mb-2">
                                                    <label class="form-label">New Card UID</label>
                                                    <input type="text" class="form-control" name="card_uid" id="cardUid<?php echo $staff['staff_id']; ?>" placeholder="Enter new card UID">
                                                    <div class="form-text text-muted small" id="uidStatus_<?php echo $staff['staff_id']; ?>">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Enter UID, click available card, or scan
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-save me-1"></i> Update Card
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- FOOTER -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span><?php echo $hasCardCount; ?> with card, <?php echo $noCardCount; ?> without card</span>
                </footer>
            </main>
        </div>
    </div>

    <!-- ============================================================
    ADD STAFF MODAL - WITH LIVE SCAN
    ============================================================ -->
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
                    <form method="POST" action="" id="addStaffForm">
                        <input type="hidden" name="add_staff" value="1">
                        <input type="hidden" name="scan_id" id="scanIdAdd" value="">
                        
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
                        
                        <!-- ✅ LIVE SCAN BUTTON -->
                        <div class="mb-2">
                            <label class="form-label">Live RFID Scan</label>
                            <button type="button" 
                                    class="btn btn-scan" 
                                    onclick="startStaffScan('add')">
                                <i class="fas fa-wifi me-2"></i> Scan Card
                            </button>
                            <small class="text-muted d-block mt-1">
                                <span class="pulse-reader"></span>Tap card on reader to auto-fill
                            </small>
                        </div>
                        
                        <?php if (!empty($availableCards)): ?>
                            <div class="mb-2">
                                <label class="form-label">Available Cards (Click to auto-fill)</label>
                                <div class="available-card-list" id="availableCardListAdd">
                                    <?php foreach ($availableCards as $card): ?>
                                        <span class="card-item-mini" data-uid="<?php echo $card['card_uid']; ?>" onclick="selectCard(this, 'cardUidAdd')">
                                            <?php echo $card['card_uid']; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <label class="form-label">Card UID</label>
                            <input type="text" class="form-control" name="card_uid" id="cardUidAdd" placeholder="Enter card UID (optional)">
                            <div class="form-text text-muted small" id="uidStatusAdd">
                                <i class="fas fa-info-circle me-1"></i>
                                Leave empty or assign a card
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i> Add Staff
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         ✅ SCAN MODAL
         ============================================================ -->
    <div class="scan-modal-overlay" id="scanModal">
        <div class="scan-modal">
            <div class="scan-icon">
                <i class="fas fa-wifi"></i>
            </div>
            <h3>Scanning for RFID Card...</h3>
            <p>Please tap your card on the RFID reader now</p>
            
            <div class="scan-status" id="scanStatus">
                <span class="pulse-reader"></span>
                Waiting for card...
            </div>
            
            <div class="scanned-uid" id="scannedUid">----</div>
            
            <button type="button" class="btn-cancel-scan" onclick="cancelStaffScan()">
                <i class="fas fa-times me-1"></i> Cancel Scan
            </button>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // GLOBAL STATE
        // ============================================================
        let scanPollingInterval = null;
        let scanActive = false;
        let currentScanTarget = null;
        let lastScannedUid = '';
        
        const SCAN_API_URL = '../../backend/api/get_latest_scan.php';
        
        // ============================================================
        // ✅ START SCAN
        // ============================================================
        function startStaffScan(target) {
            scanActive = true;
            currentScanTarget = target;
            lastScannedUid = '';
            
            document.getElementById('scanStatus').innerHTML = '<span class="pulse-reader"></span> Waiting for card...';
            document.getElementById('scanStatus').classList.remove('scanned');
            document.getElementById('scannedUid').classList.remove('show');
            document.getElementById('scannedUid').textContent = '----';
            
            document.getElementById('scanModal').classList.add('show');
            
            fetch(SCAN_API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'clear_scans'})
            }).then(() => {
                scanPollingInterval = setInterval(pollForStaffScan, 1000);
            });
        }
        
        // ============================================================
        // ✅ POLL FOR SCAN
        // ============================================================
        function pollForStaffScan() {
            if (!scanActive) return;
            
            fetch(SCAN_API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_latest_scan'})
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) return;
                
                if (data.has_scan && data.scan && data.scan.uid !== lastScannedUid) {
                    lastScannedUid = data.scan.uid;
                    
                    document.getElementById('scanStatus').innerHTML = '<i class="fas fa-check-circle me-1"></i> Card Detected!';
                    document.getElementById('scanStatus').classList.add('scanned');
                    
                    const uidEl = document.getElementById('scannedUid');
                    uidEl.textContent = data.scan.uid;
                    uidEl.classList.add('show');
                    
                    // Determine target input
                    if (currentScanTarget === 'add') {
                        document.getElementById('cardUidAdd').value = data.scan.uid;
                        document.getElementById('scanIdAdd').value = data.scan.scan_id;
                        
                        const statusEl = document.getElementById('uidStatusAdd');
                        statusEl.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Scanned from reader!';
                        statusEl.style.color = '#34d399';
                    } else {
                        const inputId = 'cardUid' + currentScanTarget;
                        const scanIdField = 'scanId_' + currentScanTarget;
                        const statusId = 'uidStatus_' + currentScanTarget;
                        
                        document.getElementById(inputId).value = data.scan.uid;
                        document.getElementById(scanIdField).value = data.scan.scan_id;
                        
                        const statusEl = document.getElementById(statusId);
                        if (statusEl) {
                            statusEl.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Scanned from reader!';
                            statusEl.style.color = '#34d399';
                        }
                    }
                    
                    setTimeout(() => {
                        cancelStaffScan();
                    }, 1500);
                }
            })
            .catch(err => console.warn('Scan polling error:', err));
        }
        
        // ============================================================
        // ✅ CANCEL SCAN
        // ============================================================
        function cancelStaffScan() {
            scanActive = false;
            currentScanTarget = null;
            
            if (scanPollingInterval) {
                clearInterval(scanPollingInterval);
                scanPollingInterval = null;
            }
            
            document.getElementById('scanModal').classList.remove('show');
        }
        
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
            const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            const updateElement = document.getElementById('lastUpdate');
            if (updateElement) updateElement.textContent = 'Updated: ' + timeString;
            
            const serverTimeElement = document.getElementById('serverTime');
            if (serverTimeElement) {
                const dateString = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                serverTimeElement.textContent = 'Server Time: ' + dateString + ' ' + timeString;
            }
        }

        setInterval(updateLastUpdateTime, 10000);
        document.addEventListener('DOMContentLoaded', updateLastUpdateTime);
        
        // ESC to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && scanActive) cancelStaffScan();
        });
        
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
