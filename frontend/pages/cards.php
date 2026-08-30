<?php
/**
 * Tap-and-Go Doorlock - RFID Card List
 * COMPLETE WITH ALL ASSIGNED CARDS
 * PURE DARK MODE - WITH SHOW ENTRIES
 * WITH AUTO-EXPIRATION SYSTEM
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 * WITH PROFILE PHOTO SUPPORT
 * WITH PRINT ID BUTTON
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
// HANDLE CARD ACTIONS
// ============================================================

// Deactivate card
if (isset($_GET['deactivate']) && !empty($_GET['deactivate'])) {
    $card_uid = $_GET['deactivate'];
    $stmt = $conn->prepare("UPDATE rfid_cards SET status = 'deactivated' WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card deactivated successfully!";
        logAudit($_SESSION['admin_id'], 'Deactivate RFID', "Deactivated RFID card $card_uid");
    } else {
        $error = "Failed to deactivate card.";
    }
    $stmt->close();
}

// Activate card
if (isset($_GET['activate']) && !empty($_GET['activate'])) {
    $card_uid = $_GET['activate'];
    $stmt = $conn->prepare("UPDATE rfid_cards SET status = 'active' WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card activated successfully!";
        logAudit($_SESSION['admin_id'], 'Activate RFID', "Activated RFID card $card_uid");
    } else {
        $error = "Failed to activate card.";
    }
    $stmt->close();
}

// Delete card
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $card_uid = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM rfid_cards WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    if ($stmt->execute()) {
        $success = "✅ RFID card deleted successfully!";
        logAudit($_SESSION['admin_id'], 'Delete RFID', "Deleted RFID card $card_uid");
    } else {
        $error = "Failed to delete card.";
    }
    $stmt->close();
}

// ============================================================
// GET FILTERS
// ============================================================
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';

// ============================================================
// GET TOTAL CARDS FOR PAGINATION
// ============================================================
$countQuery = "
    SELECT COUNT(*) as total
    FROM rfid_cards c
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $countQuery .= " AND c.status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $countQuery .= " AND c.card_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $countQuery .= " AND (u.full_name LIKE '%$searchFilter%' OR c.card_uid LIKE '%$searchFilter%' OR u.student_id LIKE '%$searchFilter%' OR c.visitor_name LIKE '%$searchFilter%')";
}

$countResult = $conn->query($countQuery);
$totalCards = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalCards = (int)$row['total'];
}

$totalPages = ceil($totalCards / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// ============================================================
// GET CARDS WITH USER INFO
// ============================================================
$cards = [];
$query = "
    SELECT 
        c.*,
        u.full_name as user_name,
        u.student_id,
        u.room_number,
        u.profile_photo,
        rp.course,
        rp.year_level,
        rp.gender
    FROM rfid_cards c
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $query .= " AND c.status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $query .= " AND c.card_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $query .= " AND (u.full_name LIKE '%$searchFilter%' OR c.card_uid LIKE '%$searchFilter%' OR u.student_id LIKE '%$searchFilter%' OR c.visitor_name LIKE '%$searchFilter%')";
}

$query .= " ORDER BY c.status = 'active' DESC, c.created_at DESC LIMIT $perPage OFFSET $offset";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cards[] = $row;
    }
}

// ============================================================
// GET STATS - FIXED WITH PROPER ERROR HANDLING
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'deactivated' => 0,
    'expired' => 0,
    'lost' => 0,
    'resident' => 0,
    'staff' => 0,
    'visitor' => 0,
    'expiring_soon' => 0
];

// Helper function to safely get count
function getCount($conn, $query) {
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['count'];
    }
    return 0;
}

$stats['total'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards");
$stats['active'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
$stats['deactivated'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'deactivated'");
$stats['expired'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'expired'");
$stats['lost'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'lost'");
$stats['resident'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards WHERE card_type = 'resident'");
$stats['staff'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards WHERE card_type = 'staff'");
$stats['visitor'] = getCount($conn, "SELECT COUNT(*) as count FROM rfid_cards WHERE card_type = 'visitor'");
$stats['expiring_soon'] = getCount($conn, "
    SELECT COUNT(*) as count 
    FROM rfid_cards 
    WHERE status = 'active' 
    AND expiry_date IS NOT NULL
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
");

// Get dark mode
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
    <title>RFID Cards - Tap-and-Go Doorlock</title>
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
           FOOTER - STICKY BOTTOM
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
        .stat-card .text-danger { color: #f87171 !important; }
        .stat-card .text-success { color: #34d399 !important; }
        .stat-card .text-warning { color: #fbbf24 !important; }
        
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
            font-size: 14px;
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
        .card-item .status-expired {
            background: #7a2a2a !important;
            color: #f87171 !important;
        }
        .card-item .status-lost {
            background: #7a2a2a !important;
            color: #f87171 !important;
        }
        .card-item.deactivated {
            border-left-color: #6b7280 !important;
            opacity: 0.7;
        }
        .card-item.expired {
            border-left-color: #ef4444 !important;
            background: #1a0a0a !important;
        }
        .card-item.lost {
            border-left-color: #ef4444 !important;
        }
        
        /* ============================================================
           PROFILE PHOTO STYLES
           ============================================================ */
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #2a2a4a;
            flex-shrink: 0;
            background: #1a1a2e;
        }
        .profile-img-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            border: 2px solid #2a2a4a;
        }
        
        /* ============================================================
           DARK FILTERS
           ============================================================ */
        .filter-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .filter-section .form-control,
        .filter-section .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            color: #e0e0e0 !important;
            height: 34px;
        }
        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .filter-section .form-control::placeholder { color: #606070 !important; }
        .filter-section .form-label { color: #b0b0c0 !important; font-size: 12px; }
        .filter-section .btn-filter {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px;
            padding: 6px 16px;
            font-weight: 500;
            font-size: 12px;
            transition: all 0.3s ease;
            height: 34px;
        }
        .filter-section .btn-filter:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        
        /* ============================================================
           DARK BUTTONS
           ============================================================ */
        .btn-primary {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            color: white !important;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
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
        .btn-danger {
            background: #7a2a2a !important;
            color: #f87171 !important;
        }
        .btn-danger:hover { background: #8a3a3a !important; color: #fca5a5 !important; }
        .btn-info {
            background: #1a3a6a !important;
            color: #93c5fd !important;
        }
        .btn-info:hover { background: #2a5a8a !important; color: #93c5fd !important; }
        .btn-outline-danger {
            color: #f87171 !important;
            border-color: #7a2a2a !important;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 8px;
        }
        .btn-outline-danger:hover {
            background: #7a2a2a !important;
            color: white !important;
        }
        
        /* ============================================================
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-header {
            background: #111827 !important;
            border-bottom: 1px solid #1a2a4a !important;
            padding: 10px 18px;
        }
        .card-header h5 { color: #e0e0e0 !important; font-size: 15px; }
        .card-body { background: #111827 !important; padding: 15px 18px; }
        .card .text-muted { color: #808090 !important; }
        
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
           EXPIRATION BADGES
           ============================================================ */
        .badge-expired {
            background: #7a2a2a !important;
            color: #f87171 !important;
            font-size: 9px;
        }
        .badge-expiring-soon {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            font-size: 9px;
        }
        .badge-secondary {
            background: #2a2a3a !important;
            color: #808090 !important;
            font-size: 9px;
        }
        
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
            .profile-img, .profile-img-placeholder {
                width: 32px;
                height: 32px;
                font-size: 11px;
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
                    <h1><i class="fas fa-id-card me-2"></i>RFID Cards</h1>
                    <div class="btn-toolbar">
                        <a href="register-rfid.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Register New Card
                        </a>
                        <a href="cards.php?status=expired" class="btn btn-sm btn-outline-danger ms-1">
                            <i class="fas fa-hourglass-end me-1"></i> Expired
                            <?php if ($stats['expired'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $stats['expired']; ?></span>
                            <?php endif; ?>
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

                <!-- ============================================================
                EXPIRING SOON NOTIFICATION
                ============================================================ -->
                <?php if ($stats['expiring_soon'] > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-clock me-2"></i>
                        <strong><?php echo $stats['expiring_soon']; ?> card(s)</strong> will expire within 3 days. 
                        Please remind residents to renew their cards.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ============================================================
                STATS CARDS
                ============================================================ -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Cards</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['active']; ?></div>
                                <div class="stat-label">Active</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-pause-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['deactivated']; ?></div>
                                <div class="stat-label">Deactivated</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $stats['expired'] > 0 ? '#ef4444' : '#f59e0b'; ?>;">
                                <i class="fas fa-hourglass-end"></i>
                            </div>
                            <div>
                                <div class="stat-number <?php echo $stats['expired'] > 0 ? 'text-danger' : 'text-warning'; ?>">
                                    <?php echo $stats['expired']; ?>
                                </div>
                                <div class="stat-label">Expired</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['lost']; ?></div>
                                <div class="stat-label">Lost</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $stats['expiring_soon'] > 0 ? '#f59e0b' : '#8b5cf6'; ?>;">
                                <i class="fas <?php echo $stats['expiring_soon'] > 0 ? 'fa-clock' : 'fa-users'; ?>"></i>
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

                <!-- ============================================================
                FILTERS
                ============================================================ -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="deactivated" <?php echo $statusFilter == 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                <option value="expired" <?php echo $statusFilter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="lost" <?php echo $statusFilter == 'lost' ? 'selected' : ''; ?>>Lost</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Card Type</label>
                            <select class="form-select" name="type">
                                <option value="">All Types</option>
                                <option value="resident" <?php echo $typeFilter == 'resident' ? 'selected' : ''; ?>>Resident</option>
                                <option value="staff" <?php echo $typeFilter == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="visitor" <?php echo $typeFilter == 'visitor' ? 'selected' : ''; ?>>Visitor</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name, UID, or Student ID" value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-filter w-100">
                                <i class="fas fa-filter me-1"></i> Apply
                            </button>
                        </div>
                        <!-- Hidden fields to preserve pagination -->
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- ============================================================
                CARDS LIST
                ============================================================ -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list me-2"></i>Card List</h5>
                        <span class="text-muted small">
                            <?php if ($totalCards > 0): ?>
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalCards); ?> of <?php echo $totalCards; ?> cards
                            <?php else: ?>
                                0 cards
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cards)): ?>
                            <p class="text-muted text-center py-3">
                                <i class="fas fa-id-card fa-2x d-block mb-2"></i>
                                No RFID cards found
                            </p>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($cards as $card): 
                                    $days_left = 0;
                                    $expiring_soon = false;
                                    if ($card['expiry_date']) {
                                        $days_left = ceil((strtotime($card['expiry_date']) - time()) / 86400);
                                        $expiring_soon = $days_left >= 0 && $days_left <= 3 && $card['status'] == 'active';
                                    }
                                    
                                    // Determine display name
                                    if ($card['card_type'] == 'visitor' && !empty($card['visitor_name'])) {
                                        $display_name = $card['visitor_name'];
                                    } else {
                                        $display_name = $card['user_name'] ?? 'Unassigned';
                                    }
                                    
                                    // For visitors: tenant is the user they are visiting
                                    if ($card['card_type'] == 'visitor') {
                                        $tenant_name = $card['user_name'] ?? 'Unknown Tenant';
                                    } else {
                                        $tenant_name = null;
                                    }
                                    
                                    // Get profile photo
                                    $profile_photo = $card['profile_photo'] ?? null;
                                    $has_profile_photo = false;
                                    $profile_photo_path = null;
                                    
                                    if (!empty($profile_photo)) {
                                        if (strpos($profile_photo, 'uploads/') === 0) {
                                            $full_path = '../../' . $profile_photo;
                                        } else {
                                            $full_path = '../../uploads/resident_photos/' . $profile_photo;
                                        }
                                        
                                        if (file_exists($full_path)) {
                                            $has_profile_photo = true;
                                            $profile_photo_path = $full_path;
                                        }
                                    }
                                    
                                    // Get initials
                                    $parts = explode(' ', $display_name);
                                    $initials = '';
                                    foreach ($parts as $p) {
                                        if (!empty($p)) $initials .= strtoupper($p[0]);
                                    }
                                    $initials = substr($initials, 0, 2) ?: '?';
                                ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card-item <?php echo $card['status']; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="d-flex align-items-center gap-2">
                                                    <!-- Profile Photo / Avatar -->
                                                    <?php if ($has_profile_photo && !empty($card['user_name']) && $card['card_type'] != 'visitor'): ?>
                                                        <img src="<?php echo $profile_photo_path; ?>" 
                                                             alt="<?php echo htmlspecialchars($display_name); ?>" 
                                                             class="profile-img"
                                                             onerror="this.onerror=null; this.parentNode.innerHTML='<div class=\'profile-img-placeholder\'>'+'<?php echo $initials; ?>'+'</div>'">
                                                    <?php else: ?>
                                                        <div class="profile-img-placeholder"><?php echo $initials; ?></div>
                                                    <?php endif; ?>
                                                    
                                                    <div>
                                                        <div class="name">
                                                            <?php echo htmlspecialchars($display_name); ?>
                                                            <span class="status-badge status-<?php echo $card['status']; ?> ms-1">
                                                                <?php echo ucfirst($card['status']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="detail">
                                                            <i class="fas fa-tag me-1"></i>
                                                            <?php echo ucfirst($card['card_type']); ?>
                                                            <?php if (!empty($card['room_number'])): ?>
                                                                <span class="mx-1">•</span>
                                                                <i class="fas fa-door-open me-1"></i>
                                                                Room <?php echo htmlspecialchars($card['room_number']); ?>
                                                            <?php endif; ?>
                                                            <?php if ($card['card_type'] == 'visitor' && $tenant_name): ?>
                                                                <span class="mx-1">•</span>
                                                                <i class="fas fa-user me-1"></i>
                                                                <strong>Tenant:</strong> <?php echo htmlspecialchars($tenant_name); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="detail">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php 
                                                                if ($card['card_type'] == 'visitor') {
                                                                    echo htmlspecialchars($card['visitor_phone'] ?? 'N/A');
                                                                } else {
                                                                    echo htmlspecialchars($card['student_id'] ?? 'N/A');
                                                                }
                                                            ?>
                                                            <?php if (!empty($card['course']) && $card['card_type'] != 'visitor'): ?>
                                                                <span class="mx-1">•</span>
                                                                <?php echo htmlspecialchars($card['course']); ?>
                                                            <?php endif; ?>
                                                            <?php if (!empty($card['year_level']) && $card['card_type'] != 'visitor'): ?>
                                                                <span class="mx-1">•</span>
                                                                Year <?php echo htmlspecialchars($card['year_level']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="detail">
                                                            <i class="far fa-calendar-alt me-1"></i>
                                                            Issued: <?php echo date('M d, Y', strtotime($card['issued_date'])); ?>
                                                            <?php if ($card['expiry_date']): ?>
                                                                <span class="mx-1">|</span>
                                                                <i class="fas fa-hourglass-end me-1"></i>
                                                                Expires: <?php echo date('M d, Y', strtotime($card['expiry_date'])); ?>
                                                                <?php 
                                                                    if ($days_left < 0 && $card['status'] != 'expired'): 
                                                                ?>
                                                                    <span class="badge badge-expired ms-1">EXPIRED</span>
                                                                <?php elseif ($expiring_soon): ?>
                                                                    <span class="badge badge-expiring-soon ms-1">
                                                                        <?php echo $days_left; ?> day<?php echo $days_left > 1 ? 's' : ''; ?>
                                                                    </span>
                                                                <?php elseif ($card['status'] == 'expired'): ?>
                                                                    <span class="badge badge-expired ms-1">EXPIRED</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary ms-1">No expiry</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column gap-1">
                                                    <?php if ($card['status'] == 'active'): ?>
                                                        <a href="?deactivate=<?php echo $card['card_uid']; ?>&<?php echo http_build_query($_GET); ?>" 
                                                           class="btn btn-warning btn-sm-custom"
                                                           onclick="return confirm('Deactivate this card?')">
                                                            <i class="fas fa-pause me-1"></i> Deactivate
                                                        </a>
                                                    <?php elseif ($card['status'] == 'deactivated' || $card['status'] == 'expired'): ?>
                                                        <a href="?activate=<?php echo $card['card_uid']; ?>&<?php echo http_build_query($_GET); ?>" 
                                                           class="btn btn-success btn-sm-custom"
                                                           onclick="return confirm('Activate this card?')">
                                                            <i class="fas fa-play me-1"></i> Activate
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- PRINT ID BUTTON -->
                                                    <a href="print-card.php?uid=<?php echo $card['card_uid']; ?>" 
                                                       target="_blank" 
                                                       class="btn btn-info btn-sm-custom">
                                                        <i class="fas fa-print me-1"></i> Print ID
                                                    </a>
                                                    
                                                    <a href="?delete=<?php echo $card['card_uid']; ?>&<?php echo http_build_query($_GET); ?>" 
                                                       class="btn btn-danger btn-sm-custom"
                                                       onclick="return confirm('Delete this card permanently?')">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
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
                                            <a class="page-link" href="?page=1<?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <li class="page-item"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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

                <!-- ============================================================
                CARD SUMMARY
                ============================================================ -->
                <div class="text-center text-muted small mt-2">
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo $stats['total']; ?> RFID cards registered
                    <span class="mx-1">|</span>
                    <i class="fas fa-check-circle me-1 text-success"></i>
                    <?php echo $stats['active']; ?> active
                    <?php if ($stats['expired'] > 0): ?>
                        <span class="mx-1">|</span>
                        <i class="fas fa-hourglass-end me-1 text-danger"></i>
                        <span class="text-danger"><?php echo $stats['expired']; ?> expired</span>
                    <?php endif; ?>
                    <?php if ($stats['expiring_soon'] > 0): ?>
                        <span class="mx-1">|</span>
                        <i class="fas fa-clock me-1 text-warning"></i>
                        <span class="text-warning"><?php echo $stats['expiring_soon']; ?> expiring soon</span>
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
        // AUTO-SUBMIT FILTER ON CHANGE
        // ============================================================
        document.querySelectorAll('.filter-section select').forEach(el => {
            el.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
        
        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
        
        // ============================================================
        // TOAST NOTIFICATION FOR EXPIRING CARDS
        // ============================================================
        <?php if ($stats['expiring_soon'] > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.style.cssText = `
                    position: fixed;
                    top: 70px;
                    right: 20px;
                    z-index: 9999;
                    background: #111827 !important;
                    color: #e0e0e0 !important;
                    padding: 12px 16px;
                    border-radius: 10px;
                    margin-bottom: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                    animation: slideInRight 0.5s ease;
                    max-width: 300px;
                    border-left: 4px solid #fbbf24;
                    border: 1px solid #1a2a4a;
                    font-size: 13px;
                `;
                toast.innerHTML = `
                    <div style="font-weight:600;color:#e0e0e0;">
                        ⏰ ${<?php echo $stats['expiring_soon']; ?>} cards expiring soon
                    </div>
                    <div style="font-size:11px;color:#b0b0c0;margin-top:2px;">
                        Please remind residents to renew their cards.
                    </div>
                `;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => toast.remove(), 500);
                }, 5000);
            }, 2000);
        });
        <?php endif; ?>
    </script>
</body>
</html>
