<?php
/**
 * Tap-and-Go Doorlock - Access Report Copy
 * PURE PLAIN TABLE - BLACK AND WHITE
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

// Filters
$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get logs
$logs = [];
$query = "
    SELECT 
        al.log_id,
        al.timestamp,
        u.full_name,
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
    <title>Access Report Copy</title>
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
        .filter-bar .form-control {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
            height: 36px;
        }
        .filter-bar .form-control:focus {
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

        /* ============================================================
           PURE PLAIN TABLE - BLACK AND WHITE
           ============================================================ */
        .table-wrapper {
            background: #ffffff;
            padding: 15px;
        }

        table.plain {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            color: #000000;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        table.plain th {
            background: #ffffff;
            color: #000000;
            font-weight: bold;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #000000;
        }

        table.plain td {
            background: #ffffff;
            color: #000000;
            padding: 6px 8px;
            border: 1px solid #000000;
        }

        table.plain tr {
            background: #ffffff;
        }

        /* PRINT */
        @media print {
            .no-print { display: none !important; }
            .navbar, .sidebar, .filter-bar, .page-header, .footer {
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
            .table-wrapper {
                padding: 0 !important;
            }
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
            table.plain {
                font-size: 10px;
            }
            table.plain th,
            table.plain td {
                padding: 4px 6px;
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

        <!-- PLAIN TABLE -->
        <div class="table-wrapper">
            <table class="plain">
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
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">
                                No access records found
                            </td>
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
