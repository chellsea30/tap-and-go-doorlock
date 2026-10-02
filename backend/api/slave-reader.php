<?php
/**
 * Slave Reader API endpoint.
 * Location: backend/api/slave-reader.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

header('Content-Type: application/json');

if (FORCE_HTTPS && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    http_response_code(403);
    echo json_encode(['error' => 'HTTPS required']);
    exit;
}

$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!defined('SLAVE_READER_API_KEY') || SLAVE_READER_API_KEY === '' ||
    !hash_equals(SLAVE_READER_API_KEY, $provided)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDBConnection();

    $res = $conn->query("
        SELECT
            u.user_id,
            u.full_name,
            u.student_id,
            u.room_number,
            u.profile_photo,
            c.card_uid,
            c.status AS card_status
        FROM users u
        LEFT JOIN rfid_cards c
            ON c.user_id = u.user_id AND c.status = 'active'
        WHERE u.approval_status = 'approved'
          AND u.status != 'deleted'
    ");

    $out = [];
    if ($res) while ($row = $res->fetch_assoc()) $out[] = $row;

    echo json_encode([
        'status'    => 'ok',
        'count'     => count($out),
        'timestamp' => date('c'),
        'residents' => $out,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
