<?php
/**
 * Tap-and-Go Doorlock - Access Logs / Attendance
 * FIXED VERSION
 */

session_start();

// ============================================================
// FIXED: CORRECT AUTHENTICATION CHECK
// ============================================================
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication - FIXED for all user types
if (!isset($_SESSION['user_type']) || !isset($_SESSION['user_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php'; 
$conn = getDBConnection();

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
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET ALL ACCESS LOGS - FIXED
// ============================================================
$allLogs = [];
$countQuery = "SELECT COUNT(*) as total FROM access_logs WHERE 1=1";
$query = "
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
        ru.full_name as resident_visited_name
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    WHERE 1=1
";

// Apply filters
if (!empty($dateFilter)) {
    $query .= " AND DATE(al.timestamp) = '$dateFilter'";
    $countQuery .= " AND DATE(timestamp) = '$dateFilter'";
}
if (!empty($statusFilter)) {
    $query .= " AND al.access_status = '$statusFilter'";
    $countQuery .= " AND access_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $query .= " AND al.access_type = '$typeFilter'";
    $countQuery .= " AND access_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $query .= " AND (al.card_uid LIKE '%$searchFilter%' OR u.full_name LIKE '%$searchFilter%' OR c.visitor_name LIKE '%$searchFilter%')";
    $countQuery .= " AND (card_uid LIKE '%$searchFilter%')";
}

// Get total count
$countResult = $conn->query($countQuery);
$totalLogs = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalLogs = (int)$row['total'];
}

$totalPages = ceil($totalLogs / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$query .= " ORDER BY al.timestamp DESC LIMIT $perPage OFFSET $offset";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allLogs[] = $row;
    }
}

// ============================================================
// GET ALERT LOGS
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
if (!empty($statusFilter) && ($statusFilter == 'pending' || $statusFilter == 'resolved')) {
    $alertQuery .= " AND alog.delivery_status = '$statusFilter'";
}
if (!empty($searchFilter)) {
    $alertQuery .= " AND (alog.card_uid LIKE '%$searchFilter%' OR alog.user_name LIKE '%$searchFilter%')";
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
// GET STATS
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

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_status = 'denied'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unauthorized_total'] = (int)$row['count'];
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
        .stat-card .text-danger { color: #f87171 !important; }
        .stat-card .text-muted { color: #606070 !important; }
        .pulse-badge {
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
        
        .log-table { font-size: 13px; }
        .log-table th {
            font-weight: 600;
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
        }
        .log-table td {
            vertical-align: middle;
            padding: 8px 12px;
            color: #e0e0e0;
            border-bottom: 1px solid #1a2a4a;
        }
        .log-table tr:hover td { background: rgba(255,255,255,0.02); }
        .log-table .user-cell { display: flex; align-items: center; gap: 10px; }
        .log-table .user-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #4a5a8a, #5a3a7a);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 12px; flex-shrink: 0;
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
            color: #93c5fd;
            background: #1a2a4a;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .log-table .uid-cell.denied-uid {
            color: #f87171;
            background: #3a1a1a;
        }
        
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
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        
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
        
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
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
            .pagination { justify-content: center !important; }
        }
        
        @media (max-width: 576px) {
            .filter-section .row .col-md-2,
            .filter-section .row .col-md-3 { margin-bottom: 8px; }
            .log-table { font-size: 11px; }
            .log-table th, .log-table td { padding: 6px 8px; }
            .log-table .user-avatar { width: 24px; height: 24px; font-size: 9px; }
            .log-table .uid-cell { font-size: 10px; padding: 1px 4px; }
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
                            <a href="alerts.php" class="badge badge-danger ms-2 p-2" style="text-decoration: none; animation: pulseBadge 1s infinite;">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <?php echo $stats['pending_alerts']; ?> Alert<?php echo $stats['pending_alerts'] > 1 ? 's' : ''; ?>
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- STATS ROW 1 -->
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

                <!-- STATS ROW 2 -->
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

                <!-- FILTERS -->
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

                <!-- LOGS TABLE -->
                <div class="card">
                    <div class="card-header">
                        <div class="log-header-actions">
                            <div class="left">
                                <div class="section-header">
                                    <span class="icon">📋</span>
                                    <span class="title">All Access Logs</span>
                                    <span class="count badge badge-primary"><?php echo count($allLogs); ?> logs</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-muted small">
                                    <?php if ($totalLogs > 0): ?>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover log-table">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>RFID UID</th>
                                        <th>User</th>
                                        <th>Room</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Power</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($allLogs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No access logs found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($allLogs as $log): 
                                            $displayName = $log['user_name'] ?? 'Unknown';
                                            $cardType = $log['card_type'] ?? 'resident';
                                            $cardUid = $log['card_uid'] ?? 'N/A';
                                            $roomDisplay = $log['room_number'] ?? 'N/A';
                                            
                                            // Handle visitor names
                                            if ($cardType == 'visitor' && !empty($log['visitor_name'])) {
                                                $displayName = $log['visitor_name'];
                                                if (!empty($log['resident_visited_name'])) {
                                                    $roomDisplay = 'Visit: ' . $log['resident_visited_name'];
                                                }
                                            }
                                            
                                            $avatarClass = '';
                                            $userTypeTag = '';
                                            
                                            if ($cardType == 'staff') {
                                                $avatarClass = 'staff';
                                                $userTypeTag = '<span class="staff-tag">Staff</span>';
                                            } elseif ($cardType == 'visitor') {
                                                $avatarClass = 'visitor';
                                                $userTypeTag = '<span class="visitor-tag">Visitor</span>';
                                            } else {
                                                $userTypeTag = '<span class="resident-tag">Resident</span>';
                                            }
                                            
                                            // Check if denied
                                            if ($log['access_status'] == 'denied') {
                                                $avatarClass = 'denied';
                                            }
                                            
                                            $initials = '';
                                            $nameParts = explode(' ', $displayName);
                                            foreach ($nameParts as $p) {
                                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                            }
                                            $initials = substr($initials, 0, 2);
                                        ?>
                                            <tr>
                                                <td><?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?></td>
                                                <td>
                                                    <span class="uid-cell <?php echo $log['access_status'] == 'denied' ? 'denied-uid' : ''; ?>">
                                                        <?php echo htmlspecialchars($cardUid); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar <?php echo $avatarClass; ?>">
                                                            <?php echo $initials ?: '?'; ?>
                                                        </div>
                                                        <div>
                                                            <div>
                                                                <?php echo htmlspecialchars($displayName); ?> 
                                                                <?php echo $userTypeTag; ?>
                                                            </div>
                                                            <?php if (!empty($log['student_id'])): ?>
                                                                <div style="font-size: 10px; color: #808090;"><?php echo htmlspecialchars($log['student_id']); ?></div>
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
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="page-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> entries
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
                                                $endPage = min($totalPages, $page + 2);
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
                                                <?php if ($endPage < $totalPages): ?>
                                                    <li class="page-item"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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

                <!-- ALERTS SECTION -->
                <?php if (!empty($alertLogs)): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="log-header-actions">
                            <div class="left">
                                <div class="section-header">
                                    <span class="icon">🚨</span>
                                    <span class="title" style="<?php echo $stats['critical_alerts'] > 0 ? 'color: #f87171;' : ''; ?>">Alerts</span>
                                    <span class="count badge <?php echo $stats['pending_alerts'] > 0 ? 'badge-pending' : 'badge-resolved'; ?>">
                                        <?php echo count($alertLogs); ?> alerts
                                    </span>
                                </div>
                            </div>
                            <div>
                                <a href="alerts.php" class="btn btn-<?php echo $stats['critical_alerts'] > 0 ? 'danger' : 'outline-secondary'; ?> btn-sm">
                                    <i class="fas fa-eye me-1"></i> View All Alerts
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
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
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FOOTER -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
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
