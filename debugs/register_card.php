<?php
/**
 * Quick RFID Card Registration Script
 * RUN ONCE TO REGISTER YOUR CARD
 */

require_once 'backend/config/config.php';

$conn = getDBConnection();

$card_uid = '7387F904';  // CHANGE THIS TO YOUR CARD UID
$user_id = 1;            // CHANGE THIS TO YOUR USER ID

echo "<h1>RFID Card Registration</h1>";

// Check if card already exists
$check = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ?");
$check->bind_param("s", $card_uid);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "❌ Card UID <strong>$card_uid</strong> already exists!<br>";
    
    // Show current status
    $stmt = $conn->prepare("SELECT * FROM rfid_cards WHERE card_uid = ?");
    $stmt->bind_param("s", $card_uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    
    echo "<pre>";
    print_r($row);
    echo "</pre>";
    
    echo "<br>To activate it, run: UPDATE rfid_cards SET status = 'active' WHERE card_uid = '$card_uid'";
    
} else {
    // Insert card
    $stmt = $conn->prepare("
        INSERT INTO rfid_cards (card_uid, user_id, issued_date, expiry_date, card_type, status) 
        VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'resident', 'active')
    ");
    $stmt->bind_param("si", $card_uid, $user_id);
    
    if ($stmt->execute()) {
        echo "✅ Card UID <strong>$card_uid</strong> registered successfully!<br>";
        echo "📌 Assigned to User ID: $user_id<br>";
        echo "📌 Status: Active<br>";
        echo "📌 Expires: " . date('Y-m-d', strtotime('+1 year')) . "<br>";
    } else {
        echo "❌ Failed to register: " . $stmt->error . "<br>";
    }
}

echo "<br><br>";
echo "<strong>Get all users:</strong><br>";
$users = $conn->query("SELECT user_id, full_name, room_number FROM users WHERE status = 'active'");
if ($users) {
    while ($u = $users->fetch_assoc()) {
        echo "ID: " . $u['user_id'] . " - " . $u['full_name'] . " (Room " . $u['room_number'] . ")<br>";
    }
}

echo "<br><a href='frontend/pages/register-rfid.php'>Go to Register RFID Page</a>";