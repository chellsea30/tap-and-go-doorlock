<?php
/**
 * Tap-and-Go Doorlock - Staff Register Visitor with RFID
 * WITH AES-256-CBC ENCRYPTION
 * COMPLETE ENCRYPTED VERSION
 * PURE DARK MODE
 * WITH DURATION OPTIONS: 1 Week, 1 Month, 5 Months (1 Semester)
 * STAFF VERSION - SAME AS ADMIN
 * FIXED: Sidebar position
 */

session_start();

// Load config and functions
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';
require_once __DIR__ . '/../../../backend/helpers/encryption.php';

// Check authentication - Staff only
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: ../login.php');
    exit();
}

// Include navbar
include __DIR__ . '/../../includes/navbar_staff.php';

$conn = getDBConnection();
$error = '';
$success = '';

// ============================================================
// AUTO-CHECK EXPIRED CARDS ON PAGE LOAD
// ============================================================
$expired_deactivated = checkExpiredVisitorCards();
if ($expired_deactivated > 0) {
    $success = "✅ $expired_deactivated expired visitor card(s) have been automatically deactivated.";
}

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
// GET RESIDENTS LIST
// ============================================================
$residentsList = [];
$result = $conn->query("
    SELECT user_id, full_name, room_number 
    FROM users 
    WHERE status = 'active' 
    ORDER BY full_name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsList[] = $row;
    }
}

// ============================================================
// GET AVAILABLE RFID CARDS
// ============================================================
$availableCards = [];
$result = $conn->query("
    SELECT card_uid 
    FROM rfid_cards 
    WHERE status = 'active' 
    AND card_type = 'visitor'
    AND user_id IS NULL
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ORDER BY card_uid
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $availableCards[] = $row['card_uid'];
    }
}

// ============================================================
// GET CARDS EXPIRING SOON
// ============================================================
$expiringSoonCards = [];
$result = $conn->query("
    SELECT card_uid, expiry_date, visitor_name
    FROM rfid_cards 
    WHERE card_type = 'visitor'
    AND status = 'active'
    AND expiry_date IS NOT NULL
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY expiry_date ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $expiringSoonCards[] = $row;
    }
}

// ============================================================
// HANDLE VISITOR REGISTRATION WITH ENCRYPTION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_visitor'])) {
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $resident_visited = (int)($_POST['resident_visited'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $validity_start = $_POST['validity_start'] ?? date('Y-m-d');
    $duration_option = $_POST['duration_option'] ?? '1week';
    $card_uid = isset($_POST['card_uid']) ? strtoupper(trim($_POST['card_uid'])) : '';
    
    // ============================================================
    // CALCULATE DURATION BASED ON SELECTED OPTION
    // ============================================================
    $duration_days = 7; // Default: 1 week
    
    switch ($duration_option) {
        case '1week':
            $duration_days = 7;
            $duration_label = '1 Week';
            break;
        case '1month':
            $duration_days = 30;
            $duration_label = '1 Month';
            break;
        case '5months':
            $duration_days = 150; // 5 months = ~150 days (1 semester)
            $duration_label = '5 Months (1 Semester)';
            break;
        default:
            $duration_days = 7;
            $duration_label = '1 Week';
    }
    
    // Calculate validity end
    if (!empty($validity_start) && $duration_days > 0) {
        $validity_end = date('Y-m-d', strtotime($validity_start . " + $duration_days days"));
    } else {
        $validity_end = date('Y-m-d', strtotime('+7 days'));
    }
    
    if (empty($visitor_name) || empty($resident_visited) || empty($purpose)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $conn->begin_transaction();
            
            // ============================================================
            // ENCRYPT SENSITIVE DATA
            // ============================================================
            $encrypted_name = encryptData($visitor_name);
            $encrypted_phone = encryptData($phone);
            $encrypted_relationship = encryptData($relationship);
            $encrypted_purpose = encryptData($purpose);
            
            // Get resident name for encryption
            $residentName = '';
            foreach ($residentsList as $r) {
                if ($r['user_id'] == $resident_visited) {
                    $residentName = $r['full_name'];
                    break;
                }
            }
            $encrypted_resident = encryptData($residentName);
            
            if (!empty($card_uid)) {
                $check = $conn->prepare("
                    SELECT card_uid, expiry_date 
                    FROM rfid_cards 
                    WHERE card_uid = ? 
                    AND status = 'active' 
                    AND user_id IS NULL
                    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ");
                $check->bind_param("s", $card_uid);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows == 0) {
                    $check2 = $conn->prepare("SELECT card_uid, user_id, status, expiry_date FROM rfid_cards WHERE card_uid = ?");
                    $check2->bind_param("s", $card_uid);
                    $check2->execute();
                    $result2 = $check2->get_result();
                    if ($result2->num_rows > 0) {
                        $row = $result2->fetch_assoc();
                        if ($row['user_id'] !== null) {
                            $error = 'RFID card is already assigned to someone else.';
                        } elseif ($row['status'] != 'active') {
                            $error = 'RFID card is not active.';
                        } elseif ($row['expiry_date'] !== null && $row['expiry_date'] < date('Y-m-d')) {
                            $error = 'RFID card has expired. Please activate a new card.';
                        } else {
                            $error = 'RFID card is not available.';
                        }
                    } else {
                        $error = 'RFID card does not exist. Please register the card first.';
                    }
                    $check2->close();
                    $check->close();
                    $conn->rollback();
                } else {
                    $card_expiry = $result->fetch_assoc()['expiry_date'] ?? null;
                    $check->close();
                    
                    // Use the earlier of card expiry or visitor validity end
                    $expiry_date = $card_expiry ?? $validity_end;
                    if ($card_expiry && $validity_end) {
                        $expiry_date = min($card_expiry, $validity_end);
                    }
                    
                    // ============================================================
                    // UPDATE RFID CARD WITH ENCRYPTED DATA
                    // ============================================================
                    $stmt = $conn->prepare("
                        UPDATE rfid_cards 
                        SET user_id = ?, 
                            visitor_name = ?, 
                            visitor_phone = ?, 
                            resident_visited = ?, 
                            purpose_of_visit = ?,
                            expiry_date = ?,
                            is_encrypted = 1
                        WHERE card_uid = ?
                    ");
                    $stmt->bind_param("ississs", 
                        $resident_visited, 
                        $encrypted_name, 
                        $encrypted_phone, 
                        $resident_visited, 
                        $encrypted_purpose,
                        $expiry_date,
                        $card_uid
                    );
                    $stmt->execute();
                    $stmt->close();
                    
                    // ============================================================
                    // SAVE ENCRYPTED VISITOR DATA TO SEPARATE TABLE
                    // ============================================================
                    // Check if table exists
                    $tableCheck = $conn->query("SHOW TABLES LIKE 'encrypted_visitor_data'");
                    if ($tableCheck->num_rows > 0) {
                        $stmt = $conn->prepare("
                            INSERT INTO encrypted_visitor_data (
                                card_uid,
                                visitor_name_enc,
                                phone_enc,
                                relationship_enc,
                                purpose_enc,
                                resident_visited_enc,
                                validity_start,
                                validity_end,
                                created_by,
                                ip_address
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $stmt->bind_param("ssssssssis",
                            $card_uid,
                            $encrypted_name,
                            $encrypted_phone,
                            $encrypted_relationship,
                            $encrypted_purpose,
                            $encrypted_resident,
                            $validity_start,
                            $validity_end,
                            $_SESSION['staff_id'],
                            $ip
                        );
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            
            if (empty($error)) {
                $card_uid_value = !empty($card_uid) ? $card_uid : NULL;
                
                // ============================================================
                // SAVE ENCRYPTED TO VISITOR LOGS
                // ============================================================
                $stmt = $conn->prepare("
                    INSERT INTO visitor_logs (
                        visitor_name, 
                        phone, 
                        relationship, 
                        resident_visited, 
                        purpose_of_visit, 
                        temporary_card_uid, 
                        validity_start, 
                        validity_end, 
                        access_status, 
                        is_encrypted,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 1, NOW())
                ");
                $stmt->bind_param("sssissss", 
                    $encrypted_name, 
                    $encrypted_phone, 
                    $encrypted_relationship, 
                    $resident_visited, 
                    $encrypted_purpose, 
                    $card_uid_value, 
                    $validity_start, 
                    $validity_end
                );
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $success = "✅ Visitor registered successfully with AES-256 encryption!";
                    $success .= " Duration: $duration_label ($duration_days days)";
                    $success .= " Expires: " . date('M d, Y', strtotime($validity_end));
                    logStaffAudit($_SESSION['staff_id'], 'Register Visitor', "Registered encrypted visitor: $visitor_name (Duration: $duration_label, $duration_days days)");
                    
                    // Refresh available cards
                    $availableCards = [];
                    $result = $conn->query("
                        SELECT card_uid 
                        FROM rfid_cards 
                        WHERE status = 'active' 
                        AND card_type = 'visitor'
                        AND user_id IS NULL
                        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                        ORDER BY card_uid
                    ");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $availableCards[] = $row['card_uid'];
                        }
                    }
                    
                    $_POST = array();
                } else {
                    $conn->rollback();
                    $error = "Failed to register visitor: " . $stmt->error;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// HANDLE RFID CARD REGISTRATION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_rfid_card'])) {
    $card_uid = strtoupper(trim($_POST['card_uid'] ?? ''));
    $card_type = $_POST['card_type'] ?? 'visitor';
    $expiry_date = $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'));
    
    if (empty($card_uid)) {
        $error = 'Please enter a card UID.';
    } else {
        $check = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ?");
        $check->bind_param("s", $card_uid);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Card UID already exists.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO rfid_cards (card_uid, user_id, card_type, status, issued_date, expiry_date, is_encrypted)
                VALUES (?, NULL, ?, 'active', CURDATE(), ?, 1)
            ");
            $stmt->bind_param("sss", $card_uid, $card_type, $expiry_date);
            if ($stmt->execute()) {
                $success = "✅ RFID card registered successfully!";
                logStaffAudit($_SESSION['staff_id'], 'Register RFID Card', "Registered visitor RFID card: $card_uid");
                
                $availableCards = [];
                $result = $conn->query("
                    SELECT card_uid 
                    FROM rfid_cards 
                    WHERE status = 'active' 
                    AND card_type = 'visitor'
                    AND user_id IS NULL
                    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                    ORDER BY card_uid
                ");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $availableCards[] = $row['card_uid'];
                    }
                }
            } else {
                $error = "Failed to register RFID card: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// HANDLE CARD ACTIONS
// ============================================================
if (isset($_GET['deactivate']) && !empty($_GET['deactivate'])) {
    $card_uid = $_GET['deactivate'];
    $stmt = $conn->prepare("UPDATE rfid_cards SET status = 'deactivated' WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card deactivated successfully!";
        logStaffAudit($_SESSION['staff_id'], 'Deactivate RFID', "Deactivated visitor card: $card_uid");
    } else {
        $error = "Failed to deactivate card.";
    }
    $stmt->close();
}

if (isset($_GET['activate']) && !empty($_GET['activate'])) {
    $card_uid = $_GET['activate'];
    $stmt = $conn->prepare("UPDATE rfid_cards SET status = 'active' WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card activated successfully!";
        logStaffAudit($_SESSION['staff_id'], 'Activate RFID', "Activated visitor card: $card_uid");
    } else {
        $error = "Failed to activate card.";
    }
    $stmt->close();
}

if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $card_uid = $_GET['delete'];
    
    // Delete encrypted data first if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'encrypted_visitor_data'");
    if ($tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM encrypted_visitor_data WHERE card_uid = ?");
        $stmt->bind_param("s", $card_uid);
        $stmt->execute();
        $stmt->close();
    }
    
    $stmt = $conn->prepare("DELETE FROM rfid_cards WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card deleted successfully!";
        logStaffAudit($_SESSION['staff_id'], 'Delete RFID', "Deleted visitor card: $card_uid");
    } else {
        $error = "Failed to delete card.";
    }
    $stmt->close();
}

// ============================================================
// GET VISITOR CARDS WITH DECRYPTION
// ============================================================
$visitorCards = [];

$totalResult = $conn->query("
    SELECT COUNT(*) as total
    FROM rfid_cards c
    WHERE c.card_type = 'visitor'
");
$totalCards = 0;
if ($totalResult && $row = $totalResult->fetch_assoc()) {
    $totalCards = (int)$row['total'];
}

$totalPages = ceil($totalCards / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$result = $conn->query("
    SELECT c.*, u.full_name as resident_name, u.room_number
    FROM rfid_cards c
    LEFT JOIN users u ON c.resident_visited = u.user_id
    WHERE c.card_type = 'visitor'
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // ============================================================
        // DECRYPT SENSITIVE DATA FOR DISPLAY
        // ============================================================
        if ($row['is_encrypted'] == 1) {
            $row['visitor_name'] = safeDecryptData($row['visitor_name'] ?? '');
            $row['visitor_phone'] = safeDecryptData($row['visitor_phone'] ?? '');
            $row['purpose_of_visit'] = safeDecryptData($row['purpose_of_visit'] ?? '');
        }
        $visitorCards[] = $row;
    }
}

// Get stats
$stats = [
    'total_visitors' => 0,
    'pending' => 0,
    'available_cards' => count($availableCards),
    'total_residents' => count($residentsList),
    'expired_visitors' => 0,
    'expiring_soon' => 0,
    'encrypted_visitors' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_visitors'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM rfid_cards 
    WHERE card_type = 'visitor' 
    AND status = 'expired'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['expired_visitors'] = (int)$row['count'];
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
    FROM rfid_cards 
    WHERE card_type = 'visitor' 
    AND is_encrypted = 1
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['encrypted_visitors'] = (int)$row['count'];
}

// Get staff info
$staffInfo = null;
$stmt = $conn->prepare("SELECT * FROM staff_users WHERE staff_id = ?");
$stmt->bind_param("i", $_SESSION['staff_id']);
$stmt->execute();
$result = $stmt->get_result();
$staffInfo = $result->fetch_assoc();
$stmt->close();

// Get dark mode
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['staff_id'])) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE staff_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['staff_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $darkModeFromDb = $row['setting_value'];
            if ($darkModeFromDb == 'true') {
                $darkModeClass = 'dark-mode';
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Register Visitor - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        
        /* ============================================================
           FIX: MAIN CONTENT OFFSET
           ============================================================ */
        .container-fluid {
            padding-top: 10px !important;
        }
        
        main {
            padding-top: 10px !important;
            margin-top: 0 !important;
        }
        
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
           SIDEBAR - FIXED POSITION (SAME AS DASHBOARD)
           ============================================================ */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 260px !important;
            z-index: 100 !important;
            overflow-y: auto !important;
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
           MAIN CONTENT - OFFSET FOR SIDEBAR
           ============================================================ */
        .main-content {
            margin-left: 260px !important;
            padding: 20px 30px !important;
            min-height: calc(100vh - 70px) !important;
        }
        
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
        .stat-number.text-warning { color: #fbbf24 !important; }
        .stat-number.text-success { color: #34d399 !important; }
        
        .pulse-badge {
            animation: pulseBadge 1s infinite;
        }
        @keyframes pulseBadge {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .form-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .form-section h5 {
            color: #93c5fd !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
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
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-info { color: #60a5fa !important; }
        
        .duration-option {
            background: #1a1a2e !important;
            border: 2px solid #2a2a4a !important;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #e0e0e0;
        }
        .duration-option:hover {
            border-color: #2a5a9a !important;
            background: #1a2a3e !important;
            transform: translateY(-2px);
        }
        .duration-option.selected {
            border-color: #2a5a9a !important;
            background: #1a2a4a !important;
            box-shadow: 0 0 20px rgba(26,58,106,0.3);
        }
        .duration-option .duration-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .duration-option .duration-label {
            font-weight: 600;
            font-size: 14px;
        }
        .duration-option .duration-days {
            font-size: 11px;
            color: #808090;
        }
        .duration-option input[type="radio"] {
            display: none;
        }
        
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
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-success-custom {
            background: #065f46 !important;
            border: none !important;
            color: #34d399 !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-success-custom:hover {
            background: #0a7a5a !important;
            color: #6ee7b7 !important;
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .btn-warning {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            border: none !important;
        }
        .btn-warning:hover { background: #5a4a2a !important; color: #fcd34d !important; }
        .btn-success {
            background: #065f46 !important;
            color: #34d399 !important;
            border: none !important;
        }
        .btn-success:hover { background: #0a7a5a !important; color: #6ee7b7 !important; }
        .btn-danger {
            background: #7a2a2a !important;
            color: #f87171 !important;
            border: none !important;
        }
        .btn-danger:hover { background: #8a3a3a !important; color: #fca5a5 !important; }
        .btn-sm { font-size: 12px !important; padding: 4px 10px !important; }
        
        .card-item {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-left: 4px solid #10b981;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
        }
        .card-item:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .card-item.deactivated { border-left-color: #6b7280 !important; opacity: 0.7; }
        .card-item.expired { border-left-color: #ef4444 !important; background: #1a0a0a !important; }
        .card-item .uid {
            font-family: monospace;
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 14px;
        }
        .card-item .visitor-detail {
            font-size: 12px;
            color: #808090 !important;
        }
        .card-item .resident-name {
            font-weight: 600;
            color: #93c5fd !important;
        }
        .card-item .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .card-item .badge-secondary { background: #2a2a3a !important; color: #808090 !important; }
        .card-item .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .card-item .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        
        .available-card-item {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 15px;
            text-align: center;
            border-left: 4px solid #10b981;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
        }
        .available-card-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .available-card-item .uid {
            font-family: monospace;
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 13px;
        }
        .available-card-item .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .available-card-item .expiry-label {
            font-size: 10px;
            color: #808090 !important;
        }
        
        .encryption-badge {
            background: #1a3a6a !important;
            color: #93c5fd !important;
            border: 1px solid #2a5a9a !important;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        .encryption-badge i {
            margin-right: 4px;
        }
        
        .view-only-badge {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
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
        .alert-warning {
            background: #4a3a1a !important;
            border-color: #4a3a1a !important;
            color: #fbbf24 !important;
        }
        
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
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
            .sidebar {
                padding-top: 70px !important;
                position: fixed !important;
                top: 60px !important;
                bottom: 0 !important;
                left: -280px !important;
                width: 280px !important;
                transition: left 0.3s ease !important;
                z-index: 999 !important;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0 !important; }
            .main-content {
                margin-left: 0 !important;
                padding: 15px !important;
            }
            .form-section { padding: 20px; }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            .duration-option { padding: 10px; }
            .duration-option .duration-label { font-size: 12px; }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <?php include __DIR__ . '/includes/navbar_staff.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/includes/sidebar_staff.php'; ?>
            
            <main class="main-content">
                
                <!-- HEADER -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-plus me-2" style="color: #1a3a6a;"></i>
                        Register Visitor
                        <span class="encryption-badge ms-2">
                            <i class="fas fa-lock"></i> AES-256 Encrypted
                        </span>
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge bg-warning ms-2"><?php echo $stats['pending']; ?> pending</span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <span class="view-only-badge me-2">
                            <i class="fas fa-eye me-1"></i> Staff View
                        </span>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="visitors.php" class="btn btn-outline-secondary btn-sm ms-2">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
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

                <!-- EXPIRING SOON NOTIFICATION -->
                <?php if (!empty($expiringSoonCards)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-clock me-2"></i>
                        <strong><?php echo count($expiringSoonCards); ?> visitor card(s)</strong> will expire within 3 days:
                        <?php foreach ($expiringSoonCards as $card): ?>
                            <span class="badge bg-warning text-dark ms-1">
                                <?php echo htmlspecialchars($card['card_uid']); ?>
                                (<?php echo htmlspecialchars($card['visitor_name'] ?? 'Unassigned'); ?>)
                                - <?php echo date('M d', strtotime($card['expiry_date'])); ?>
                            </span>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- STATS CARDS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-user-plus"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_visitors']; ?></div>
                                <div class="stat-label">Total Visitors</div>
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
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['available_cards'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $stats['available_cards']; ?>
                                </div>
                                <div class="stat-label">Available Cards</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_residents']; ?></div>
                                <div class="stat-label">Residents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $stats['encrypted_visitors'] > 0 ? '#10b981' : '#6b7280'; ?>;">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div>
                                <div class="stat-number <?php echo $stats['encrypted_visitors'] > 0 ? 'text-success' : ''; ?>">
                                    <?php echo $stats['encrypted_visitors']; ?>
                                </div>
                                <div class="stat-label">Encrypted</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $stats['expiring_soon'] > 0 ? '#f59e0b' : '#10b981'; ?>;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div class="stat-number <?php echo $stats['expiring_soon'] > 0 ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo $stats['expiring_soon']; ?>
                                </div>
                                <div class="stat-label">Expiring Soon</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- REGISTER VISITOR FORM -->
                <div class="form-section">
                    <h5>
                        <i class="fas fa-user-plus me-2"></i>Visitor Registration
                        <span class="encryption-badge ms-2">
                            <i class="fas fa-lock me-1"></i> AES-256 Encrypted
                        </span>
                    </h5>
                    
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Visitor Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="visitor_name" placeholder="Full name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="required">*</span></label>
                                <input type="text" class="form-control" name="phone" placeholder="09XXXXXXXXX" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resident to Visit <span class="required">*</span></label>
                                <select class="form-select" name="resident_visited" required>
                                    <option value="">-- Select Resident --</option>
                                    <?php foreach ($residentsList as $resident): ?>
                                        <option value="<?php echo $resident['user_id']; ?>">
                                            <?php echo htmlspecialchars($resident['full_name']); ?> 
                                            (Room <?php echo htmlspecialchars($resident['room_number'] ?? 'N/A'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Relationship</label>
                                <input type="text" class="form-control" name="relationship" placeholder="e.g., Friend, Family">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Purpose of Visit <span class="required">*</span></label>
                                <input type="text" class="form-control" name="purpose" placeholder="e.g., Visit friend, Meeting" required>
                            </div>
                            
                            <!-- DURATION OPTIONS -->
                            <div class="col-md-12">
                                <label class="form-label">Validity Duration <span class="required">*</span></label>
                                <div class="row g-2">
                                    <div class="col-4 col-md-4">
                                        <label class="duration-option" id="duration_1week">
                                            <input type="radio" name="duration_option" value="1week" checked>
                                            <div class="duration-icon">📅</div>
                                            <div class="duration-label">1 Week</div>
                                            <div class="duration-days">7 days</div>
                                        </label>
                                    </div>
                                    <div class="col-4 col-md-4">
                                        <label class="duration-option" id="duration_1month">
                                            <input type="radio" name="duration_option" value="1month">
                                            <div class="duration-icon">📆</div>
                                            <div class="duration-label">1 Month</div>
                                            <div class="duration-days">30 days</div>
                                        </label>
                                    </div>
                                    <div class="col-4 col-md-4">
                                        <label class="duration-option" id="duration_5months">
                                            <input type="radio" name="duration_option" value="5months">
                                            <div class="duration-icon">🎓</div>
                                            <div class="duration-label">5 Months</div>
                                            <div class="duration-days">150 days (1 Semester)</div>
                                        </label>
                                    </div>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Card will automatically expire after the selected duration
                                </small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Validity Start <span class="required">*</span></label>
                                <input type="date" class="form-control" name="validity_start" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Validity End <span class="required">*</span></label>
                                <input type="date" class="form-control" name="validity_end" id="validity_end" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" readonly style="background: #0d1220 !important; cursor: not-allowed;">
                                <small class="text-muted">Auto-calculated from duration</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="p-2 text-center" style="background: #0d1528; border-radius: 10px; border: 1px solid #1a2a4a;">
                                    <span id="duration_display" class="text-info">
                                        <i class="fas fa-clock me-1"></i>
                                        <span id="duration_label_display">1 Week</span>
                                        (<span id="duration_days_display">7</span> days)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label">Assign RFID Card</label>
                                <select class="form-select" name="card_uid">
                                    <option value="">-- No Card --</option>
                                    <?php foreach ($availableCards as $card): ?>
                                        <option value="<?php echo htmlspecialchars($card); ?>">
                                            <?php echo htmlspecialchars($card); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    <i class="fas fa-lock me-1"></i> All visitor data will be encrypted at rest (AES-256)
                                </small>
                                <?php if (empty($availableCards)): ?>
                                    <br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> No available cards. Register a new card below.</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" name="register_visitor" class="btn btn-submit" <?php echo empty($availableCards) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-lock me-1"></i> Register Visitor (Encrypted)
                                </button>
                                <?php if (empty($availableCards)): ?>
                                    <span class="text-muted ms-2"><i class="fas fa-info-circle me-1"></i> Register a card first</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- REGISTER RFID CARD -->
                <div class="form-section">
                    <h5><i class="fas fa-id-card me-2"></i>Register Visitor RFID Card</h5>
                    <p class="text-muted">Register a new RFID card for visitors</p>
                    
                    <form method="POST" action="" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Card UID <span class="required">*</span></label>
                            <input type="text" class="form-control" name="card_uid" placeholder="e.g., A1B2C3D4" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Card Type</label>
                            <select class="form-select" name="card_type">
                                <option value="visitor">Visitor</option>
                                <option value="resident">Resident</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expiry Date <span class="required">*</span></label>
                            <input type="date" class="form-control" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" required>
                            <small class="text-muted">Cards expire on this date</small>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="register_rfid_card" class="btn btn-success-custom w-100">
                                <i class="fas fa-save me-1"></i> Register
                            </button>
                        </div>
                    </form>
                </div>

                <!-- AVAILABLE RFID CARDS -->
                <div class="form-section">
                    <h5><i class="fas fa-list me-2"></i>Available Visitor RFID Cards</h5>
                    
                    <?php if (empty($availableCards)): ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-info-circle me-2"></i>
                            No available visitor RFID cards. Register a new card above.
                        </p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($availableCards as $card): 
                                $expiryInfo = '';
                                $stmt = $conn->prepare("SELECT expiry_date FROM rfid_cards WHERE card_uid = ?");
                                $stmt->bind_param("s", $card);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($row = $result->fetch_assoc()) {
                                    if ($row['expiry_date']) {
                                        $expiryInfo = 'Expires: ' . date('M d, Y', strtotime($row['expiry_date']));
                                    }
                                }
                                $stmt->close();
                            ?>
                                <div class="col-md-3 col-lg-2">
                                    <div class="available-card-item">
                                        <i class="fas fa-id-card fa-2x text-primary mb-2" style="color: #667eea !important;"></i>
                                        <div class="uid"><?php echo htmlspecialchars($card); ?></div>
                                        <div class="expiry-label"><?php echo $expiryInfo ?: 'No expiry'; ?></div>
                                        <span class="badge badge-success mt-1">Available</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- REGISTERED VISITOR CARDS -->
                <div class="form-section">
                    <h5>
                        <i class="fas fa-list me-2"></i>Registered Visitor Cards
                        <span class="encryption-badge ms-2">
                            <i class="fas fa-lock me-1"></i> Encrypted
                        </span>
                    </h5>
                    
                    <?php if (empty($visitorCards)): ?>
                        <p class="text-muted text-center py-3">No visitor cards registered yet</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($visitorCards as $card): 
                                $days_left = 0;
                                $expiring_soon = false;
                                if ($card['expiry_date']) {
                                    $days_left = ceil((strtotime($card['expiry_date']) - time()) / 86400);
                                    $expiring_soon = $days_left >= 0 && $days_left <= 3 && $card['status'] == 'active';
                                }
                            ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card-item <?php echo $card['status'] != 'active' ? $card['status'] : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="uid">
                                                    <?php echo htmlspecialchars($card['card_uid']); ?>
                                                    <?php if ($card['is_encrypted'] == 1): ?>
                                                        <span class="encryption-badge ms-1" style="font-size: 8px;">
                                                            <i class="fas fa-lock"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="visitor-detail">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($card['visitor_name'] ?? 'Unassigned'); ?>
                                                </div>
                                                <?php if (!empty($card['visitor_phone'])): ?>
                                                    <div class="visitor-detail">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <?php echo htmlspecialchars($card['visitor_phone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($card['resident_name'])): ?>
                                                    <div class="visitor-detail">
                                                        <i class="fas fa-user me-1"></i>
                                                        Visiting: <span class="resident-name"><?php echo htmlspecialchars($card['resident_name']); ?></span>
                                                        (Room <?php echo htmlspecialchars($card['room_number'] ?? 'N/A'); ?>)
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($card['purpose_of_visit'])): ?>
                                                    <div class="visitor-detail">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        <?php echo htmlspecialchars($card['purpose_of_visit']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="visitor-detail">
                                                    <i class="fas fa-calendar-alt me-1"></i>
                                                    Status: 
                                                    <span class="badge <?php 
                                                        echo $card['status'] == 'active' ? 'badge-success' : 
                                                            ($card['status'] == 'expired' ? 'badge-danger' : 'badge-secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($card['status']); ?>
                                                    </span>
                                                    <?php if ($card['expiry_date']): ?>
                                                        <span class="mx-1">|</span>
                                                        <i class="fas fa-hourglass-end me-1"></i>
                                                        Expires: <?php echo date('M d, Y', strtotime($card['expiry_date'])); ?>
                                                        <?php if ($expiring_soon): ?>
                                                            <span class="badge badge-warning ms-1">
                                                                <?php echo $days_left; ?> day<?php echo $days_left > 1 ? 's' : ''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column gap-1">
                                                <?php if ($card['status'] == 'active'): ?>
                                                    <a href="?deactivate=<?php echo $card['card_uid']; ?>" 
                                                       class="btn btn-sm btn-warning"
                                                       onclick="return confirm('Deactivate this card?')">
                                                        <i class="fas fa-pause"></i> Deactivate
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?activate=<?php echo $card['card_uid']; ?>" 
                                                       class="btn btn-sm btn-success"
                                                       onclick="return confirm('Activate this card?')">
                                                        <i class="fas fa-play"></i> Activate
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $card['card_uid']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Delete this card permanently? This will also delete encrypted data.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- PAGINATION -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="page-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalCards); ?> of <?php echo $totalCards; ?> cards
                                        <span class="mx-1 text-muted">|</span>
                                        <span class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
                                        <div class="per-page-selector d-flex align-items-center gap-2">
                                            <label>Show:</label>
                                            <select onchange="changePerPage(this.value)">
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
                                                    <a class="page-link" href="?page=1<?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-left"></i>
                                                    </a>
                                                </li>
                                                
                                                <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                if ($startPage > 1) {
                                                    echo '<li class="page-item"><span class="page-link">...</span></li>';
                                                }
                                                for ($i = $startPage; $i <= $endPage; $i++):
                                                ?>
                                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo '&per_page=' . $perPage; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                <?php if ($endPage < $totalPages): ?>
                                                    <li class="page-item"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- FOOTER -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span>Total: <?php echo $stats['total_visitors']; ?> visitors</span>
                    <span class="mx-2">|</span>
                    <span class="text-success"><i class="fas fa-id-card me-1"></i><?php echo $stats['available_cards']; ?> available</span>
                    <span class="mx-2">|</span>
                    <span class="text-info"><i class="fas fa-lock me-1"></i><?php echo $stats['encrypted_visitors']; ?> encrypted</span>
                    <?php if ($stats['expired_visitors'] > 0): ?>
                        <span class="mx-2">|</span>
                        <span class="text-danger"><i class="fas fa-hourglass-end me-1"></i><?php echo $stats['expired_visitors']; ?> expired</span>
                    <?php endif; ?>
                </footer>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer_staff.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // DURATION SELECTOR - Update display when selected
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const durationOptions = document.querySelectorAll('input[name="duration_option"]');
            const startDateInput = document.querySelector('input[name="validity_start"]');
            const endDateInput = document.getElementById('validity_end');
            const durationLabelDisplay = document.getElementById('duration_label_display');
            const durationDaysDisplay = document.getElementById('duration_days_display');
            
            // Duration mapping
            const durationMap = {
                '1week': { label: '1 Week', days: 7 },
                '1month': { label: '1 Month', days: 30 },
                '5months': { label: '5 Months (1 Semester)', days: 150 }
            };
            
            // Update end date based on start date and duration
            function updateEndDate() {
                const selectedOption = document.querySelector('input[name="duration_option"]:checked');
                if (!selectedOption) return;
                
                const duration = durationMap[selectedOption.value];
                if (!duration) return;
                
                const startDate = new Date(startDateInput.value);
                if (isNaN(startDate.getTime())) return;
                
                const endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + duration.days);
                
                // Format as YYYY-MM-DD
                const year = endDate.getFullYear();
                const month = String(endDate.getMonth() + 1).padStart(2, '0');
                const day = String(endDate.getDate()).padStart(2, '0');
                endDateInput.value = `${year}-${month}-${day}`;
                
                // Update display
                durationLabelDisplay.textContent = duration.label;
                durationDaysDisplay.textContent = duration.days;
            }
            
            // Add click event to duration options
            durationOptions.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    // Update visual selection
                    document.querySelectorAll('.duration-option').forEach(function(el) {
                        el.classList.remove('selected');
                    });
                    const parentLabel = this.closest('.duration-option');
                    if (parentLabel) {
                        parentLabel.classList.add('selected');
                    }
                    updateEndDate();
                });
            });
            
            // Update on start date change
            if (startDateInput) {
                startDateInput.addEventListener('change', updateEndDate);
            }
            
            // Initial update
            updateEndDate();
            
            // Select default (1 Week)
            const defaultOption = document.querySelector('input[name="duration_option"][value="1week"]');
            if (defaultOption) {
                defaultOption.checked = true;
                const parentLabel = defaultOption.closest('.duration-option');
                if (parentLabel) {
                    parentLabel.classList.add('selected');
                }
                updateEndDate();
            }
        });
        
        // ============================================================
        // PAGINATION
        // ============================================================
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // ============================================================
        // TIME UPDATE
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
        
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
