<?php
/**
 * API: Check Pending Approvals Count
 * Location: frontend/pages/api/check_pending_approvals.php
 */

session_start();
require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE approval_status = 'pending' AND status != 'deleted'");
    $count = 0;
    if ($result && $row = $result->fetch_assoc()) {
        $count = (int)$row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'pending_count' => $count
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
