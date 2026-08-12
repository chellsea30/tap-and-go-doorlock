<?php
// test_api.php - I-save sa root folder

echo "<h1>🔍 API Test</h1>";

$api_url = "http://localhost/tap-and-go-doorlock/backend/api/v1/rfid_access.php";

// Test get_alert_count
echo "<h2>1. Test get_alert_count</h2>";
$data = ['action' => 'get_alert_count'];
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";

// Test get_notifications
echo "<h2>2. Test get_notifications</h2>";
$data = ['action' => 'get_notifications', 'limit' => 5];
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>