<?php
/**
 * Tap-and-Go Doorlock - Get Latest RFID Scan
 * Location: backend/api/get_latest_scan.php
 * 
 * Ginagamit ito ng register-rfid.php para sa AUTO-FILL ng card UID
 * kapag nag-tap ng card sa second RFID reader.
 * 
 * FLOW:
 * 1. Web page sends "start_scan" action → ESP32 starts scanning
 * 2. ESP32 detects card → POST to "save_scan" action with UID
 * 3. Web page polls "get_latest_scan" → returns UID
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

$conn = getDBConnection();

// ============================================================
// CREATE TABLE IF NOT EXISTS
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS `live_scan` (
        `scan_id` int(11) NOT NULL AUTO_INCREMENT,
        `card_uid` varchar(20) NOT NULL,
        `reader_id` tinyint(4) DEFAULT 1,
        `admin_id` int(11) DEFAULT NULL,
        `status` enum('pending','used') DEFAULT 'pending',
        `scanned_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`scan_id`),
        KEY `idx_status` (`status`),
        KEY `idx_scanned` (`scanned_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ============================================================
// CLEANUP OLD SCANS (older than 5 minutes)
// ============================================================
$conn->query("DELETE FROM live_scan WHERE scanned_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

// ============================================================
// HANDLE ACTIONS
// ============================================================
switch ($action) {
    
    // ============================================================
    // ESP32 POSTS A NEW SCAN
    // ============================================================
    case 'save_scan':
        $uid = isset($input['uid']) ? strtoupper(trim($input['uid'])) : '';
        $reader_id = isset($input['reader_id']) ? (int)$input['reader_id'] : 1;
        
        if (empty($uid)) {
            echo json_encode(['success' => false, 'message' => 'Card UID required']);
            exit();
        }
        
        // Insert new scan
        $stmt = $conn->prepare("
            INSERT INTO live_scan (card_uid, reader_id, status, scanned_at)
            VALUES (?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("si", $uid, $reader_id);
        
        if ($stmt->execute()) {
            $scan_id = $conn->insert_id;
            error_log("📇 New RFID scan: $uid (Reader $reader_id) - Scan ID: $scan_id");
            echo json_encode([
                'success' => true,
                'message' => 'Scan saved',
                'scan_id' => $scan_id,
                'uid' => $uid
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save scan']);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // WEB PAGE POLLS FOR LATEST SCAN
    // ============================================================
    case 'get_latest_scan':
        $admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
        
        // Get latest pending scan (within last 2 minutes)
        $result = $conn->query("
            SELECT scan_id, card_uid, reader_id, scanned_at
            FROM live_scan
            WHERE status = 'pending'
            AND scanned_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            ORDER BY scanned_at DESC
            LIMIT 1
        ");
        
        if ($result && $row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'has_scan' => true,
                'scan' => [
                    'scan_id' => (int)$row['scan_id'],
                    'uid' => $row['card_uid'],
                    'reader_id' => (int)$row['reader_id'],
                    'scanned_at' => $row['scanned_at']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_scan' => false
            ]);
        }
        break;
    
    // ============================================================
    // MARK SCAN AS USED (after registration)
    // ============================================================
    case 'mark_used':
        $scan_id = isset($input['scan_id']) ? (int)$input['scan_id'] : 0;
        
        if ($scan_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid scan ID']);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE live_scan SET status = 'used' WHERE scan_id = ?");
        $stmt->bind_param("i", $scan_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Scan marked as used']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark scan']);
        }
        $stmt->close();
        break;
    
    // ============================================================
    // CLEAR ALL PENDING SCANS (when admin closes modal)
    // ============================================================
    case 'clear_scans':
        $conn->query("DELETE FROM live_scan WHERE status = 'pending'");
        echo json_encode(['success' => true, 'message' => 'Scans cleared']);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
