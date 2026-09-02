<?php
/**
 * Tap-and-Go Doorlock - Access Reports
 * PERMANENT STORAGE - NO DELETE OPTION
 * WITH DAY, WEEK, MONTH, YEAR FILTERS
 * WITH SHOW ENTRIES PAGINATION
 * WITH PROFILE PHOTO DISPLAY
 * PURE DARK MODE
 * FIXED: ALL timestamp CHANGED TO created_at
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
// GET FILTERS
// ============================================================
$period = isset($_GET['period']) ? $_GET['period'] : 'day';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// BUILD DATE FILTER BASED ON PERIOD - USING created_at
// ============================================================
$dateCondition = "";
$dateLabel = "";

switch ($period) {
    case 'day':
        $dateCondition = "DATE(al.created_at) = '$dateFilter'";
        $dateLabel = date('F d, Y', strtotime($dateFilter));
        break;
    case 'week':
        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($dateFilter)));
        $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($dateFilter)));
        $dateCondition = "DATE(al.created_at) BETWEEN '$weekStart' AND '$weekEnd'";
        $dateLabel = "Week of " . date('M d', strtotime($weekStart)) . " - " . date('M d, Y', strtotime($weekEnd));
        break;
    case 'month':
        $monthStart = date('Y-m-01', strtotime($dateFilter));
        $monthEnd = date('Y-m-t', strtotime($dateFilter));
        $dateCondition = "DATE(al.created_at) BETWEEN '$monthStart' AND '$monthEnd'";
        $dateLabel = date('F Y', strtotime($dateFilter));
        break;
    case 'year':
        $yearStart = date('Y-01-01', strtotime($dateFilter));
        $yearEnd = date('Y-12-31', strtotime($dateFilter));
        $dateCondition = "DATE(al.created_at) BETWEEN '$yearStart' AND '$yearEnd'";
        $dateLabel = date('Y', strtotime($dateFilter));
        break;
    default:
        $dateCondition = "DATE(al.created_at) = '$dateFilter'";
        $dateLabel = date('F d, Y', strtotime($dateFilter));
}

// ============================================================
// GET TOTAL ACCESS LOGS FOR PAGINATION - PERMANENT
// ============================================================
$countQuery = "
    SELECT COUNT(*) as total
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE $dateCondition
";

if (!empty($statusFilter)) {
    $countQuery .= " AND al.access_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $countQuery .= " AND al.access_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $countQuery .= " AND (
        al.card_uid LIKE '%$searchFilter%' 
        OR u.full_name LIKE '%$searchFilter%'
        OR c.visitor_name LIKE '%$searchFilter%'
    )";
}

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

// ============================================================
// GET ACCESS LOGS - PERMANENT (NO DELETE)
// ============================================================
$logs = [];
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
        u.profile_photo,
        rp.course,
        rp.year_level,
        ru.full_name as resident_visited_name,
        ru.room_number as resident_room
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE $dateCondition
";

if (!empty($statusFilter)) {
    $query .= " AND al.access_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $query .= " AND al.access_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $query .= " AND (
        al.card_uid LIKE '%$searchFilter%' 
        OR u.full_name LIKE '%$searchFilter%'
        OR c.visitor_name LIKE '%$searchFilter%'
    )";
}

$query .= " ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// ============================================================
// GET STATS FOR THE SELECTED PERIOD - PERMANENT
// ============================================================
$stats = [
    'total' => 0,
    'granted' => 0,
    'denied' => 0,
    'entry' => 0,
    'exit' => 0,
    'residents' => 0,
    'visitors' => 0,
    'unique_cards' => 0,
    'unique_residents' => 0
];

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    WHERE $dateCondition
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    WHERE $dateCondition AND al.access_status = 'granted'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['granted'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    WHERE $dateCondition AND al.access_status = 'denied'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['denied'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    WHERE $dateCondition AND al.access_type = 'entry'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['entry'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    WHERE $dateCondition AND al.access_type = 'exit'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['exit'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(DISTINCT al.card_uid) as count 
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    WHERE $dateCondition AND c.card_type = 'resident'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['residents'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(DISTINCT al.card_uid) as count 
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    WHERE $dateCondition AND c.card_type = 'visitor'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['visitors'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(DISTINCT al.card_uid) as count 
    FROM access_logs al
    WHERE $dateCondition
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unique_cards'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(DISTINCT u.user_id) as count 
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE $dateCondition AND u.user_id IS NOT NULL
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unique_residents'] = (int)$row['count'];
}

// ============================================================
// GET CHART DATA - DAILY ACCESS TRENDS - USING created_at
// ============================================================
$chartData = [];
$chartQuery = "
    SELECT 
        DATE(al.created_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN al.access_status = 'granted' THEN 1 ELSE 0 END) as granted,
        SUM(CASE WHEN al.access_status = 'denied' THEN 1 ELSE 0 END) as denied
    FROM access_logs al
    WHERE $dateCondition
    GROUP BY DATE(al.created_at)
    ORDER BY date ASC
";

$result = $conn->query($chartQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $chartData[] = $row;
    }
}

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

// ============================================================
// HELPER FUNCTION: GET PROFILE PHOTO PATH
// ============================================================
function getProfilePhotoPath($photoPath) {
    if (empty($photoPath)) {
        return null;
    }
    
    // Check if path already has 'uploads/'
    if (strpos($photoPath, 'uploads/') === 0) {
        $fullPath = '../../' . $photoPath;
    } else {
        $fullPath = '../../uploads/resident_photos/' . $photoPath;
    }
    
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    return null;
}

// ============================================================
// HELPER FUNCTION: GET INITIALS
// ============================================================
function getInitials($name) {
    if (empty($name)) return '?';
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper($part[0]);
        }
    }
    return substr($initials, 0, 2) ?: '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Reports - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
           DARK STAT CARDS
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
        .stat-number.text-warning { color: #fbbf24 !important; }
        .stat-number.text-success { color: #34d399 !important; }
        
        /* ============================================================
           DARK CARDS
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
           DARK TABLE
           ============================================================ */
        .access-table { font-size: 13px; }
        .access-table th {
            font-weight: 600;
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            background: transparent !important;
        }
        .access-table td {
            vertical-align: middle;
            padding: 8px 12px;
            color: #e0e0e0;
            border-bottom: 1px solid #1a2a4a;
            background: transparent !important;
        }
        .access-table tr {
            background: transparent !important;
        }
        .access-table tr:hover td {
            background: rgba(255,255,255,0.02) !important;
        }
        .access-table .user-cell { display: flex; align-items: center; gap: 10px; }
        .access-table .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
            overflow: hidden;
            background: linear-gradient(135deg, #4a5a8a, #5a3a7a);
        }
        .access-table .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .access-table .user-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 12px;
            font-weight: 700;
            color: white;
        }
        .access-table .uid-cell {
            font-family: monospace;
            font-weight: 600;
            color: #93c5fd;
            background: #1a2a4a;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .access-table .denied-row {
            background-color: #1a0a0a !important;
        }
        .access-table .denied-row:hover td {
            background-color: #2a1a1a !important;
        }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge-granted { background: #065f46 !important; color: #34d399 !important; }
        .badge-denied { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-entry { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-exit { background: #2a2a4a !important; color: #808090 !important; }
        .badge-visitor { background: #1a2a5a !important; color: #93c5fd !important; }
        .badge-resident { background: #065f46 !important; color: #34d399 !important; }
        .badge-staff { background: #4a3a1a !important; color: #fbbf24 !important; }
        
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
           PERIOD SELECTOR - DARK
           ============================================================ */
        .period-selector {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .period-selector .btn-period {
            background: transparent !important;
            border: 1px solid #2a2a4a !important;
            color: #808090 !important;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .period-selector .btn-period:hover {
            border-color: #2a5a9a !important;
            color: #e0e0e0 !important;
        }
        .period-selector .btn-period.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border-color: #1a3a6a !important;
            color: white !important;
        }
        .period-selector .btn-period i { margin-right: 4px; }
        
        /* ============================================================
           CHART CONTAINER
           ============================================================ */
        .chart-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .chart-bar {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            height: 120px;
            padding-top: 10px;
        }
        .chart-bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .chart-bar-item .bar {
            width: 100%;
            min-height: 4px;
            border-radius: 4px 4px 0 0;
            transition: height 0.5s ease;
            position: relative;
        }
        .chart-bar-item .bar.granted { background: #10b981; }
        .chart-bar-item .bar.denied { background: #ef4444; }
        .chart-bar-item .bar-label {
            font-size: 9px;
            color: #808090;
            text-align: center;
            transform: rotate(-45deg);
            white-space: nowrap;
        }
        .chart-bar-item .bar-value {
            font-size: 9px;
            font-weight: 600;
            color: #e0e0e0;
        }
        
        /* ============================================================
           NO DELETE BANNER
           ============================================================ */
        .no-delete-banner {
            background: rgba(16, 185, 129, 0.1) !important;
            border: 1px solid rgba(16, 185, 129, 0.2) !important;
            border-radius: 12px;
            padding: 10px 16px;
            color: #6ee7b7;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .no-delete-banner i {
            font-size: 18px;
            color: #34d399;
        }
        
        /* ============================================================
           PAGINATION WITH SHOW ENTRIES
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
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        .text-primary { color: #93c5fd !important; }
        
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
            .access-table {
                font-size: 11px;
            }
            .access-table .user-cell {
                flex-direction: column;
                align-items: flex-start;
            }
            .chart-bar {
                height: 80px;
            }
            .chart-bar-item .bar-label {
                font-size: 7px;
            }
            .chart-bar-item .bar-value {
                font-size: 7px;
            }
            .period-selector .btn-period {
                font-size: 11px;
                padding: 4px 10px;
            }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ============================================================
                HEADER
                ============================================================ -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-door-open me-2" style="color: #1a3a6a;"></i>
                        Access Reports
                        <span class="badge bg-primary ms-2"><?php echo $stats['total']; ?> logs</span>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator me-1"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- ============================================================
                NO DELETE BANNER
                ============================================================ -->
                <div class="no-delete-banner mb-3">
                    <i class="fas fa-database"></i>
                    <div>
                        <strong>Permanent Storage</strong>
                        <span class="text-muted ms-2">|</span>
                        <span class="text-muted ms-2">Access logs are stored permanently for record keeping. Data cannot be deleted to maintain accurate historical records.</span>
                    </div>
                </div>

                <!-- ============================================================
                STATS CARDS
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-list"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Logs</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['granted']; ?></div>
                                <div class="stat-label">Granted</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #dc2626;"><i class="fas fa-times-circle"></i></div>
                            <div>
                                <div class="stat-number text-danger"><?php echo $stats['denied']; ?></div>
                                <div class="stat-label">Denied</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['entry']; ?></div>
                                <div class="stat-label">Entries</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-sign-out-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['exit']; ?></div>
                                <div class="stat-label">Exits</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['unique_cards']; ?></div>
                                <div class="stat-label">Unique Cards</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                PERIOD SELECTOR & FILTERS
                ============================================================ -->
                <div class="filter-section">
                    <div class="row g-2">
                        <div class="col-md-12 mb-2">
                            <label class="form-label">Period</label>
                            <div class="period-selector">
                                <a href="?period=day&date=<?php echo date('Y-m-d'); ?>" 
                                   class="btn-period <?php echo $period == 'day' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-day"></i> Day
                                </a>
                                <a href="?period=week&date=<?php echo date('Y-m-d'); ?>" 
                                   class="btn-period <?php echo $period == 'week' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-week"></i> Week
                                </a>
                                <a href="?period=month&date=<?php echo date('Y-m-d'); ?>" 
                                   class="btn-period <?php echo $period == 'month' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt"></i> Month
                                </a>
                                <a href="?period=year&date=<?php echo date('Y-m-d'); ?>" 
                                   class="btn-period <?php echo $period == 'year' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar"></i> Year
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <form method="GET" action="" class="row g-2 align-items-end mt-2">
                        <input type="hidden" name="period" value="<?php echo $period; ?>">
                        
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
                            <button type="submit" class="btn btn-filter w-100">
                                <i class="fas fa-filter me-1"></i> Apply
                            </button>
                        </div>
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- ============================================================
                CHART - DAILY ACCESS TRENDS
                ============================================================ -->
                <?php if (!empty($chartData)): ?>
                <div class="chart-container">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 style="color: #e0e0e0; margin: 0;">
                            <i class="fas fa-chart-bar me-2" style="color: #ffd700;"></i>
                            Daily Access Trends - <?php echo $dateLabel; ?>
                        </h6>
                        <span class="text-muted small">
                            <span class="badge badge-granted me-2">Granted</span>
                            <span class="badge badge-denied">Denied</span>
                        </span>
                    </div>
                    <div class="chart-bar">
                        <?php 
                        $maxValue = max(array_column($chartData, 'total'));
                        if ($maxValue < 1) $maxValue = 1;
                        foreach ($chartData as $data): 
                            $total = $data['total'];
                            $granted = $data['granted'];
                            $denied = $data['denied'];
                            $height = max(4, ($total / $maxValue) * 100);
                            $grantedHeight = max(2, ($granted / $maxValue) * 100);
                            $deniedHeight = max(2, ($denied / $maxValue) * 100);
                            $dateDisplay = date('M d', strtotime($data['date']));
                        ?>
                            <div class="chart-bar-item">
                                <div class="bar-value"><?php echo $total; ?></div>
                                <div style="width:100%; display:flex; flex-direction:column; align-items:center; height:<?php echo $height; ?>%; justify-content:flex-end;">
                                    <div class="bar granted" style="height:<?php echo $grantedHeight; ?>%;"></div>
                                    <div class="bar denied" style="height:<?php echo $deniedHeight; ?>%;"></div>
                                </div>
                                <div class="bar-label"><?php echo $dateDisplay; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                ACCESS LOGS TABLE - PERMANENT
                ============================================================ -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Access Logs</h5>
                                <span class="text-muted small">Showing <?php echo count($logs); ?> logs</span>
                                <span class="badge bg-info"><?php echo $dateLabel; ?></span>
                            </div>
                            <div>
                                <span class="badge bg-success me-1">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <?php echo $stats['granted']; ?> Granted
                                </span>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times-circle me-1"></i>
                                    <?php echo $stats['denied']; ?> Denied
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover access-table">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>RFID UID</th>
                                        <th>User</th>
                                        <th>Card Type</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Power</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No access logs found for this period
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): 
                                            $displayName = 'Unknown';
                                            $cardType = $log['card_type'] ?? 'unknown';
                                            $isDenied = $log['access_status'] == 'denied';
                                            $isVisitor = $cardType == 'visitor';
                                            
                                            if ($isVisitor && !empty($log['visitor_name'])) {
                                                $displayName = $log['visitor_name'] . ' (Visitor)';
                                            } elseif (!empty($log['user_name'])) {
                                                $displayName = $log['user_name'];
                                            }
                                            
                                            $roomDisplay = $log['room_number'] ?? 'N/A';
                                            if ($isVisitor && !empty($log['resident_visited_name'])) {
                                                $roomDisplay = 'Visit: ' . $log['resident_visited_name'];
                                            }
                                            
                                            // Get profile photo - FIXED
                                            $photoPath = $log['profile_photo'] ?? null;
                                            $hasPhoto = false;
                                            $photoUrl = '';
                                            
                                            if (!empty($photoPath)) {
                                                // Try different path formats
                                                if (strpos($photoPath, 'uploads/') === 0) {
                                                    $fullPath = '../../' . $photoPath;
                                                } else {
                                                    $fullPath = '../../uploads/resident_photos/' . $photoPath;
                                                }
                                                
                                                // Also try without '../../' prefix
                                                if (!file_exists($fullPath)) {
                                                    $fullPath = '../' . $photoPath;
                                                }
                                                if (!file_exists($fullPath)) {
                                                    $fullPath = $photoPath;
                                                }
                                                
                                                if (file_exists($fullPath)) {
                                                    $hasPhoto = true;
                                                    $photoUrl = $fullPath;
                                                }
                                            }
                                            
                                            $initials = getInitials($displayName);
                                            
                                            $cardTypeBadge = $isVisitor ? 'badge-visitor' : ($cardType == 'staff' ? 'badge-staff' : 'badge-resident');
                                        ?>
                                            <tr class="<?php echo $isDenied ? 'denied-row' : ''; ?>">
                                                <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                                <td><span class="uid-cell <?php echo $isDenied ? 'text-danger' : ''; ?>"><?php echo htmlspecialchars($log['card_uid'] ?? 'N/A'); ?></span></td>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar">
                                                            <?php if ($hasPhoto): ?>
                                                                <img src="<?php echo $photoUrl; ?>" alt="<?php echo htmlspecialchars($displayName); ?>" 
                                                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'no-photo\'>' + '<?php echo $initials; ?>' + '</div>'">
                                                            <?php else: ?>
                                                                <div class="no-photo"><?php echo $initials; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div>
                                                                <?php echo htmlspecialchars($displayName); ?>
                                                                <?php if (!empty($log['student_id'])): ?>
                                                                    <span style="font-size: 10px; color: #808090;">(<?php echo htmlspecialchars($log['student_id']); ?>)</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!empty($log['course'])): ?>
                                                                <div style="font-size: 10px; color: #808090;"><?php echo htmlspecialchars($log['course']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $cardTypeBadge; ?>">
                                                        <?php echo ucfirst($cardType); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $log['access_type'] == 'entry' ? 'badge-entry' : 'badge-exit'; ?>">
                                                        <i class="fas <?php echo $log['access_type'] == 'entry' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?> me-1"></i>
                                                        <?php echo ucfirst($log['access_type'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $isDenied ? 'badge-denied' : 'badge-granted'; ?>">
                                                        <i class="fas <?php echo $isDenied ? 'fa-times-circle' : 'fa-check-circle'; ?> me-1"></i>
                                                        <?php echo ucfirst($log['access_status'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo isset($log['power_source']) && $log['power_source'] == 'main' ? 'badge-entry' : 'badge-exit'; ?>">
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
                        
                        <!-- ============================================================
                        PAGINATION WITH SHOW ENTRIES
                        ============================================================ -->
                        <div class="pagination-container">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="page-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> logs
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
                                                    <a class="page-link" href="?page=1&period=<?php echo $period; ?>&date=<?php echo urlencode($dateFilter); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&period=<?php echo $period; ?>&date=<?php echo urlencode($dateFilter); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                        <a class="page-link" href="?page=<?php echo $i; ?>&period=<?php echo $period; ?>&date=<?php echo urlencode($dateFilter); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                <?php if ($endPage < $totalPages): ?>
                                                    <li class="page-item"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&period=<?php echo $period; ?>&date=<?php echo urlencode($dateFilter); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?>&period=<?php echo $period; ?>&date=<?php echo urlencode($dateFilter); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                SUMMARY
                ============================================================ -->
                <div class="text-center text-muted small mt-2">
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo $stats['total']; ?> access logs recorded
                    <span class="mx-1">|</span>
                    <i class="fas fa-check-circle me-1 text-success"></i>
                    <?php echo $stats['granted']; ?> granted
                    <span class="mx-1">|</span>
                    <i class="fas fa-times-circle me-1 text-danger"></i>
                    <?php echo $stats['denied']; ?> denied
                    <span class="mx-1">|</span>
                    <i class="fas fa-id-card me-1 text-primary"></i>
                    <?php echo $stats['unique_cards']; ?> unique cards
                    <span class="mx-1">|</span>
                    <i class="fas fa-users me-1"></i>
                    <?php echo $stats['residents']; ?> residents, <?php echo $stats['visitors']; ?> visitors
                    <span class="mx-1">|</span>
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php echo $dateLabel; ?>
                </div>
            </main>
        </div>
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
        // UPDATE TIME
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
