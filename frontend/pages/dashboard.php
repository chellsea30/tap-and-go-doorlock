<?php
/**
 * Tap-and-Go Doorlock - Dashboard
 * Location: frontend/pages/dashboard.php
 * COMPLETE WITH AUTO-UPDATE (No refresh needed)
 * 13 ROOMS × 8 SLOTS EACH
 * PURE DARK MODE
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

include '../includes/header.php';

$conn = getDBConnection();

// ============================================================
// ROOM CONFIGURATION - 13 ROOMS, 8 SLOTS EACH
// ============================================================
$totalRooms = 13;
$maxPerRoom = 8;

// ============================================================
// GET DASHBOARD STATISTICS
// ============================================================
$stats = [
    'total_residents' => 0,
    'active_cards' => 0,
    'today_access' => 0,
    'unauthorized_today' => 0,
    'residents_inside' => 0,
    'residents_outside' => 0,
    'total_visitors' => 0,
    'visitors_inside' => 0,
    'pending_alerts' => 0,
    'critical_alerts' => 0,
    'total_rooms' => $totalRooms,
    'max_per_room' => $maxPerRoom
];

// 1. Total Registered Residents
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE status = 'active' 
    AND room_number IS NOT NULL 
    AND room_number != ''
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_residents'] = (int)$row['count'];
}

// 2. Total Active Cards
$result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['active_cards'] = (int)$row['count'];
}

// 3. Today's Access
$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['today_access'] = (int)$row['count'];
}

// 4. Unauthorized Today
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs 
    WHERE DATE(timestamp) = CURDATE() 
    AND access_status = 'denied'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unauthorized_today'] = (int)$row['count'];
}

// 5 & 6. Residents Inside & Outside
$insideCount = 0;
$outsideCount = 0;
$result = $conn->query("
    SELECT 
        u.user_id, 
        u.full_name, 
        u.room_number,
        u.student_id,
        al.access_type as last_access_type,
        al.timestamp as last_timestamp
    FROM users u
    LEFT JOIN access_logs al ON u.user_id = al.user_id 
        AND al.timestamp = (
            SELECT MAX(timestamp) 
            FROM access_logs al2 
            WHERE al2.user_id = u.user_id
        )
    WHERE u.status = 'active'
    AND u.room_number IS NOT NULL
    AND u.room_number != ''
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['last_access_type'] === 'entry') {
            $insideCount++;
        } else {
            $outsideCount++;
        }
    }
}
$stats['residents_inside'] = $insideCount;
$stats['residents_outside'] = $outsideCount;

// 7. Total Visitors
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_timestamp) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_visitors'] = (int)$row['count'];
}

// 8. Visitors Inside
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM visitor_logs 
    WHERE DATE(entry_timestamp) = CURDATE() 
    AND exit_timestamp IS NULL
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['visitors_inside'] = (int)$row['count'];
}

// Pending alerts
$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_alerts'] = (int)$row['count'];
}

// Critical alerts
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM alert_logs 
    WHERE delivery_status = 'pending' 
    AND alert_type = 'unauthorized'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['critical_alerts'] = (int)$row['count'];
}

// ============================================================
// COURSE & YEAR LEVEL DISTRIBUTION
// ============================================================
$courseData = [];
$yearLevelData = [];

$result = $conn->query("
    SELECT rp.course, COUNT(*) as count
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status = 'active' AND rp.course IS NOT NULL
    GROUP BY rp.course
    ORDER BY count DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courseData[] = $row;
    }
}

$result = $conn->query("
    SELECT rp.year_level, COUNT(*) as count
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status = 'active' AND rp.year_level IS NOT NULL
    GROUP BY rp.year_level
    ORDER BY FIELD(rp.year_level, '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year')
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yearLevelData[] = $row;
    }
}

// ============================================================
// LATEST UNAUTHORIZED
// ============================================================
$latestUnauthorized = null;
$result = $conn->query("
    SELECT 
        alog.*,
        c.card_type as rfid_card_type,
        c.visitor_name,
        u.full_name as user_name,
        u.room_number
    FROM alert_logs alog
    LEFT JOIN rfid_cards c ON alog.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
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
    $latestUnauthorized = $row;
}

// ============================================================
// LATEST ALERTS
// ============================================================
$latestAlerts = [];
$result = $conn->query("
    SELECT 
        alog.*,
        c.card_type as rfid_card_type,
        c.visitor_name,
        u.full_name as user_name,
        u.room_number
    FROM alert_logs alog
    LEFT JOIN rfid_cards c ON alog.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
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
// ROOM OCCUPANCY (1-13)
// ============================================================
$roomData = [];
for ($i = 1; $i <= $totalRooms; $i++) {
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
    
    $roomData[$i]['is_full'] = $roomData[$i]['count'] >= $maxPerRoom;
}

// ============================================================
// RESIDENTS OUTSIDE (with Room Number)
// ============================================================
$outsideResidents = [];
$result = $conn->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.student_id,
        u.room_number,
        u.profile_photo,
        rp.course,
        rp.year_level,
        al.timestamp as last_exit,
        al.card_uid
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    LEFT JOIN access_logs al ON u.user_id = al.user_id 
        AND al.timestamp = (
            SELECT MAX(timestamp) 
            FROM access_logs al2 
            WHERE al2.user_id = u.user_id
        )
    WHERE u.status = 'active'
    AND u.room_number IS NOT NULL
    AND u.room_number != ''
    AND al.access_type = 'exit'
    ORDER BY al.timestamp DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $outsideResidents[] = $row;
    }
}

// ============================================================
// ANNOUNCEMENTS
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
        
        /* NAVBAR */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important; left: 0 !important; right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* SIDEBAR */
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
        
        /* CARDS */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-header { background: #111827 !important; border-bottom: 1px solid #1a2a4a !important; }
        .card-header h5 { color: #e0e0e0 !important; }
        .card-body { background: #111827 !important; }
        
        /* STAT CARDS */
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
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; transition: all 0.3s ease; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        
        /* ROOM CARDS */
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
        .room-card .room-title { font-weight: 700; color: #93c5fd !important; font-size: 18px; margin-bottom: 2px; }
        .room-card .room-capacity { font-size: 12px; color: #808090; }
        .room-card .room-count { font-size: 24px; font-weight: 700; }
        .room-card .room-count.full { color: #f87171; }
        .room-card .room-count.available { color: #34d399; }
        .room-card .room-count.partial { color: #fbbf24; }
        .room-card .occupant-item {
            padding: 4px 8px; margin: 2px 0;
            background: #1a2a4a !important;
            border-radius: 6px; font-size: 12px;
            display: flex; justify-content: space-between; align-items: center;
            color: #e0e0e0;
        }
        .room-card .room-empty { color: #606070; font-size: 13px; text-align: center; padding: 10px 0; }
        .room-card .room-full-badge {
            background: #7a2a2a; color: #f87171;
            padding: 2px 10px; border-radius: 20px;
            font-size: 10px; font-weight: 600;
        }
        
        /* ALERT ITEMS */
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
        .alert-item.critical { border-left-color: #ef4444; background: #1a0a0a !important; }
        .alert-item .alert-uid { font-family: monospace; font-weight: 700; color: #93c5fd; font-size: 14px; }
        
        .btn-resolve {
            background: #10b981 !important;
            color: white !important;
            border: none !important;
            border-radius: 8px;
            padding: 5px 15px;
            font-size: 12px;
            font-weight: 500;
        }
        .btn-resolve:hover { background: #059669 !important; color: white !important; }
        
        /* ANNOUNCEMENTS */
        .announcement-item { padding: 10px 0; border-bottom: 1px solid #1a2a4a; }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-item .title { font-weight: 600; color: #e0e0e0; }
        .announcement-item .content { font-size: 13px; color: #b0b0c0; }
        .announcement-item .meta { font-size: 11px; color: #606070; }
        
        /* WARNING BAR */
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
        .warning-bar.danger { background: #2a0a0a !important; border-color: #7a2a2a !important; }
        .warning-bar .warning-text { font-weight: 600; color: #f87171; }
        .warning-bar .warning-count {
            background: #ef4444; color: white;
            padding: 2px 12px; border-radius: 20px;
            font-weight: 700; font-size: 14px;
        }
        
        /* ALERT CARD */
        .alert-card {
            animation: slideDown 0.5s ease;
            border-left: 4px solid #ef4444 !important;
            border-color: #5a2a2a !important;
        }
        .alert-card .card-header { background: #2a0a0a !important; border-bottom: 1px solid #5a2a2a !important; }
        .alert-card .card-header h5 { color: #f87171 !important; }
        .alert-card .card-body { background: #1a0a0a !important; }
        .alert-card code { color: #93c5fd !important; background: #1a2a4a !important; padding: 2px 6px; border-radius: 4px; }
        
        /* TOAST */
        .toast-container {
            position: fixed;
            top: 80px; right: 20px;
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
        .toast-notification.info { border-left-color: #3b82f6; }
        .toast-notification .toast-title { font-weight: 600; font-size: 14px; color: #e0e0e0; }
        .toast-notification .toast-body { font-size: 12px; color: #b0b0c0; margin-top: 4px; }
        .toast-notification .toast-time { font-size: 10px; color: #606070; margin-top: 4px; }
        
        /* ANIMATIONS */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulseBadge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        @keyframes pulseRed {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.9); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
        @keyframes flashGold {
            0% { color: #e0e0e0; }
            50% { color: #ffd700; transform: scale(1.2); }
            100% { color: #e0e0e0; transform: scale(1); }
        }
        .pulse-badge { animation: pulseBadge 1s infinite; display: inline-block; }
        .pulse-red { animation: pulseRed 1.5s infinite; display: inline-block; }
        .live-indicator {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            transition: all 0.3s ease;
        }
        .flash-gold { animation: flashGold 0.6s ease; }
        
        /* PIE CHART */
        .pie-chart-container { display: flex; align-items: center; gap: 30px; flex-wrap: wrap; }
        .pie-chart {
            width: 180px; height: 180px;
            border-radius: 50%;
            position: relative;
            flex-shrink: 0;
        }
        .pie-chart::after {
            content: '';
            position: absolute;
            top: 25px; left: 25px;
            width: 130px; height: 130px;
            background: #111827;
            border-radius: 50%;
        }
        .pie-chart .center-text {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 10;
        }
        .pie-chart .center-text .number { font-size: 28px; font-weight: 700; color: #ffd700 !important; }
        .pie-chart .center-text .label { font-size: 11px; color: #6b7280; }
        .pie-legend { flex: 1; min-width: 200px; }
        .pie-legend .legend-item {
            display: flex; align-items: center;
            margin-bottom: 8px; font-size: 13px; color: #e0e0e0;
        }
        .pie-legend .legend-dot {
            width: 12px; height: 12px;
            border-radius: 4px;
            margin-right: 10px;
            flex-shrink: 0;
        }
        .pie-legend .legend-count { margin-left: auto; font-weight: 600; color: #d1d5db; }
        .pie-legend .legend-percent { font-size: 11px; color: #6b7280; margin-left: 5px; }
        
        /* TABLE */
        .table-dark { background: #111827 !important; border-color: #1a2a4a !important; }
        .table-dark thead th {
            color: #808090 !important;
            font-size: 12px; font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #1a2a4a !important;
            padding: 10px 12px;
        }
        .table-dark tbody td {
            color: #e0e0e0 !important;
            border-bottom: 1px solid #1a2a4a !important;
            padding: 10px 12px;
            vertical-align: middle;
        }
        .table-dark tbody tr:hover { background: #1a2a4a !important; }
        .profile-img-placeholder {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: white;
            flex-shrink: 0;
        }
        
        /* BADGES */
        .badge-room { background: #4a3a1a !important; color: #fbbf24 !important; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .badge-denied { background: #7a2a2a !important; color: #f87171 !important; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .badge-granted { background: #065f46 !important; color: #34d399 !important; }
        .badge-entry { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-exit { background: #2a2a4a !important; color: #808090 !important; }
        .badge-priority-high { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-priority-medium { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-priority-low { background: #065f46 !important; color: #34d399 !important; }
        .badge-pending { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-light { background: #2a2a4a !important; color: #b0b0c0 !important; }
        
        /* MISC */
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-primary { color: #93c5fd !important; }
        .bg-light { background: #1a2a4a !important; }
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 { color: #e0e0e0 !important; }
        a { color: #93c5fd !important; text-decoration: none; }
        a:hover { color: #bfdbfe !important; }
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px; bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            .toast-container { top: 70px; right: 10px; left: 10px; }
            .toast-notification { max-width: 100%; }
            .pie-chart { width: 140px; height: 140px; }
            .pie-chart::after { top: 20px; left: 20px; width: 100px; height: 100px; }
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
                            <span class="badge bg-danger ms-2 pulse-badge" id="alertBadge">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?php echo $stats['pending_alerts']; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger ms-2 pulse-badge" id="alertBadge" style="display:none;">
                                <i class="fas fa-exclamation-circle me-1"></i> 0
                            </span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator me-1"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Loading...</span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="fetchDashboardData()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- WARNING NOTIFICATION BAR -->
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
                <?php endif; ?>

                <!-- UNAUTHORIZED ACCESS ALERT CARD -->
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
                                                    <span class="badge badge-denied"><i class="fas fa-times-circle me-1"></i> Denied</span>
                                                </p>
                                                <p class="mb-0 text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('F d, Y h:i A', strtotime($latestUnauthorized['timestamp'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <a href="alerts.php" class="btn btn-sm btn-danger">
                                            <i class="fas fa-eye me-1"></i> View All Alerts
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- STATS CARDS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number" id="stat-total-residents"><?php echo $stats['total_residents']; ?></div>
                                <div class="stat-label">Total Registered Residents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number" id="stat-active-cards"><?php echo $stats['active_cards']; ?></div>
                                <div class="stat-label">Total Active Cards</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div class="stat-number" id="stat-today-access"><?php echo $stats['today_access']; ?></div>
                                <div class="stat-label">Today's Access</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $stats['unauthorized_today'] > 0 ? '#ef4444' : '#6b7280'; ?>;" id="stat-unauth-icon">
                                <i class="fas <?php echo $stats['unauthorized_today'] > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                            </div>
                            <div>
                                <div class="stat-number <?php echo $stats['unauthorized_today'] > 0 ? 'text-danger' : ''; ?>" id="stat-unauthorized">
                                    <?php echo $stats['unauthorized_today']; ?>
                                </div>
                                <div class="stat-label">Unauthorized Today</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RESIDENT & VISITOR STATUS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #34d399;"><i class="fas fa-door-open"></i></div>
                            <div>
                                <div class="stat-number text-success" id="stat-inside"><?php echo $stats['residents_inside']; ?></div>
                                <div class="stat-label">Residents Inside Rooms</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f87171;"><i class="fas fa-door-closed"></i></div>
                            <div>
                                <div class="stat-number text-danger" id="stat-outside"><?php echo $stats['residents_outside']; ?></div>
                                <div class="stat-label">Residents Outside</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-user-friends"></i></div>
                            <div>
                                <div class="stat-number" id="stat-visitors"><?php echo $stats['total_visitors']; ?></div>
                                <div class="stat-label">Total Visitors Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-user-check"></i></div>
                            <div>
                                <div class="stat-number text-primary" id="stat-visitors-inside"><?php echo $stats['visitors_inside']; ?></div>
                                <div class="stat-label">Visitors Inside</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LATEST ALERTS -->
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

                <!-- ROOMS 1-13 - OCCUPANCY -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card" id="roomOccupancyCard">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-bed me-2"></i>Room Occupancy <span class="text-muted small">(Max <?php echo $maxPerRoom; ?> per room)</span></h5>
                                <span class="text-muted small" id="roomOccupancySummary">
                                    <?php 
                                        $totalOccupied = 0;
                                        $totalCapacity = $totalRooms * $maxPerRoom;
                                        foreach ($roomData as $room) {
                                            $totalOccupied += $room['count'];
                                        }
                                        echo $totalOccupied . ' / ' . $totalCapacity . ' occupied';
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row g-3" id="roomsContainer">
                                    <?php foreach ($roomData as $room): 
                                        $count = $room['count'];
                                        $isFull = $count >= $maxPerRoom;
                                        $isPartial = $count > 0 && $count < $maxPerRoom;
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
                                                        / <?php echo $maxPerRoom; ?> residents
                                                        <span class="badge <?php echo $isFull ? 'bg-danger' : ($isPartial ? 'bg-warning' : 'bg-success'); ?> ms-1">
                                                            <?php echo $isFull ? 'Full' : ($isPartial ? 'Partial' : 'Available'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-light text-dark"><?php echo $maxPerRoom - $count; ?> slots</span>
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

                <!-- RESIDENTS OUTSIDE (WITH ROOM NUMBER) -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card" id="outsideResidentsCard">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>
                                    <i class="fas fa-door-closed me-2" style="color: #f87171;"></i>
                                    Residents Outside 
                                    <span class="badge bg-danger ms-2" id="outsideCountBadge"><?php echo count($outsideResidents); ?></span>
                                </h5>
                                <span class="text-muted small">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Auto-updates
                                </span>
                            </div>
                            <div class="card-body" id="outsideResidentsBody">
                                <?php if (empty($outsideResidents)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                                        <p class="mb-0">All residents are currently inside their rooms.</p>
                                        <small class="text-muted">No residents have exited yet today.</small>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-dark table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Resident</th>
                                                    <th>Room</th>
                                                    <th>Course / Year</th>
                                                    <th>Last Exit</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $counter = 1; foreach ($outsideResidents as $resident): ?>
                                                <tr>
                                                    <td style="color: #808090; font-size: 13px;"><?php echo $counter++; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <?php 
                                                            $parts = explode(' ', $resident['full_name']);
                                                            $initials = '';
                                                            foreach ($parts as $p) {
                                                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                                            }
                                                            $initials = substr($initials, 0, 2) ?: '?';
                                                            ?>
                                                            <div class="profile-img-placeholder" style="background: #7a2a2a !important;">
                                                                <?php echo $initials; ?>
                                                            </div>
                                                            <div>
                                                                <div style="color: #e0e0e0; font-weight: 500; font-size: 14px;">
                                                                    <?php echo htmlspecialchars($resident['full_name']); ?>
                                                                </div>
                                                                <div style="color: #606070; font-size: 11px;">
                                                                    <?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-room">
                                                            <i class="fas fa-door-open me-1"></i>
                                                            Room <?php echo htmlspecialchars($resident['room_number']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="color: #b0b0c0; font-size: 13px;">
                                                        <?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?>
                                                        <span class="text-muted small">
                                                            (<?php echo htmlspecialchars($resident['year_level'] ?? 'N/A'); ?>)
                                                        </span>
                                                    </td>
                                                    <td style="color: #b0b0c0; font-size: 13px;">
                                                        <i class="far fa-clock me-1 text-warning"></i>
                                                        <?php echo $resident['last_exit'] ? date('M d, h:i A', strtotime($resident['last_exit'])) : 'N/A'; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-denied">
                                                            <i class="fas fa-door-closed me-1"></i>
                                                            Outside
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-muted small mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <span id="outsideTableCount"><?php echo count($outsideResidents); ?></span> resident(s) currently outside
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ANNOUNCEMENTS -->
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

                <!-- COURSE DISTRIBUTION -->
                <div class="row g-3 mb-4 mt-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>Course Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $courseColors = ['#667eea', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#06b6d4'];
                                $courseTotal = 0;
                                foreach ($courseData as $c) { $courseTotal += $c['count']; }
                                
                                if (empty($courseData) || $courseTotal == 0): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-pie fa-2x mb-2 d-block"></i>
                                        No data available
                                    </div>
                                <?php else: 
                                    $courseGradient = '';
                                    $currentPercent = 0;
                                    foreach ($courseData as $index => $c) {
                                        $percent = ($c['count'] / $courseTotal) * 100;
                                        $color = $courseColors[$index % count($courseColors)];
                                        $courseGradient .= $color . ' ' . $currentPercent . '% ' . ($currentPercent + $percent) . '%, ';
                                        $currentPercent += $percent;
                                    }
                                    $courseGradient = rtrim($courseGradient, ', ');
                                ?>
                                <div class="pie-chart-container">
                                    <div class="pie-chart" style="background: conic-gradient(<?php echo $courseGradient; ?>);">
                                        <div class="center-text">
                                            <div class="number"><?php echo $courseTotal; ?></div>
                                            <div class="label">Residents</div>
                                        </div>
                                    </div>
                                    <div class="pie-legend">
                                        <?php foreach ($courseData as $index => $c): 
                                            $color = $courseColors[$index % count($courseColors)];
                                            $percent = round(($c['count'] / $courseTotal) * 100, 1);
                                        ?>
                                        <div class="legend-item">
                                            <span class="legend-dot" style="background: <?php echo $color; ?>;"></span>
                                            <span><?php echo htmlspecialchars($c['course']); ?></span>
                                            <span class="legend-count"><?php echo $c['count']; ?></span>
                                            <span class="legend-percent">(<?php echo $percent; ?>%)</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- YEAR LEVEL DISTRIBUTION -->
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>Year Level Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $yearColors = ['#10b981', '#667eea', '#f59e0b', '#ef4444', '#8b5cf6'];
                                $yearTotal = 0;
                                foreach ($yearLevelData as $y) { $yearTotal += $y['count']; }
                                
                                if (empty($yearLevelData) || $yearTotal == 0): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-pie fa-2x mb-2 d-block"></i>
                                        No data available
                                    </div>
                                <?php else: 
                                    $yearGradient = '';
                                    $currentPercent = 0;
                                    foreach ($yearLevelData as $index => $y) {
                                        $percent = ($y['count'] / $yearTotal) * 100;
                                        $color = $yearColors[$index % count($yearColors)];
                                        $yearGradient .= $color . ' ' . $currentPercent . '% ' . ($currentPercent + $percent) . '%, ';
                                        $currentPercent += $percent;
                                    }
                                    $yearGradient = rtrim($yearGradient, ', ');
                                ?>
                                <div class="pie-chart-container">
                                    <div class="pie-chart" style="background: conic-gradient(<?php echo $yearGradient; ?>);">
                                        <div class="center-text">
                                            <div class="number"><?php echo $yearTotal; ?></div>
                                            <div class="label">Residents</div>
                                        </div>
                                    </div>
                                    <div class="pie-legend">
                                        <?php foreach ($yearLevelData as $index => $y): 
                                            $color = $yearColors[$index % count($yearColors)];
                                            $percent = round(($y['count'] / $yearTotal) * 100, 1);
                                        ?>
                                        <div class="legend-item">
                                            <span class="legend-dot" style="background: <?php echo $color; ?>;"></span>
                                            <span><?php echo htmlspecialchars($y['year_level']); ?></span>
                                            <span class="legend-count"><?php echo $y['count']; ?></span>
                                            <span class="legend-percent">(<?php echo $percent; ?>%)</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
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
        // GLOBAL STATE
        // ============================================================
        let lastLogId = 0;
        let lastInsideCount = <?php echo $stats['residents_inside']; ?>;
        let lastOutsideCount = <?php echo $stats['residents_outside']; ?>;
        let lastPendingAlerts = <?php echo $stats['pending_alerts']; ?>;
        let isFirstLoad = true;
        let updateInterval = null;
        let currentRoomsSignature = '';
        let currentOutsideSignature = '';

        // ✅ API PATH - backend/api/dashboard_data.php
        const API_URL = '../../backend/api/dashboard_data.php';

        // ============================================================
        // SHOW TOAST
        // ============================================================
        function showToast(title, message, type = 'warning') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            if (type === 'success') toast.classList.add('success');
            if (type === 'info') toast.classList.add('info');
            
            const icon = type === 'success' ? '✅' : (type === 'info' ? 'ℹ️' : '🚨');
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
                setTimeout(() => toast.remove(), 500);
            }, 5000);
        }

        // ============================================================
        // UPDATE STAT WITH FLASH
        // ============================================================
        function updateStat(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            
            const newVal = String(value);
            if (el.textContent.trim() !== newVal) {
                el.textContent = newVal;
                el.classList.remove('flash-gold');
                void el.offsetWidth;
                el.classList.add('flash-gold');
            }
        }

        // ============================================================
        // ESCAPE HTML
        // ============================================================
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================================
        // RENDER ROOMS
        // ============================================================
        function renderRooms(rooms) {
            const container = document.getElementById('roomsContainer');
            if (!container) return;
            
            const signature = JSON.stringify(rooms.map(r => ({ n: r.room_number, c: r.count, o: r.occupants.map(x => x.user_id) })));
            if (signature === currentRoomsSignature) return;
            currentRoomsSignature = signature;
            
            const maxPerRoom = 8;
            let html = '';
            let totalOccupied = 0;
            
            rooms.forEach(room => {
                const count = room.count;
                totalOccupied += count;
                const isFull = count >= maxPerRoom;
                const isPartial = count > 0 && count < maxPerRoom;
                const isEmpty = count === 0;
                const statusClass = isFull ? 'full' : (isPartial ? 'partial' : 'available');
                
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="room-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="room-title">
                                        Room ${room.room_number}
                                        ${isFull ? '<span class="room-full-badge ms-1"><i class="fas fa-exclamation-triangle me-1"></i>FULL</span>' : ''}
                                    </div>
                                    <div class="room-capacity">
                                        <span class="room-count ${statusClass}">${count}</span>
                                        / ${maxPerRoom} residents
                                        <span class="badge ${isFull ? 'bg-danger' : (isPartial ? 'bg-warning' : 'bg-success')} ms-1">
                                            ${isFull ? 'Full' : (isPartial ? 'Partial' : 'Available')}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark">${maxPerRoom - count} slots</span>
                                </div>
                            </div>
                            <hr>
                            <div class="room-occupants">
                `;
                
                if (isEmpty) {
                    html += `<div class="room-empty"><i class="fas fa-bed fa-2x d-block mb-1"></i>No occupants</div>`;
                } else {
                    room.occupants.forEach(o => {
                        const entryTime = o.last_entry ? new Date(o.last_entry).toLocaleTimeString('en-US', {
                            hour: '2-digit', minute: '2-digit', hour12: true
                        }) : 'N/A';
                        
                        html += `
                            <div class="occupant-item">
                                <span>
                                    <span class="status-dot inside" style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#34d399; margin-right:6px;"></span>
                                    ${escapeHtml(o.full_name)}
                                    <span class="text-muted small ms-1">(${escapeHtml(o.student_id)})</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="fas fa-clock me-1"></i>${entryTime}
                                </span>
                            </div>
                        `;
                    });
                }
                
                html += `</div></div></div>`;
            });
            
            container.innerHTML = html;
            
            const summary = document.getElementById('roomOccupancySummary');
            if (summary) summary.textContent = `${totalOccupied} / ${rooms.length * maxPerRoom} occupied`;
        }

        // ============================================================
        // RENDER OUTSIDE RESIDENTS
        // ============================================================
        function renderOutsideResidents(residents) {
            const container = document.getElementById('outsideResidentsBody');
            if (!container) return;
            
            const signature = JSON.stringify(residents.map(r => ({ u: r.user_id, t: r.last_exit })));
            if (signature === currentOutsideSignature) return;
            currentOutsideSignature = signature;
            
            const countBadge = document.getElementById('outsideCountBadge');
            if (countBadge) countBadge.textContent = residents.length;
            
            const tableCount = document.getElementById('outsideTableCount');
            if (tableCount) tableCount.textContent = residents.length;
            
            if (residents.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                        <p class="mb-0">All residents are currently inside their rooms.</p>
                        <small class="text-muted">No residents have exited yet today.</small>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Resident</th>
                                <th>Room</th>
                                <th>Last Exit</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            residents.forEach((r, index) => {
                const parts = (r.full_name || '').split(' ');
                let initials = '';
                parts.forEach(p => { if (p) initials += p[0].toUpperCase(); });
                initials = initials.substring(0, 2) || '?';
                
                const exitTime = r.last_exit ? new Date(r.last_exit).toLocaleString('en-US', {
                    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true
                }) : 'N/A';
                
                html += `
                    <tr>
                        <td style="color: #808090; font-size: 13px;">${index + 1}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="profile-img-placeholder" style="background: #7a2a2a !important;">${initials}</div>
                                <div>
                                    <div style="color: #e0e0e0; font-weight: 500; font-size: 14px;">${escapeHtml(r.full_name)}</div>
                                    <div style="color: #606070; font-size: 11px;">${escapeHtml(r.student_id)}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-room">
                                <i class="fas fa-door-open me-1"></i>
                                Room ${escapeHtml(r.room_number)}
                            </span>
                        </td>
                        <td style="color: #b0b0c0; font-size: 13px;">
                            <i class="far fa-clock me-1 text-warning"></i>
                            ${exitTime}
                        </td>
                        <td>
                            <span class="badge badge-denied">
                                <i class="fas fa-door-closed me-1"></i>
                                Outside
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Showing ${residents.length} resident(s) currently outside
                </div>
            `;
            
            container.innerHTML = html;
        }

        // ============================================================
        // MAIN FETCH FUNCTION
        // ============================================================
        function fetchDashboardData() {
            fetch(API_URL + '?_=' + Date.now(), {
                cache: 'no-store',
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) throw new Error('Network error: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (!data.success) return;
                    
                    const stats = data.stats;
                    
                    // ---- UPDATE STAT CARDS ----
                    updateStat('stat-total-residents', stats.total_residents);
                    updateStat('stat-active-cards', stats.active_cards);
                    updateStat('stat-today-access', stats.today_access);
                    updateStat('stat-unauthorized', stats.unauthorized_today);
                    updateStat('stat-inside', stats.residents_inside);
                    updateStat('stat-outside', stats.residents_outside);
                    updateStat('stat-visitors', stats.total_visitors);
                    updateStat('stat-visitors-inside', stats.visitors_inside);
                    
                    // ---- DETECT NEW ACCESS ----
                    if (!isFirstLoad && data.latest_access_logs && data.latest_access_logs.length > 0) {
                        const latest = data.latest_access_logs[0];
                        const latestId = parseInt(latest.log_id) || 0;
                        
                        if (lastLogId > 0 && latestId > lastLogId) {
                            const isDenied = latest.access_status === 'denied';
                            const isEntry = latest.access_type === 'entry';
                            
                            showToast(
                                isDenied ? '🚫 Unauthorized Access!' : (isEntry ? '✅ Entry Detected' : '👋 Exit Detected'),
                                `${latest.full_name} (Room ${latest.room_number}) ${isEntry ? 'entered' : 'exited'} the dormitory`,
                                isDenied ? 'warning' : 'success'
                            );
                        }
                        
                        if (latestId > 0) lastLogId = latestId;
                    } else if (data.latest_access_logs && data.latest_access_logs.length > 0) {
                        lastLogId = parseInt(data.latest_access_logs[0].log_id) || 0;
                    }
                    
                    // ---- NEW ALERT NOTIFICATION ----
                    if (!isFirstLoad && stats.pending_alerts > lastPendingAlerts) {
                        const diff = stats.pending_alerts - lastPendingAlerts;
                        showToast(
                            '🚨 New Alert!',
                            `${diff} new unauthorized access alert${diff > 1 ? 's' : ''} detected!`,
                            'warning'
                        );
                    }
                    
                    lastInsideCount = stats.residents_inside;
                    lastOutsideCount = stats.residents_outside;
                    lastPendingAlerts = stats.pending_alerts;
                    
                    // ---- UPDATE ROOMS ----
                    renderRooms(data.rooms);
                    
                    // ---- UPDATE OUTSIDE RESIDENTS ----
                    renderOutsideResidents(data.outside_residents);
                    
                    // ---- UPDATE ALERT BADGE ----
                    const alertBadge = document.getElementById('alertBadge');
                    if (alertBadge) {
                        if (stats.pending_alerts > 0) {
                            alertBadge.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i> ${stats.pending_alerts}`;
                            alertBadge.style.display = '';
                        } else {
                            alertBadge.style.display = 'none';
                        }
                    }
                    
                    // ---- UPDATE TIMESTAMP ----
                    const updateElement = document.getElementById('lastUpdate');
                    if (updateElement) {
                        const now = new Date();
                        updateElement.textContent = 'Live: ' + now.toLocaleTimeString('en-US', { 
                            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
                        });
                    }
                    
                    // ---- LIVE INDICATOR FLASH ----
                    const liveIndicator = document.querySelector('.live-indicator');
                    if (liveIndicator) {
                        liveIndicator.style.background = '#34d399';
                        liveIndicator.style.boxShadow = '0 0 10px #34d399';
                        setTimeout(() => {
                            liveIndicator.style.boxShadow = 'none';
                        }, 500);
                    }
                    
                    isFirstLoad = false;
                })
                .catch(err => {
                    console.warn('Auto-update error:', err);
                    const liveIndicator = document.querySelector('.live-indicator');
                    if (liveIndicator) {
                        liveIndicator.style.background = '#f87171';
                        liveIndicator.style.boxShadow = '0 0 10px #f87171';
                    }
                });
        }

        // ============================================================
        // AUTO-UPDATE - EVERY 3 SECONDS
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(fetchDashboardData, 500);
            updateInterval = setInterval(fetchDashboardData, 3000);
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
