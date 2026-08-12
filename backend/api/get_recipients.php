<?php
/**
 * API: Get Recipients for Email
 */

session_start();
require_once '../../backend/config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();
$type = $_GET['type'] ?? 'staff';
$recipients = [];

if ($type === 'staff') {
    $result = $conn->query("SELECT staff_id as id, full_name as name, email FROM staff_users WHERE is_active = 1 ORDER BY full_name");
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row;
    }
} else {
    $result = $conn->query("SELECT user_id as id, full_name as name, email FROM users WHERE status = 'active' ORDER BY full_name");
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row;
    }
}

echo json_encode(['success' => true, 'recipients' => $recipients]);
?>