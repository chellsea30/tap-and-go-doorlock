<?php
/**
 * Tap-and-Go Doorlock - RFID Access API
 * COMPLETE - WITH OCCUPANCY TRACKING
 * ENTRY = ADD TO OCCUPANCY | EXIT = REMOVE FROM OCCUPANCY
 * WITH REAL-TIME ALERT NOTIFICATIONS
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once '../../config/config.php';
require_once '../../helpers/functions.php';

$conn = getDBConnection();

// ============================================================
// API KEY AUTHENTICATION
// ============================================================
$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
$valid_api_key = 'TAP_AND_GO_2024_SECURE_KEY';

if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    // Optional: Uncomment to enable API key validation
    // if (empty($api_key) || $api_key !== $valid_api_key) {
    //     sendResponse(false, 'Invalid API Key', [], 401);
    // }
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';

function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success, 'message' => $message];
    foreach ($data as $key => $value) {
        $response[$key] = $value;
    }
    echo json_encode($response);
    exit();
}

// ============================================================
// CHECK IF TABLE EXISTS
// ============================================================
function tableExists($table) {
    global $conn;
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// ============================================================
// CREATE OCCUPANCY TABLE IF NOT EXISTS
// ============================================================
function createOccupancyTable() {
    global $conn;
    
    if (!tableExists('current_occupancy')) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `current_occupancy` (
                `occupancy_id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `card_uid` varchar(20) NOT NULL,
                `full_name` varchar(100) NOT NULL,
                `room_number` varchar(20) DEFAULT NULL,
                `card_type` enum('resident','staff','visitor') DEFAULT 'resident',
                `entry_time` datetime NOT NULL,
                `last_activity` datetime NOT NULL,
                `status` enum('inside','outside') DEFAULT 'inside',
                PRIMARY KEY (`occupancy_id`),
                UNIQUE KEY `unique_user` (`user_id`),
                KEY `idx_status` (`status`),
                KEY `idx_card_uid` (`card_uid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        error_log("✅ Created current_occupancy table");
    }
}

// ============================================================
// UPDATE OCCUPANCY - ENTRY/EXIT
// ============================================================
function updateOccupancy($user_id, $card_uid, $full_name, $room_number, $card_type, $action) {
    global $conn;
    
    createOccupancyTable();
    
    if ($action == 'entry') {
        // Check if already inside
        $check = $conn->prepare("SELECT occupancy_id FROM current_occupancy WHERE user_id = ? AND status = 'inside'");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            // Update entry time
            $stmt = $conn->prepare("
                UPDATE current_occupancy 
                SET entry_time = NOW(), last_activity = NOW() 
                WHERE user_id = ? AND status = 'inside'
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            error_log("✅ OCCUPANCY: $full_name updated entry time (already inside)");
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO current_occupancy (
                    user_id, card_uid, full_name, room_number, card_type, entry_time, last_activity, status
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 'inside')
            ");
            $stmt->bind_param("issss", $user_id, $card_uid, $full_name, $room_number, $card_type);
            $stmt->execute();
            $stmt->close();
            error_log("✅ OCCUPANCY: $full_name ($card_type) ENTRY - NOW INSIDE");
        }
        $check->close();
        
    } elseif ($action == 'exit') {
        // Mark as outside
        $stmt = $conn->prepare("
            UPDATE current_occupancy 
            SET status = 'outside', last_activity = NOW() 
            WHERE user_id = ? AND status = 'inside'
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            error_log("✅ OCCUPANCY: $full_name ($card_type) EXIT - NOW OUTSIDE");
        } else {
            error_log("⚠️ OCCUPANCY: $full_name - No inside record found (already outside)");
        }
        $stmt->close();
    }
}

// ============================================================
// GET CURRENT OCCUPANCY
// ============================================================
function getCurrentOccupancy() {
    global $conn;
    $occupants = [];
    
    $result = $conn->query("
        SELECT * FROM current_occupancy 
        WHERE status = 'inside' 
        ORDER BY entry_time DESC
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $occupants[] = $row;
        }
    }
    
    return $occupants;
}

// ============================================================
// CREATE REAL-TIME ALERT NOTIFICATION
// ============================================================
function createAlertNotification($uid, $reason, $user_name, $card_type, $access_type = 'entry') {
    global $conn;
    
    // Check if notifications table exists, if not create it
    if (!tableExists('notifications')) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `notification_id` int(11) NOT NULL AUTO_INCREMENT,
                `notification_type` enum('unauthorized','buzzer','system') DEFAULT 'unauthorized',
                `card_uid` varchar(20) NOT NULL,
                `user_name` varchar(100) DEFAULT NULL,
                `card_type` varchar(20) DEFAULT NULL,
                `reason` text DEFAULT NULL,
                `access_type` enum('entry','exit') DEFAULT 'entry',
                `status` enum('unread','read') DEFAULT 'unread',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `read_at` datetime DEFAULT NULL,
                `expires_at` datetime NOT NULL,
                PRIMARY KEY (`notification_id`),
                KEY `idx_status` (`status`),
                KEY `idx_created` (`created_at`),
                KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (
            notification_type, card_uid, user_name, card_type, reason, access_type, 
            status, created_at, expires_at
        ) VALUES ('unauthorized', ?, ?, ?, ?, ?, 'unread', NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");
    $stmt->bind_param("sssss", $uid, $user_name, $card_type, $reason, $access_type);
    
    if ($stmt->execute()) {
        $notif_id = $conn->insert_id;
        $stmt->close();
        error_log("✅ Notification created with ID: $notif_id");
        return $notif_id;
    }
    $stmt->close();
    return false;
}

// ============================================================
// MAIN SWITCH
// ============================================================
switch ($action) {
    
    // ============================================================
    // GET ALL AUTHORIZED CARDS
    // ============================================================
    case 'get_cards':
        $cards = [];
        
        $result = $conn->query("
            SELECT 
                c.card_uid, c.user_id, c.card_type, c.status,
                c.visitor_name, c.purpose_of_visit, c.resident_visited,
                u.full_name as user_name, u.room_number, u.student_id,
                ru.full_name as resident_visited_name
            FROM rfid_cards c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN users ru ON c.resident_visited = ru.user_id
            WHERE c.status = 'active'
            ORDER BY c.card_type, c.created_at DESC
        ");
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $displayName = '';
                $roomNumber = $row['room_number'] ?? 'N/A';
                $cardType = $row['card_type'] ?? 'resident';
                
                switch ($cardType) {
                    case 'visitor':
                        $displayName = !empty($row['visitor_name']) 
                            ? $row['visitor_name'] 
                            : ($row['user_name'] ?? 'Visitor');
                        if (!empty($row['resident_visited_name'])) {
                            $roomNumber = 'Visit: ' . $row['resident_visited_name'];
                        }
                        break;
                    case 'staff':
                        $displayName = $row['user_name'] ?? 'Staff';
                        break;
                    case 'resident':
                    default:
                        $displayName = $row['user_name'] ?? 'Resident';
                        break;
                }
                
                $cards[] = [
                    'uid' => $row['card_uid'],
                    'user_id' => $row['user_id'],
                    'user_name' => $displayName,
                    'room_number' => $roomNumber,
                    'student_id' => $row['student_id'] ?? 'N/A',
                    'card_type' => $cardType,
                    'status' => $row['status'] ?? 'active',
                    'visitor_name' => $row['visitor_name'] ?? '',
                    'purpose_of_visit' => $row['purpose_of_visit'] ?? '',
                    'resident_visited' => $row['resident_visited'] ?? null,
                    'resident_visited_name' => $row['resident_visited_name'] ?? ''
                ];
            }
            sendResponse(true, 'Cards loaded successfully', ['cards' => $cards]);
        } else {
            sendResponse(true, 'No active cards found', ['cards' => []]);
        }
        break;
    
    // ============================================================
    // LOG ACCESS - WITH OCCUPANCY TRACKING
    // ============================================================
    case 'log_access':
        // Get input data
        $uid = isset($input['uid']) ? strtoupper(trim($input['uid'])) : '';
        $type = isset($input['type']) ? $input['type'] : 'entry';
        $granted = isset($input['granted']) ? (bool)$input['granted'] : false;
        $power_source = isset($input['power_source']) ? $input['power_source'] : 'main';
        $user_name = isset($input['user_name']) ? $input['user_name'] : 'Unknown';
        $user_type = isset($input['user_type']) ? $input['user_type'] : 'unknown';
        $card_type = isset($input['card_type']) ? $input['card_type'] : 'unknown';
        $room_number = isset($input['room_number']) ? $input['room_number'] : 'N/A';
        
        if (empty($uid)) {
            sendResponse(false, 'Card UID required');
        }
        
        error_log("📥 Received: UID=$uid, Type=$type, Granted=" . ($granted ? 'true' : 'false'));
        
        // Initialize variables
        $user_id = null;
        $isAuthorized = false;
        $visitor_name = '';
        $purpose = '';
        $resident_visited = null;
        $card_exists = false;
        $alert_created = false;
        $full_name = '';
        $room_number_final = 'N/A';
        $card_type_final = 'unknown';
        
        // ------------------------------------------------------------
        // STEP 1: Check rfid_cards table
        // ------------------------------------------------------------
        $stmt = $conn->prepare("
            SELECT 
                c.user_id, c.card_type, c.status, c.visitor_name,
                c.purpose_of_visit, c.resident_visited,
                u.full_name, u.room_number, u.student_id,
                ru.full_name as resident_visited_name,
                ru.room_number as resident_room
            FROM rfid_cards c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN users ru ON c.resident_visited = ru.user_id
            WHERE c.card_uid = ?
        ");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $card_exists = true;
            $cardType = $row['card_type'] ?? 'unknown';
            $cardStatus = $row['status'] ?? 'inactive';
            $visitor_name = $row['visitor_name'] ?? '';
            $purpose = $row['purpose_of_visit'] ?? '';
            $resident_visited = $row['resident_visited'] ?? null;
            
            if ($cardStatus == 'active') {
                switch ($cardType) {
                    case 'visitor':
                        $visitorValid = false;
                        $visitorCheck = $conn->prepare("
                            SELECT validity_end, access_status 
                            FROM visitor_logs 
                            WHERE temporary_card_uid = ? 
                            AND access_status != 'denied'
                            ORDER BY created_at DESC LIMIT 1
                        ");
                        $visitorCheck->bind_param("s", $uid);
                        $visitorCheck->execute();
                        $visitorResult = $visitorCheck->get_result();
                        
                        if ($visitorRow = $visitorResult->fetch_assoc()) {
                            $today = date('Y-m-d');
                            if ($visitorRow['validity_end'] >= $today) {
                                $visitorValid = true;
                            }
                        }
                        $visitorCheck->close();
                        
                        if ($visitorValid) {
                            $full_name = !empty($visitor_name) 
                                ? $visitor_name 
                                : ($row['full_name'] ?? 'Visitor');
                            $room_number_final = !empty($row['resident_visited_name']) 
                                ? 'Visit: ' . $row['resident_visited_name'] 
                                : ($row['resident_room'] ?? 'N/A');
                            $user_id = $row['user_id'];
                            $card_type_final = 'visitor';
                            $isAuthorized = true;
                            $granted = true;
                        } else {
                            $full_name = !empty($visitor_name) 
                                ? $visitor_name . ' (Expired)' 
                                : 'Visitor (Expired)';
                            $card_type_final = 'visitor';
                            $isAuthorized = false;
                            $granted = false;
                        }
                        break;
                        
                    case 'staff':
                        $full_name = $row['full_name'] ?? 'Staff';
                        $room_number_final = $row['room_number'] ?? 'Staff Area';
                        $user_id = $row['user_id'];
                        $card_type_final = 'staff';
                        $isAuthorized = true;
                        $granted = true;
                        break;
                        
                    case 'resident':
                    default:
                        $full_name = $row['full_name'] ?? 'Resident';
                        $room_number_final = $row['room_number'] ?? 'N/A';
                        $user_id = $row['user_id'];
                        $card_type_final = 'resident';
                        $isAuthorized = true;
                        $granted = true;
                        break;
                }
            } else {
                $full_name = 'Inactive Card';
                $card_type_final = $row['card_type'] ?? 'unknown';
                $isAuthorized = false;
                $granted = false;
            }
        }
        $stmt->close();
        
        // ------------------------------------------------------------
        // STEP 2: If not found in rfid_cards, check visitor_logs
        // ------------------------------------------------------------
        if (!$card_exists || $full_name == 'Unknown' || $full_name == 'Inactive Card') {
            $stmt = $conn->prepare("
                SELECT 
                    v.visitor_name, v.resident_visited, v.purpose_of_visit,
                    v.validity_start, v.validity_end, v.temporary_card_uid,
                    v.access_status, u.full_name as resident_name, u.room_number as resident_room
                FROM visitor_logs v
                LEFT JOIN users u ON v.resident_visited = u.user_id
                WHERE v.temporary_card_uid = ? 
                AND v.access_status != 'denied'
                ORDER BY v.created_at DESC LIMIT 1
            ");
            $stmt->bind_param("s", $uid);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $card_exists = true;
                $today = date('Y-m-d');
                if ($row['validity_end'] >= $today) {
                    $full_name = $row['visitor_name'] . ' (Visitor)';
                    $room_number_final = $row['resident_room'] ?? 'N/A';
                    $card_type_final = 'visitor';
                    $visitor_name = $row['visitor_name'];
                    $purpose = $row['purpose_of_visit'] ?? '';
                    $isAuthorized = true;
                    $granted = true;
                } else {
                    $full_name = $row['visitor_name'] . ' (Expired)';
                    $card_type_final = 'visitor';
                    $isAuthorized = false;
                    $granted = false;
                }
            }
            $stmt->close();
        }
        
        // ------------------------------------------------------------
        // STEP 3: If card doesn't exist at all, it's unauthorized
        // ------------------------------------------------------------
        if (!$card_exists) {
            $granted = false;
            $isAuthorized = false;
            $full_name = 'Unknown Card';
            $card_type_final = 'unknown';
        }
        
        // ------------------------------------------------------------
        // INSERT ACCESS LOG
        // ------------------------------------------------------------
        $status = $granted ? 'granted' : 'denied';
        $alert_triggered = $granted ? 0 : 1;
        
        $stmt = $conn->prepare("
            INSERT INTO access_logs (
                card_uid, access_status, access_type, user_id, alert_triggered, power_source, timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sssiss", $uid, $status, $type, $user_id, $alert_triggered, $power_source);
        
        if ($stmt->execute()) {
            error_log("✅ Access log inserted for UID: $uid, Status: $status");
            
            // ============================================================
            // UPDATE OCCUPANCY - ENTRY = ADD, EXIT = REMOVE
            // ============================================================
            if ($granted && $user_id) {
                $action_type = ($type == 'entry') ? 'entry' : 'exit';
                updateOccupancy($user_id, $uid, $full_name, $room_number_final, $card_type_final, $action_type);
            }
            
            // ------------------------------------------------------------
            // CREATE ALERT FOR DENIED ACCESS
            // ------------------------------------------------------------
            if (!$granted) {
                $reason = '';
                if ($full_name == 'Unknown Card') {
                    $reason = 'Unknown card detected: ' . $uid;
                } elseif (strpos($full_name, 'Expired') !== false) {
                    $reason = 'Expired visitor card: ' . $uid;
                } elseif ($full_name == 'Inactive Card') {
                    $reason = 'Inactive card detected: ' . $uid;
                } else {
                    $reason = 'Unauthorized access attempt by: ' . $full_name;
                }
                
                $displayName = $full_name;
                if ($card_type_final == 'visitor' && !empty($visitor_name)) {
                    $displayName = $visitor_name . ' (Visitor)';
                }
                
                // Insert into alert_logs
                $stmt2 = $conn->prepare("
                    INSERT INTO alert_logs (
                        card_uid, alert_type, reason, delivery_status, timestamp,
                        access_type, user_name, card_type
                    ) VALUES (?, 'unauthorized', ?, 'pending', NOW(), ?, ?, ?)
                ");
                $stmt2->bind_param("sssss", $uid, $reason, $type, $displayName, $card_type_final);
                
                if ($stmt2->execute()) {
                    $alert_id = $conn->insert_id;
                    $alert_created = true;
                    
                    // CREATE REAL-TIME NOTIFICATION
                    $notif_id = createAlertNotification($uid, $reason, $displayName, $card_type_final, $type);
                    
                    error_log("✅ Alert created with ID: " . $alert_id . " for card: " . $uid);
                    if ($notif_id) {
                        error_log("✅ Notification created with ID: " . $notif_id);
                    }
                } else {
                    error_log("❌ Failed to create alert: " . $stmt2->error);
                }
                $stmt2->close();
                
                // Also insert into security_logs if table exists
                if (tableExists('security_logs')) {
                    $stmt3 = $conn->prepare("
                        INSERT INTO security_logs (
                            event_type, card_uid, user_name, details, timestamp
                        ) VALUES ('unauthorized_access', ?, ?, ?, NOW())
                    ");
                    $details = $reason . ' | Type: ' . $type;
                    $stmt3->bind_param("sss", $uid, $displayName, $details);
                    $stmt3->execute();
                    $stmt3->close();
                }
            }
            
            // If visitor granted, update visitor_logs
            if ($card_type_final == 'visitor' && $granted) {
                $stmt5 = $conn->prepare("
                    UPDATE visitor_logs 
                    SET access_status = 'granted', entry_timestamp = NOW()
                    WHERE temporary_card_uid = ?
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt5->bind_param("s", $uid);
                $stmt5->execute();
                $stmt5->close();
            }
            
            sendResponse(true, 'Access logged successfully', [
                'uid' => $uid,
                'type' => $type,
                'granted' => $granted,
                'user_id' => $user_id,
                'user_name' => $full_name,
                'room_number' => $room_number_final,
                'card_type' => $card_type_final,
                'is_authorized' => $isAuthorized,
                'visitor_name' => $visitor_name,
                'purpose' => $purpose,
                'alert_created' => $alert_created,
                'card_exists' => $card_exists
            ]);
        } else {
            error_log("❌ Failed to insert access log: " . $stmt->error);
            sendResponse(false, 'Failed to log access: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // SEND ALERT FROM ESP
    // ============================================================
    case 'send_alert':
        $uid = isset($input['uid']) ? strtoupper(trim($input['uid'])) : '';
        $reason = isset($input['reason']) ? trim($input['reason']) : 'Unauthorized access attempt';
        $alert_type = isset($input['alert_type']) ? $input['alert_type'] : 'unauthorized';
        $access_type = isset($input['access_type']) ? $input['access_type'] : 'entry';
        
        if (empty($uid)) {
            sendResponse(false, 'Card UID required');
        }
        
        $user_name = 'Unknown';
        $card_type = 'unknown';
        $visitor_name = '';
        
        $stmt = $conn->prepare("
            SELECT c.card_type, c.visitor_name, u.full_name 
            FROM rfid_cards c
            LEFT JOIN users u ON c.user_id = u.user_id
            WHERE c.card_uid = ?
        ");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $card_type = $row['card_type'] ?? 'unknown';
            $visitor_name = $row['visitor_name'] ?? '';
            if ($card_type == 'visitor' && !empty($visitor_name)) {
                $user_name = $visitor_name . ' (Visitor)';
            } else {
                $user_name = $row['full_name'] ?? 'Unknown';
            }
        }
        $stmt->close();
        
        if ($user_name == 'Unknown') {
            $stmt = $conn->prepare("
                SELECT visitor_name 
                FROM visitor_logs 
                WHERE temporary_card_uid = ? 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->bind_param("s", $uid);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $user_name = $row['visitor_name'] . ' (Visitor)';
                $card_type = 'visitor';
            }
            $stmt->close();
        }
        
        $stmt = $conn->prepare("
            INSERT INTO alert_logs (
                card_uid, alert_type, reason, user_name, card_type, access_type,
                delivery_status, timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("ssssss", $uid, $alert_type, $reason, $user_name, $card_type, $access_type);
        
        if ($stmt->execute()) {
            $alert_id = $conn->insert_id;
            createAlertNotification($uid, $reason, $user_name, $card_type, $access_type);
            sendResponse(true, 'Alert sent successfully', [
                'uid' => $uid,
                'reason' => $reason,
                'user_name' => $user_name,
                'card_type' => $card_type,
                'alert_id' => $alert_id
            ]);
        } else {
            sendResponse(false, 'Failed to send alert: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // GET CURRENT OCCUPANCY
    // ============================================================
    case 'get_occupancy':
        createOccupancyTable();
        $occupants = getCurrentOccupancy();
        sendResponse(true, 'Occupancy retrieved', [
            'occupants' => $occupants,
            'total' => count($occupants)
        ]);
        break;
    
    // ============================================================
    // GET REAL-TIME NOTIFICATIONS
    // ============================================================
    case 'get_notifications':
        $limit = isset($input['limit']) ? (int)$input['limit'] : 20;
        $status = isset($input['status']) ? $input['status'] : '';
        
        $query = "
            SELECT n.*, c.card_type as rfid_card_type, c.visitor_name
            FROM notifications n
            LEFT JOIN rfid_cards c ON n.card_uid = c.card_uid
            WHERE 1=1
        ";
        
        if (!empty($status)) {
            $query .= " AND n.status = '$status'";
        }
        $query .= " AND n.expires_at > NOW()";
        $query .= " ORDER BY 
            CASE WHEN n.status = 'unread' THEN 0 ELSE 1 END,
            n.created_at DESC LIMIT $limit";
        
        $result = $conn->query($query);
        $notifications = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
        }
        
        $countResult = $conn->query("
            SELECT COUNT(*) as count FROM notifications 
            WHERE status = 'unread' AND expires_at > NOW()
        ");
        $unreadCount = 0;
        if ($countResult && $row = $countResult->fetch_assoc()) {
            $unreadCount = (int)$row['count'];
        }
        
        sendResponse(true, 'Notifications retrieved', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'total' => count($notifications)
        ]);
        break;
    
    // ============================================================
    // MARK NOTIFICATION AS READ
    // ============================================================
    case 'mark_read':
        $notif_id = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;
        
        if ($notif_id <= 0) {
            sendResponse(false, 'Invalid notification ID');
        }
        
        $stmt = $conn->prepare("
            UPDATE notifications SET status = 'read', read_at = NOW() WHERE notification_id = ?
        ");
        $stmt->bind_param("i", $notif_id);
        
        if ($stmt->execute()) {
            sendResponse(true, 'Notification marked as read');
        } else {
            sendResponse(false, 'Failed to mark as read: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // MARK ALL NOTIFICATIONS AS READ
    // ============================================================
    case 'mark_all_read':
        $stmt = $conn->prepare("
            UPDATE notifications SET status = 'read', read_at = NOW() WHERE status = 'unread'
        ");
        
        if ($stmt->execute()) {
            $count = $stmt->affected_rows;
            sendResponse(true, "Marked $count notifications as read");
        } else {
            sendResponse(false, 'Failed to mark all as read: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // GET ALERTS
    // ============================================================
    case 'get_alerts':
        $limit = isset($input['limit']) ? (int)$input['limit'] : 100;
        $status = isset($input['status']) ? $input['status'] : '';
        $type = isset($input['type']) ? $input['type'] : '';
        
        $query = "
            SELECT al.*, c.card_type as rfid_card_type, c.visitor_name,
                   u.full_name as resident_name, u.room_number
            FROM alert_logs al
            LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
            LEFT JOIN users u ON c.user_id = u.user_id
            WHERE 1=1
        ";
        
        if (!empty($status)) {
            $query .= " AND al.delivery_status = '$status'";
        }
        if (!empty($type)) {
            $query .= " AND al.alert_type = '$type'";
        }
        
        $query .= " ORDER BY 
            CASE WHEN al.delivery_status = 'pending' THEN 0 ELSE 1 END,
            al.timestamp DESC LIMIT $limit";
        
        $result = $conn->query($query);
        $alerts = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $alerts[] = $row;
            }
        }
        
        sendResponse(true, 'Alerts retrieved', [
            'alerts' => $alerts,
            'total' => count($alerts)
        ]);
        break;
    
    // ============================================================
    // GET PENDING ALERT COUNT
    // ============================================================
    case 'get_alert_count':
        $result = $conn->query("
            SELECT COUNT(*) as pending FROM alert_logs WHERE delivery_status = 'pending'
        ");
        $pending = 0;
        if ($result && $row = $result->fetch_assoc()) {
            $pending = (int)$row['pending'];
        }
        
        $notifResult = $conn->query("
            SELECT COUNT(*) as unread FROM notifications 
            WHERE status = 'unread' AND expires_at > NOW()
        ");
        $unread = 0;
        if ($notifResult && $row = $notifResult->fetch_assoc()) {
            $unread = (int)$row['unread'];
        }
        
        sendResponse(true, 'Alert count retrieved', [
            'pending_alerts' => $pending,
            'unread_notifications' => $unread,
            'total' => $pending + $unread
        ]);
        break;
    
    // ============================================================
    // RESOLVE ALERT
    // ============================================================
    case 'resolve_alert':
        $alert_id = isset($input['alert_id']) ? (int)$input['alert_id'] : 0;
        
        if ($alert_id <= 0) {
            sendResponse(false, 'Invalid alert ID');
        }
        
        $stmt = $conn->prepare("
            UPDATE alert_logs SET delivery_status = 'resolved', resolved_at = NOW() WHERE alert_id = ?
        ");
        $stmt->bind_param("i", $alert_id);
        
        if ($stmt->execute()) {
            sendResponse(true, 'Alert resolved successfully');
        } else {
            sendResponse(false, 'Failed to resolve alert: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // DELETE ALERT
    // ============================================================
    case 'delete_alert':
        $alert_id = isset($input['alert_id']) ? (int)$input['alert_id'] : 0;
        
        if ($alert_id <= 0) {
            sendResponse(false, 'Invalid alert ID');
        }
        
        $stmt = $conn->prepare("DELETE FROM alert_logs WHERE alert_id = ?");
        $stmt->bind_param("i", $alert_id);
        
        if ($stmt->execute()) {
            sendResponse(true, 'Alert deleted successfully');
        } else {
            sendResponse(false, 'Failed to delete alert: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // GET ALERT STATS
    // ============================================================
    case 'get_alert_stats':
        $stats = ['total' => 0, 'pending' => 0, 'resolved' => 0, 'unauthorized' => 0, 'today' => 0];
        
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
        
        sendResponse(true, 'Alert stats retrieved', ['stats' => $stats]);
        break;
    
    // ============================================================
    // GET UNAUTHORIZED ACCESS LOGS
    // ============================================================
    case 'get_unauthorized':
        $limit = isset($input['limit']) ? (int)$input['limit'] : 100;
        $date = isset($input['date']) ? $input['date'] : '';
        
        $query = "
            SELECT al.*, c.card_type, c.visitor_name, u.full_name as user_name, u.room_number
            FROM access_logs al
            LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
            LEFT JOIN users u ON c.user_id = u.user_id
            WHERE al.access_status = 'denied'
        ";
        
        if (!empty($date)) {
            $query .= " AND DATE(al.timestamp) = '$date'";
        }
        
        $query .= " ORDER BY al.timestamp DESC LIMIT $limit";
        
        $result = $conn->query($query);
        $logs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
        }
        
        sendResponse(true, 'Unauthorized logs retrieved', [
            'logs' => $logs,
            'total' => count($logs)
        ]);
        break;
    
    // ============================================================
    // RESOLVE ALL ALERTS
    // ============================================================
    case 'resolve_all_alerts':
        $stmt = $conn->prepare("
            UPDATE alert_logs SET delivery_status = 'resolved', resolved_at = NOW() WHERE delivery_status = 'pending'
        ");
        
        if ($stmt->execute()) {
            $count = $stmt->affected_rows;
            sendResponse(true, "Resolved $count alerts successfully");
        } else {
            sendResponse(false, 'Failed to resolve alerts: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // DELETE OLD ALERTS
    // ============================================================
    case 'delete_old_alerts':
        $days = isset($input['days']) ? (int)$input['days'] : 30;
        
        $stmt = $conn->prepare("
            DELETE FROM alert_logs 
            WHERE DATE(timestamp) < DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND delivery_status = 'resolved'
        ");
        $stmt->bind_param("i", $days);
        
        if ($stmt->execute()) {
            $count = $stmt->affected_rows;
            sendResponse(true, "Deleted $count old alerts");
        } else {
            sendResponse(false, 'Failed to delete old alerts: ' . $stmt->error);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // REGISTER VISITOR
    // ============================================================
    case 'register_visitor':
        $uid = isset($input['uid']) ? strtoupper(trim($input['uid'])) : '';
        $visitor_name = isset($input['visitor_name']) ? trim($input['visitor_name']) : '';
        $resident_visited = isset($input['resident_visited']) ? (int)$input['resident_visited'] : 0;
        $purpose = isset($input['purpose']) ? trim($input['purpose']) : '';
        $validity_days = isset($input['validity_days']) ? (int)$input['validity_days'] : 7;
        
        if (empty($uid) || empty($visitor_name) || $resident_visited <= 0) {
            sendResponse(false, 'UID, Visitor Name, and Resident visited are required');
        }
        
        $conn->begin_transaction();
        
        try {
            $check = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ?");
            $check->bind_param("s", $uid);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                sendResponse(false, 'Card UID already exists');
            }
            $check->close();
            
            $stmt = $conn->prepare("
                INSERT INTO rfid_cards (
                    card_uid, card_type, status, visitor_name, resident_visited, purpose_of_visit,
                    issued_date, expiry_date
                ) VALUES (?, 'visitor', 'active', ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY))
            ");
            $stmt->bind_param("ssisi", $uid, $visitor_name, $resident_visited, $purpose, $validity_days);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to register card: ' . $stmt->error);
            }
            $stmt->close();
            
            $stmt2 = $conn->prepare("
                INSERT INTO visitor_logs (
                    visitor_name, resident_visited, purpose_of_visit, temporary_card_uid,
                    validity_start, validity_end, access_status, created_at
                ) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'pending', NOW())
            ");
            $stmt2->bind_param("sisis", $visitor_name, $resident_visited, $purpose, $uid, $validity_days);
            
            if (!$stmt2->execute()) {
                throw new Exception('Failed to create visitor log: ' . $stmt2->error);
            }
            $stmt2->close();
            
            $conn->commit();
            
            sendResponse(true, 'Visitor card registered successfully', [
                'uid' => $uid,
                'visitor_name' => $visitor_name,
                'validity_days' => $validity_days
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            sendResponse(false, $e->getMessage());
        }
        break;
    
    // ============================================================
    // GET OCCUPANCY STATS
    // ============================================================
    case 'get_occupancy_stats':
        createOccupancyTable();
        
        $inside = 0;
        $outside = 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM current_occupancy WHERE status = 'inside'");
        if ($result && $row = $result->fetch_assoc()) {
            $inside = (int)$row['count'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM current_occupancy WHERE status = 'outside'");
        if ($result && $row = $result->fetch_assoc()) {
            $outside = (int)$row['count'];
        }
        
        sendResponse(true, 'Occupancy stats retrieved', [
            'inside' => $inside,
            'outside' => $outside,
            'total' => $inside + $outside
        ]);
        break;
    
    // ============================================================
    // DEFAULT
    // ============================================================
    default:
        sendResponse(false, 'Invalid action. Available actions: get_cards, log_access, send_alert, get_occupancy, get_occupancy_stats, get_notifications, mark_read, mark_all_read, get_alerts, resolve_alert, delete_alert, get_alert_stats, get_unauthorized, resolve_all_alerts, delete_old_alerts, register_visitor, get_alert_count');
        break;
}

$conn->close();
?>
