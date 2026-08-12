<?php
/**
 * Tap-and-Go Doorlock - Concerns API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once '../../config/config.php';
require_once '../../helpers/functions.php';

$conn = getDBConnection();

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'count':
        $status = isset($_GET['status']) ? $_GET['status'] : 'pending';
        $result = $conn->query("SELECT COUNT(*) as count FROM student_concerns WHERE status = '$status'");
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'count' => (int)$row['count']
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}
?>