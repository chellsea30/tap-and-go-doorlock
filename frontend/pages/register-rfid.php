<?php
/**
 * Tap-and-Go Doorlock - Register RFID Card
 * WITH AUTO-FILL FROM AVAILABLE CARDS
 * PURE DARK MODE - WITH SHOW ENTRIES
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 * FIXED: Dark table and dark footer
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
// GET AVAILABLE CARDS
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
// GET RESIDENTS WITHOUT RFID CARDS (with pagination)
// ============================================================
$residentsWithoutCard = [];
$countQuery = "
    SELECT COUNT(*) as total
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    LEFT JOIN rfid_cards c ON u.user_id = c.user_id AND c.status = 'active'
    WHERE u.status = 'active' 
    AND c.card_uid IS NULL
";
$countResult = $conn->query($countQuery);
$totalWithoutCard = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalWithoutCard = (int)$row['total'];
}

$totalPages = ceil($totalWithoutCard / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$result = $conn->query("
    SELECT u.user_id, u.full_name, u.student_id, u.room_number, rp.course, rp.year_level
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    LEFT JOIN rfid_cards c ON u.user_id = c.user_id AND c.status = 'active'
    WHERE u.status = 'active' 
    AND c.card_uid IS NULL
    ORDER BY u.full_name
    LIMIT $perPage OFFSET $offset
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsWithoutCard[] = $row;
    }
}

// ============================================================
// GET RESIDENTS WITH RFID CARDS
// ============================================================
$residentsWithCard = [];
$result = $conn->query("
    SELECT u.user_id, u.full_name, u.student_id, u.room_number, rp.course, rp.year_level,
           c.card_uid, c.issued_date, c.expiry_date, c.status as card_status, c.card_type
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    JOIN rfid_cards c ON u.user_id = c.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsWithCard[] = $row;
    }
}

// ============================================================
// HANDLE RFID REGISTRATION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_rfid'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $card_uid = strtoupper(trim($_POST['card_uid'] ?? ''));
    $expiry_date = $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'));
    $card_type = $_POST['card_type'] ?? 'resident';
    
    if (empty($user_id) || empty($card_uid)) {
        $error = 'Please select a resident and enter a card UID.';
    } elseif (strlen($card_uid) < 4) {
        $error = 'Card UID must be at least 4 characters.';
    } else {
        // Check if card UID already exists in rfid_cards
        $check = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ?");
        $check->bind_param("s", $card_uid);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Card UID already assigned. Please use a different card.';
        } else {
            // Check if card is in available_cards
            $stmt = $conn->prepare("SELECT card_id FROM available_rfid_cards WHERE card_uid = ? AND status = 'available'");
            $stmt->bind_param("s", $card_uid);
            $stmt->execute();
            $availResult = $stmt->get_result();
            $isAvailable = $availResult->num_rows > 0;
            $stmt->close();
            
            // Insert into rfid_cards
            $stmt = $conn->prepare("
                INSERT INTO rfid_cards (card_uid, user_id, issued_date, expiry_date, card_type, status)
                VALUES (?, ?, CURDATE(), ?, ?, 'active')
            ");
            $stmt->bind_param("siss", $card_uid, $user_id, $expiry_date, $card_type);
            
            if ($stmt->execute()) {
                // If it was from available cards, mark as assigned
                if ($isAvailable) {
                    $stmt2 = $conn->prepare("UPDATE available_rfid_cards SET status = 'assigned' WHERE card_uid = ?");
                    $stmt2->bind_param("s", $card_uid);
                    $stmt2->execute();
                    $stmt2->close();
                }
                
                $success = "✅ RFID card registered successfully!";
                logAudit($_SESSION['admin_id'], 'Register RFID', "Registered RFID card $card_uid for user ID: $user_id");
                
                // Refresh page
                header('Location: register-rfid.php?success=1');
                exit();
            } else {
                $error = "Failed to register RFID card: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// HANDLE CARD DEACTIVATION
// ============================================================
if (isset($_GET['deactivate']) && !empty($_GET['deactivate'])) {
    $card_uid = $_GET['deactivate'];
    
    $stmt = $conn->prepare("UPDATE rfid_cards SET status = 'deactivated' WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card deactivated successfully!";
        logAudit($_SESSION['admin_id'], 'Deactivate RFID', "Deactivated RFID card $card_uid");
        header('Location: register-rfid.php?deactivated=1');
        exit();
    } else {
        $error = "Failed to deactivate card: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// HANDLE CARD ACTIVATION
// ============================================================
if (isset($_GET['activate']) && !empty($_GET['activate'])) {
    $card_uid = $_GET['activate'];
    
    $stmt = $conn->prepare("UPDATE rfid_cards SET status = 'active' WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card activated successfully!";
        logAudit($_SESSION['admin_id'], 'Activate RFID', "Activated RFID card $card_uid");
        header('Location: register-rfid.php?activated=1');
        exit();
    } else {
        $error = "Failed to activate card: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// GET DARK MODE
// ============================================================
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['admin_id']);
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
    <title>Register RFID Card - Tap-and-Go Doorlock</title>
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
            color: #e0e0e0 !important;
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
           FOOTER - STICKY BOTTOM (DARK)
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
        .footer span { color: #ffd700 !important; }
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 14px 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }
        .stat-number { font-size: 20px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 11px; color: #808090; margin: 0; }
        
        /* ============================================================
           DARK FORM SECTIONS
           ============================================================ */
        .form-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 20px 25px;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .form-section h5 {
            color: #93c5fd !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 8px;
            margin-bottom: 16px;
            font-size: 15px;
        }
        .form-label {
            font-weight: 500;
            font-size: 12px;
            color: #b0b0c0 !important;
        }
        .form-control, .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #e0e0e0 !important;
            height: 38px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .form-control::placeholder { color: #606070 !important; }
        .required { color: #f87171 !important; }
        
        /* ============================================================
           DARK BUTTONS
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 8px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
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
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
            font-size: 12px;
            padding: 5px 12px;
            border-radius: 8px;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .btn-sm-custom {
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 6px;
            border: none !important;
            transition: all 0.3s ease;
        }
        .btn-sm-custom:hover { transform: translateY(-1px); }
        .btn-warning {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
        }
        .btn-warning:hover { background: #5a4a2a !important; color: #fcd34d !important; }
        .btn-success {
            background: #065f46 !important;
            color: #34d399 !important;
        }
        .btn-success:hover { background: #0a7a5a !important; color: #6ee7b7 !important; }
        .btn-primary-sm {
            background: #1a3a6a !important;
            border: 1px solid #1a3a6a !important;
            color: white !important;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 6px;
        }
        .btn-primary-sm:hover {
            background: #2a5a9a !important;
            border-color: #2a5a9a !important;
            color: white !important;
        }
        
        /* ============================================================
           DARK CARD ITEMS
           ============================================================ */
        .card-item {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 10px !important;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-left: 3px solid #10b981;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
        }
        .card-item:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .card-item .name {
            font-weight: 600;
            color: #e0e0e0 !important;
            font-size: 13px;
        }
        .card-item .detail {
            font-size: 11px;
            color: #808090 !important;
        }
        .card-item .uid {
            font-family: monospace;
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 13px;
            background: #1a2a4a !important;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .card-item.deactivated {
            border-left-color: #6b7280 !important;
            opacity: 0.7;
        }
        .card-item .status-badge {
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .card-item .status-active {
            background: #065f46 !important;
            color: #34d399 !important;
        }
        .card-item .status-deactivated {
            background: #2a2a3a !important;
            color: #808090 !important;
        }
        
        /* ============================================================
           DARK AVAILABLE CARDS
           ============================================================ */
        .available-card-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 8px 10px;
            background: #1a1a2e !important;
            border-radius: 8px;
            border: 1px solid #2a2a4a !important;
            min-height: 40px;
        }
        .available-card-list .card-item-mini {
            display: inline-block;
            padding: 3px 10px;
            background: #1a1a2e !important;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            font-weight: 600;
            border: 2px solid #2a2a4a !important;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #e0e0e0 !important;
        }
        .available-card-list .card-item-mini:hover {
            border-color: #667eea !important;
            background: #2a2a4a !important;
            transform: scale(1.05);
        }
        .available-card-list .card-item-mini.selected {
            border-color: #667eea !important;
            background: #1a2a4a !important;
            color: #93c5fd !important;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
        }
        
        /* ============================================================
           DARK AUTO-FILL INFO
           ============================================================ */
        .auto-fill-info {
            background: #15152a !important;
            border-radius: 8px;
            padding: 10px 14px;
            border-left: 3px solid #667eea !important;
            display: none;
            margin-bottom: 12px;
        }
        .auto-fill-info.show {
            display: block;
        }
        .auto-fill-info .text-muted { color: #808090 !important; }
        .auto-fill-info .text-success { color: #34d399 !important; }
        
        /* ============================================================
           DARK TABLE - FIXED
           ============================================================ */
        .table {
            color: #e0e0e0 !important;
            font-size: 13px;
            background: #111827 !important;
        }
        .table th {
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 12px;
            background: #0d1528 !important;
        }
        .table td {
            border-bottom: 1px solid #1a2a4a !important;
            background: #111827 !important;
            color: #e0e0e0 !important;
        }
        .table-hover tbody tr:hover td {
            background: rgba(255,255,255,0.03) !important;
        }
        .table .text-muted { color: #6b7280 !important; }
        .table-responsive {
            background: #111827 !important;
            border-radius: 8px;
            border: 1px solid #1a2a4a !important;
            overflow: hidden;
        }
        
        /* ============================================================
           DARK ALERTS
           ============================================================ */
        .alert-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #6ee7b7 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert-warning {
            background: #4a3a1a !important;
            border-color: #4a3a1a !important;
            color: #fbbf24 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert-info {
            background: #1a2a4a !important;
            border-color: #1a3a6a !important;
            color: #93c5fd !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           PAGE HEADER
           ============================================================ */
        .page-header {
            padding-bottom: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1a2a4a;
        }
        .page-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #e0e0e0;
        }
        .page-header h1 i {
            color: #1a3a6a;
        }
        
        /* ============================================================
           PAGINATION - DARK
           ============================================================ */
        .pagination-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 10px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-top: 15px;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: none;
            color: #9090a0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 5px 12px;
            font-size: 12px;
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
        .page-info { color: #808090 !important; font-size: 12px; }
        .page-info strong { color: #93c5fd !important; }
        
        /* ============================================================
           PER PAGE SELECTOR - DARK
           ============================================================ */
        .per-page-selector select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 12px;
        }
        .per-page-selector select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .per-page-selector label { color: #808090 !important; font-size: 12px; margin: 0; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        
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
            .stat-card { padding: 12px; }
            .stat-number { font-size: 18px; }
            .stat-icon { width: 36px; height: 36px; font-size: 14px; }
            .pagination-container .row {
                flex-direction: column;
                gap: 8px;
            }
            .pagination-container .col-md-6 {
                width: 100%;
                text-align: center !important;
            }
            .pagination {
                justify-content: center !important;
            }
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
            .footer { display: none !important; }
            .navbar { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .stat-card { background: #f8f9fa !important; border: 1px solid #ddd !important; }
            .card-item { background: #f8f9fa !important; border: 1px solid #ddd !important; }
            body { background: #fff !important; color: #000 !important; }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="content-wrapper">
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- MAIN CONTENT -->
            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
                    <h1><i class="fas fa-id-card me-2"></i>Register RFID Card</h1>
                    <a href="cards.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Card List
                    </a>
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
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($residentsWithCard); ?></div>
                                <div class="stat-label">Cards Issued</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-user-plus"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalWithoutCard; ?></div>
                                <div class="stat-label">No Card</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-box"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($availableCards); ?></div>
                                <div class="stat-label">Available Cards</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-ban"></i></div>
                            <div>
                                <div class="stat-number"><?php 
                                    $result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status != 'active'");
                                    $row = $result->fetch_assoc();
                                    echo $row['count'] ?? 0;
                                ?></div>
                                <div class="stat-label">Inactive</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                REGISTER RFID FORM
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-plus-circle me-2"></i>Assign RFID Card</h5>
                    
                    <?php if (!empty($availableCards)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong><?php echo count($availableCards); ?> available cards</strong> in inventory.
                            Enter a card UID below or click an available card to auto-fill.
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label">Available Cards (Click to auto-fill)</label>
                            <div class="available-card-list" id="availableCardList">
                                <?php foreach ($availableCards as $card): ?>
                                    <span class="card-item-mini" data-uid="<?php echo $card['card_uid']; ?>" onclick="selectCard('<?php echo $card['card_uid']; ?>')">
                                        <?php echo $card['card_uid']; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Click any card above to auto-fill the UID below</small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No available cards in inventory. Please add cards to the <strong>available_rfid_cards</strong> table.
                        </div>
                    <?php endif; ?>
                    
                    <!-- ============================================================
                    AUTO-FILL INFO DISPLAY
                    ============================================================ -->
                    <div class="auto-fill-info" id="autoFillInfo">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle text-success fa-lg"></i>
                            <div>
                                <strong>Card Available!</strong>
                                <span class="text-muted" id="autoFillCardInfo">Card is ready to assign</span>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="" id="rfidForm">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label">Select Resident <span class="required">*</span></label>
                                <select class="form-select" name="user_id" required>
                                    <option value="">-- Select Resident --</option>
                                    <?php foreach ($residentsWithoutCard as $resident): ?>
                                        <option value="<?php echo $resident['user_id']; ?>">
                                            <?php echo htmlspecialchars($resident['full_name']); ?> 
                                            (<?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>)
                                            <?php if (!empty($resident['room_number'])): ?>
                                                - Room <?php echo htmlspecialchars($resident['room_number']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($residentsWithoutCard)): ?>
                                    <small class="text-success"><i class="fas fa-check-circle me-1"></i> All residents have RFID cards!</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Card UID <span class="required">*</span></label>
                                <input type="text" class="form-control" name="card_uid" id="cardUidInput" placeholder="e.g., A1B2C3D4" required>
                                <small class="text-muted" id="uidStatus">Enter UID or click from available cards above</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Card Type</label>
                                <select class="form-select" name="card_type" id="cardTypeSelect">
                                    <option value="resident">Resident</option>
                                    <option value="staff">Staff</option>
                                    <option value="visitor">Visitor</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" name="expiry_date" id="expiryDateInput" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                            </div>
                            <div class="col-md-12">
                                <button type="submit" name="register_rfid" class="btn btn-submit" id="registerBtn" <?php echo empty($residentsWithoutCard) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-save me-1"></i> Register RFID Card
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ============================================================
                RESIDENTS WITH CARDS
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-list me-2"></i>Residents with RFID Cards</h5>
                    
                    <?php if (empty($residentsWithCard)): ?>
                        <p class="text-muted text-center py-2">No RFID cards registered yet</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($residentsWithCard as $resident): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card-item <?php echo $resident['card_status'] != 'active' ? 'deactivated' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="name">
                                                    <?php echo htmlspecialchars($resident['full_name']); ?>
                                                    <span class="status-badge <?php echo $resident['card_status'] == 'active' ? 'status-active' : 'status-deactivated'; ?> ms-1">
                                                        <?php echo ucfirst($resident['card_status']); ?>
                                                    </span>
                                                </div>
                                                <div class="detail">
                                                    <i class="fas fa-door-open me-1"></i>
                                                    Room <?php echo htmlspecialchars($resident['room_number'] ?? 'N/A'); ?>
                                                    <span class="mx-1">•</span>
                                                    <i class="fas fa-graduation-cap me-1"></i>
                                                    <?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="detail">
                                                    <i class="fas fa-id-card me-1"></i>
                                                    <span class="uid"><?php echo htmlspecialchars($resident['card_uid']); ?></span>
                                                </div>
                                                <div class="detail">
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    Issued: <?php echo date('M d, Y', strtotime($resident['issued_date'])); ?>
                                                    <?php if ($resident['expiry_date']): ?>
                                                        <span class="mx-1">|</span>
                                                        Expires: <?php echo date('M d, Y', strtotime($resident['expiry_date'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($resident['card_status'] == 'active'): ?>
                                                    <a href="?deactivate=<?php echo $resident['card_uid']; ?>" 
                                                       class="btn btn-warning btn-sm-custom"
                                                       onclick="return confirm('Deactivate this card?')">
                                                        <i class="fas fa-pause me-1"></i> Deactivate
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?activate=<?php echo $resident['card_uid']; ?>" 
                                                       class="btn btn-success btn-sm-custom"
                                                       onclick="return confirm('Activate this card?')">
                                                        <i class="fas fa-play me-1"></i> Activate
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                RESIDENTS WITHOUT CARDS WITH PAGINATION
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-user-plus me-2"></i>Residents Without RFID Cards</h5>
                    
                    <?php if (empty($residentsWithoutCard)): ?>
                        <p class="text-muted text-center py-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            All residents have RFID cards!
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Student ID</th>
                                        <th>Room</th>
                                        <th>Course</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = $offset + 1; foreach ($residentsWithoutCard as $resident): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($resident['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($resident['room_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="?select=<?php echo $resident['user_id']; ?>" class="btn btn-primary-sm">
                                                    <i class="fas fa-plus me-1"></i> Assign Card
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalWithoutCard); ?> of <?php echo $totalWithoutCard; ?> residents
                                        <span class="mx-1 text-muted">|</span>
                                        <span class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                        <!-- Per Page Selector -->
                                        <div class="per-page-selector d-flex align-items-center gap-1">
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

            </main>
        </div>

    <?php include '../includes/footer.php'; ?>
    
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
        // SELECT CARD FROM AVAILABLE LIST - AUTO-FILL
        // ============================================================
        function selectCard(uid) {
            document.getElementById('cardUidInput').value = uid;
            
            document.querySelectorAll('.card-item-mini').forEach(el => {
                el.classList.remove('selected');
                if (el.dataset.uid === uid) {
                    el.classList.add('selected');
                }
            });
            
            const infoDiv = document.getElementById('autoFillInfo');
            infoDiv.classList.add('show');
            document.getElementById('autoFillCardInfo').textContent = 'Card UID: ' + uid + ' is available and ready to assign';
            
            document.getElementById('uidStatus').innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Card available! Click Register to assign';
            document.getElementById('uidStatus').style.color = '#34d399';
            
            document.getElementById('registerBtn').focus();
        }

        // ============================================================
        // CHECK MANUAL ENTRY
        // ============================================================
        document.getElementById('cardUidInput').addEventListener('input', function() {
            const uid = this.value.toUpperCase().trim();
            const infoDiv = document.getElementById('autoFillInfo');
            const statusText = document.getElementById('uidStatus');
            
            if (uid.length >= 4) {
                const availableUids = <?php echo json_encode(array_column($availableCards, 'card_uid')); ?>;
                
                if (availableUids.includes(uid)) {
                    infoDiv.classList.add('show');
                    document.getElementById('autoFillCardInfo').textContent = 'Card UID: ' + uid + ' is available and ready to assign';
                    statusText.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Card available!';
                    statusText.style.color = '#34d399';
                    
                    document.querySelectorAll('.card-item-mini').forEach(el => {
                        el.classList.remove('selected');
                        if (el.dataset.uid === uid) {
                            el.classList.add('selected');
                        }
                    });
                } else {
                    infoDiv.classList.remove('show');
                    statusText.innerHTML = '<i class="fas fa-info-circle text-warning me-1"></i> New card UID (not in available list)';
                    statusText.style.color = '#fbbf24';
                }
            } else {
                infoDiv.classList.remove('show');
                statusText.innerHTML = 'Enter UID or click from available cards above';
                statusText.style.color = '';
            }
        });

        // ============================================================
        // AUTO-SELECT RESIDENT
        // ============================================================
        <?php if (isset($_GET['select']) && is_numeric($_GET['select'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const select = document.querySelector('select[name="user_id"]');
                if (select) {
                    select.value = '<?php echo (int)$_GET['select']; ?>';
                }
            });
        <?php endif; ?>

        // ============================================================
        // AUTO-FOCUS
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('cardUidInput');
            if (input) {
                input.focus();
            }
        });
        
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
