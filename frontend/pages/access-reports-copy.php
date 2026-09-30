<?php
/**
 * Tap-and-Go Doorlock - Access Report Copy
 * WITH PERIOD FILTER (Day | Week | Month | Year)
 * WITH SHOW ENTRIES PAGINATION
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

if (!in_array($period, ['day', 'week', 'month', 'year'])) {
    $period = 'day';
}

// ============================================================
// ✅ SHOW ENTRIES PAGINATION
// ============================================================
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPageOptions = [5, 10, 25, 50, 100, 200];

if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}
if ($page < 1) $page = 1;

// ============================================================
// ✅ BUILD DATE RANGE BASED ON PERIOD
// ============================================================
$dateCondition = '';
$periodLabel = '';

switch ($period) {
    case 'day':
        $dateCondition = "DATE(al.timestamp) = '$dateFilter'";
        $periodLabel = date('F d, Y', strtotime($dateFilter));
        break;

    case 'week':
        $weekStart = date('Y-m-d', strtotime('sunday this week'));
        $weekEnd = date('Y-m-d', strtotime('saturday this week'));
        $dateCondition = "DATE(al.timestamp) BETWEEN '$weekStart' AND '$weekEnd'";
        $periodLabel = date('M d', strtotime($weekStart)) . ' - ' . date('M d, Y', strtotime($weekEnd));
        break;

    case 'month':
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $dateCondition = "DATE(al.timestamp) BETWEEN '$monthStart' AND '$monthEnd'";
        $periodLabel = date('F Y');
        break;

    case 'year':
        $yearStart = date('Y-01-01');
        $yearEnd = date('Y-12-31');
        $dateCondition = "DATE(al.timestamp) BETWEEN '$yearStart' AND '$yearEnd'";
        $periodLabel = date('Y');
        break;
}

// ============================================================
// ✅ COUNT TOTAL RECORDS FOR PAGINATION
// ============================================================
$countQuery = "
    SELECT COUNT(*) as total
    FROM access_logs al
    LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE $dateCondition
";

if (!empty($searchFilter)) {
    $countQuery .= " AND (
        u.full_name LIKE '%$searchFilter%' 
        OR u.student_id LIKE '%$searchFilter%'
        OR al.card_uid LIKE '%$searchFilter%'
    )";
}

$countResult = $conn->query($countQuery);
$totalRecords = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalRecords = (int)$row['total'];
}

// Calculate pagination
$totalPages = ceil($totalRecords / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

// ============================================================
// GET ACCESS LOGS WITH PAGINATION
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

$query .= " ORDER BY al.timestamp DESC LIMIT $perPage OFFSET $offset";

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

// Helper: Build URL with current params
function buildUrl($params = []) {
    $defaults = [
        'period' => $_GET['period'] ?? 'day',
        'date' => $_GET['date'] ?? date('Y-m-d'),
        'search' => $_GET['search'] ?? '',
        'per_page' => $_GET['per_page'] ?? 10,
        'page' => $_GET['page'] ?? 1
    ];
    
    $merged = array_merge($defaults, $params);
    $merged = array_filter($merged, function($v) { return $v !== '' && $v !== null; });
    
    return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Report Copy</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0d1117; color: #e6edf3; padding: 20px; margin: 0; }
        
        /* Period Buttons */
        .period-buttons { display: flex; gap: 10px; margin-bottom: 15px; }
        .period-btn { 
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 8px;
            background: #1c2333; border: 1px solid #30363d;
            color: #8b949e; text-decoration: none; font-size: 14px;
        }
        .period-btn:hover { background: #21283a; color: #e6edf3; }
        .period-btn.active { background: #1f4f9e; color: #ffffff; border-color: #1f4f9e; }
        
        /* Show Entries Bar */
        .toolbar {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px; margin-bottom: 15px;
            padding: 10px 15px; background: #1c2333; border-radius: 8px;
            border: 1px solid #30363d;
        }
        .toolbar-left { display: flex; align-items: center; gap: 8px; color: #8b949e; font-size: 13px; }
        .toolbar-left select {
            background: #0d1117; color: #e6edf3; border: 1px solid #30363d;
            border-radius: 6px; padding: 4px 8px; font-size: 13px;
        }
        .toolbar-right { color: #8b949e; font-size: 13px; }
        .toolbar-right strong { color: #58a6ff; }

        /* Table (plain black/white) */
        table { width: 100%; border-collapse: collapse; background: #ffffff; color: #000000; }
        th, td { border: 1px solid #000000; padding: 6px 8px; text-align: left; font-size: 13px; }
        th { background: #ffffff; font-weight: bold; }

        /* Pagination */
        .pagination {
            display: flex; justify-content: center; align-items: center;
            gap: 5px; margin-top: 20px; flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 12px; border-radius: 6px;
            background: #1c2333; border: 1px solid #30363d;
            color: #8b949e; text-decoration: none; font-size: 13px;
            min-width: 36px; text-align: center;
        }
        .pagination a:hover { background: #21283a; color: #e6edf3; }
        .pagination .active {
            background: #1f4f9e; color: #ffffff; border-color: #1f4f9e;
        }
        .pagination .disabled {
            opacity: 0.4; cursor: not-allowed; pointer-events: none;
        }
        .pagination .dots {
            background: transparent; border: none; color: #6e7681;
            pointer-events: none;
        }
    </style>
</head>
<body>

<!-- ============================================================
     PERIOD FILTER BUTTONS
     ============================================================ -->
<div class="period-buttons">
    <a href="<?php echo buildUrl(['period' => 'day', 'page' => 1]); ?>" 
       class="period-btn <?php echo $period === 'day' ? 'active' : ''; ?>">
        <span>📅</span> Day
    </a>
    <a href="<?php echo buildUrl(['period' => 'week', 'page' => 1]); ?>" 
       class="period-btn <?php echo $period === 'week' ? 'active' : ''; ?>">
        <span>🗓</span> Week
    </a>
    <a href="<?php echo buildUrl(['period' => 'month', 'page' => 1]); ?>" 
       class="period-btn <?php echo $period === 'month' ? 'active' : ''; ?>">
        <span>🗓</span> Month
    </a>
    <a href="<?php echo buildUrl(['period' => 'year', 'page' => 1]); ?>" 
       class="period-btn <?php echo $period === 'year' ? 'active' : ''; ?>">
        <span>📆</span> Year
    </a>
</div>

<!-- ============================================================
     SHOW ENTRIES TOOLBAR
     ============================================================ -->
<div class="toolbar">
    <div class="toolbar-left">
        <label for="perPageSelect">Show</label>
        <select id="perPageSelect" onchange="changePerPage(this.value)">
            <?php foreach ($perPageOptions as $option): ?>
                <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                    <?php echo $option; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span>entries</span>
    </div>
    <div class="toolbar-right">
        Showing <strong><?php echo $totalRecords > 0 ? $offset + 1 : 0; ?></strong> 
        to <strong><?php echo min($offset + $perPage, $totalRecords); ?></strong> 
        of <strong><?php echo $totalRecords; ?></strong> records
        &nbsp;|&nbsp;
        <strong><?php echo $periodLabel; ?></strong>
    </div>
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

<!-- ============================================================
     PAGINATION
     ============================================================ -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <!-- First Page -->
    <a href="<?php echo buildUrl(['page' => 1]); ?>" 
       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
    
    <!-- Previous Page -->
    <a href="<?php echo buildUrl(['page' => max(1, $page - 1)]); ?>" 
       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">‹</a>
    
    <!-- Page Numbers -->
    <?php
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    
    if ($startPage > 1): ?>
        <a href="<?php echo buildUrl(['page' => 1]); ?>">1</a>
        <?php if ($startPage > 2): ?>
            <span class="dots">...</span>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
            <span class="dots">...</span>
        <?php endif; ?>
        <a href="<?php echo buildUrl(['page' => $totalPages]); ?>"><?php echo $totalPages; ?></a>
    <?php endif; ?>
    
    <!-- Next Page -->
    <a href="<?php echo buildUrl(['page' => min($totalPages, $page + 1)]); ?>" 
       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">›</a>
    
    <!-- Last Page -->
    <a href="<?php echo buildUrl(['page' => $totalPages]); ?>" 
       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">»</a>
</div>
<?php endif; ?>

<script>
    // ============================================================
    // CHANGE PER PAGE — preserves current period, date, search
    // ============================================================
    function changePerPage(value) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('per_page', value);
        urlParams.set('page', 1); // Reset to first page
        window.location.href = '?' + urlParams.toString();
    }
</script>

</body>
</html>
