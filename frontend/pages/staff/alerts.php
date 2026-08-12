<?php
/**
 * Tap-and-Go Doorlock - Staff Alerts
 * VIEW ONLY - WITH REAL-TIME NOTIFICATIONS AND WARNING INDICATORS
 * PURE DARK MODE - WITH SHOW ENTRIES
 */

session_start();

require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// Check authentication - Staff only
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: ../login.php');
    exit();
}

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

// ============================================================
// GET FILTERS
// ============================================================
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET TOTAL ALERTS FOR PAGINATION
// ============================================================
$countQuery = "
    SELECT COUNT(*) as total
    FROM alert_logs alog
    LEFT JOIN rfid_cards c ON alog.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $countQuery .= " AND alog.delivery_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $countQuery .= " AND alog.alert_type = '$typeFilter'";
}
if (!empty($dateFilter)) {
    $countQuery .= " AND DATE(alog.timestamp) = '$dateFilter'";
}
if (!empty($searchFilter)) {
    $countQuery .= " AND (
        alog.card_uid LIKE '%$searchFilter%' 
        OR alog.user_name LIKE '%$searchFilter%'
        OR alog.reason LIKE '%$searchFilter%'
    )";
}

$countResult = $conn->query($countQuery);
$totalAlerts = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalAlerts = (int)$row['total'];
}

$totalPages = ceil($totalAlerts / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// ============================================================
// GET ALERTS
// ============================================================
$alerts = [];
$query = "
    SELECT 
        alog.*,
        c.card_type as rfid_card_type,
        c.visitor_name,
        c.resident_visited,
        u.full_name as resident_name,
        u.room_number,
        ru.full_name as resident_visited_name
    FROM alert_logs alog
    LEFT JOIN rfid_cards c ON alog.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN users ru ON c.resident_visited = ru.user_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $query .= " AND alog.delivery_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $query .= " AND alog.alert_type = '$typeFilter'";
}
if (!empty($dateFilter)) {
    $query .= " AND DATE(alog.timestamp) = '$dateFilter'";
}
if (!empty($searchFilter)) {
    $query .= " AND (
        alog.card_uid LIKE '%$searchFilter%' 
        OR alog.user_name LIKE '%$searchFilter%'
        OR alog.reason LIKE '%$searchFilter%'
    )";
}

$query .= " ORDER BY 
    CASE WHEN alog.delivery_status = 'pending' THEN 0 ELSE 1 END,
    alog.timestamp DESC 
    LIMIT $perPage OFFSET $offset";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $alerts[] = $row;
    }
}

// ============================================================
// GET STATS
// ============================================================
$stats = [
    'total' => 0,
    'pending' => 0,
    'resolved' => 0,
    'unauthorized' => 0,
    'today' => 0,
    'critical' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'resolved'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['resolved'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE alert_type = 'unauthorized'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unauthorized'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE DATE(timestamp) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['today'] = (int)$row['count'];
}

// Critical alerts = pending unauthorized alerts
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM alert_logs 
    WHERE delivery_status = 'pending' 
    AND alert_type = 'unauthorized'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['critical'] = (int)$row['count'];
}

// ============================================================
// MARK ALERT AS RESOLVED (Staff can also resolve alerts)
// ============================================================
if (isset($_GET['resolve']) && !empty($_GET['resolve'])) {
    $alert_id = (int)$_GET['resolve'];
    $stmt = $conn->prepare("UPDATE alert_logs SET delivery_status = 'resolved', resolved_at = NOW() WHERE alert_id = ?");
    $stmt->bind_param("i", $alert_id);
    if ($stmt->execute()) {
        $success = "✅ Alert resolved successfully!";
        logStaffAudit($_SESSION['staff_id'], 'Resolve Alert', "Resolved alert ID: $alert_id");
    } else {
        $error = "Failed to resolve alert.";
    }
    $stmt->close();
}

// ============================================================
// MARK ALL AS RESOLVED (Staff can resolve all)
// ============================================================
if (isset($_GET['resolve_all'])) {
    $stmt = $conn->prepare("UPDATE alert_logs SET delivery_status = 'resolved', resolved_at = NOW() WHERE delivery_status = 'pending'");
    if ($stmt->execute()) {
        $count = $stmt->affected_rows;
        $success = "✅ $count alerts resolved successfully!";
        logStaffAudit($_SESSION['staff_id'], 'Resolve All Alerts', "Resolved $count alerts");
    } else {
        $error = "Failed to resolve all alerts.";
    }
    $stmt->close();
}

// ============================================================
// DELETE ALERT (Staff can delete alerts)
// ============================================================
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $alert_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM alert_logs WHERE alert_id = ?");
    $stmt->bind_param("i", $alert_id);
    if ($stmt->execute()) {
        $success = "✅ Alert deleted successfully!";
        logStaffAudit($_SESSION['staff_id'], 'Delete Alert', "Deleted alert ID: $alert_id");
    } else {
        $error = "Failed to delete alert.";
    }
    $stmt->close();
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
    <title>Staff Alerts - Tap-and-Go Doorlock</title>
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
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; flex-shrink: 0; }
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        .stat-number.text-danger { color: #f87171 !important; }
        .stat-number.text-warning { color: #fbbf24 !important; }
        
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
           DARK CARD
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
        .card-body { background: #111827 !important; padding: 20px; }
        
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
        .filter-section .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(26,58,106,0.3); }
        
        /* ============================================================
           DARK ALERT ITEMS
           ============================================================ */
        .alert-item {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-left: 4px solid #ef4444;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
            position: relative;
        }
        .alert-item:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .alert-item.resolved {
            border-left-color: #10b981;
            opacity: 0.7;
        }
        .alert-item .alert-uid {
            font-family: monospace;
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 14px;
        }
        .alert-item .alert-reason {
            font-size: 13px;
            color: #b0b0c0 !important;
        }
        .alert-item .alert-meta {
            font-size: 12px;
            color: #808090 !important;
        }
        .alert-item .alert-user {
            font-weight: 600;
            color: #93c5fd !important;
        }
        .alert-item .badge-pending { background: #4a3a1a !important; color: #fbbf24 !important; }
        .alert-item .badge-resolved { background: #065f46 !important; color: #34d399 !important; }
        .alert-item .badge-unauthorized { background: #7a2a2a !important; color: #f87171 !important; }
        .alert-item .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .alert-item .badge-info { background: #1a3a6a !important; color: #93c5fd !important; }
        .alert-item .badge.bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .alert-item .badge.bg-danger.pulse-badge { background: #7a2a2a !important; color: #f87171 !important; }
        
        /* ============================================================
           VIEW ONLY BADGE
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
           DARK BUTTONS
           ============================================================ */
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
        .btn-delete {
            background: #7a2a2a !important;
            color: #f87171 !important;
            border: none !important;
            border-radius: 8px;
            padding: 5px 15px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-delete:hover { background: #8a3a3a !important; color: #fca5a5 !important; }
        .btn-success {
            background: #065f46 !important;
            color: #34d399 !important;
            border: none !important;
        }
        .btn-success:hover { background: #0a7a5a !important; color: #6ee7b7 !important; }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           DARK WARNING BAR
           ============================================================ */
        .warning-bar {
            background: #2a1a1a !important;
            border: 1px solid #5a2a2a !important;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }
        .warning-bar.danger {
            background: #3a1a1a !important;
            border-color: #7a2a2a !important;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .warning-bar .warning-icon { font-size: 24px; margin-right: 10px; }
        .warning-bar .warning-text {
            font-weight: 600;
            color: #f87171 !important;
        }
        .warning-bar .warning-count {
            background: #ef4444;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
        }
        .warning-bar .text-muted { color: #808090 !important; }
        .warning-bar .btn-danger {
            background: #7a2a2a !important;
            color: #f87171 !important;
            border: none !important;
        }
        .warning-bar .btn-danger:hover { background: #8a3a3a !important; color: #fca5a5 !important; }
        .warning-bar .btn-warning {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            border: none !important;
        }
        .warning-bar .btn-warning:hover { background: #5a4a2a !important; color: #fcd34d !important; }
        
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
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        .text-dark { color: #b0b0c0 !important; }
        
        /* ============================================================
           LIVE INDICATOR
           ============================================================ */
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            margin-right: 8px;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        .badge.bg-success { background: #065f46 !important; color: #34d399 !important; }
        .badge.bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge.bg-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge.bg-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge.bg-info { background: #1a3a6a !important; color: #93c5fd !important; }
        
        /* ============================================================
           TOAST NOTIFICATIONS
           ============================================================ */
        .toast-container {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 9999;
        }
        .toast-notification {
            background: #111827 !important;
            color: #e0e0e0 !important;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            animation: slideInRight 0.5s ease;
            max-width: 350px;
            border-left: 4px solid #ef4444;
            border: 1px solid #1a2a4a;
        }
        .toast-notification.success { border-left-color: #10b981; }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .toast-notification .toast-title { font-weight: 600; font-size: 14px; color: #e0e0e0; }
        .toast-notification .toast-body { font-size: 12px; color: #b0b0c0; margin-top: 4px; }
        .toast-notification .toast-time { font-size: 10px; color: #606070; margin-top: 4px; }
        
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
            .alert-item .row {
                flex-direction: column;
                gap: 10px;
            }
            .alert-item .col-md-7, .alert-item .col-md-5 {
                width: 100%;
            }
            .alert-item .col-md-5.text-end {
                text-align: left !important;
            }
            .alert-item .d-flex.gap-2.justify-content-end {
                justify-content: flex-start !important;
            }
            .alert-header-actions {
                flex-direction: column;
                align-items: flex-start !important;
            }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <!-- ===== NAVBAR ===== -->
    <?php include __DIR__ . '/../../includes/navbar_staff.php'; ?>
    
    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- ===== SIDEBAR ===== -->
            <?php include __DIR__ . '/includes/sidebar_staff.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-eye me-2" style="color: #fbbf24;"></i>
                        <i class="fas fa-bell me-1" style="color: #1a3a6a;"></i>
                        Alerts
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge bg-danger ms-2 pulse-badge">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?php echo $stats['pending']; ?> new
                            </span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <span class="view-only-badge me-2">
                            <i class="fas fa-eye me-1"></i> View Only
                        </span>
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

                <!-- ============================================================
                WARNING NOTIFICATION BAR
                ============================================================ -->
                <?php if ($stats['critical'] > 0): ?>
                <div class="warning-bar danger">
                    <div class="d-flex align-items-center">
                        <span class="warning-icon pulse-red" style="animation: pulseRed 1.5s infinite;">🚨</span>
                        <div>
                            <span class="warning-text">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                CRITICAL ALERT!
                            </span>
                            <span class="text-muted ms-2">
                                <?php echo $stats['critical']; ?> unauthorized access <?php echo $stats['critical'] > 1 ? 'attempts' : 'attempt'; ?> detected
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="warning-count"><?php echo $stats['critical']; ?></span>
                        <span class="text-muted ms-2">pending</span>
                        <a href="#alertsList" class="btn btn-sm btn-danger ms-2">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                    </div>
                </div>
                <?php elseif ($stats['pending'] > 0): ?>
                <div class="warning-bar">
                    <div class="d-flex align-items-center">
                        <span class="warning-icon" style="font-size:24px; margin-right:10px;">⚠️</span>
                        <div>
                            <span class="warning-text">
                                <i class="fas fa-bell me-1"></i>
                                New Alerts
                            </span>
                            <span class="text-muted ms-2">
                                <?php echo $stats['pending']; ?> pending alert<?php echo $stats['pending'] > 1 ? 's' : ''; ?> need your attention
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="warning-count"><?php echo $stats['pending']; ?></span>
                        <span class="text-muted ms-2">pending</span>
                        <a href="#alertsList" class="btn btn-sm btn-warning ms-2">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                STATS CARDS
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-list"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Alerts</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
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
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['critical'] > 0 ? 'text-danger' : ''; ?>">
                                    <?php echo $stats['critical']; ?>
                                </div>
                                <div class="stat-label">Critical</div>
                            </div>
                            <?php if ($stats['critical'] > 0): ?>
                                <span class="badge bg-danger pulse-badge">🚨</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-bell"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['unauthorized']; ?></div>
                                <div class="stat-label">Unauthorized</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-calendar-day"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['today']; ?></div>
                                <div class="stat-label">Today</div>
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
                                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="resolved" <?php echo $statusFilter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">All</option>
                                <option value="unauthorized" <?php echo $typeFilter == 'unauthorized' ? 'selected' : ''; ?>>Unauthorized</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="UID, Name, or Reason" value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="col-md-3">
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
                ALERTS LIST
                ============================================================ -->
                <div class="card" id="alertsList">
                    <div class="card-header">
                        <div class="alert-header-actions d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Alert Logs</h5>
                                <span class="text-muted small">Showing <?php echo count($alerts); ?> alerts</span>
                                <?php if ($stats['pending'] > 0): ?>
                                    <span class="badge bg-danger pulse-badge">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        <?php echo $stats['pending']; ?> pending
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if ($stats['pending'] > 0): ?>
                                    <a href="?resolve_all=1" class="btn btn-sm btn-success" onclick="return confirm('Resolve all pending alerts?')">
                                        <i class="fas fa-check-double me-1"></i> Resolve All
                                    </a>
                                <?php endif; ?>
                                <a href="alerts.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-sync-alt me-1"></i> Refresh
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($alerts)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-check-circle fa-3x d-block mb-3 text-success"></i>
                                <h5>All Clear! ✅</h5>
                                <p>No alerts found. Your system is secure.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($alerts as $alert): 
                                $isPending = $alert['delivery_status'] == 'pending';
                                $isResolved = $alert['delivery_status'] == 'resolved';
                                $isCritical = $isPending && $alert['alert_type'] == 'unauthorized';
                                $displayName = !empty($alert['user_name']) ? $alert['user_name'] : 'Unknown';
                                $cardType = !empty($alert['card_type']) ? $alert['card_type'] : 'unknown';
                                
                                if ($cardType == 'visitor' && !empty($alert['visitor_name'])) {
                                    $displayName = $alert['visitor_name'] . ' (Visitor)';
                                }
                                
                                $roomDisplay = $alert['room_number'] ?? 'N/A';
                                if ($cardType == 'visitor' && !empty($alert['resident_visited_name'])) {
                                    $roomDisplay = 'Visit: ' . $alert['resident_visited_name'];
                                }
                            ?>
                            <div class="alert-item <?php echo $isResolved ? 'resolved' : ''; ?> <?php echo $isCritical ? 'border-danger' : ''; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <div class="d-flex align-items-start">
                                            <?php if ($isCritical): ?>
                                                <span class="pulse-red me-2" style="font-size: 24px; animation: pulseRed 1.5s infinite;">🚨</span>
                                            <?php elseif ($isPending): ?>
                                                <span class="pulse-red me-2" style="font-size: 20px; animation: pulseRed 1.5s infinite;">🔴</span>
                                            <?php else: ?>
                                                <span class="me-2" style="font-size: 20px;">✅</span>
                                            <?php endif; ?>
                                            <div>
                                                <div class="d-flex align-items-center flex-wrap gap-2">
                                                    <span class="alert-uid"><?php echo htmlspecialchars($alert['card_uid']); ?></span>
                                                    <?php if ($isCritical): ?>
                                                        <span class="badge bg-danger pulse-badge">CRITICAL</span>
                                                    <?php endif; ?>
                                                    <span class="badge <?php echo $isPending ? 'badge-pending' : 'badge-resolved'; ?>">
                                                        <?php echo ucfirst($alert['delivery_status']); ?>
                                                    </span>
                                                    <span class="badge badge-unauthorized">
                                                        <?php echo ucfirst($alert['alert_type']); ?>
                                                    </span>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($cardType); ?></span>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-microchip me-1"></i> ESP32
                                                    </span>
                                                </div>
                                                <div class="alert-reason mt-1">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <?php echo htmlspecialchars($alert['reason'] ?? 'Unauthorized access attempt'); ?>
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
                                                    <?php if (!empty($alert['access_type'])): ?>
                                                        <span class="mx-2">|</span>
                                                        <span class="badge <?php echo $alert['access_type'] == 'entry' ? 'badge-entry' : 'badge-exit'; ?>">
                                                            <?php echo ucfirst($alert['access_type']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-5 text-end">
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            <?php if ($isPending): ?>
                                                <a href="?resolve=<?php echo $alert['alert_id']; ?>" class="btn btn-resolve btn-sm">
                                                    <i class="fas fa-check me-1"></i> Resolve
                                                </a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $alert['alert_id']; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Delete this alert?')">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </a>
                                            <a href="logs.php?search=<?php echo urlencode($alert['card_uid']); ?>" class="btn btn-sm btn-outline-secondary">
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
                PAGINATION WITH SHOW ENTRIES
                ============================================================ -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalAlerts); ?> of <?php echo $totalAlerts; ?> alerts
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
                                            <a class="page-link" href="?page=1<?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <li class="page-item"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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

                <div class="text-center text-muted small mt-3">
                    <i class="fas fa-eye me-1"></i> View Only Access
                    <span class="mx-2">|</span>
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo $stats['total']; ?> alerts recorded
                    <?php if ($stats['pending'] > 0): ?>
                        <span class="text-warning ms-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo $stats['pending']; ?> pending alerts
                        </span>
                    <?php endif; ?>
                    <?php if ($stats['critical'] > 0): ?>
                        <span class="text-danger ms-2">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            <?php echo $stats['critical']; ?> critical alerts!
                        </span>
                    <?php endif; ?>
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
        // SHOW TOAST NOTIFICATION
        // ============================================================
        function showToast(title, message, type = 'warning') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            if (type === 'success') {
                toast.classList.add('success');
            }
            
            const icon = type === 'success' ? '✅' : '🚨';
            const time = new Date().toLocaleTimeString();
            
            toast.innerHTML = `
                <div class="toast-title">${icon} ${title}</div>
                <div class="toast-body">${message}</div>
                <div class="toast-time">${time}</div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 5000);
        }

        // ============================================================
        // CHECK FOR NEW ALERTS
        // ============================================================
        function checkNewAlerts() {
            fetch('../api/check_alerts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.new_alerts > 0) {
                        showToast(
                            'New Alert Detected!',
                            `${data.new_alerts} new unauthorized access alert${data.new_alerts > 1 ? 's' : ''}`,
                            'warning'
                        );
                        
                        const badge = document.querySelector('.badge.bg-danger.ms-2');
                        if (badge) {
                            badge.textContent = data.pending + ' new';
                        }
                        
                        const pendingStat = document.querySelector('.stat-number.text-warning');
                        if (pendingStat) {
                            pendingStat.textContent = data.pending;
                        }
                    }
                })
                .catch(err => {});
        }

        // ============================================================
        // UPDATE LAST UPDATE TIME
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

        // ============================================================
        // AUTO REFRESH (Every 10 seconds)
        // ============================================================
        setInterval(() => {
            updateLastUpdateTime();
            checkNewAlerts();
        }, 10000);

        // ============================================================
        // PLAY SOUND FOR NEW ALERT
        // ============================================================
        function playAlertSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'square';
                gainNode.gain.value = 0.3;
                
                oscillator.start();
                setTimeout(() => {
                    oscillator.stop();
                }, 200);
                
                setTimeout(() => {
                    const osc2 = audioContext.createOscillator();
                    const gain2 = audioContext.createGain();
                    osc2.connect(gain2);
                    gain2.connect(audioContext.destination);
                    osc2.frequency.value = 1000;
                    osc2.type = 'square';
                    gain2.gain.value = 0.3;
                    osc2.start();
                    setTimeout(() => {
                        osc2.stop();
                    }, 200);
                }, 300);
            } catch(e) {}
        }

        // ============================================================
        // INITIAL CHECK
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            updateLastUpdateTime();
            
            <?php if ($stats['pending'] > 0): ?>
                setTimeout(() => {
                    showToast(
                        '⚠️ Pending Alerts',
                        'You have <?php echo $stats['pending']; ?> pending alert<?php echo $stats['pending'] > 1 ? 's' : ''; ?> that need your attention.',
                        'warning'
                    );
                    <?php if ($stats['critical'] > 0): ?>
                        playAlertSound();
                    <?php endif; ?>
                }, 1000);
            <?php endif; ?>
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