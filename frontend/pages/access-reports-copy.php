<?php
/**
 * Tap-and-Go Doorlock - Access Report Copy
 * WITH PERIOD FILTER: Day | Week | Month | Year
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
// ✅ PERIOD FILTER SETUP
// ============================================================
$period = isset($_GET['period']) ? $_GET['period'] : 'day';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validate period
if (!in_array($period, ['day', 'week', 'month', 'year'])) {
    $period = 'day';
}

// ============================================================
// ✅ BUILD DATE RANGE BASED ON PERIOD
// ============================================================
$dateCondition = '';
$periodLabel = '';

switch ($period) {
    case 'day':
        // Today or selected date
        $dateCondition = "DATE(al.timestamp) = '$dateFilter'";
        $periodLabel = date('F d, Y', strtotime($dateFilter));
        break;

    case 'week':
        // Current week (Sunday to Saturday)
        $weekStart = date('Y-m-d', strtotime('sunday this week'));
        $weekEnd = date('Y-m-d', strtotime('saturday this week'));
        $dateCondition = "DATE(al.timestamp) BETWEEN '$weekStart' AND '$weekEnd'";
        $periodLabel = date('M d', strtotime($weekStart)) . ' - ' . date('M d, Y', strtotime($weekEnd));
        break;

    case 'month':
        // Current month
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $dateCondition = "DATE(al.timestamp) BETWEEN '$monthStart' AND '$monthEnd'";
        $periodLabel = date('F Y');
        break;

    case 'year':
        // Current year
        $yearStart = date('Y-01-01');
        $yearEnd = date('Y-12-31');
        $dateCondition = "DATE(al.timestamp) BETWEEN '$yearStart' AND '$yearEnd'";
        $periodLabel = date('Y');
        break;
}

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
    WHERE $dateCondition
";

if (!empty($searchFilter)) {
    $query .= " AND (
        u.full_name LIKE '%$searchFilter%' 
        OR u.student_id LIKE '%$searchFilter%'
        OR al.card_uid LIKE '%$searchFilter%'
    )";
}

$query .= " ORDER BY al.timestamp DESC LIMIT 1000";

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
    <style>
        /* Minimal styles para lang sa period buttons */
        body { font-family: Arial, sans-serif; background: #0d1117; color: #e6edf3; padding: 20px; margin: 0; }
        .period-buttons { display: flex; gap: 10px; margin-bottom: 15px; }
        .period-btn { 
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 8px;
            background: #1c2333; border: 1px solid #30363d;
            color: #8b949e; text-decoration: none; font-size: 14px;
            font-family: Arial, sans-serif;
        }
        .period-btn:hover { background: #21283a; color: #e6edf3; }
        .period-btn.active { background: #1f4f9e; color: #ffffff; border-color: #1f4f9e; }
        .period-btn i { font-style: normal; }
        .info { margin-bottom: 15px; color: #8b949e; font-size: 13px; }

        /* Plain table lang - black and white */
        table { width: 100%; border-collapse: collapse; background: #ffffff; color: #000000; }
        th, td { border: 1px solid #000000; padding: 6px 8px; text-align: left; font-size: 13px; }
        th { background: #ffffff; font-weight: bold; }
    </style>
</head>
<body>

<!-- ============================================================
     PERIOD FILTER BUTTONS
     ============================================================ -->
<div class="period-buttons">
    <a href="?period=day&date=<?php echo $dateFilter; ?>" 
       class="period-btn <?php echo $period === 'day' ? 'active' : ''; ?>">
        <i>📅</i> Day
    </a>
    <a href="?period=week<?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?>" 
       class="period-btn <?php echo $period === 'week' ? 'active' : ''; ?>">
        <i>🗓</i> Week
    </a>
    <a href="?period=month<?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?>" 
       class="period-btn <?php echo $period === 'month' ? 'active' : ''; ?>">
        <i>🗓</i> Month
    </a>
    <a href="?period=year<?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?>" 
       class="period-btn <?php echo $period === 'year' ? 'active' : ''; ?>">
        <i>📆</i> Year
    </a>
</div>

<!-- ============================================================
     PERIOD INFO
     ============================================================ -->
<div class="info">
    Showing: <strong><?php echo $periodLabel; ?></strong> &nbsp;|&nbsp; 
    Total Records: <strong><?php echo count($logs); ?></strong>
</div>

<!-- ============================================================
     PLAIN TABLE - BLACK AND WHITE
     ============================================================ -->
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
