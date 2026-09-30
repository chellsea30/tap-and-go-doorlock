<?php
/**
 * Tap-and-Go Doorlock - Access Report Copy
 * PLAIN TABLE - SIMPLE DESIGN
 * Columns: Date/Time | Name | Age | Gender | Birthdate | Address | Contact Number
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
// GET ACCESS LOGS WITH USER DETAILS
// ============================================================
$logs = [];

$query = "
    SELECT 
        al.log_id,
        al.timestamp,
        al.access_type,
        al.access_status,
        al.card_uid,
        u.user_id,
        u.full_name,
        u.student_id,
        u.room_number,
        u.contact_number,
        rp.age,
        rp.gender,
        rp.birth_date,
        rp.home_address
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

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function formatDate($date, $format = 'M d, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    $ts = strtotime($date);
    if ($ts === false) return 'N/A';
    return date($format, $ts);
}

function showVal($value, $default = 'N/A') {
    if ($value !== null && $value !== '' && $value !== '0') {
        return htmlspecialchars(trim($value));
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Report Copy - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
        }

        /* NAVBAR */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important; left: 0 !important; right: 0 !important;
            z-index: 1050 !important;
            height: 56px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }

        /* SIDEBAR */
        .sidebar {
            position: fixed !important;
            top: 56px !important; left: 0 !important; bottom: 0 !important;
            width: 220px !important;
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            overflow-y: auto !important;
            z-index: 1040 !important;
            padding-top: 10px !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            margin: 2px 10px !important;
            font-size: 13px !important;
        }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.05) !important; color: #e0e0e0 !important; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important; color: white !important; }
        .sidebar .nav-link i { width: 18px; text-align: center; }
        .sidebar-footer { border-top-color: #1a2a4a !important; padding: 12px 16px !important; margin-top: 10px !important; }
        .sidebar-footer .text-muted { color: #606070 !important; font-size: 11px !important; }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 20px 25px !important;
            min-height: calc(100vh - 56px) !important;
            background: #0a0e1a !important;
        }

        /* PAGE HEADER */
        .page-header {
            padding-bottom: 12px;
            margin-bottom: 20px;
            border-bottom: 1px solid #1a2a4a;
        }
        .page-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #e0e0e0;
            margin: 0;
        }
        .page-header h1 i {
            color: #ffd700;
            margin-right: 8px;
        }

        /* FILTER BAR */
        .filter-bar {
            background: #111827;
            border: 1px solid #1a2a4a;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .filter-bar .form-control,
        .filter-bar .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
            height: 36px;
        }
        .filter-bar .form-control:focus,
        .filter-bar .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .filter-bar .form-control::placeholder { color: #606070 !important; }
        .filter-bar .btn-filter {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px;
            padding: 6px 20px;
            font-weight: 500;
            font-size: 13px;
            height: 36px;
        }
        .filter-bar .btn-filter:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }

        /* ============================================================
           PLAIN TABLE - SIMPLE DESIGN
           ============================================================ */
        .plain-table-wrapper {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            overflow-x: auto;
        }

        .plain-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #000000;
            background: #ffffff;
        }

        .plain-table thead th {
            background: #f0f0f0;
            color: #000000;
            font-weight: 700;
            padding: 10px 12px;
            text-align: left;
            border: 1px solid #cccccc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plain-table tbody td {
            padding: 8px 12px;
            border: 1px solid #cccccc;
            color: #000000;
            font-size: 12px;
            vertical-align: middle;
        }

        .plain-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .plain-table tbody tr:hover {
            background: #e8f0ff;
        }

        .plain-table .empty-row td {
            text-align: center;
            padding: 30px;
            color: #999999;
            font-style: italic;
        }

        /* PRINT HEADER */
        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 15px;
            color: #000;
        }
        .print-header h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .print-header p {
            font-size: 12px;
            margin: 2px 0;
        }

        /* SUMMARY */
        .report-summary {
            margin-top: 15px;
            font-size: 13px;
            color: #808090;
            text-align: center;
        }
        .report-summary strong { color: #ffd700; }

        /* PRINT STYLES */
        @media print {
            .no-print { display: none !important; }
            .navbar, .sidebar, .filter-bar, .page-header, .report-summary {
                display: none !important;
            }
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .plain-table-wrapper {
                padding: 0 !important;
                border-radius: 0 !important;
            }
            .plain-table thead th {
                background: #e0e0e0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .plain-table tbody tr:nth-child(even) {
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print-header { display: block !important; }
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed !important;
                top: 56px !important; bottom: 0 !important;
                left: -260px !important;
                width: 260px !important;
                transition: left 0.3s ease !important;
                z-index: 1040 !important;
            }
            .sidebar.show { left: 0 !important; }
            .main-content {
                margin-left: 0 !important;
                padding: 12px 15px !important;
            }
            .plain-table {
                font-size: 11px;
            }
            .plain-table thead th,
            .plain-table tbody td {
                padding: 6px 8px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- PAGE HEADER -->
        <div class="page-header no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1><i class="fas fa-copy"></i>Access Report Copy</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
                <a href="access-reports.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar no-print">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Name, Student ID, or Card UID" value="<?php echo htmlspecialchars($searchFilter); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-3">
                    <a href="access-reports-copy.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- PRINT HEADER -->
        <div class="print-header">
            <h2>ISU-E LADIES DORMITORY</h2>
            <p>Isabela State University · Echague, Isabela</p>
            <p><strong>Access Report Copy</strong></p>
            <p>Date: <?php echo !empty($dateFilter) ? date('F d, Y', strtotime($dateFilter)) : 'All Records'; ?></p>
        </div>

        <!-- ============================================================
             PLAIN TABLE
             ============================================================ -->
        <div class="plain-table-wrapper">
            <table class="plain-table">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Birthdate</th>
                        <th>Address</th>
                        <th>Contact Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr class="empty-row">
                            <td colspan="7">
                                <i class="fas fa-inbox" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                                No access records found for the selected date
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <!-- 1. DATE / TIME -->
                                <td>
                                    <?php 
                                        $timestamp = strtotime($log['timestamp']);
                                        echo date('M d, Y', $timestamp) . ' ' . 
                                             strtolower(date('D', $timestamp)) . ' ' . 
                                             date('h:i A', $timestamp);
                                    ?>
                                </td>

                                <!-- 2. NAME -->
                                <td><?php echo showVal($log['full_name'] ?? null, 'Unknown'); ?></td>

                                <!-- 3. AGE -->
                                <td><?php echo showVal($log['age'] ?? null); ?></td>

                                <!-- 4. GENDER -->
                                <td><?php echo showVal($log['gender'] ?? null); ?></td>

                                <!-- 5. BIRTHDATE -->
                                <td><?php echo formatDate($log['birth_date'] ?? null); ?></td>

                                <!-- 6. ADDRESS -->
                                <td><?php echo showVal($log['home_address'] ?? null); ?></td>

                                <!-- 7. CONTACT NUMBER -->
                                <td><?php echo showVal($log['contact_number'] ?? null); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- SUMMARY -->
        <div class="report-summary no-print">
            <i class="fas fa-database me-1"></i>
            Total Records: <strong><?php echo count($logs); ?></strong>
            <?php if (!empty($dateFilter)): ?>
                <span class="mx-2">|</span>
                Date: <strong><?php echo date('F d, Y', strtotime($dateFilter)); ?></strong>
            <?php endif; ?>
            <?php if (!empty($searchFilter)): ?>
                <span class="mx-2">|</span>
                Search: <strong>"<?php echo htmlspecialchars($searchFilter); ?>"</strong>
            <?php endif; ?>
        </div>

    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
