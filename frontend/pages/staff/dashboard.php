<?php
/**
 * Tap-and-Go Doorlock - Staff Dashboard
 * VIEW ONLY - UPDATED STATISTICS (SAME AS ADMIN)
 * PURE DARK MODE - No white backgrounds
 * WITH RESIDENTS OUTSIDE SECTION
 * FIXED: Total Registered Residents (Excluding Visitors)
 */

// Start session
session_start();

// Load config and functions - GUMAGAMIT NG __DIR__
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// Check if logged in as staff
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: ../login.php');
    exit();
}

$conn = getDBConnection();

// ============================================================
// GET DASHBOARD STATISTICS (UPDATED - SAME AS ADMIN)
// ============================================================
$stats = [
    'total_residents' => 0,          // Total registered residents (EXCLUDING visitors)
    'active_cards' => 0,             // Total active cards
    'today_access' => 0,             // Total access today
    'unauthorized_today' => 0,       // Total unauthorized today
    'residents_inside' => 0,         // Total residents inside rooms
    'residents_outside' => 0,        // Total residents outside
    'total_visitors' => 0,           // Total registered visitors
    'visitors_inside' => 0,          // Total visitors inside
    'pending_alerts' => 0,
    'critical_alerts' => 0,
    'total_rooms' => 5,
    'max_per_room' => 7
];

// 1. Total Registered Residents (EXCLUDING visitors and deleted)
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

// 4. Total Unauthorized Today (Denied)
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs 
    WHERE DATE(timestamp) = CURDATE() 
    AND access_status = 'denied'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['unauthorized_today'] = (int)$row['count'];
}

// 5 & 6. Total Residents Inside & Outside
$residentsStatus = [
    'inside' => [],
    'outside' => []
];

$result = $conn->query("
    SELECT 
        u.user_id, 
        u.full_name, 
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
            $residentsStatus['inside'][] = $row;
        } else {
            $residentsStatus['outside'][] = $row;
        }
    }
}

$stats['residents_inside'] = count($residentsStatus['inside']);
$stats['residents_outside'] = count($residentsStatus['outside']);

// 7. Total Visitors (Registered visitors today)
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_timestamp) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_visitors'] = (int)$row['count'];
}

// 8. Total Visitors Inside (Not yet exited)
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
// GET COURSE & YEAR LEVEL DISTRIBUTION (FOR PIE CHART)
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
// GET LATEST UNAUTHORIZED ACCESS
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
// GET RESIDENTS OUTSIDE (for the new section)
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

// ============================================================
// GET STAFF INFO FOR DISPLAY
// ============================================================
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
    <title>Staff Dashboard - Tap-and-Go Doorlock</title>
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
        .sidebar .nav-link .fa-chevron-down {
            font-size: 11px;
            opacity: 0.4;
            transition: transform 0.3s ease;
            color: #606070 !important;
            margin-left: auto;
        }
        .sidebar .nav-link.active .fa-chevron-down {
            color: white !important;
        }
        .sidebar .nav-link[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }
        .sidebar .nav-link.active .badge {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        .sidebar-footer {
            padding: 10px 0 20px 0;
            border-top: 1px solid #1a2a4a !important;
            margin-top: auto;
        }
        .sidebar-footer .border-top {
            border-color: #1a2a4a !important;
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
        }
        
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
           PIE CHART / DONUT CHART (CSS conic-gradient)
           ============================================================ */
        .pie-chart-container {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        .pie-chart {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            position: relative;
            flex-shrink: 0;
        }
        .pie-chart::after {
            content: '';
            position: absolute;
            top: 25px;
            left: 25px;
            width: 130px;
            height: 130px;
            background: #111827;
            border-radius: 50%;
        }
        .pie-chart .center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 10;
        }
        .pie-chart .center-text .number {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700 !important;
        }
        .pie-chart .center-text .label {
            font-size: 11px;
            color: #6b7280;
        }
        .pie-legend {
            flex: 1;
            min-width: 200px;
        }
        .pie-legend .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
            color: #e0e0e0;
        }
        .pie-legend .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            margin-right: 10px;
            flex-shrink: 0;
        }
        .pie-legend .legend-count {
            margin-left: auto;
            font-weight: 600;
            color: #d1d5db;
        }
        .pie-legend .legend-percent {
            font-size: 11px;
            color: #6b7280;
            margin-left: 5px;
        }
        
        /* ============================================================
           OUTSIDE RESIDENTS TABLE
           ============================================================ */
        .table-dark {
            background: #111827 !important;
            border-color: #1a2a4a !important;
        }
        .table-dark thead th {
            color: #808090 !important;
            font-size: 12px;
            font-weight: 600;
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
        .table-dark tbody tr:hover {
            background: #1a2a4a !important;
        }
        .profile-img-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .badge-room {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        .badge-denied {
            background: #7a2a2a !important;
            color: #f87171 !important;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        
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
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        .border { border-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        
        /* ============================================================
           ANIMATIONS
           ============================================================ */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <!-- ===== NAVBAR ===== -->
    <?php include __DIR__ . '/includes/navbar_staff.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- ===== SIDEBAR ===== -->
            <?php include __DIR__ . '/includes/sidebar_staff.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" style="color:#e0e0e0 !important;">
                        <i class="fas fa-eye me-2" style="color: #fbbf24;"></i>
                        Staff Dashboard
                        <?php if ($stats['pending_alerts'] > 0): ?>
                            <span class="badge bg-danger ms-2 pulse-badge">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?php echo $stats['pending_alerts']; ?>
                            </span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <span class="view-only-badge me-2">
                            <i class="fas fa-eye me-1"></i> View Only
                        </span>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator me-1"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Welcome Card -->
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 style="color:#93c5fd; font-weight:700;">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>! 👋</h4>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-user-tie me-1"></i>
                                    <?php echo htmlspecialchars($staffInfo['staff_id_number'] ?? 'N/A'); ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($staffInfo['department'] ?? 'Dormitory Staff'); ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-eye me-1"></i>
                                    <span class="text-warning">View Only Access</span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-primary"><i class="fas fa-clock me-1"></i> <?php echo date('h:i A'); ?></span>
                                <span class="badge bg-secondary ms-1"><i class="fas fa-calendar-day me-1"></i> <?php echo date('M d, Y'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning Bar -->
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

                <!-- ===== STATS CARDS (UPDATED - SAME AS ADMIN) ===== -->
                <div class="row g-3 mb-4">
                    <!-- Total Registered Residents (FIXED - Excluding Visitors) -->
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_residents']; ?></div>
                                <div class="stat-label">Total Registered Residents</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Active Cards -->
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['active_cards']; ?></div>
                                <div class="stat-label">Total Active Cards</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Today's Access -->
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['today_access']; ?></div>
                                <div class="stat-label">Today's Access</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Unauthorized Today -->
                    <div class="col-6 col-sm-6 col-xl-3">
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
                </div>

                <!-- ===== RESIDENT & VISITOR STATUS CARDS ===== -->
                <div class="row g-3 mb-4">
                    <!-- Total Residents Inside -->
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #34d399;"><i class="fas fa-door-open"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['residents_inside']; ?></div>
                                <div class="stat-label">Residents Inside Rooms</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Residents Outside -->
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f87171;"><i class="fas fa-door-closed"></i></div>
                            <div>
                                <div class="stat-number text-danger"><?php echo $stats['residents_outside']; ?></div>
                                <div class="stat-label">Residents Outside</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Visitors -->
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-user-friends"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total_visitors']; ?></div>
                                <div class="stat-label">Total Visitors Today</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Visitors Inside -->
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-user-check"></i></div>
                            <div>
                                <div class="stat-number text-primary"><?php echo $stats['visitors_inside']; ?></div>
                                <div class="stat-label">Visitors Inside</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== LATEST ALERTS ===== -->
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

                <!-- ===== ROOMS 1-5 - OCCUPANCY ===== -->
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
                RESIDENTS OUTSIDE / EXITED SECTION
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>
                                    <i class="fas fa-door-closed me-2" style="color: #f87171;"></i>
                                    Residents Outside 
                                    <span class="badge bg-danger ms-2"><?php echo count($outsideResidents); ?></span>
                                </h5>
                                <span class="text-muted small">
                                    <i class="fas fa-clock me-1"></i>
                                    Last exit recorded
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($outsideResidents)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>
                                        <p class="mb-0">All residents are currently inside their rooms.</p>
                                        <small class="text-muted">No residents have exited yet today.</small>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-dark table-hover" style="background: #111827 !important; border-color: #1a2a4a !important;">
                                            <thead>
                                                <tr style="border-color: #1a2a4a !important;">
                                                    <th style="color: #808090; font-size: 12px;">#</th>
                                                    <th style="color: #808090; font-size: 12px;">Resident</th>
                                                    <th style="color: #808090; font-size: 12px;">Room</th>
                                                    <th style="color: #808090; font-size: 12px;">Course / Year</th>
                                                    <th style="color: #808090; font-size: 12px;">Last Exit</th>
                                                    <th style="color: #808090; font-size: 12px;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $counter = 1; foreach ($outsideResidents as $resident): ?>
                                                <tr style="border-color: #1a2a4a !important;">
                                                    <td style="color: #808090; font-size: 13px;"><?php echo $counter++; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <?php 
                                                            // Get initials
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
                                        Showing <?php echo count($outsideResidents); ?> resident(s) currently outside
                                        <span class="mx-1">|</span>
                                        <i class="fas fa-sync-alt me-1"></i>
                                        Auto-updates every 10 seconds
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                COURSE DISTRIBUTION PIE CHART (CSS-BASED)
                ============================================================ -->
                <div class="row g-3 mb-4 mt-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>Course Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                // Calculate colors and totals for pie
                                $courseColors = ['#667eea', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#06b6d4'];
                                $courseTotal = 0;
                                foreach ($courseData as $c) { $courseTotal += $c['count']; }
                                
                                if (empty($courseData) || $courseTotal == 0): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-pie fa-2x mb-2 d-block"></i>
                                        No data available for courses
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

                <!-- ============================================================
                YEAR LEVEL DISTRIBUTION PIE CHART (CSS-BASED)
                ============================================================ -->
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
                                        No data available for year levels
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

                <!-- ============================================================
                ANNOUNCEMENTS
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
                    <i class="fas fa-eye me-1"></i> View Only Access
                    <span class="mx-2">|</span>
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
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body> 
</html>
