<?php
/**
 * Tap-and-Go Doorlock - Alerts API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/config.php';
require_once '../../helpers/functions.php';

$conn = getDBConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';

switch ($action) {
    case 'count':
        $result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = '$status'");
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'count' => (int)$row['count']]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}