<?php
/**
 * Tap-and-Go Doorlock - Access Logs / Attendance
 * COMPLETE - With same layout as dashboard.php
 * PURE DARK MODE - Fixed navbar, sidebar, footer
 * FIXED: Complete dark table
 * ADDED: Print buttons and show entries dropdown
 * UPDATED: Print layout - Entry/Exit with Signature columns
 * ADDED: Picture display in Residents table (with fallback)
 */

session_start();

// ============================================================
// FIXED: CORRECT FILE PATHS FOR ADMIN
// ============================================================
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
        header('Location: login.php');
        exit();
    }
}
// Include header
include '../includes/header.php'; 
$conn = getDBConnection();

// ============================================================
// CHECK IF PROFILE_PICTURE COLUMN EXISTS
// ============================================================
$hasProfilePicture = false;
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $hasProfilePicture = true;
    }
} catch (Exception $e) {
    $hasProfilePicture = false;
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

// Get filters
$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET ALERT LOGS (Like Dashboard)
// ============================================================
$alertLogs = [];
$alertQuery = "
    SELECT 
        alog.*,
        c.card_type as rfid_card_type,
        c.visitor_name,
        c.resident_visited,
        u.full_name as user_name,
        u.room_number,
        ru.full_name as resident_visited_name
    FROM alert_logs alog
    LEFT JOIN rfid_cards c ON alog.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    WHERE 1=1
";

if (!empty($dateFilter)) {
    $alertQuery .= " AND DATE(alog.timestamp) = '$dateFilter'";
}
if (!empty($statusFilter)) {
    $alertQuery .= " AND alog.delivery_status = '$statusFilter'";
}
if (!empty($searchFilter)) {
    $alertQuery .= " AND (
        alog.card_uid LIKE '%$searchFilter%' 
        OR alog.user_name LIKE '%$searchFilter%'
        OR alog.reason LIKE '%$searchFilter%'
    )";
}

$alertQuery .= " ORDER BY 
    CASE WHEN alog.delivery_status = 'pending' THEN 0 ELSE 1 END,
    alog.timestamp DESC 
    LIMIT 100";

$result = $conn->query($alertQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $displayName = !empty($row['user_name']) ? $row['user_name'] : 'Unknown';
        if ($row['rfid_card_type'] == 'visitor' && !empty($row['visitor_name'])) {
            $displayName = $row['visitor_name'] . ' (Visitor)';
        }
        $row['display_name'] = $displayName;
        $alertLogs[] = $row;
    }
}

// ============================================================
// GET RESIDENT ACCESS LOGS WITH COUNT
// ============================================================
$residentLogs = [];
$residentCountQuery = "
    SELECT COUNT(*) as total
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE (c.card_type = 'resident' OR c.card_type = 'staff' OR c.card_type IS NULL)
";

if (!empty($dateFilter)) {
    $residentCountQuery .= " AND DATE(al.timestamp) = '$dateFilter'";
}
if (!empty($statusFilter)) {
    $residentCountQuery .= " AND al.access_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $residentCountQuery .= " AND al.access_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $residentCountQuery .= " AND (u.full_name LIKE '%$searchFilter%' OR al.card_uid LIKE '%$searchFilter%')";
}

$countResult = $conn->query($residentCountQuery);
$residentTotal = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $residentTotal = (int)$row['total'];
}

$residentPages = ceil($residentTotal / $perPage);
if ($residentPages < 1) $residentPages = 1;
if ($page > $residentPages) $page = $residentPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// Build resident query with or without profile_picture
$residentQuery = "
    SELECT 
        al.*,
        c.card_uid,
        c.card_type,
        c.visitor_name,
        c.purpose_of_visit,
        c.resident_visited,
        u.full_name as user_name,
        u.room_number,
        u.student_id,
        rp.course,
        rp.year_level,
        ru.full_name as resident_visited_name
";

// Only add profile_picture if column exists
if ($hasProfilePicture) {
    $residentQuery .= ", u.profile_picture";
}

$residentQuery .= "
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE (c.card_type = 'resident' OR c.card_type = 'staff' OR c.card_type IS NULL)
";

if (!empty($dateFilter)) {
    $residentQuery .= " AND DATE(al.timestamp) = '$dateFilter'";
}
if (!empty($statusFilter)) {
    $residentQuery .= " AND al.access_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $residentQuery .= " AND al.access_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $residentQuery .= " AND (u.full_name LIKE '%$searchFilter%' OR al.card_uid LIKE '%$searchFilter%')";
}

$residentQuery .= " ORDER BY al.timestamp DESC LIMIT $perPage OFFSET $offset";

$result = $conn->query($residentQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentLogs[] = $row;
    }
}

// ============================================================
// GET VISITOR ACCESS LOGS
// ============================================================
$visitorLogs = [];
$visitorQuery = "
    SELECT 
        al.*,
        c.card_uid,
        c.card_type,
        c.visitor_name,
        c.purpose_of_visit,
        c.resident_visited,
        ru.full_name as resident_visited_name,
        ru.room_number as resident_room,
        vl.validity_end
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    LEFT JOIN visitor_logs vl ON c.card_uid = vl.temporary_card_uid
    WHERE c.card_type = 'visitor'
";

if (!empty($dateFilter)) {
    $visitorQuery .= " AND DATE(al.timestamp) = '$dateFilter'";
}
if (!empty($statusFilter)) {
    $visitorQuery .= " AND al.access_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $visitorQuery .= " AND al.access_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $visitorQuery .= " AND (c.visitor_name LIKE '%$searchFilter%' OR al.card_uid LIKE '%$searchFilter%')";
}

$visitorQuery .= " ORDER BY al.timestamp DESC LIMIT 500";

$result = $conn->query($visitorQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $visitorLogs[] = $row;
    }
}

// ============================================================
// GET UNAUTHORIZED ACCESS LOGS (DENIED)
// ============================================================
$unauthorizedLogs = [];
$unauthorizedQuery = "
    SELECT 
        al.*,
        c.card_uid,
        c.card_type,
        c.visitor_name,
        u.full_name as user_name,
        u.room_number
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE al.access_status = 'denied'
";

if (!empty($dateFilter)) {
    $unauthorizedQuery .= " AND DATE(al.timestamp) = '$dateFilter'";
}
if (!empty($searchFilter)) {
    $unauthorizedQuery .= " AND (al.card_uid LIKE '%$searchFilter%' OR u.full_name LIKE '%$searchFilter%')";
}

$unauthorizedQuery .= " ORDER BY al.timestamp DESC LIMIT 100";

$result = $conn->query($unauthorizedQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $unauthorizedLogs[] = $row;
    }
}

// ============================================================
// GET STATS - WITH UNAUTHORIZED TOTAL COUNT
// ============================================================
$stats = [
    'total' => 0,
    'granted' => 0,
    'denied' => 0,
    'entry' => 0,
    'exit' => 0,
    'today' => 0,
    'residents' => 0,
    'visitors' => 0,
    'unauthorized_today' => 0,
    'unauthorized_total' => 0,
    'pending_alerts' => 0,
    'critical_alerts' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_status = 'granted'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['granted'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_status = 'denied'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['denied'] = (int)$row['count'];
    $stats['unauthorized_total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_type = 'entry'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['entry'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_type = 'exit'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['exit'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['today'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE() AND access_status = 'denied'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unauthorized_today'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    WHERE c.card_type != 'visitor' OR c.card_type IS NULL
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['residents'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    WHERE c.card_type = 'visitor'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['visitors'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_alerts'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM alert_logs 
    WHERE delivery_status = 'pending' 
    AND alert_type = 'unauthorized'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['critical_alerts'] = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Logs - Tap-and-Go Doorlock</title>
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
        .stat-card .text-danger { color: #f87171 !important; }
        .stat-card .text-muted { color: #606070 !important; }
        .stat-card .pulse-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            animation: pulseBadge 1s infinite;
        }
        @keyframes pulseBadge {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        /* ============================================================
           DARK CARDS - SAME AS DASHBOARD
           ============================================================ */
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
        
        /* ============================================================
           DARK FILTERS
           ============================================================ */
        .filter-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .filter-section .form-control, .filter-section .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #e0e0e0 !important;
        }
        .filter-section .form-control:focus, .filter-section .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .filter-section .form-control::placeholder { color: #606070 !important; }
        .filter-section .form-label { color: #b0b0c0 !important; font-size: 13px; }
        .filter-section .btn-filter {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .filter-section .btn-filter:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        
        /* ============================================================
           DARK TABLE - COMPLETE FIX
           ============================================================ */
        .log-table {
            font-size: 13px;
            background: #111827 !important;
            border-collapse: collapse !important;
            width: 100%;
        }
        .log-table thead th {
            font-weight: 600;
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            background: #0d1528 !important;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .log-table tbody td {
            vertical-align: middle;
            padding: 8px 12px;
            color: #e0e0e0 !important;
            border-bottom: 1px solid #1a2a4a !important;
            background: #111827 !important;
        }
        .log-table tbody tr {
            background: #111827 !important;
        }
        .log-table tbody tr:hover td {
            background: rgba(255,255,255,0.03) !important;
        }
        .log-table tbody tr:nth-child(even) td {
            background: #0f172a !important;
        }
        .log-table tbody tr:nth-child(even):hover td {
            background: rgba(255,255,255,0.04) !important;
        }
        .table-responsive {
            background: #111827 !important;
            border-radius: 12px;
            border: 1px solid #1a2a4a !important;
            overflow: hidden;
        }
        
        .log-table .user-cell { display: flex; align-items: center; gap: 10px; }
        .log-table .user-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #4a5a8a, #5a3a7a);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
            overflow: hidden;
        }
        .log-table .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .log-table .user-avatar.visitor {
            background: linear-gradient(135deg, #2a3a6a, #3a2a7a);
        }
        .log-table .user-avatar.staff {
            background: linear-gradient(135deg, #4a3a1a, #6a4a2a);
        }
        .log-table .user-avatar.denied {
            background: linear-gradient(135deg, #7a2a2a, #5a1a1a);
        }
        .log-table .uid-cell {
            font-family: monospace;
            font-weight: 600;
            color: #93c5fd !important;
            background: #1a2a4a !important;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .log-table .uid-cell.denied-uid {
            color: #f87171 !important;
            background: #3a1a1a !important;
        }
        .log-table .text-muted { color: #808090 !important; }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge-granted { background: #065f46 !important; color: #34d399 !important; }
        .badge-denied { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-entry { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-exit { background: #2a2a4a !important; color: #808090 !important; }
        .badge-main { background: #065f46 !important; color: #34d399 !important; }
        .badge-battery { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-visitor { background: #1a2a5a !important; color: #93c5fd !important; }
        .badge-resident { background: #065f46 !important; color: #34d399 !important; }
        .badge-staff { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-resident-header { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-visitor-header { background: #2a2a6a !important; color: #93c5fd !important; }
        .badge-pending { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-resolved { background: #065f46 !important; color: #34d399 !important; }
        .badge-unauthorized { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-buzzer { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-unauthorized-header { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-info { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-light { background: #2a2a4a !important; color: #b0b0c0 !important; }
        
        /* ============================================================
           DARK ALERT ITEMS
           ============================================================ */
        .alert-item {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-left: 4px solid #f59e0b;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
        }
        .alert-item:hover { transform: translateX(4px); box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important; }
        .alert-item.resolved { border-left-color: #10b981; opacity: 0.7; }
        .alert-item.critical {
            border-left-color: #ef4444;
            background: #1a0a0a !important;
        }
        .alert-item .alert-uid {
            font-family: monospace;
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 14px;
        }
        .alert-item .alert-reason { font-size: 13px; color: #b0b0c0; }
        .alert-item .alert-meta { font-size: 12px; color: #606070; }
        .alert-item .alert-user { font-weight: 600; color: #93c5fd; }
        .alert-item .pulse-red { animation: pulseRed 1.5s infinite; }
        @keyframes pulseRed {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .btn-resolve {
            background: #10b981 !important;
            color: white !important;
            border: none !important;
            border-radius: 8px;
            padding: 5px 15px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-resolve:hover { background: #059669 !important; color: white !important; }
        
        /* ============================================================
           DARK BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .badge-denied-alert {
            background: #7a2a2a !important;
            color: #f87171 !important;
            animation: pulseAlert 1.5s infinite;
        }
        @keyframes pulseAlert {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .visitor-tag {
            font-size: 9px;
            background: #1a2a5a !important;
            color: #93c5fd !important;
            padding: 1px 6px;
            border-radius: 10px;
            margin-left: 4px;
        }
        .resident-tag {
            font-size: 9px;
            background: #065f46 !important;
            color: #34d399 !important;
            padding: 1px 6px;
            border-radius: 10px;
            margin-left: 4px;
        }
        .staff-tag {
            font-size: 9px;
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            padding: 1px 6px;
            border-radius: 10px;
            margin-left: 4px;
        }
        
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
           SECTION HEADER
           ============================================================ */
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0;
        }
        .section-header .icon { font-size: 24px; }
        .section-header .title { font-size: 18px; font-weight: 700; color: #e0e0e0; }
        .section-header .count { font-size: 14px; color: #808090; }
        
        .log-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .log-header-actions .left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* ============================================================
           PRINT BUTTONS & SHOW ENTRIES
           ============================================================ */
        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #0d1528 !important;
            border-radius: 10px;
            border: 1px solid #1a2a4a !important;
        }
        .table-toolbar .left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .table-toolbar .right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-print {
            background: #1a3a6a !important;
            color: white !important;
            border: none !important;
            border-radius: 8px;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-print:hover {
            background: #2a5a9a !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        .btn-print i { margin-right: 6px; }
        
        .show-entries-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
        }
        .show-entries-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .show-entries-label {
            color: #808090 !important;
            font-size: 13px;
            margin: 0;
        }
        .entry-count {
            color: #606070 !important;
            font-size: 13px;
        }
        .entry-count strong { color: #93c5fd !important; }
        
        /* ============================================================
           PRINT STYLES
           ============================================================ */
        @media print {
            body {
                background: white !important;
                color: #000 !important;
                padding-top: 0 !important;
            }
            .navbar, .sidebar, .sidebar-footer, .filter-section, 
            .stat-card, .card-header .log-header-actions .right,
            .table-toolbar .right, .table-toolbar .left .show-entries-wrapper,
            .pagination-container, footer, .btn, .badge, .no-print {
                display: none !important;
            }
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                background: white !important;
            }
            .card-header {
                background: white !important;
                border-bottom: 1px solid #ddd !important;
            }
            .card-body {
                background: white !important;
            }
            .log-table thead th {
                background: #f0f0f0 !important;
                color: #000 !important;
                border-bottom: 2px solid #ddd !important;
            }
            .log-table tbody td {
                color: #000 !important;
                background: white !important;
                border-bottom: 1px solid #eee !important;
            }
            .log-table tbody tr:nth-child(even) td {
                background: #f9f9f9 !important;
            }
            .log-table .uid-cell {
                color: #0066cc !important;
                background: #f0f0f0 !important;
            }
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
            }
            main {
                padding: 20px !important;
                margin: 0 !important;
            }
            .table-toolbar {
                background: #f0f0f0 !important;
                border: 1px solid #ddd !important;
            }
            .table-toolbar .left .entry-count {
                color: #333 !important;
            }
            .section-header .title { color: #000 !important; }
            .section-header .count { color: #666 !important; }
            .h1, .h2, h1, h2 { color: #000 !important; }
            .text-muted { color: #666 !important; }
            .badge-granted { background: #d4edda !important; color: #155724 !important; }
            .badge-denied { background: #f8d7da !important; color: #721c24 !important; }
            .badge-entry { background: #d1ecf1 !important; color: #0c5460 !important; }
            .badge-exit { background: #e2e3e5 !important; color: #383d41 !important; }
            .badge-resident-header { background: #d1ecf1 !important; color: #0c5460 !important; }
            .badge-visitor-header { background: #d1ecf1 !important; color: #0c5460 !important; }
            .staff-tag, .resident-tag, .visitor-tag { 
                background: #e9ecef !important; 
                color: #495057 !important;
                border: 1px solid #ced4da !important;
            }
            .log-table .user-avatar {
                background: #e9ecef !important;
                color: #495057 !important;
            }
            .log-table .user-avatar img {
                display: none !important;
            }
        }
        
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
            
            .table-toolbar {
                flex-direction: column;
                align-items: stretch !important;
            }
            .table-toolbar .left {
                flex-direction: column;
                align-items: stretch !important;
            }
            .table-toolbar .right {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .filter-section .row .col-md-2,
            .filter-section .row .col-md-3 {
                margin-bottom: 8px;
            }
            
            .log-table {
                font-size: 11px;
            }
            .log-table th,
            .log-table td {
                padding: 6px 8px;
            }
            .log-table .user-avatar {
                width: 24px;
                height: 24px;
                font-size: 9px;
            }
            .log-table .uid-cell {
                font-size: 10px;
                padding: 1px 4px;
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
                        <i class="fas fa-history me-2" style="color: #1a3a6a;"></i>
                        Access Logs / Attendance
                        <?php if ($stats['pending_alerts'] > 0): ?>
                            <span class="badge bg-danger ms-2 pulse-badge">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?php echo $stats['pending_alerts']; ?>
                            </span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator me-1"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <?php if ($stats['pending_alerts'] > 0): ?>
                            <a href="alerts.php" class="badge badge-denied-alert ms-2 p-2" style="text-decoration: none;">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <?php echo $stats['pending_alerts']; ?> Alert<?php echo $stats['pending_alerts'] > 1 ? 's' : ''; ?>
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- ============================================================
                STATS ROW 1 - SAME AS DASHBOARD
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-list"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Logs</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['granted']; ?></div>
                                <div class="stat-label">Granted</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <div class="stat-number text-danger">
                                    <strong><?php echo $stats['unauthorized_total']; ?></strong>
                                </div>
                                <div class="stat-label">Unauthorized <span class="text-muted">(Total)</span></div>
                            </div>
                            <?php if ($stats['unauthorized_total'] > 0): ?>
                                <span class="badge bg-danger pulse-badge"><?php echo $stats['unauthorized_total']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['entry']; ?></div>
                                <div class="stat-label">Entries</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-sign-out-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['exit']; ?></div>
                                <div class="stat-label">Exits</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-calendar-day"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['today']; ?></div>
                                <div class="stat-label">Today</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                STATS ROW 2 - SAME AS DASHBOARD
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-times-circle"></i></div>
                            <div>
                                <div class="stat-number text-danger"><?php echo $stats['unauthorized_today']; ?></div>
                                <div class="stat-label">Unauthorized Today</div>
                            </div>
                            <?php if ($stats['unauthorized_today'] > 0): ?>
                                <span class="badge bg-danger pulse-badge">🚨</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-bell"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['pending_alerts'] > 0 ? 'text-danger' : ''; ?>">
                                    <?php echo $stats['pending_alerts']; ?>
                                </div>
                                <div class="stat-label">Pending Alerts</div>
                            </div>
                            <?php if ($stats['pending_alerts'] > 0): ?>
                                <span class="badge bg-warning pulse-badge"><?php echo $stats['pending_alerts']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['residents']; ?></div>
                                <div class="stat-label">Residents Logs</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3730a3;"><i class="fas fa-user-clock"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['visitors']; ?></div>
                                <div class="stat-label">Visitors Logs</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                FILTERS
                ============================================================ -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="granted" <?php echo $statusFilter == 'granted' ? 'selected' : ''; ?>>Granted</option>
                                <option value="denied" <?php echo $statusFilter == 'denied' ? 'selected' : ''; ?>>Denied</option>
                                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="resolved" <?php echo $statusFilter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">All</option>
                                <option value="entry" <?php echo $typeFilter == 'entry' ? 'selected' : ''; ?>>Entry</option>
                                <option value="exit" <?php echo $typeFilter == 'exit' ? 'selected' : ''; ?>>Exit</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name or UID" value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-filter w-100"><i class="fas fa-filter me-1"></i> Apply</button>
                        </div>
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- ============================================================
                ALERTS TABLE
                ============================================================ -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="log-header-actions">
                            <div class="left">
                                <div class="section-header">
                                    <span class="icon">🚨</span>
                                    <span class="title" style="<?php echo $stats['critical_alerts'] > 0 ? 'color: #f87171;' : ''; ?>">Alerts</span>
                                    <span class="count badge <?php echo $stats['pending_alerts'] > 0 ? 'badge-pending' : 'badge-resolved'; ?>">
                                        <?php echo count($alertLogs); ?> logs
                                        <?php if ($stats['pending_alerts'] > 0): ?>
                                            | <?php echo $stats['pending_alerts']; ?> pending
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <a href="alerts.php" class="btn btn-<?php echo $stats['critical_alerts'] > 0 ? 'btn-danger' : 'btn-outline-secondary'; ?> btn-sm">
                                    <i class="fas fa-eye me-1"></i> View All Alerts
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($alertLogs)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                                <h5>All Clear! ✅</h5>
                                <p>No alerts found. Your system is secure.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($alertLogs as $alert): 
                                $isPending = $alert['delivery_status'] == 'pending';
                                $isResolved = $alert['delivery_status'] == 'resolved';
                                $isCritical = $isPending && $alert['alert_type'] == 'unauthorized';
                                $displayName = !empty($alert['display_name']) ? $alert['display_name'] : 'Unknown';
                                $cardUid = $alert['card_uid'] ?? 'N/A';
                                $alertType = $alert['alert_type'] ?? 'unauthorized';
                                $reason = $alert['reason'] ?? 'Unauthorized access attempt';
                                $roomDisplay = $alert['room_number'] ?? 'N/A';
                                if (!empty($alert['rfid_card_type']) && $alert['rfid_card_type'] == 'visitor' && !empty($alert['resident_visited_name'])) {
                                    $roomDisplay = 'Visit: ' . $alert['resident_visited_name'];
                                }
                            ?>
                            <div class="alert-item <?php echo $isResolved ? 'resolved' : ''; ?> <?php echo $isCritical ? 'critical' : ''; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start">
                                            <?php if ($isCritical): ?>
                                                <span class="pulse-red me-2" style="font-size: 20px;">🚨</span>
                                            <?php elseif ($isPending): ?>
                                                <span class="pulse-red me-2" style="font-size: 16px;">🔴</span>
                                            <?php else: ?>
                                                <span class="me-2" style="font-size: 16px;">✅</span>
                                            <?php endif; ?>
                                            <div>
                                                <div class="d-flex align-items-center flex-wrap gap-2">
                                                    <span class="alert-uid"><?php echo htmlspecialchars($cardUid); ?></span>
                                                    <?php if ($isCritical): ?>
                                                        <span class="badge bg-danger">CRITICAL</span>
                                                    <?php endif; ?>
                                                    <span class="badge <?php echo $isPending ? 'badge-pending' : 'badge-resolved'; ?>">
                                                        <?php echo ucfirst($alert['delivery_status']); ?>
                                                    </span>
                                                    <span class="badge <?php echo $alertType == 'unauthorized' ? 'badge-unauthorized' : 'badge-buzzer'; ?>">
                                                        <?php echo ucfirst($alertType); ?>
                                                    </span>
                                                    <?php if (!empty($alert['rfid_card_type'])): ?>
                                                        <span class="badge bg-secondary"><?php echo ucfirst($alert['rfid_card_type']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="alert-reason mt-1">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <?php echo htmlspecialchars($reason); ?>
                                                </div>
                                                <div class="alert-meta mt-1">
                                                    <i class="fas fa-user me-1"></i>
                                                    <span class="alert-user"><?php echo htmlspecialchars($displayName); ?></span>
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-door-open me-1"></i>
                                                    <?php echo htmlspecialchars($roomDisplay); ?>
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('M d, Y h:i A', strtotime($alert['timestamp'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            <?php if ($isPending): ?>
                                                <a href="alerts.php?resolve=<?php echo $alert['alert_id']; ?>" class="btn btn-resolve btn-sm">
                                                    <i class="fas fa-check me-1"></i> Resolve
                                                </a>
                                            <?php endif; ?>
                                            <a href="logs.php?search=<?php echo urlencode($cardUid); ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-history me-1"></i> View Logs
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============================================================
                RESIDENTS TABLE WITH PRINT & SHOW ENTRIES
                ============================================================ -->
                <div class="card">
                    <div class="card-header">
                        <div class="log-header-actions">
                            <div class="left">
                                <div class="section-header">
                                    <span class="icon">🏠</span>
                                    <span class="title">Residents / Staff</span>
                                    <span class="count badge badge-resident-header"><?php echo count($residentLogs); ?> logs</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-muted small">
                                    <?php if ($residentTotal > 0): ?>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $residentTotal); ?> of <?php echo $residentTotal; ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- TABLE TOOLBAR: PRINT + SHOW ENTRIES -->
                        <div class="table-toolbar">
                            <div class="left">
                                <div class="show-entries-wrapper d-flex align-items-center gap-2">
                                    <span class="show-entries-label">Show entries:</span>
                                    <select class="show-entries-select" onchange="changeResidentPerPage(this.value)">
                                        <?php foreach ([10, 25, 50, 100] as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                                                <?php echo $option; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <span class="entry-count">
                                    <i class="fas fa-file-alt me-1"></i>
                                    <strong><?php echo count($residentLogs); ?></strong> entries shown
                                    <?php if ($residentTotal > count($residentLogs)): ?>
                                        (of <strong><?php echo $residentTotal; ?></strong> total)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="right no-print">
                                <button class="btn-print" onclick="printTable('residentTable')">
                                    <i class="fas fa-print"></i> Print Residents
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive" id="residentTable">
                            <table class="table table-hover log-table">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>RFID UID</th>
                                        <th>Tenant</th>
                                        <th>Room</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Power</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($residentLogs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No resident/staff access logs found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($residentLogs as $log): 
                                            $displayName = $log['user_name'] ?? 'Unknown';
                                            $cardType = $log['card_type'] ?? 'resident';
                                            $avatarClass = '';
                                            $userTypeTag = '';
                                            $profilePic = isset($log['profile_picture']) ? $log['profile_picture'] : '';
                                            
                                            if ($cardType == 'staff') {
                                                $avatarClass = 'staff';
                                                $userTypeTag = '<span class="staff-tag">Staff</span>';
                                            } else {
                                                $userTypeTag = '<span class="resident-tag">Resident</span>';
                                            }
                                            
                                            $roomDisplay = $log['room_number'] ?? 'N/A';
                                            
                                            $initials = '';
                                            $nameParts = explode(' ', $displayName);
                                            foreach ($nameParts as $p) {
                                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                            }
                                            $initials = substr($initials, 0, 2);
                                            
                                            // Determine avatar content
                                            $avatarContent = $initials ?: '?';
                                            if ($hasProfilePicture && !empty($profilePic) && file_exists('../../uploads/profile/' . $profilePic)) {
                                                $avatarContent = '<img src="../../uploads/profile/' . htmlspecialchars($profilePic) . '" alt="Profile">';
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?></td>
                                                <td><span class="uid-cell"><?php echo htmlspecialchars($log['card_uid'] ?? 'N/A'); ?></span></td>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar <?php echo $avatarClass; ?>">
                                                            <?php echo $avatarContent; ?>
                                                        </div>
                                                        <div>
                                                            <div><?php echo htmlspecialchars($displayName); ?> <?php echo $userTypeTag; ?></div>
                                                            <?php if (!empty($log['student_id'])): ?>
                                                                <div style="font-size: 10px; color: #808090;"><?php echo htmlspecialchars($log['student_id']); ?></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($log['course'])): ?>
                                                                <div style="font-size: 10px; color: #808090;"><?php echo htmlspecialchars($log['course']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($roomDisplay); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $log['access_type'] == 'entry' ? 'badge-entry' : 'badge-exit'; ?>">
                                                        <i class="fas <?php echo $log['access_type'] == 'entry' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?> me-1"></i>
                                                        <?php echo ucfirst($log['access_type'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $log['access_status'] == 'granted' ? 'badge-granted' : 'badge-denied'; ?>">
                                                        <i class="fas <?php echo $log['access_status'] == 'granted' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                                        <?php echo ucfirst($log['access_status'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo isset($log['power_source']) && $log['power_source'] == 'main' ? 'badge-main' : 'badge-battery'; ?>">
                                                        <i class="fas <?php echo isset($log['power_source']) && $log['power_source'] == 'main' ? 'fa-bolt' : 'fa-battery-quarter'; ?> me-1"></i>
                                                        <?php echo isset($log['power_source']) ? ucfirst($log['power_source']) : 'N/A'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- PAGINATION -->
                        <?php if ($residentPages > 1): ?>
                        <div class="pagination-container">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="page-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $residentTotal); ?> of <?php echo $residentTotal; ?> entries
                                        <span class="mx-1 text-muted">|</span>
                                        <span class="text-muted">Page <?php echo $page; ?> of <?php echo $residentPages; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end mb-0">
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=1<?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-left"></i>
                                                    </a>
                                                </li>
                                                <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($residentPages, $page + 2);
                                                if ($startPage > 1) {
                                                    echo '<li class="page-item"><span class="page-link">...</span></li>';
                                                }
                                                for ($i = $startPage; $i <= $endPage; $i++):
                                                ?>
                                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                <?php if ($endPage < $residentPages): ?>
                                                    <li class="page-item"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item <?php echo ($page >= $residentPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page >= $residentPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $residentPages; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                    </div>
                </div>

                <!-- ============================================================
                VISITORS TABLE WITH PRINT & SHOW ENTRIES
                ============================================================ -->
                <div class="card">
                    <div class="card-header">
                        <div class="log-header-actions">
                            <div class="left">
                                <div class="section-header">
                                    <span class="icon">👤</span>
                                    <span class="title">Visitors</span>
                                    <span class="count badge badge-visitor-header"><?php echo count($visitorLogs); ?> logs</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?php echo count($visitorLogs); ?> visitor logs
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- TABLE TOOLBAR: PRINT + SHOW ENTRIES -->
                        <div class="table-toolbar">
                            <div class="left">
                                <div class="show-entries-wrapper d-flex align-items-center gap-2">
                                    <span class="show-entries-label">Show entries:</span>
                                    <select class="show-entries-select" onchange="changeVisitorPerPage(this.value)">
                                        <?php foreach ([10, 25, 50, 100] as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $option == 10 ? 'selected' : ''; ?>>
                                                <?php echo $option; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <span class="entry-count">
                                    <i class="fas fa-file-alt me-1"></i>
                                    <strong><?php echo count($visitorLogs); ?></strong> entries shown
                                </span>
                            </div>
                            <div class="right no-print">
                                <button class="btn-print" onclick="printTable('visitorTable')">
                                    <i class="fas fa-print"></i> Print Visitors
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive" id="visitorTable">
                            <table class="table table-hover log-table">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>RFID UID</th>
                                        <th>Visitor</th>
                                        <th>Visiting</th>
                                        <th>Purpose</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($visitorLogs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No visitor access logs found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($visitorLogs as $log): 
                                            $visitorName = $log['visitor_name'] ?? 'Unknown Visitor';
                                            $residentVisited = $log['resident_visited_name'] ?? 'Unknown';
                                            $purpose = $log['purpose_of_visit'] ?? 'N/A';
                                            
                                            $initials = '';
                                            $nameParts = explode(' ', $visitorName);
                                            foreach ($nameParts as $p) {
                                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                            }
                                            $initials = substr($initials, 0, 2);
                                        ?>
                                            <tr>
                                                <td><?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?></td>
                                                <td><span class="uid-cell"><?php echo htmlspecialchars($log['card_uid'] ?? 'N/A'); ?></span></td>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar visitor">
                                                            <?php echo $initials ?: '?'; ?>
                                                        </div>
                                                        <div>
                                                            <div><?php echo htmlspecialchars($visitorName); ?> <span class="visitor-tag">Visitor</span></div>
                                                            <?php if (!empty($log['validity_end'])): ?>
                                                                <div style="font-size: 10px; color: #808090;">
                                                                    Valid until: <?php echo date('M d, Y', strtotime($log['validity_end'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($residentVisited); ?></span>
                                                        <?php if (!empty($log['resident_room'])): ?>
                                                            <br><span style="font-size: 10px; color: #808090;">Room <?php echo htmlspecialchars($log['resident_room']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="font-size: 12px;"><?php echo htmlspecialchars($purpose); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $log['access_type'] == 'entry' ? 'badge-entry' : 'badge-exit'; ?>">
                                                        <i class="fas <?php echo $log['access_type'] == 'entry' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?> me-1"></i>
                                                        <?php echo ucfirst($log['access_type'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $log['access_status'] == 'granted' ? 'badge-granted' : 'badge-denied'; ?>">
                                                        <i class="fas <?php echo $log['access_status'] == 'granted' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                                        <?php echo ucfirst($log['access_status'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
                    <span>Total: <?php echo $stats['total']; ?> logs</span>
                    <span class="text-danger ms-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo $stats['unauthorized_total']; ?> unauthorized
                    </span>
                    <?php if ($stats['pending_alerts'] > 0): ?>
                        <span class="text-warning ms-3">
                            <i class="fas fa-bell me-1"></i>
                            <?php echo $stats['pending_alerts']; ?> pending alerts
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
        // CHANGE PER PAGE - RESIDENTS
        // ============================================================
        function changeResidentPerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // ============================================================
        // CHANGE PER PAGE - VISITORS
        // ============================================================
        function changeVisitorPerPage(value) {
            // For visitors, we reload the page with a visitor-specific limit
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('visitor_limit', value);
            window.location.href = '?' + urlParams.toString();
        }
        
        // ============================================================
        // PRINT TABLE FUNCTION
        // ============================================================
        function printTable(tableId) {
            var printContents = document.getElementById(tableId).innerHTML;
            
            // Get the table title from the card header
            var cardHeader = document.getElementById(tableId).closest('.card').querySelector('.card-header .title');
            var tableTitle = cardHeader ? cardHeader.textContent.trim() : 'Access Logs';
            
            // Check if this is the resident table or visitor table
            var isResidentTable = tableId === 'residentTable';
            var isVisitorTable = tableId === 'visitorTable';
            
            var printWindow = window.open('', '_blank', 'width=1200,height=800');
            printWindow.document.write('<html><head><title>Print - ' + tableTitle + '</title>');
            printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: Arial, sans-serif; padding: 20px; background: white; color: #000; }
                .print-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .print-header h2 { margin: 0; font-size: 22px; }
                .print-header .sub { color: #666; font-size: 14px; }
                .print-header .date { color: #888; font-size: 12px; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                thead th { 
                    background: #f0f0f0; 
                    color: #000; 
                    font-weight: 700; 
                    border: 1px solid #ddd; 
                    padding: 8px 10px; 
                    text-align: left;
                    text-transform: uppercase;
                    font-size: 10px;
                }
                tbody td { 
                    border: 1px solid #ddd; 
                    padding: 6px 10px; 
                    color: #000;
                }
                tbody tr:nth-child(even) { background: #f9f9f9; }
                .badge { 
                    padding: 2px 8px; 
                    border-radius: 4px; 
                    font-size: 10px; 
                    font-weight: 600;
                    display: inline-block;
                }
                .badge-granted { background: #d4edda; color: #155724; }
                .badge-denied { background: #f8d7da; color: #721c24; }
                .badge-entry { background: #d1ecf1; color: #0c5460; }
                .badge-exit { background: #e2e3e5; color: #383d41; }
                .badge-main { background: #d4edda; color: #155724; }
                .badge-battery { background: #fff3cd; color: #856404; }
                .resident-tag, .staff-tag, .visitor-tag {
                    font-size: 8px;
                    padding: 1px 5px;
                    border-radius: 10px;
                    background: #e9ecef;
                    color: #495057;
                    border: 1px solid #ced4da;
                    margin-left: 3px;
                }
                .user-avatar {
                    width: 28px;
                    height: 28px;
                    border-radius: 50%;
                    background: #e9ecef;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    font-size: 10px;
                    color: #495057;
                    margin-right: 6px;
                    overflow: hidden;
                }
                .user-avatar img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .user-cell { display: flex; align-items: center; }
                .uid-cell {
                    font-family: monospace;
                    background: #f0f0f0;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 11px;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 20px; 
                    padding-top: 10px; 
                    border-top: 1px solid #ddd; 
                    color: #888; 
                    font-size: 11px;
                }
                .entry-count { color: #666; font-size: 12px; }
                .print-meta { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 15px; 
                    font-size: 12px; 
                    color: #666;
                    flex-wrap: wrap;
                }
                .print-meta span { margin-right: 15px; }
                .signature-cell {
                    text-align: center;
                    min-width: 80px;
                    height: 40px;
                    padding: 0 5px;
                }
                .signature-cell .signature-line {
                    display: block;
                    width: 100%;
                    border-bottom: 1px solid #999;
                    margin-top: 15px;
                    height: 20px;
                }
                .signature-cell .type-label {
                    font-size: 8px;
                    color: #999;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    display: block;
                    margin-top: 2px;
                }
                @media print {
                    body { padding: 10px; }
                    .no-print { display: none !important; }
                    .table-toolbar { display: none !important; }
                    .user-avatar img { display: block !important; }
                }
            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            
            // Get the selected date from the filter
            var selectedDate = document.querySelector('input[name="date"]')?.value || 'All Dates';
            var displayDate = selectedDate;
            if (selectedDate && selectedDate !== 'All Dates') {
                var dateObj = new Date(selectedDate + 'T00:00:00');
                if (!isNaN(dateObj.getTime())) {
                    displayDate = dateObj.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                }
            }
            
            printWindow.document.write(`
                <div class="print-header">
                    <h2>ISU ECHAGUE DORMITORY</h2>
                    <div class="sub">${tableTitle}</div>
                    <div class="date">Printed: ${new Date().toLocaleString()}</div>
                </div>
                <div class="print-meta">
                    <span><i class="fas fa-calendar-alt"></i> Date: ${displayDate}</span>
                    <span><i class="fas fa-filter"></i> Status: ${document.querySelector('select[name="status"]')?.value || 'All'}</span>
                    <span><i class="fas fa-tag"></i> Type: ${document.querySelector('select[name="type"]')?.value || 'All'}</span>
                    <span><i class="fas fa-search"></i> Search: ${document.querySelector('input[name="search"]')?.value || 'None'}</span>
                </div>
            `);
            
            // Parse and modify the table
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = printContents;
            var table = tempDiv.querySelector('table');
            
            if (table) {
                // For Residents: Date/Time | RFID UID | Tenant | Room | Type (Entry) | Signature In | Type (Exit) | Signature Out
                if (isResidentTable) {
                    // Replace thead
                    var thead = table.querySelector('thead');
                    if (thead) {
                        var headerRow = thead.querySelector('tr');
                        if (headerRow) {
                            // Get all headers
                            var headers = headerRow.querySelectorAll('th');
                            if (headers.length >= 7) {
                                // Keep: Date/Time, RFID UID, Tenant (User), Room
                                // Replace: Type -> Type (Entry) | Status -> Signature In | Power -> Type (Exit) | add Signature Out
                                
                                // Modify headers
                                headers[0].textContent = 'Date/Time';
                                headers[1].textContent = 'RFID UID';
                                headers[2].textContent = 'Tenant';
                                headers[3].textContent = 'Room';
                                headers[4].textContent = 'Type (Entry)';
                                headers[5].textContent = 'Signature In';
                                headers[6].textContent = 'Type (Exit)';
                                
                                // Add extra header for Signature Out
                                var newTh = document.createElement('th');
                                newTh.textContent = 'Signature Out';
                                headerRow.appendChild(newTh);
                            }
                        }
                    }
                    
                    // Modify tbody rows
                    var rows = table.querySelectorAll('tbody tr');
                    rows.forEach(function(row) {
                        var cells = row.querySelectorAll('td');
                        if (cells.length >= 7) {
                            // Get the type cell (index 4)
                            var typeCell = cells[4];
                            var typeText = typeCell.textContent.trim();
                            var isEntry = typeText.toLowerCase().includes('entry');
                            
                            // Keep first 5 cells: Date/Time, RFID UID, User, Room, Type
                            // Cell 4 (Type) - show Entry/Exit
                            // Cell 5 (Status) -> Signature In
                            // Cell 6 (Power) -> Type (Exit)
                            // Add new cell for Signature Out
                            
                            // Determine if it's Entry or Exit for the new Type (Exit) column
                            var exitType = isEntry ? 'Exit' : 'Entry';
                            
                            // Cell 4: Keep as Type (Entry)
                            // Cell 5: Signature In
                            var sigInCell = cells[5];
                            sigInCell.innerHTML = '<div class="signature-cell"><span class="signature-line"></span><span class="type-label">Signature In</span></div>';
                            sigInCell.className = 'signature-cell';
                            
                            // Cell 6: Type (Exit)
                            var exitTypeCell = cells[6];
                            exitTypeCell.innerHTML = '<span class="badge ' + (isEntry ? 'badge-exit' : 'badge-entry') + '">' + exitType + '</span>';
                            exitTypeCell.style.textAlign = 'center';
                            
                            // Add new cell for Signature Out
                            var newTd = document.createElement('td');
                            newTd.className = 'signature-cell';
                            newTd.innerHTML = '<span class="signature-line"></span><span class="type-label">Signature Out</span>';
                            row.appendChild(newTd);
                        }
                    });
                }
                
                // For Visitors: Date/Time | RFID UID | Visitor | Visiting | Purpose | Type (Entry) | Signature In | Type (Exit) | Signature Out
                if (isVisitorTable) {
                    // Replace thead
                    var thead = table.querySelector('thead');
                    if (thead) {
                        var headerRow = thead.querySelector('tr');
                        if (headerRow) {
                            var headers = headerRow.querySelectorAll('th');
                            if (headers.length >= 7) {
                                headers[0].textContent = 'Date/Time';
                                headers[1].textContent = 'RFID UID';
                                headers[2].textContent = 'Visitor';
                                headers[3].textContent = 'Visiting';
                                headers[4].textContent = 'Purpose';
                                headers[5].textContent = 'Type (Entry)';
                                headers[6].textContent = 'Signature In';
                                
                                var newTh1 = document.createElement('th');
                                newTh1.textContent = 'Type (Exit)';
                                headerRow.appendChild(newTh1);
                                
                                var newTh2 = document.createElement('th');
                                newTh2.textContent = 'Signature Out';
                                headerRow.appendChild(newTh2);
                            }
                        }
                    }
                    
                    // Modify tbody rows
                    var rows = table.querySelectorAll('tbody tr');
                    rows.forEach(function(row) {
                        var cells = row.querySelectorAll('td');
                        if (cells.length >= 7) {
                            var typeCell = cells[5];
                            var typeText = typeCell.textContent.trim();
                            var isEntry = typeText.toLowerCase().includes('entry');
                            
                            // Keep cells 0-4: Date/Time, RFID UID, Visitor, Visiting, Purpose
                            // Cell 5: Type (Entry) - keep as is
                            // Cell 6: Signature In
                            var sigInCell = cells[6];
                            sigInCell.innerHTML = '<div class="signature-cell"><span class="signature-line"></span><span class="type-label">Signature In</span></div>';
                            sigInCell.className = 'signature-cell';
                            
                            // Add Type (Exit)
                            var exitType = isEntry ? 'Exit' : 'Entry';
                            var newTd1 = document.createElement('td');
                            newTd1.style.textAlign = 'center';
                            newTd1.innerHTML = '<span class="badge ' + (isEntry ? 'badge-exit' : 'badge-entry') + '">' + exitType + '</span>';
                            row.appendChild(newTd1);
                            
                            // Add Signature Out
                            var newTd2 = document.createElement('td');
                            newTd2.className = 'signature-cell';
                            newTd2.innerHTML = '<span class="signature-line"></span><span class="type-label">Signature Out</span>';
                            row.appendChild(newTd2);
                        }
                    });
                }
                
                printContents = tempDiv.innerHTML;
            }
            
            printWindow.document.write(printContents);
            printWindow.document.write(`
                <div class="footer">
                    &copy; ${new Date().getFullYear()} Tap-and-Go Doorlock System &bull; Generated on ${new Date().toLocaleString()}
                </div>
            `);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
        
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
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
