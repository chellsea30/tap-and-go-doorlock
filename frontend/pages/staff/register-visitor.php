<?php
/**
 * Tap-and-Go Doorlock - Staff Register Visitor with RFID
 * STAFF CAN REGISTER - With Visitor Details
 * PURE DARK MODE - With Show Entries
 */

session_start();

// Load config and functions
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// Check authentication - Staff only
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: ../login.php');
    exit();
}

$conn = getDBConnection();
$error = '';
$success = '';

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
    WHERE status = 'active' AND card_type = 'visitor'
    AND user_id IS NULL
    ORDER BY card_uid
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $availableCards[] = $row['card_uid'];
    }
}

// ============================================================
// HANDLE VISITOR REGISTRATION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_visitor'])) {
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $resident_visited = (int)($_POST['resident_visited'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $validity_start = $_POST['validity_start'] ?? date('Y-m-d');
    $validity_end = $_POST['validity_end'] ?? date('Y-m-d', strtotime('+1 week'));
    $card_uid = isset($_POST['card_uid']) ? strtoupper(trim($_POST['card_uid'])) : '';
    
    if (empty($visitor_name) || empty($resident_visited) || empty($purpose)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $conn->begin_transaction();
            
            if (!empty($card_uid)) {
                $check = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ? AND status = 'active' AND user_id IS NULL");
                $check->bind_param("s", $card_uid);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows == 0) {
                    $check2 = $conn->prepare("SELECT card_uid, user_id FROM rfid_cards WHERE card_uid = ?");
                    $check2->bind_param("s", $card_uid);
                    $check2->execute();
                    $result2 = $check2->get_result();
                    if ($result2->num_rows > 0) {
                        $row = $result2->fetch_assoc();
                        if ($row['user_id'] !== null) {
                            $error = 'RFID card is already assigned to someone else.';
                        } else {
                            $error = 'RFID card exists but is not active.';
                        }
                    } else {
                        $error = 'RFID card does not exist. Please register the card first.';
                    }
                    $check2->close();
                    $check->close();
                    $conn->rollback();
                } else {
                    $check->close();
                    
                    $residentInfo = null;
                    $stmt = $conn->prepare("SELECT full_name, room_number FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $resident_visited);
                    $stmt->execute();
                    $resResult = $stmt->get_result();
                    if ($row = $resResult->fetch_assoc()) {
                        $residentInfo = $row;
                    }
                    $stmt->close();
                    
                    $stmt = $conn->prepare("
                        UPDATE rfid_cards 
                        SET user_id = ?, 
                            visitor_name = ?, 
                            visitor_phone = ?, 
                            resident_visited = ?, 
                            purpose_of_visit = ?
                        WHERE card_uid = ?
                    ");
                    $stmt->bind_param("ississ", 
                        $resident_visited, 
                        $visitor_name, 
                        $phone, 
                        $resident_visited, 
                        $purpose, 
                        $card_uid
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            if (empty($error)) {
                $card_uid_value = !empty($card_uid) ? $card_uid : NULL;
                
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
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("sssissss", 
                    $visitor_name, 
                    $phone, 
                    $relationship, 
                    $resident_visited, 
                    $purpose, 
                    $card_uid_value, 
                    $validity_start, 
                    $validity_end
                );
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $success = "✅ Visitor registered successfully!";
                    logStaffAudit($_SESSION['staff_id'], 'Register Visitor', "Registered visitor: $visitor_name");
                    
                    // Refresh available cards
                    $availableCards = [];
                    $result = $conn->query("
                        SELECT card_uid 
                        FROM rfid_cards 
                        WHERE status = 'active' AND card_type = 'visitor'
                        AND user_id IS NULL
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
                INSERT INTO rfid_cards (card_uid, user_id, card_type, status, issued_date)
                VALUES (?, NULL, ?, 'active', CURDATE())
            ");
            $stmt->bind_param("ss", $card_uid, $card_type);
            if ($stmt->execute()) {
                $success = "✅ RFID card registered successfully!";
                logStaffAudit($_SESSION['staff_id'], 'Register RFID Card', "Registered visitor RFID card: $card_uid");
                
                // Refresh available cards
                $availableCards = [];
                $result = $conn->query("
                    SELECT card_uid 
                    FROM rfid_cards 
                    WHERE status = 'active' AND card_type = 'visitor'
                    AND user_id IS NULL
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
// GET VISITOR CARDS WITH PAGINATION
// ============================================================
$visitorCards = [];

// Get total count
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
        $visitorCards[] = $row;
    }
}

// ============================================================
// GET STATS
// ============================================================
$stats = [
    'total_visitors' => 0,
    'pending' => 0,
    'available_cards' => count($availableCards),
    'total_residents' => count($residentsList)
];

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_visitors'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending'] = (int)$row['count'];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            padding-top: 56px;
        }
        
        /* ============================================================
           DARK NAVBAR
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           DARK SIDEBAR
           ============================================================ */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            min-height: 100vh;
            box-shadow: 2px 0 15px rgba(0,0,0,0.3) !important;
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            width: 260px;
            z-index: 100;
            padding: 15px 0 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
            padding: 10px 16px;
            border-radius: 8px;
            margin: 2px 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(26,58,106,0.3) !important;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            color: #606070 !important;
            margin-right: 10px;
        }
        .sidebar .nav-link.active i {
            color: white !important;
        }
        .sidebar .nav-link .badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: auto;
        }
        .sidebar-footer {
            padding: 10px 0 20px 0;
            border-top: 1px solid #1a2a4a !important;
            margin-top: auto;
        }
        .sidebar-footer .text-muted {
            color: #606070 !important;
        }
        
        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: calc(100vh - 56px);
        }
        
        /* ============================================================
           VIEW ONLY BADGE - PINAKITA LANG SA HEADER
           ============================================================ */
        .view-only-badge {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #e0e0e0;
            margin: 0;
        }
        .stat-label {
            font-size: 12px;
            color: #808090;
            margin: 0;
        }
        
        /* ============================================================
           DARK FORM SECTIONS
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
        .btn-outline-primary {
            border-color: #1a3a6a !important;
            color: #93c5fd !important;
        }
        .btn-outline-primary:hover {
            background: #1a3a6a !important;
            color: white !important;
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
        
        /* ============================================================
           DARK CARD ITEMS
           ============================================================ */
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
        .card-item.deactivated {
            border-left-color: #6b7280 !important;
            opacity: 0.7;
        }
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
        
        /* ============================================================
           DARK AVAILABLE CARDS
           ============================================================ */
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
        .alert .btn-close { filter: invert(1) !important; }
        
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
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
            }
            .sidebar.show { left: 0; }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
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
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <!-- ===== NAVBAR ===== -->
    <?php include __DIR__ . '/../../includes/navbar_staff.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- ===== SIDEBAR ===== -->
            <?php include __DIR__ . '/includes/sidebar_staff.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-plus me-2" style="color: #1a3a6a;"></i>
                        Register Visitor
                    </h1>
                    <div>
                        <span class="view-only-badge me-2">
                            <i class="fas fa-eye me-1"></i> View Only
                        </span>
                        <a href="visitors.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Visitors
                        </a>
                        <a href="visitor-logs.php" class="btn btn-outline-primary btn-sm ms-1">
                            <i class="fas fa-history me-1"></i> Visitor Logs
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

                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-user-plus"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_visitors']; ?></div>
                                <div class="stat-label">Total Visitors</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['available_cards']; ?></div>
                                <div class="stat-label">Available Cards</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_residents']; ?></div>
                                <div class="stat-label">Residents</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                REGISTER VISITOR FORM - FULLY FUNCTIONAL
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-user-plus me-2"></i>Visitor Registration</h5>
                    
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Visitor Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="visitor_name" placeholder="Full name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" placeholder="09XXXXXXXXX">
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
                            <div class="col-md-6">
                                <label class="form-label">Validity Start <span class="required">*</span></label>
                                <input type="date" class="form-control" name="validity_start" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Validity End <span class="required">*</span></label>
                                <input type="date" class="form-control" name="validity_end" value="<?php echo date('Y-m-d', strtotime('+1 week')); ?>" required>
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
                                <small class="text-muted">Assign an available visitor RFID card</small>
                                <?php if (empty($availableCards)): ?>
                                    <br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> No available cards. Register a new card below.</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" name="register_visitor" class="btn btn-submit" <?php echo empty($availableCards) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-save me-1"></i> Register Visitor
                                </button>
                                <?php if (empty($availableCards)): ?>
                                    <span class="text-muted ms-2"><i class="fas fa-info-circle me-1"></i> Register a card first</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
 
                <!-- ============================================================
                REGISTERED VISITOR CARDS WITH PAGINATION
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-list me-2"></i>Registered Visitor Cards</h5>
                    
                    <?php if (empty($visitorCards)): ?>
                        <p class="text-muted text-center py-3">No visitor cards registered yet</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($visitorCards as $card): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card-item <?php echo $card['status'] != 'active' ? 'deactivated' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="uid"><?php echo htmlspecialchars($card['card_uid']); ?></div>
                                                <div class="visitor-detail">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($card['visitor_name'] ?? 'Unassigned'); ?>
                                                </div>
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
                                                    <span class="badge <?php echo $card['status'] == 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                                        <?php echo ucfirst($card['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($card['status'] == 'active'): ?>
                                                    <a href="?deactivate=<?php echo $card['card_uid']; ?>" 
                                                       class="btn btn-sm btn-warning"
                                                       onclick="return confirm('Deactivate this card?')">
                                                        <i class="fas fa-pause"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?activate=<?php echo $card['card_uid']; ?>" 
                                                       class="btn btn-sm btn-success"
                                                       onclick="return confirm('Activate this card?')">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $card['card_uid']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Delete this card permanently?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- ============================================================
                        PAGINATION WITH SHOW ENTRIES
                        ============================================================ -->
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
                                        <!-- Per Page Selector -->
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
                                        
                                        <!-- Pagination -->
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

                <div class="text-center text-muted small mt-3">
                    <i class="fas fa-eye me-1"></i> View Only Access
                    <span class="mx-2">|</span>
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo $stats['total_visitors']; ?> visitors registered
                    <span class="mx-1">|</span>
                    <i class="fas fa-id-card me-1"></i>
                    <?php echo count($availableCards); ?> available cards
                </div>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer_staff.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // CHANGE PER PAGE
        // ============================================================
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>