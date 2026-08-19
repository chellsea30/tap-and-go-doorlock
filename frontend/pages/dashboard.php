<?php
/**
 * Tap-and-Go Doorlock - Dashboard
 * COMPLETE WITH UNAUTHORIZED ALERT - USING alert_logs
 * PURE DARK MODE - No white backgrounds
 * WITHOUT RECENT ACCESS LOGS
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}
// Include header
include '../includes/header.php'; 

$conn = getDBConnection();

// ============================================================
// GET DASHBOARD STATISTICS
// ============================================================
$stats = [
    'total_residents' => 0,
    'active_cards' => 0,
    'today_access' => 0,
    'pending_alerts' => 0,
    'current_occupancy' => 0,
    'total_visitors' => 0,
    'total_announcements' => 0,
    'total_rooms' => 5,
    'max_per_room' => 7,
    'unauthorized_today' => 0,
    'critical_alerts' => 0
];

// Total active residents
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_residents'] = (int)$row['count'];
}

// Active cards
$result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['active_cards'] = (int)$row['count'];
}

// Today's access
$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['today_access'] = (int)$row['count'];
}

// Pending alerts
$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_alerts'] = (int)$row['count'];
}

// Critical alerts (pending unauthorized)
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM alert_logs 
    WHERE delivery_status = 'pending' 
    AND alert_type = 'unauthorized'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['critical_alerts'] = (int)$row['count'];
}

// Unauthorized today - FROM access_logs (denied access ngayong araw)
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs 
    WHERE DATE(timestamp) = CURDATE() 
    AND access_status = 'denied'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unauthorized_today'] = (int)$row['count'];
}

// Current occupancy
$result = $conn->query("
    SELECT COUNT(DISTINCT user_id) as count 
    FROM access_logs 
    WHERE user_id IS NOT NULL 
    AND access_type = 'entry' 
    AND timestamp = (
        SELECT MAX(timestamp) 
        FROM access_logs al2 
        WHERE al2.user_id = access_logs.user_id
    )
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['current_occupancy'] = (int)$row['count'];
}

// Total visitors today
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_timestamp) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_visitors'] = (int)$row['count'];
}

// Total announcements
$result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_announcements'] = (int)$row['count'];
}

// ============================================================
// GET LATEST UNAUTHORIZED ACCESS - FROM alert_logs
// ============================================================
$latestUnauthorized = null;
$result = $conn->query("
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
    WHERE alog.alert_type = 'unauthorized'
    AND alog.delivery_status = 'pending'
    ORDER BY alog.timestamp DESC 
    LIMIT 1
");
if ($result && $row = $result->fetch_assoc()) {
    $displayName = !empty($row['user_name']) ? $row['user_name'] : 'Unknown';
    if ($row['rfid_card_type'] == 'visitor' && !empty($row['visitor_name'])) {
        $displayName = $row['visitor_name'] . ' (Visitor)';
    }
    if (empty($displayName) || $displayName == 'Unknown') {
        $displayName = 'Unknown Card';
    }
    $row['display_name'] = $displayName;
    $row['card_uid'] = $row['card_uid'] ?? 'N/A';
    $row['access_type'] = $row['access_type'] ?? 'entry';
    $row['timestamp'] = $row['timestamp'] ?? date('Y-m-d H:i:s');
    $latestUnauthorized = $row;
}

// ============================================================
// GET LATEST ALERTS
// ============================================================
$latestAlerts = [];
$result = $conn->query("
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
    WHERE alog.delivery_status = 'pending'
    ORDER BY alog.timestamp DESC 
    LIMIT 5
");
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
        $latestAlerts[] = $row;
    }
}

// ============================================================
// GET ROOM OCCUPANCY DATA (Rooms 1-5)
// ============================================================
$roomData = [];
for ($i = 1; $i <= 5; $i++) {
    $roomData[$i] = [
        'room_number' => $i,
        'occupants' => [],
        'count' => 0,
        'is_full' => false
    ];
    
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.student_id, rp.course, rp.year_level,
               al.timestamp as last_entry, al.card_uid
        FROM users u
        LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
        LEFT JOIN access_logs al ON u.user_id = al.user_id AND al.access_type = 'entry'
        WHERE u.room_number = ? 
        AND u.status = 'active'
        AND al.timestamp = (
            SELECT MAX(timestamp) 
            FROM access_logs al2 
            WHERE al2.user_id = u.user_id
        )
        ORDER BY u.full_name
    ");
    $stmt->bind_param("i", $i);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $roomData[$i]['occupants'][] = $row;
        $roomData[$i]['count']++;
    }
    $stmt->close();
    
    $roomData[$i]['is_full'] = $roomData[$i]['count'] >= 7;
}

// ============================================================
// GET RECENT ANNOUNCEMENTS
// ============================================================
$announcements = [];
$result = $conn->query("
    SELECT a.*, u.full_name as admin_name 
    FROM announcements a
    LEFT JOIN admin_users u ON a.admin_id = u.admin_id
    WHERE a.is_active = 1
    ORDER BY a.priority = 'high' DESC, a.created_at DESC
    LIMIT 3
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$showAlert = $latestUnauthorized !== null && $stats['critical_alerts'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/master.css">

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
            padding-top: 70px !important; /* FIX: Add padding for fixed navbar */
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
        
        /* If navbar is fixed-top, ensure content doesn't hide behind it */
        .navbar.fixed-top + .container-fluid,
        .navbar.fixed-top ~ .container-fluid {
            padding-top: 20px !important;
        }
        
        /* ============================================================
           DARK NAVBAR OVERRIDE
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
           DARK SIDEBAR
           ============================================================ */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important; /* FIX: Add padding for fixed navbar */
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
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-header {
            background: #111827 !important;
            border-bottom: 1px solid #1a2a4a !important;
        }
        .card-header h5 { color: #e0e0e0 !important; }
        .card-body { background: #111827 !important; }
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white; flex-shrink: 0;
        }
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        .stat-card .text-danger { color: #f87171 !important; }
        .stat-card .text-success { color: #34d399 !important; }
        
        /* ============================================================
           DARK ROOM CARDS
           ============================================================ */
        .room-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            border-left: 4px solid #10b981;
            transition: all 0.3s ease;
            height: 100%;
        }
        .room-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5); }
        .room-card .room-title {
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 18px;
            margin-bottom: 2px;
        }
        .room-card .room-capacity { font-size: 12px; color: #808090; }
        .room-card .room-count { font-size: 24px; font-weight: 700; }
        .room-card .room-count.full { color: #f87171; }
        .room-card .room-count.available { color: #34d399; }
        .room-card .room-count.partial { color: #fbbf24; }
        .room-card .occupant-item {
            padding: 4px 8px;
            margin: 2px 0;
            background: #1a2a4a !important;
            border-radius: 6px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #e0e0e0;
        }
        .room-card .room-empty { color: #606070; font-size: 13px; text-align: center; padding: 10px 0; }
        .room-card .room-full-badge {
            background: #7a2a2a;
            color: #f87171;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        
        /* ============================================================
           DARK ALERT ITEMS
           ============================================================ */
        .alert-item {
            background: #111827 !important;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-left: 4px solid #f59e0b;
            box-shadow: 0 1px 5px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        .alert-item:hover { transform: translateX(4px); box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .alert-item.resolved { border-left-color: #10b981; opacity: 0.7; }
        .alert-item.critical {
            border-left-color: #ef4444;
            background: #1a0a0a !important;
        }
        .alert-item .alert-uid { font-family: monospace; font-weight: 700; color: #93c5fd; font-size: 14px; }
        .alert-item .alert-reason { font-size: 13px; color: #b0b0c0; }
        .alert-item .alert-meta { font-size: 12px; color: #606070; }
        .alert-item .alert-user { font-weight: 600; color: #93c5fd; }
        .alert-item .fw-bold { color: #e0e0e0 !important; }
        
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
           DARK ANNOUNCEMENTS
           ============================================================ */
        .announcement-item {
            padding: 10px 0;
            border-bottom: 1px solid #1a2a4a;
        }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-item .title { font-weight: 600; color: #e0e0e0; }
        .announcement-item .content { font-size: 13px; color: #b0b0c0; }
        .announcement-item .meta { font-size: 11px; color: #606070; }
        
        /* ============================================================
           DARK WARNING BAR
           ============================================================ */
        .warning-bar {
            background: #1a0a0a !important;
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
            background: #2a0a0a !important;
            border-color: #7a2a2a !important;
        }
        .warning-bar .warning-text { font-weight: 600; color: #f87171; }
        .warning-bar .warning-count {
            background: #ef4444;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
        }
        .warning-bar .text-muted { color: #808090 !important; }
        
        /* ============================================================
           ALERT CARD
           ============================================================ */
        .alert-card {
            animation: slideDown 0.5s ease;
            border-left: 4px solid #ef4444 !important;
            border-color: #5a2a2a !important;
        }
        .alert-card .card-header {
            background: #2a0a0a !important;
            border-bottom: 1px solid #5a2a2a !important;
        }
        .alert-card .card-header h5 { color: #f87171 !important; }
        .alert-card .card-body { background: #1a0a0a !important; }
        .alert-card .text-danger { color: #f87171 !important; }
        .alert-card .text-muted { color: #808090 !important; }
        .alert-card .bg-light { background: #1a2a4a !important; color: #93c5fd !important; }
        .alert-card code { color: #93c5fd !important; background: #1a2a4a !important; padding: 2px 6px; border-radius: 4px; }
        
        /* ============================================================
           TOAST NOTIFICATIONS
           ============================================================ */
        .toast-container {
            position: fixed;
            top: 80px;
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
        .toast-notification .toast-title { font-weight: 600; font-size: 14px; color: #e0e0e0; }
        .toast-notification .toast-body { font-size: 12px; color: #b0b0c0; margin-top: 4px; }
        .toast-notification .toast-time { font-size: 10px; color: #606070; margin-top: 4px; }
        
        /* ============================================================
           ANIMATIONS
           ============================================================ */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulseBadge {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        @keyframes pulseRed {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        .pulse-badge { animation: pulseBadge 1s infinite; display: inline-block; }
        .pulse-red { animation: pulseRed 1.5s infinite; display: inline-block; }
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
        }
        
        /* ============================================================
           BORDER & DIVIDERS
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        .border { border-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge-granted { background: #065f46 !important; color: #34d399 !important; }
        .badge-denied { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-entry { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-exit { background: #2a2a4a !important; color: #808090 !important; }
        .badge-room { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-priority-high { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-priority-medium { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-priority-low { background: #065f46 !important; color: #34d399 !important; }
        .badge-visitor { background: #1a2a5a !important; color: #93c5fd !important; }
        .badge-resident { background: #065f46 !important; color: #34d399 !important; }
        .badge-staff { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-pending { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-resolved { background: #065f46 !important; color: #34d399 !important; }
        .badge-unauthorized { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-light { background: #2a2a4a !important; color: #b0b0c0 !important; }
        
        /* ============================================================
           MISC
           ============================================================ */
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-primary { color: #93c5fd !important; }
        .bg-light { background: #1a2a4a !important; }
        .bg-success { background: #065f46 !important; color: #34d399 !important; }
        .bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .bg-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .bg-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .bg-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 { color: #e0e0e0 !important; }
        .fw-bold { color: #e0e0e0 !important; }
        .fw-medium { color: #e0e0e0 !important; }
        
        a { color: #93c5fd !important; text-decoration: none; }
        a:hover { color: #bfdbfe !important; }
        
        .status-dot.inside { background: #34d399 !important; }
        
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
            
            .room-card { padding: 15px; }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            
            .toast-container {
                top: 70px;
                right: 10px;
                left: 10px;
            }
            .toast-notification {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" style="color:#e0e0e0 !important;">
                        Dashboard
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
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- ============================================================
                WARNING NOTIFICATION BAR
                ============================================================ -->
                <?php if ($stats['critical_alerts'] > 0): ?>
                <div class="warning-bar danger">
                    <div class="d-flex align-items-center">
                        <span class="warning-icon pulse-red" style="font-size:24px; margin-right:10px;">🚨</span>
                        <div>
                            <span class="warning-text">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                CRITICAL ALERT!
                            </span>
                            <span class="text-muted ms-2">
                                <?php echo $stats['critical_alerts']; ?> unauthorized access <?php echo $stats['critical_alerts'] > 1 ? 'attempts' : 'attempt'; ?> detected
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="warning-count"><?php echo $stats['critical_alerts']; ?></span>
                        <span class="text-muted ms-2">pending</span>
                        <a href="alerts.php" class="btn btn-sm btn-danger ms-2">
                            <i class="fas fa-eye me-1"></i> View Alerts
                        </a>
                    </div>
                </div>
                <?php elseif ($stats['pending_alerts'] > 0): ?>
                <div class="warning-bar">
                    <div class="d-flex align-items-center">
                        <span class="warning-icon" style="font-size:24px; margin-right:10px;">⚠️</span>
                        <div>
                            <span class="warning-text">
                                <i class="fas fa-bell me-1"></i>
                                New Alerts
                            </span>
                            <span class="text-muted ms-2">
                                <?php echo $stats['pending_alerts']; ?> pending alert<?php echo $stats['pending_alerts'] > 1 ? 's' : ''; ?> need your attention
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="warning-count"><?php echo $stats['pending_alerts']; ?></span>
                        <span class="text-muted ms-2">pending</span>
                        <a href="alerts.php" class="btn btn-sm btn-warning ms-2">
                            <i class="fas fa-eye me-1"></i> View Alerts
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                UNAUTHORIZED ACCESS ALERT CARD
                ============================================================ -->
                <?php if ($showAlert && $latestUnauthorized !== null): ?>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card alert-card border-danger">
                            <div class="card-header bg-danger text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <span class="pulse-red me-2">🔴</span>
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        UNAUTHORIZED ACCESS DETECTED!
                                    </h5>
                                    <div>
                                        <span class="badge bg-light text-danger me-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('h:i A', strtotime($latestUnauthorized['timestamp'])); ?>
                                        </span>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            <?php echo $stats['unauthorized_today']; ?> attempt(s) today
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3" style="font-size: 40px;">
                                                <i class="fas fa-user-slash text-danger"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1 text-danger">
                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                    Unauthorized Attempt
                                                </h5>
                                                <p class="mb-1">
                                                    <span class="fw-bold">Card UID:</span>
                                                    <code class="bg-light p-1 rounded"><?php echo htmlspecialchars($latestUnauthorized['card_uid'] ?? 'N/A'); ?></code>
                                                    <span class="mx-2">|</span>
                                                    <span class="fw-bold">Type:</span>
                                                    <span class="badge badge-denied">
                                                        <i class="fas fa-times-circle me-1"></i>
                                                        Denied
                                                    </span>
                                                    <span class="mx-2">|</span>
                                                    <span class="fw-bold">Access:</span>
                                                    <span class="badge badge-entry"><?php echo ucfirst($latestUnauthorized['access_type'] ?? 'N/A'); ?></span>
                                                </p>
                                                <p class="mb-0 text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('F d, Y h:i A', strtotime($latestUnauthorized['timestamp'])); ?>
                                                    <?php if (!empty($latestUnauthorized['display_name'])): ?>
                                                        <span class="mx-2">|</span>
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($latestUnauthorized['display_name']); ?>
                                                        (Attempted)
                                                    <?php endif; ?>
                                                </p>
                                                <p class="mb-0 text-danger small mt-1">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <?php echo htmlspecialchars($latestUnauthorized['reason'] ?? 'Unauthorized card was denied access. Security alert triggered.'); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            <button class="btn btn-sm btn-outline-danger" onclick="dismissAlert()">
                                                <i class="fas fa-check me-1"></i> Dismiss
                                            </button>
                                            <a href="alerts.php" class="btn btn-sm btn-danger">
                                                <i class="fas fa-eye me-1"></i> View All Alerts
                                            </a>
                                            <a href="logs.php?status=denied" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-history me-1"></i> View Logs
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                STATS CARDS
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_residents']; ?></div>
                                <div class="stat-label">Residents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['active_cards']; ?></div>
                                <div class="stat-label">Active Cards</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['today_access']; ?></div>
                                <div class="stat-label">Today's Access</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $stats['unauthorized_today'] > 0 ? '#ef4444' : '#6b7280'; ?>;">
                                <i class="fas <?php echo $stats['unauthorized_today'] > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                            </div>
                            <div>
                                <div class="stat-number <?php echo $stats['unauthorized_today'] > 0 ? 'text-danger' : ''; ?>">
                                    <?php echo $stats['unauthorized_today']; ?>
                                </div>
                                <div class="stat-label">Unauthorized Today</div>
                            </div>
                            <?php if ($stats['unauthorized_today'] > 0): ?>
                                <span class="badge bg-danger pulse-badge">🚨</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-people-arrows"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['current_occupancy']; ?></div>
                                <div class="stat-label">Occupancy</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-user-clock"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_visitors']; ?></div>
                                <div class="stat-label">Visitors Today</div>
                            </div>
                            <?php if ($stats['pending_alerts'] > 0): ?>
                                <span class="badge bg-warning pulse-badge">
                                    <?php echo $stats['pending_alerts']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                LATEST ALERTS
                ============================================================ -->
                <?php if (!empty($latestAlerts)): ?>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-bell me-2"></i>Recent Alerts</h5>
                                <a href="alerts.php" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-eye me-1"></i> View All Alerts
                                </a>
                            </div>
                            <div class="card-body">
                                <?php foreach ($latestAlerts as $alert): 
                                    $isCritical = $alert['delivery_status'] == 'pending' && $alert['alert_type'] == 'unauthorized';
                                    $displayName = !empty($alert['display_name']) ? $alert['display_name'] : 'Unknown';
                                ?>
                                <div class="alert-item <?php echo $isCritical ? 'critical' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-bold"><?php echo $isCritical ? '🚨' : '⚠️'; ?></span>
                                            <span class="alert-uid"><?php echo htmlspecialchars($alert['card_uid']); ?></span>
                                            <span class="badge <?php echo $isCritical ? 'bg-danger' : 'badge-pending'; ?> ms-2">
                                                <?php echo ucfirst($alert['alert_type']); ?>
                                            </span>
                                            <span class="text-muted ms-2">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($displayName); ?>
                                            </span>
                                            <span class="text-muted ms-2" style="font-size: 12px;">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('h:i A', strtotime($alert['timestamp'])); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <a href="alerts.php?resolve=<?php echo $alert['alert_id']; ?>" class="btn btn-sm btn-resolve">
                                                <i class="fas fa-check me-1"></i> Resolve
                                            </a>
                                        </div>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <?php echo htmlspecialchars($alert['reason'] ?? 'Unauthorized access attempt'); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                ROOMS 1-5
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-bed me-2"></i>Room Occupancy <span class="text-muted small">(Max 7 per room)</span></h5>
                                <span class="text-muted small">
                                    <?php 
                                        $totalOccupied = 0;
                                        $totalCapacity = 5 * 7;
                                        foreach ($roomData as $room) {
                                            $totalOccupied += $room['count'];
                                        }
                                        echo $totalOccupied . ' / ' . $totalCapacity . ' occupied';
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php foreach ($roomData as $room): 
                                        $count = $room['count'];
                                        $isFull = $count >= 7;
                                        $isPartial = $count > 0 && $count < 7;
                                        $isEmpty = $count == 0;
                                        $statusClass = $isFull ? 'full' : ($isPartial ? 'partial' : 'available');
                                    ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="room-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="room-title">
                                                        Room <?php echo $room['room_number']; ?>
                                                        <?php if ($isFull): ?>
                                                            <span class="room-full-badge ms-1"><i class="fas fa-exclamation-triangle me-1"></i>FULL</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="room-capacity">
                                                        <span class="room-count <?php echo $statusClass; ?>"><?php echo $count; ?></span>
                                                        / 7 residents
                                                        <span class="badge <?php echo $isFull ? 'bg-danger' : ($isPartial ? 'bg-warning' : 'bg-success'); ?> ms-1">
                                                            <?php echo $isFull ? 'Full' : ($isPartial ? 'Partial' : 'Available'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-light text-dark"><?php echo 7 - $count; ?> slots</span>
                                                </div>
                                            </div>
                                            
                                            <hr>
                                            
                                            <div class="room-occupants">
                                                <?php if ($isEmpty): ?>
                                                    <div class="room-empty">
                                                        <i class="fas fa-bed fa-2x d-block mb-1"></i>
                                                        No occupants
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($room['occupants'] as $occupant): ?>
                                                        <div class="occupant-item">
                                                            <span>
                                                                <span class="status-dot inside" style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#34d399; margin-right:6px;"></span>
                                                                <?php echo htmlspecialchars($occupant['full_name']); ?>
                                                                <span class="text-muted small ms-1">
                                                                    (<?php echo htmlspecialchars($occupant['student_id'] ?? 'N/A'); ?>)
                                                                </span>
                                                            </span>
                                                            <span class="text-muted small">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo $occupant['last_entry'] ? date('h:i A', strtotime($occupant['last_entry'])) : 'N/A'; ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                ANNOUNCEMENTS ONLY (NO ACCESS LOGS)
                ============================================================ -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-bullhorn me-2"></i>Announcements</h5>
                                <a href="announcements.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No announcements
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($announcements as $announcement): 
                                            $priority = $announcement['priority'] ?? 'medium';
                                            $priorityBadge = 'badge-priority-' . $priority;
                                        ?>
                                        <div class="col-md-4">
                                            <div class="announcement-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <span class="title"><?php echo htmlspecialchars($announcement['title']); ?></span>
                                                    <span class="badge <?php echo $priorityBadge; ?>">
                                                        <?php echo ucfirst($priority); ?>
                                                    </span>
                                                </div>
                                                <div class="content">
                                                    <?php echo htmlspecialchars(substr($announcement['content'] ?? '', 0, 80)); ?>
                                                    <?php if (strlen($announcement['content'] ?? '') > 80): ?>...<?php endif; ?>
                                                </div>
                                                <div class="meta mt-1">
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                                    <?php if (!empty($announcement['admin_name'])): ?>
                                                        <span class="mx-1">•</span>
                                                        <i class="far fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($announcement['admin_name']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                </footer>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // DISMISS ALERT
        // ============================================================
        function dismissAlert() {
            const alertElement = document.querySelector('.alert-card');
            if (alertElement) {
                alertElement.style.transition = 'opacity 0.5s ease';
                alertElement.style.opacity = '0';
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 500);
            }
            
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                const label = card.querySelector('.stat-label');
                if (label && label.textContent.trim() === 'Unauthorized Today') {
                    const number = card.querySelector('.stat-number');
                    if (number) {
                        number.textContent = '0';
                        number.classList.remove('text-danger');
                    }
                    const icon = card.querySelector('.stat-icon');
                    if (icon) {
                        icon.style.background = '#6b7280';
                        const iconElement = icon.querySelector('i');
                        if (iconElement) {
                            iconElement.className = 'fas fa-check-circle';
                        }
                    }
                }
            });
        }

        // ============================================================
        // SHOW TOAST
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
        // CHECK NEW ALERTS
        // ============================================================
        function checkNewAlerts() {
            fetch('api/check_alerts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.new_alerts > 0) {
                        showToast(
                            'New Alert Detected!',
                            `${data.new_alerts} new unauthorized access alert${data.new_alerts > 1 ? 's' : ''}`,
                            'warning'
                        );
                    }
                })
                .catch(err => {});
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

        // ============================================================
        // AUTO REFRESH
        // ============================================================
        setInterval(() => {
            updateLastUpdateTime();
            checkNewAlerts();
        }, 10000);

        // ============================================================
        // INITIAL LOAD
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            updateLastUpdateTime();
            
            <?php if ($stats['pending_alerts'] > 0): ?>
                setTimeout(() => {
                    showToast(
                        '⚠️ Pending Alerts',
                        'You have <?php echo $stats['pending_alerts']; ?> pending alert<?php echo $stats['pending_alerts'] > 1 ? 's' : ''; ?> that need your attention.',
                        'warning'
                    );
                }, 1000);
            <?php endif; ?>
        });
        
        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body> 
</html>
