<?php
/**
 * Tap-and-Go Doorlock - Reports
 * COMPLETE - History of all alerts and unauthorized access
 * WITH PAGINATION - Show Entries
 * PURE DARK MODE - FIXED LAYOUT SAME AS DASHBOARD
 * FIXED: Dark table with proper styling
 */

session_start();

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
// GET FILTERS
// ============================================================
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// PAGINATION SETTINGS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $perPage;

$perPageOptions = [10, 25, 50, 100, 250, 500];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

// ============================================================
// GET ALERT LOGS (HISTORY) WITH PAGINATION
// ============================================================
$alertLogs = [];
$alertCount = 0;

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

$countQuery = "SELECT COUNT(*) as total FROM alert_logs alog WHERE 1=1";

if (!empty($dateFrom)) {
    $alertQuery .= " AND DATE(alog.timestamp) >= '$dateFrom'";
    $countQuery .= " AND DATE(alog.timestamp) >= '$dateFrom'";
}
if (!empty($dateTo)) {
    $alertQuery .= " AND DATE(alog.timestamp) <= '$dateTo'";
    $countQuery .= " AND DATE(alog.timestamp) <= '$dateTo'";
}
if (!empty($typeFilter)) {
    $alertQuery .= " AND alog.alert_type = '$typeFilter'";
    $countQuery .= " AND alog.alert_type = '$typeFilter'";
}
if (!empty($statusFilter)) {
    $alertQuery .= " AND alog.delivery_status = '$statusFilter'";
    $countQuery .= " AND alog.delivery_status = '$statusFilter'";
}
if (!empty($searchFilter)) {
    $alertQuery .= " AND (
        alog.card_uid LIKE '%$searchFilter%' 
        OR alog.user_name LIKE '%$searchFilter%'
        OR alog.reason LIKE '%$searchFilter%'
    )";
    $countQuery .= " AND (
        alog.card_uid LIKE '%$searchFilter%' 
        OR alog.user_name LIKE '%$searchFilter%'
        OR alog.reason LIKE '%$searchFilter%'
    )";
}

$countResult = $conn->query($countQuery);
if ($countResult && $row = $countResult->fetch_assoc()) {
    $alertCount = (int)$row['total'];
}

$alertQuery .= " ORDER BY alog.timestamp DESC LIMIT $offset, $perPage";

$result = $conn->query($alertQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $displayName = !empty($row['user_name']) ? $row['user_name'] : 'Unknown';
        if ($row['rfid_card_type'] == 'visitor' && !empty($row['visitor_name'])) {
            $displayName = $row['visitor_name'] . ' (Visitor)';
        }
        if (empty($displayName) || $displayName == 'Unknown') {
            $displayName = 'Unknown Card';
        }
        $row['display_name'] = $displayName;
        $alertLogs[] = $row;
    }
}

// ============================================================
// GET UNAUTHORIZED ACCESS LOGS WITH PAGINATION
// ============================================================
$unauthorizedLogs = [];
$unauthorizedCount = 0;

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

$countUnauthorizedQuery = "SELECT COUNT(*) as total FROM access_logs al WHERE al.access_status = 'denied'";

if (!empty($dateFrom)) {
    $unauthorizedQuery .= " AND DATE(al.timestamp) >= '$dateFrom'";
    $countUnauthorizedQuery .= " AND DATE(al.timestamp) >= '$dateFrom'";
}
if (!empty($dateTo)) {
    $unauthorizedQuery .= " AND DATE(al.timestamp) <= '$dateTo'";
    $countUnauthorizedQuery .= " AND DATE(al.timestamp) <= '$dateTo'";
}
if (!empty($searchFilter)) {
    $unauthorizedQuery .= " AND (al.card_uid LIKE '%$searchFilter%' OR u.full_name LIKE '%$searchFilter%')";
    $countUnauthorizedQuery .= " AND (al.card_uid LIKE '%$searchFilter%' OR u.full_name LIKE '%$searchFilter%')";
}

$countResult = $conn->query($countUnauthorizedQuery);
if ($countResult && $row = $countResult->fetch_assoc()) {
    $unauthorizedCount = (int)$row['total'];
}

$unauthorizedQuery .= " ORDER BY al.timestamp DESC LIMIT $offset, $perPage";

$result = $conn->query($unauthorizedQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $displayName = $row['user_name'] ?? 'Unknown';
        if (empty($displayName) || $displayName == 'Unknown') {
            $displayName = 'Unknown Card';
        }
        $row['display_name'] = $displayName;
        $unauthorizedLogs[] = $row;
    }
}

// ============================================================
// GET ACCESS LOGS WITH PAGINATION
// ============================================================
$accessLogs = [];
$accessCount = 0;

$accessQuery = "
    SELECT 
        al.*,
        c.card_uid,
        c.card_type,
        c.visitor_name,
        u.full_name as user_name,
        u.room_number,
        u.student_id,
        rp.course,
        rp.year_level
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE 1=1
";

$countAccessQuery = "SELECT COUNT(*) as total FROM access_logs al WHERE 1=1";

if (!empty($dateFrom)) {
    $accessQuery .= " AND DATE(al.timestamp) >= '$dateFrom'";
    $countAccessQuery .= " AND DATE(al.timestamp) >= '$dateFrom'";
}
if (!empty($dateTo)) {
    $accessQuery .= " AND DATE(al.timestamp) <= '$dateTo'";
    $countAccessQuery .= " AND DATE(al.timestamp) <= '$dateTo'";
}
if (!empty($searchFilter)) {
    $accessQuery .= " AND (u.full_name LIKE '%$searchFilter%' OR al.card_uid LIKE '%$searchFilter%')";
    $countAccessQuery .= " AND (u.full_name LIKE '%$searchFilter%' OR al.card_uid LIKE '%$searchFilter%')";
}

$countResult = $conn->query($countAccessQuery);
if ($countResult && $row = $countResult->fetch_assoc()) {
    $accessCount = (int)$row['total'];
}

$accessQuery .= " ORDER BY al.timestamp DESC LIMIT $offset, $perPage";

$result = $conn->query($accessQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $accessLogs[] = $row;
    }
}

// ============================================================
// GET STATS
// ============================================================
$stats = [
    'total_alerts' => 0,
    'pending_alerts' => 0,
    'resolved_alerts' => 0,
    'unauthorized_total' => 0,
    'total_access' => 0,
    'granted_access' => 0,
    'denied_access' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_alerts'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_alerts'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'resolved'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['resolved_alerts'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_status = 'denied'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unauthorized_total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_access'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_status = 'granted'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['granted_access'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE access_status = 'denied'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['denied_access'] = (int)$row['count'];
}

// ============================================================
// EXPORT TO CSV
// ============================================================
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $exportType = isset($_GET['export_type']) ? $_GET['export_type'] : 'alerts';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $exportType . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($exportType == 'alerts') {
        fputcsv($output, ['ID', 'Card UID', 'User', 'Type', 'Status', 'Reason', 'Timestamp']);
        foreach ($alertLogs as $log) {
            fputcsv($output, [
                $log['alert_id'],
                $log['card_uid'],
                $log['display_name'],
                $log['alert_type'],
                $log['delivery_status'],
                $log['reason'],
                $log['timestamp']
            ]);
        }
    } elseif ($exportType == 'unauthorized') {
        fputcsv($output, ['ID', 'Card UID', 'User', 'Room', 'Type', 'Timestamp']);
        foreach ($unauthorizedLogs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['card_uid'],
                $log['display_name'],
                $log['room_number'] ?? 'N/A',
                $log['access_type'],
                $log['timestamp']
            ]);
        }
    } else {
        fputcsv($output, ['ID', 'Card UID', 'User', 'Room', 'Type', 'Status', 'Timestamp']);
        foreach ($accessLogs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['card_uid'] ?? 'N/A',
                $log['user_name'] ?? 'Unknown',
                $log['room_number'] ?? 'N/A',
                $log['access_type'],
                $log['access_status'],
                $log['timestamp']
            ]);
        }
    }
    
    fclose($output);
    exit();
}

// ============================================================
// PAGINATION HELPER FUNCTION
// ============================================================
function renderPagination($currentPage, $totalCount, $perPage, $tab) {
    $totalPages = ceil($totalCount / $perPage);
    if ($totalPages <= 1) return '';
    
    $queryParams = array_filter([
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'type' => $_GET['type'] ?? '',
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'per_page' => $perPage,
        'tab' => $tab
    ]);
    
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    
    if ($currentPage > 1) {
        $queryParams['page'] = $currentPage - 1;
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($queryParams) . '">«</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">«</span></li>';
    }
    
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $queryParams['page'] = 1;
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($queryParams) . '">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $queryParams['page'] = $i;
            $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($queryParams) . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $queryParams['page'] = $totalPages;
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($queryParams) . '">' . $totalPages . '</a></li>';
    }
    
    if ($currentPage < $totalPages) {
        $queryParams['page'] = $currentPage + 1;
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($queryParams) . '">»</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">»</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'alerts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Tap-and-Go Doorlock</title>
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
        .stat-number.text-danger { color: #f87171 !important; }
        .stat-number.text-success { color: #34d399 !important; }
        .stat-number.text-warning { color: #fbbf24 !important; }
        
        .pulse-badge {
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
        .filter-section .btn-export {
            background: #065f46 !important;
            color: #34d399 !important;
            border: none !important;
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .filter-section .btn-export:hover {
            background: #0a7a5a !important;
            color: #6ee7b7 !important;
        }
        
        /* ============================================================
           DARK TABLE - FIXED
           ============================================================ */
        .log-table {
            font-size: 13px;
            background: #111827 !important;
            border-radius: 8px;
            overflow: hidden;
            width: 100%;
            border-collapse: collapse;
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
            border-color: #1a2a4a !important;
        }
        
        .log-table tbody td {
            vertical-align: middle;
            padding: 8px 12px;
            color: #e0e0e0 !important;
            border-bottom: 1px solid #1a2a4a !important;
            background: #111827 !important;
        }
        
        .log-table tbody tr:hover td {
            background: rgba(255,255,255,0.03) !important;
        }
        
        .log-table .user-cell { display: flex; align-items: center; gap: 10px; }
        .log-table .user-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #4a5a8a, #5a3a7a) !important;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 12px; flex-shrink: 0;
        }
        .log-table .user-avatar.denied {
            background: linear-gradient(135deg, #8a2a2a, #5a1a1a) !important;
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
            background: #2a1a1a !important;
        }
        .log-table .text-muted { color: #808090 !important; }
        
        .table-responsive {
            background: #111827 !important;
            border-radius: 8px;
            border: 1px solid #1a2a4a !important;
            overflow: hidden;
        }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge-granted { background: #065f46 !important; color: #34d399 !important; }
        .badge-denied { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-entry { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-exit { background: #2a2a4a !important; color: #808090 !important; }
        .badge-pending { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-resolved { background: #065f46 !important; color: #34d399 !important; }
        .badge-unauthorized { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-buzzer { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-visitor { background: #1a2a5a !important; color: #93c5fd !important; }
        .badge-resident { background: #065f46 !important; color: #34d399 !important; }
        .badge-staff { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        
        /* ============================================================
           DARK NAV TABS
           ============================================================ */
        .nav-tabs {
            border-bottom-color: #1a2a4a !important;
        }
        .nav-tabs .nav-link {
            color: #808090 !important;
            font-weight: 500;
            border: none;
            padding: 10px 20px;
            background: transparent !important;
        }
        .nav-tabs .nav-link:hover {
            color: #e0e0e0 !important;
            background: rgba(255,255,255,0.03) !important;
        }
        .nav-tabs .nav-link.active {
            color: #93c5fd !important;
            border-bottom: 3px solid #93c5fd !important;
            background: transparent !important;
        }
        .nav-tabs .nav-link .badge {
            background: #1a2a4a !important;
            color: #808090 !important;
        }
        .nav-tabs .nav-link.active .badge {
            background: #1a3a6a !important;
            color: #93c5fd !important;
        }
        
        /* ============================================================
           DARK PAGINATION
           ============================================================ */
        .pagination .page-link {
            color: #808090 !important;
            background: transparent !important;
            border: none !important;
            padding: 6px 12px;
            margin: 0 2px;
            border-radius: 8px;
        }
        .pagination .page-link:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            border-radius: 8px;
        }
        .pagination .page-item.disabled .page-link {
            color: #4a4a5a !important;
        }
        .pagination-info {
            font-size: 13px;
            color: #808090 !important;
        }
        
        /* ============================================================
           DARK PER PAGE SELECT
           ============================================================ */
        .per-page-select {
            width: auto;
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            border: 1px solid #2a2a4a !important;
            font-size: 13px;
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .per-page-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        
        /* ============================================================
           DENIED ROW
           ============================================================ */
        .denied-row {
            background-color: #1a0a0a !important;
        }
        .denied-row:hover td {
            background-color: #2a1a1a !important;
        }
        
        /* ============================================================
           LIVE INDICATOR
           ============================================================ */
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
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        
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
            
            .filter-section .row { flex-direction: column; gap: 8px; }
            .filter-section .col-md-2 { width: 100%; }
            .nav-tabs { flex-wrap: nowrap; overflow-x: auto; }
            .nav-tabs .nav-link { font-size: 12px; padding: 8px 12px; white-space: nowrap; }
        }
        
        @media (max-width: 576px) {
            .filter-section .row .col-md-2 {
                margin-bottom: 8px;
            }
            .log-table {
                font-size: 11px;
            }
            .log-table th,
            .log-table td {
                padding: 6px 8px;
            }
            .uid-cell {
                font-size: 10px;
                padding: 1px 4px;
            }
            .card-header .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px;
            }
        }
        
        @media print {
            .no-print { display: none !important; }
            .filter-section, .stat-row, .btn, .nav-tabs, .pagination { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            body { background: white !important; color: black !important; }
            .log-table th { color: #333 !important; border-bottom: 2px solid #ddd !important; }
            .log-table td { color: #333 !important; }
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
                        <i class="fas fa-file-alt me-2" style="color: #1a3a6a;"></i>
                        Reports / History
                        <?php if ($stats['pending_alerts'] > 0): ?>
                            <span class="badge bg-danger ms-2 pulse-badge">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?php echo $stats['pending_alerts']; ?> pending
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

                <!-- ============================================================
                STATS CARDS - SAME AS DASHBOARD
                ============================================================ -->
                <div class="row g-3 mb-4 no-print">
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-bell"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_alerts']; ?></div>
                                <div class="stat-label">Total Alerts</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['pending_alerts'] > 0 ? 'text-warning' : ''; ?>">
                                    <?php echo $stats['pending_alerts']; ?>
                                </div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <?php if ($stats['pending_alerts'] > 0): ?>
                                <span class="badge bg-danger pulse-badge" style="position:absolute; top:8px; right:8px;"><?php echo $stats['pending_alerts']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['resolved_alerts']; ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <div class="stat-number text-danger"><?php echo $stats['unauthorized_total']; ?></div>
                                <div class="stat-label">Unauthorized</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_access']; ?></div>
                                <div class="stat-label">Total Access</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-database"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['granted_access'] + $stats['denied_access']; ?></div>
                                <div class="stat-label">Total Records</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                FILTERS
                ============================================================ -->
                <div class="filter-section no-print">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">All</option>
                                <option value="unauthorized" <?php echo $typeFilter == 'unauthorized' ? 'selected' : ''; ?>>Unauthorized</option>
                                <option value="buzzer" <?php echo $typeFilter == 'buzzer' ? 'selected' : ''; ?>>Buzzer</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="resolved" <?php echo $statusFilter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="UID or Name" value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-filter w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                            </div>
                        </div>
                        <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                    </form>
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="?export=csv&export_type=alerts&tab=alerts&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?><?php echo !empty($typeFilter) ? '&type='.$typeFilter : ''; ?><?php echo !empty($statusFilter) ? '&status='.$statusFilter : ''; ?><?php echo !empty($searchFilter) ? '&search='.urlencode($searchFilter) : ''; ?>" class="btn btn-export btn-sm">
                                    <i class="fas fa-file-csv me-1"></i> Export Alerts
                                </a>
                                <a href="?export=csv&export_type=unauthorized&tab=unauthorized&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?><?php echo !empty($searchFilter) ? '&search='.urlencode($searchFilter) : ''; ?>" class="btn btn-export btn-sm">
                                    <i class="fas fa-file-csv me-1"></i> Export Unauthorized
                                </a>
                                <a href="?export=csv&export_type=access&tab=access&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?><?php echo !empty($searchFilter) ? '&search='.urlencode($searchFilter) : ''; ?>" class="btn btn-export btn-sm">
                                    <i class="fas fa-file-csv me-1"></i> Export All Access
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                TABS
                ============================================================ -->
                <ul class="nav nav-tabs no-print" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'alerts' ? 'active' : ''; ?>" href="?tab=alerts&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?><?php echo !empty($typeFilter) ? '&type='.$typeFilter : ''; ?><?php echo !empty($statusFilter) ? '&status='.$statusFilter : ''; ?><?php echo !empty($searchFilter) ? '&search='.urlencode($searchFilter) : ''; ?>&per_page=<?php echo $perPage; ?>">
                            <i class="fas fa-bell me-1"></i> Alerts History
                            <span class="badge ms-1"><?php echo $alertCount; ?></span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'unauthorized' ? 'active' : ''; ?>" href="?tab=unauthorized&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?><?php echo !empty($searchFilter) ? '&search='.urlencode($searchFilter) : ''; ?>&per_page=<?php echo $perPage; ?>">
                            <i class="fas fa-exclamation-triangle me-1"></i> Unauthorized
                            <span class="badge ms-1"><?php echo $unauthorizedCount; ?></span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'access' ? 'active' : ''; ?>" href="?tab=access&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?><?php echo !empty($searchFilter) ? '&search='.urlencode($searchFilter) : ''; ?>&per_page=<?php echo $perPage; ?>">
                            <i class="fas fa-history me-1"></i> All Access Logs
                            <span class="badge ms-1"><?php echo $accessCount; ?></span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="reportTabsContent">
                    
                    <!-- ============================================================
                    TAB 1: ALERTS HISTORY
                    ============================================================ -->
                    <div class="tab-pane fade show <?php echo $activeTab == 'alerts' ? 'active' : ''; ?>" id="alerts" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5><i class="fas fa-bell me-2"></i>Alerts History</h5>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small">Total: <?php echo $alertCount; ?> records</span>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="text-muted small">Show</span>
                                            <select class="per-page-select" onchange="window.location.href=this.value">
                                                <?php foreach ($perPageOptions as $option): ?>
                                                    <option value="?<?php echo http_build_query(array_merge($_GET, ['per_page' => $option, 'page' => 1, 'tab' => 'alerts'])); ?>" <?php echo $perPage == $option ? 'selected' : ''; ?>>
                                                        <?php echo $option; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="text-muted small">entries</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover log-table">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Card UID</th>
                                                <th>User</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($alertLogs)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                        No alerts found
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($alertLogs as $log): 
                                                    $isPending = $log['delivery_status'] == 'pending';
                                                    $isResolved = $log['delivery_status'] == 'resolved';
                                                    $isCritical = $isPending && $log['alert_type'] == 'unauthorized';
                                                ?>
                                                    <tr class="<?php echo $isCritical ? 'denied-row' : ''; ?>">
                                                        <td>
                                                            <span class="text-muted small">
                                                                <?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="uid-cell <?php echo $isCritical ? 'denied-uid' : ''; ?>">
                                                                <?php echo htmlspecialchars($log['card_uid'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="user-cell">
                                                                <div class="user-avatar <?php echo $isCritical ? 'denied' : ''; ?>">
                                                                    <?php 
                                                                        $name = $log['display_name'] ?? '?';
                                                                        $initials = '';
                                                                        $nameParts = explode(' ', $name);
                                                                        foreach ($nameParts as $p) {
                                                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                                                        }
                                                                        echo substr($initials, 0, 2) ?: '?';
                                                                    ?>
                                                                </div>
                                                                <div>
                                                                    <?php echo htmlspecialchars($log['display_name'] ?? 'Unknown'); ?>
                                                                    <?php if (!empty($log['rfid_card_type'])): ?>
                                                                        <span class="badge <?php echo $log['rfid_card_type'] == 'visitor' ? 'badge-visitor' : ($log['rfid_card_type'] == 'staff' ? 'badge-staff' : 'badge-resident'); ?>" style="font-size:8px;">
                                                                            <?php echo ucfirst($log['rfid_card_type']); ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $log['alert_type'] == 'unauthorized' ? 'badge-unauthorized' : 'badge-buzzer'; ?>">
                                                                <?php echo ucfirst($log['alert_type'] ?? 'unknown'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $isPending ? 'badge-pending' : 'badge-resolved'; ?>">
                                                                <?php echo ucfirst($log['delivery_status'] ?? 'unknown'); ?>
                                                            </span>
                                                        </td>
                                                        <td style="font-size: 12px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                            <?php echo htmlspecialchars($log['reason'] ?? 'Unauthorized access attempt'); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($alertCount > $perPage): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="pagination-info">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $alertCount); ?> of <?php echo $alertCount; ?> entries
                                    </div>
                                    <?php echo renderPagination($page, $alertCount, $perPage, 'alerts'); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================
                    TAB 2: UNAUTHORIZED ACCESS
                    ============================================================ -->
                    <div class="tab-pane fade show <?php echo $activeTab == 'unauthorized' ? 'active' : ''; ?>" id="unauthorized" role="tabpanel">
                        <div class="card">
                            <div class="card-header" style="background: #2a1a1a; border-bottom-color: #4a2a2a;">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5><i class="fas fa-exclamation-triangle me-2" style="color: #f87171;"></i>Unauthorized Access History</h5>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small">Total: <?php echo $unauthorizedCount; ?> records</span>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="text-muted small">Show</span>
                                            <select class="per-page-select" onchange="window.location.href=this.value">
                                                <?php foreach ($perPageOptions as $option): ?>
                                                    <option value="?<?php echo http_build_query(array_merge($_GET, ['per_page' => $option, 'page' => 1, 'tab' => 'unauthorized'])); ?>" <?php echo $perPage == $option ? 'selected' : ''; ?>>
                                                        <?php echo $option; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="text-muted small">entries</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover log-table">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Card UID</th>
                                                <th>User</th>
                                                <th>Room</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($unauthorizedLogs)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                                                        No unauthorized access attempts
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($unauthorizedLogs as $log): ?>
                                                    <tr class="denied-row">
                                                        <td>
                                                            <span class="text-muted small">
                                                                <?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="uid-cell denied-uid">
                                                                <?php echo htmlspecialchars($log['card_uid'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="user-cell">
                                                                <div class="user-avatar denied">
                                                                    <?php 
                                                                        $name = $log['display_name'] ?? '?';
                                                                        $initials = '';
                                                                        $nameParts = explode(' ', $name);
                                                                        foreach ($nameParts as $p) {
                                                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                                                        }
                                                                        echo substr($initials, 0, 2) ?: '?';
                                                                    ?>
                                                                </div>
                                                                <div>
                                                                    <?php echo htmlspecialchars($log['display_name'] ?? 'Unknown'); ?>
                                                                    <?php if (!empty($log['card_type']) && $log['card_type'] == 'visitor'): ?>
                                                                        <span class="badge badge-visitor" style="font-size:8px;">Visitor</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($log['room_number'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $log['access_type'] == 'entry' ? 'badge-entry' : 'badge-exit'; ?>">
                                                                <i class="fas <?php echo $log['access_type'] == 'entry' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?> me-1"></i>
                                                                <?php echo ucfirst($log['access_type'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-denied">
                                                                <i class="fas fa-times-circle me-1"></i> Denied
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($unauthorizedCount > $perPage): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="pagination-info">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $unauthorizedCount); ?> of <?php echo $unauthorizedCount; ?> entries
                                    </div>
                                    <?php echo renderPagination($page, $unauthorizedCount, $perPage, 'unauthorized'); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================
                    TAB 3: ALL ACCESS LOGS
                    ============================================================ -->
                    <div class="tab-pane fade show <?php echo $activeTab == 'access' ? 'active' : ''; ?>" id="access" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5><i class="fas fa-history me-2"></i>All Access Logs</h5>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small">Total: <?php echo $accessCount; ?> records</span>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="text-muted small">Show</span>
                                            <select class="per-page-select" onchange="window.location.href=this.value">
                                                <?php foreach ($perPageOptions as $option): ?>
                                                    <option value="?<?php echo http_build_query(array_merge($_GET, ['per_page' => $option, 'page' => 1, 'tab' => 'access'])); ?>" <?php echo $perPage == $option ? 'selected' : ''; ?>>
                                                        <?php echo $option; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="text-muted small">entries</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover log-table">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Card UID</th>
                                                <th>User</th>
                                                <th>Room</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($accessLogs)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                        No access logs found
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($accessLogs as $log): 
                                                    $isDenied = $log['access_status'] == 'denied';
                                                    $isEntry = $log['access_type'] == 'entry';
                                                    $displayName = $log['user_name'] ?? 'Unknown';
                                                    $initials = '';
                                                    $nameParts = explode(' ', $displayName);
                                                    foreach ($nameParts as $p) {
                                                        if (!empty($p)) $initials .= strtoupper($p[0]);
                                                    }
                                                    $initials = substr($initials, 0, 2) ?: '?';
                                                ?>
                                                    <tr class="<?php echo $isDenied ? 'denied-row' : ''; ?>">
                                                        <td>
                                                            <span class="text-muted small">
                                                                <?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="uid-cell <?php echo $isDenied ? 'denied-uid' : ''; ?>">
                                                                <?php echo htmlspecialchars($log['card_uid'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="user-cell">
                                                                <div class="user-avatar <?php echo $isDenied ? 'denied' : ''; ?>">
                                                                    <?php echo $initials; ?>
                                                                </div>
                                                                <div>
                                                                    <?php echo htmlspecialchars($displayName); ?>
                                                                    <?php if (!empty($log['card_type']) && $log['card_type'] == 'visitor'): ?>
                                                                        <span class="badge badge-visitor" style="font-size:8px;">Visitor</span>
                                                                    <?php elseif (!empty($log['card_type']) && $log['card_type'] == 'staff'): ?>
                                                                        <span class="badge badge-staff" style="font-size:8px;">Staff</span>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($log['student_id'])): ?>
                                                                        <div style="font-size: 10px; color: #808090;"><?php echo htmlspecialchars($log['student_id']); ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($log['room_number'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $isEntry ? 'badge-entry' : 'badge-exit'; ?>">
                                                                <i class="fas <?php echo $isEntry ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?> me-1"></i>
                                                                <?php echo $isEntry ? 'Entry' : 'Exit'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($isDenied): ?>
                                                                <span class="badge badge-denied">
                                                                    <i class="fas fa-times-circle me-1"></i> Denied
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge badge-granted">
                                                                    <i class="fas fa-check-circle me-1"></i> Granted
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($accessCount > $perPage): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="pagination-info">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $accessCount); ?> of <?php echo $accessCount; ?> entries
                                    </div>
                                    <?php echo renderPagination($page, $accessCount, $perPage, 'access'); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                FOOTER - SAME AS DASHBOARD
                ============================================================ -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3 no-print">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span>Total: <?php echo $stats['total_alerts']; ?> alerts</span>
                    <span class="mx-2">|</span>
                    <span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i><?php echo $stats['unauthorized_total']; ?> unauthorized</span>
                    <span class="mx-2">|</span>
                    <span class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo $stats['total_access']; ?> access logs</span>
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
