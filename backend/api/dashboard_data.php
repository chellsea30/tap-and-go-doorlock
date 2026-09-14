<?php
/**
 * Tap-and-Go Doorlock - Dashboard Live Data API
 * Returns JSON data for auto-updating dashboard
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$conn = getDBConnection();

// ============================================================
// ROOM CONFIGURATION
// ============================================================
$totalRooms = 13;
$maxPerRoom = 8;

$data = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'stats' => [],
    'rooms' => [],
    'outside_residents' => [],
    'latest_alerts' => [],
    'latest_unauthorized' => null,
    'pending_alerts_count' => 0,
    'critical_alerts_count' => 0,
    'new_access_count' => 0,
    'latest_access_logs' => []
];

// 1. TOTAL REGISTERED RESIDENTS
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE status = 'active' 
    AND room_number IS NOT NULL 
    AND room_number != ''
");
$data['stats']['total_residents'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

// 2. TOTAL ACTIVE CARDS
$result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
$data['stats']['active_cards'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

// 3. TODAY'S ACCESS
$result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE()");
$data['stats']['today_access'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

// 4. UNAUTHORIZED TODAY
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs 
    WHERE DATE(timestamp) = CURDATE() 
    AND access_status = 'denied'
");
$data['stats']['unauthorized_today'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

// 5 & 6. RESIDENTS INSIDE/OUTSIDE
$insideCount = 0;
$outsideCount = 0;

$result = $conn->query("
    SELECT 
        u.user_id, 
        u.full_name,
        u.student_id,
        u.room_number,
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

$outsideResidents = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['last_access_type'] === 'entry') {
            $insideCount++;
        } else {
            $outsideCount++;
            $outsideResidents[] = [
                'user_id' => $row['user_id'],
                'full_name' => $row['full_name'],
                'student_id' => $row['student_id'] ?? 'N/A',
                'room_number' => $row['room_number'],
                'last_exit' => $row['last_timestamp']
            ];
        }
    }
}

$data['stats']['residents_inside'] = $insideCount;
$data['stats']['residents_outside'] = $outsideCount;
$data['outside_residents'] = $outsideResidents;

// 7. VISITORS
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_timestamp) = CURDATE()");
$data['stats']['total_visitors'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM visitor_logs 
    WHERE DATE(entry_timestamp) = CURDATE() 
    AND exit_timestamp IS NULL
");
$data['stats']['visitors_inside'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

// 8. PENDING ALERTS
$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
$pendingCount = $result ? (int)$result->fetch_assoc()['count'] : 0;
$data['stats']['pending_alerts'] = $pendingCount;
$data['pending_alerts_count'] = $pendingCount;

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM alert_logs 
    WHERE delivery_status = 'pending' 
    AND alert_type = 'unauthorized'
");
$criticalCount = $result ? (int)$result->fetch_assoc()['count'] : 0;
$data['stats']['critical_alerts'] = $criticalCount;
$data['critical_alerts_count'] = $criticalCount;

// 9. ROOM OCCUPANCY (13 Rooms)
for ($i = 1; $i <= $totalRooms; $i++) {
    $roomInfo = [
        'room_number' => $i,
        'count' => 0,
        'occupants' => []
    ];
    
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.student_id,
               al.timestamp as last_entry
        FROM users u
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
        $roomInfo['occupants'][] = [
            'user_id' => $row['user_id'],
            'full_name' => $row['full_name'],
            'student_id' => $row['student_id'] ?? 'N/A',
            'last_entry' => $row['last_entry']
        ];
        $roomInfo['count']++;
    }
    $stmt->close();
    
    $data['rooms'][] = $roomInfo;
}

// 10. LATEST ALERTS
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
        $data['latest_alerts'][] = $row;
    }
}

// 11. LATEST UNAUTHORIZED
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
    $row['display_name'] = $displayName;
    $data['latest_unauthorized'] = $row;
}

// 12. NEW ACCESS COUNT (last 10 seconds)
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
");
$data['new_access_count'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

// 13. LATEST ACCESS LOGS (for live indicator)
$result = $conn->query("
    SELECT 
        al.*,
        u.full_name,
        u.room_number
    FROM access_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.timestamp DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data['latest_access_logs'][] = [
            'log_id' => $row['log_id'] ?? ($row['id'] ?? 0),
            'full_name' => $row['full_name'] ?? 'Unknown',
            'room_number' => $row['room_number'] ?? 'N/A',
            'card_uid' => $row['card_uid'] ?? 'N/A',
            'access_type' => $row['access_type'] ?? 'N/A',
            'access_status' => $row['access_status'] ?? 'N/A',
            'timestamp' => $row['timestamp'] ?? date('Y-m-d H:i:s')
        ];
    }
}

$conn->close();

echo json_encode($data);
