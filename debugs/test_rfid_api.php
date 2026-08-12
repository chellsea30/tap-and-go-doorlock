<?php
/**
 * Test RFID API - Run this to check if API is working
 */

echo "<h1>RFID API Test</h1>";

$url = "http://localhost/tap-and-go-doorlock/backend/api/v1/rfid_access.php";

// Test 1: Get Cards
echo "<h2>Test 1: Get Cards</h2>";
$data = ['action' => 'get_cards'];
$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data),
    ],
];
$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "<pre>" . print_r(json_decode($result, true), true) . "</pre>";

// Test 2: Log Access
echo "<h2>Test 2: Log Access</h2>";
$data = [
    'action' => 'log_access',
    'uid' => '7387F904',
    'type' => 'entry',
    'granted' => false,
    'power_source' => 'main'
];
$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data),
    ],
];
$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "<pre>" . print_r(json_decode($result, true), true) . "</pre>";

// Check database
echo "<h2>Database Check</h2>";
require_once 'backend/config/config.php';
$conn = getDBConnection();

$result = $conn->query("SELECT * FROM access_logs ORDER BY timestamp DESC LIMIT 5");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Card UID</th><th>Status</th><th>Type</th><th>User ID</th><th>Timestamp</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['log_id'] . "</td>";
        echo "<td>" . $row['card_uid'] . "</td>";
        echo "<td>" . $row['access_status'] . "</td>";
        echo "<td>" . $row['access_type'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['timestamp'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No logs found";
}