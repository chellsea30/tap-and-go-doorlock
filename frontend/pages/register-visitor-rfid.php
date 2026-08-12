<?php
/**
 * Register Visitor RFID Card
 * Run this to register a visitor card
 */

require_once 'backend/config/config.php';
require_once 'backend/helpers/functions.php';

$conn = getDBConnection();

$card_uid = '0A1FD006';
$visitor_name = 'Test Visitor';
$resident_id = 1; // Change to actual resident ID
$purpose = 'Visit friend';

echo "<h1>Register Visitor Card</h1>";

// Check if card exists in rfid_cards
$check = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ?");
$check->bind_param("s", $card_uid);
$check->execute();
if ($check->get_result()->num_rows == 0) {
    // Insert card
    $stmt = $conn->prepare("
        INSERT INTO rfid_cards (card_uid, card_type, status, issued_date, expiry_date, user_id)
        VALUES (?, 'visitor', 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 WEEK), ?)
    ");
    $stmt->bind_param("si", $card_uid, $resident_id);
    if ($stmt->execute()) {
        echo "✅ Card registered: $card_uid<br>";
    } else {
        echo "❌ Failed to register card: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "✅ Card already exists: $card_uid<br>";
}
$check->close();

// Check if visitor log exists
$check = $conn->prepare("SELECT visitor_log_id FROM visitor_logs WHERE temporary_card_uid = ?");
$check->bind_param("s", $card_uid);
$check->execute();
if ($check->get_result()->num_rows == 0) {
    // Insert visitor log
    $stmt = $conn->prepare("
        INSERT INTO visitor_logs (
            visitor_name, resident_visited, purpose_of_visit, 
            temporary_card_uid, validity_start, validity_end, access_status, created_at
        ) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 WEEK), 'pending', NOW())
    ");
    $stmt->bind_param("siss", $visitor_name, $resident_id, $purpose, $card_uid);
    if ($stmt->execute()) {
        echo "✅ Visitor log created for: $visitor_name<br>";
    } else {
        echo "❌ Failed to create visitor log: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "✅ Visitor log already exists for: $card_uid<br>";
}
$check->close();

echo "<br><a href='frontend/pages/visitors.php'>Go to Visitors</a>";