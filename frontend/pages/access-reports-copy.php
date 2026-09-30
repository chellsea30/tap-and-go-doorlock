<?php
/**
 * Tap-and-Go Doorlock - Access Report Copy
 * PURE HTML TABLE - NO CSS - PLAIN BLACK AND WHITE
 * Location: frontend/pages/access-reports-copy.php
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// ============================================================
// GET FILTERS
// ============================================================
$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET ACCESS LOGS
// ============================================================
$logs = [];

$query = "
    SELECT 
        al.timestamp,
        u.full_name,
        rp.age,
        rp.gender,
        rp.birth_date,
        rp.home_address,
        u.contact_number
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE 1=1
";

if (!empty($dateFilter)) {
    $query .= " AND DATE(al.timestamp) = '$dateFilter'";
}

if (!empty($searchFilter)) {
    $query .= " AND (
        u.full_name LIKE '%$searchFilter%' 
        OR u.student_id LIKE '%$searchFilter%'
        OR al.card_uid LIKE '%$searchFilter%'
    )";
}

$query .= " ORDER BY al.timestamp DESC LIMIT 500";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

function showVal($value, $default = 'N/A') {
    if ($value !== null && $value !== '' && $value !== '0') {
        return htmlspecialchars(trim($value));
    }
    return $default;
}

function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    $ts = strtotime($date);
    if ($ts === false) return 'N/A';
    return date('M d, Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Report Copy</title>
</head>
<body>

<table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th align="left">Date / Time</th>
            <th align="left">Name</th>
            <th align="left">Age</th>
            <th align="left">Gender</th>
            <th align="left">Birthdate</th>
            <th align="left">Address</th>
            <th align="left">Contact Number</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="7" align="center">No access records found</td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <?php 
                            $timestamp = strtotime($log['timestamp']);
                            echo date('M d, Y', $timestamp) . ' ' . 
                                 strtolower(date('D', $timestamp)) . ' ' . 
                                 date('h:i A', $timestamp);
                        ?>
                    </td>
                    <td><?php echo showVal($log['full_name'] ?? null, 'Unknown'); ?></td>
                    <td><?php echo showVal($log['age'] ?? null); ?></td>
                    <td><?php echo showVal($log['gender'] ?? null); ?></td>
                    <td><?php echo formatDate($log['birth_date'] ?? null); ?></td>
                    <td><?php echo showVal($log['home_address'] ?? null); ?></td>
                    <td><?php echo showVal($log['contact_number'] ?? null); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
