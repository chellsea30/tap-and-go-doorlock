<?php
/**
 * Tap-and-Go Doorlock - Get Current Occupancy API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

session_start();

// Check authentication
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();

$result = $conn->query("
    SELECT * FROM current_occupancy 
    WHERE status = 'inside' 
    ORDER BY entry_time DESC
");

$occupants = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $occupants[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'occupants' => $occupants,
    'total' => count($occupants)
]);

$conn->close();
?>
