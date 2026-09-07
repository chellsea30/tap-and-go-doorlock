<?php
/**
 * Tap-and-Go Doorlock - Staff Access Logs
 * DARK MODE - WITH FILTERS AND PAGINATION
 * SHOWS ALL STAFF ACCESS ACTIVITY
 */

session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php'; 

$conn = getDBConnection();
$error = '';
$success = '';

// ============================================================
// PAGINATION SETTINGS
// ============================================================
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPageOptions = [15, 30, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 15;
}

// ============================================================
// FILTERS
// ============================================================
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================================
// GET STAFF LIST FOR FILTER DROPDOWN
// ============================================================
$staffList = [];
$result = $conn->query("
    SELECT staff_id, staff_id_number, full_name 
    FROM staff_users 
    ORDER BY full_name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

// ============================================================
// GET STAFF ACCESS LOGS
// ============================================================
$query = "
    SELECT 
        al.log_id,
        al.card_uid,
        al.user_id,
        al.access_type,
        al.access_status,
        al.alert_triggered,
        al.power_source,
        al.reason,
        al.timestamp,
        al.created_at,
        su.full_name as staff_name,
        su.staff_id_number,
        su.department,
        su.avatar
    FROM access_logs al
    LEFT JOIN staff_users su ON al.user_id = su.staff_id
    WHERE su.staff_id IS NOT NULL
";

if (!empty($statusFilter)) {
    $query .= " AND al.access_status = '$statusFilter'";
}
if (!empty($typeFilter)) {
    $query .= " AND al.access_type = '$typeFilter'";
}
if (!empty($searchFilter)) {
    $query .= " AND (su.full_name LIKE '%$searchFilter%' OR su.staff_id_number LIKE '%$searchFilter%' OR al.card_uid LIKE '%$searchFilter%')";
}
if (!empty($dateFrom)) {
    $query .= " AND DATE(al.timestamp) >= '$dateFrom'";
}
if (!empty($dateTo)) {
    $query .= " AND DATE(al.timestamp) <= '$dateTo'";
}

$query .= " ORDER BY al.timestamp DESC";

// ============================================================
// GET TOTAL COUNT FOR PAGINATION
// ============================================================
$countQuery = str_replace(
    "SELECT 
        al.log_id,
        al.card_uid,
        al.user_id,
        al.access_type,
        al.access_status,
        al.alert_triggered,
        al.power_source,
        al.reason,
        al.timestamp,
        al.created_at,
        su.full_name as staff_name,
        su.staff_id_number,
        su.department,
        su.avatar",
    "SELECT COUNT(*) as total",
    $query
);

$countResult = $conn->query($countQuery);
$totalLogs = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalLogs = (int)$row['total'];
}

$totalPages = ceil($totalLogs / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$query .= " LIMIT $perPage OFFSET $offset";
$result = $conn->query($query);

$logs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'granted' => 0,
    'denied' => 0,
    'entry' => 0,
    'exit' => 0,
    'today' => 0,
    'this_week' => 0,
    'this_month' => 0
];

// Total staff logs
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

// Granted
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
    WHERE al.access_status = 'granted'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['granted'] = (int)$row['count'];
}

// Denied
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
    WHERE al.access_status = 'denied'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['denied'] = (int)$row['count'];
}

// Entry
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
    WHERE al.access_type = 'entry'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['entry'] = (int)$row['count'];
}

// Exit
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
    WHERE al.access_type = 'exit'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['exit'] = (int)$row['count'];
}

// Today
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
    WHERE DATE(al.timestamp) = CURDATE()
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['today'] = (int)$row['count'];
}

// This week
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
    WHERE YEARWEEK(al.timestamp) = YEARWEEK(CURDATE())
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['this_week'] = (int)$row['count'];
}

// This month
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM access_logs al
    INNER JOIN staff_users su ON al.user_id = su.staff_id
    WHERE MONTH(al.timestamp) = MONTH(CURDATE()) 
    AND YEAR(al.timestamp) = YEAR(CURDATE())
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['this_month'] = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Access Logs - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        
        .container-fluid { padding-top: 10px !important; }
        main { padding-top: 10px !important; margin-top: 0 !important; }
        
        /* NAVBAR */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* SIDEBAR */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar-footer { border-top-color: #1a2a4a !important; }
        .sidebar-footer .text-muted { color: #606070 !important; }
        
        /* STAT CARDS */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            text-align: center;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700 !important;
        }
        .stat-card .label {
            font-size: 12px;
            color: #808090 !important;
        }
        .stat-card .number.text-success { color: #34d399 !important; }
        .stat-card .number.text-danger { color: #f87171 !important; }
        .stat-card .number.text-info { color: #60a5fa !important; }
        .stat-card .number.text-warning { color: #fbbf24 !important; }
        
        /* FILTER SECTION */
        .filter-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .filter-section .form-control,
        .filter-section .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            color: #e0e0e0 !important;
            height: 38px;
        }
        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .filter-section .form-control::placeholder { color: #606070 !important; }
        .filter-section .form-label {
            font-weight: 500;
            font-size: 12px;
            color: #b0b0c0 !important;
        }
        .btn-filter {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            color: white !important;
            padding: 8px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            height: 38px;
        }
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26,58,106,0.4);
            color: white !important;
        }
        .btn-clear {
            background: #1a2a4a !important;
            border: none !important;
            color: #808090 !important;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s ease;
            height: 38px;
        }
        .btn-clear:hover {
            background: #2a3a5a !important;
            color: #e0e0e0 !important;
        }
        
        /* LOG TABLE */
        .log-table {
            background: #111827 !important;
            border-radius: 16px !important;
            overflow: hidden;
            border: 1px solid #1a2a4a !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .log-table .table {
            margin-bottom: 0;
            color: #e0e0e0 !important;
        }
        .log-table .table thead th {
            background: #0d1528 !important;
            color: #808090 !important;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #1a2a4a !important;
            padding: 12px 15px;
        }
        .log-table .table tbody td {
            border-bottom: 1px solid #1a2a4a !important;
            padding: 12px 15px;
            vertical-align: middle;
            font-size: 13px;
        }
        .log-table .table tbody tr:hover {
            background: #1a2a4a !important;
        }
        .log-table .table tbody tr:last-child td {
            border-bottom: none !important;
        }
        
        /* BADGES */
        .badge-granted {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #34d399 !important;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        .badge-denied {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        .badge-entry {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #60a5fa !important;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        .badge-exit {
            background: rgba(107, 114, 128, 0.2) !important;
            color: #9ca3af !important;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        .badge-alert {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
        }
        .badge-power {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
        }
        
        /* STAFF AVATAR */
        .staff-avatar-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #1a2a4a;
            background: #1a1a2e;
        }
        .staff-avatar-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
            border: 2px solid #1a2a4a;
        }
        
        /* PAGINATION */
        .pagination-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-top: 20px;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: none;
            color: #9090a0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 6px 14px;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .pagination .page-link:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        .pagination .page-item.disabled .page-link {
            color: #4a4a5a !important;
        }
        .page-info { color: #808090 !important; font-size: 13px; }
        .page-info strong { color: #93c5fd !important; }
        
        .per-page-selector select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 13px;
        }
        .per-page-selector select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .per-page-selector label { color: #808090 !important; font-size: 13px; margin: 0; }
        
        /* ALERTS */
        .alert-success {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
        }
        .btn-close { filter: invert(1) !important; }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            margin-right: 4px;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        
        .no-logs {
            padding: 40px 20px;
            text-align: center;
            color: #606070;
        }
        .no-logs i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
            color: #2a2a4a;
        }
        
        .log-uid {
            font-family: monospace;
            font-weight: 600;
            color: #93c5fd !important;
            font-size: 12px;
            background: #1a2a4a !important;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            .stat-card { padding: 12px; }
            .stat-card .number { font-size: 22px; }
            .filter-section { padding: 12px; }
            .log-table .table thead th,
            .log-table .table tbody td {
                font-size: 11px;
                padding: 8px 10px;
            }
            .pagination-container .row {
                flex-direction: column;
                gap: 8px;
            }
            .pagination-container .col-md-6 {
                width: 100%;
                text-align: center !important;
            }
            .pagination {
                justify-content: center !important;
            }
        }
        
        @media print {
            .no-print { display: none !important; }
            .footer { display: none !important; }
            .navbar { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .stat-card { background: #f8f9fa !important; border: 1px solid #ddd !important; }
            .log-table { border: 1px solid #ddd !important; }
            .log-table .table thead th { background: #f8f9fa !important; color: #333 !important; }
            .log-table .table tbody td { color: #333 !important; }
            body { background: #fff !important; color: #000 !important; }
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- HEADER -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-history me-2" style="color: #ffd700;"></i>
                        Staff Access Logs
                        <span class="badge bg-secondary ms-2"><?php echo $totalLogs; ?> total</span>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="?export=csv" class="btn btn-sm btn-outline-primary ms-1">
                            <i class="fas fa-file-export me-1"></i> Export
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- STATISTICS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="number"><?php echo $stats['total']; ?></div>
                            <div class="label">Total Access</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="number text-success"><?php echo $stats['granted']; ?></div>
                            <div class="label">Granted</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="number text-danger"><?php echo $stats['denied']; ?></div>
                            <div class="label">Denied</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="number text-info"><?php echo $stats['entry']; ?></div>
                            <div class="label">Entry</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="number text-muted"><?php echo $stats['exit']; ?></div>
                            <div class="label">Exit</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="number text-warning"><?php echo $stats['today']; ?></div>
                            <div class="label">Today</div>
                        </div>
                    </div>
                </div>

                <!-- FILTERS -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="granted" <?php echo $statusFilter == 'granted' ? 'selected' : ''; ?>>Granted</option>
                                <option value="denied" <?php echo $statusFilter == 'denied' ? 'selected' : ''; ?>>Denied</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">All</option>
                                <option value="entry" <?php echo $typeFilter == 'entry' ? 'selected' : ''; ?>>Entry</option>
                                <option value="exit" <?php echo $typeFilter == 'exit' ? 'selected' : ''; ?>>Exit</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name, UID..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-filter w-100">
                                    <i class="fas fa-search me-1"></i> Filter
                                </button>
                                <a href="staff-logs.php" class="btn btn-clear">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- LOGS TABLE -->
                <div class="log-table">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Staff</th>
                                    <th>Card UID</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Power</th>
                                    <th>Alert</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="no-logs">
                                                <i class="fas fa-inbox"></i>
                                                <h5>No access logs found</h5>
                                                <p class="text-muted">Try adjusting your filters or check back later.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $counter = $offset + 1; foreach ($logs as $log): 
                                        $initials = '';
                                        $name = $log['staff_name'] ?? 'Unknown Staff';
                                        $parts = explode(' ', $name);
                                        foreach ($parts as $p) {
                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                        }
                                        $initials = substr($initials, 0, 2) ?: 'ST';
                                        
                                        $avatar = $log['avatar'] ?? '';
                                        $hasPhoto = false;
                                        $fullPhotoPath = '';
                                        
                                        if (!empty($avatar)) {
                                            if (strpos($avatar, 'uploads/') === 0) {
                                                $fullPhotoPath = '../../' . $avatar;
                                            } else {
                                                $fullPhotoPath = '../../uploads/staff_photos/' . $avatar;
                                            }
                                            if (file_exists($fullPhotoPath)) {
                                                $hasPhoto = true;
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td style="color: #606070;"><?php echo $counter++; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if ($hasPhoto): ?>
                                                        <img src="<?php echo $fullPhotoPath; ?>" 
                                                             alt="<?php echo htmlspecialchars($name); ?>"
                                                             class="staff-avatar-mini"
                                                             onerror="this.style.display='none'; this.parentNode.querySelector('.staff-avatar-placeholder').style.display='flex';">
                                                        <div class="staff-avatar-placeholder" style="display:none;"><?php echo $initials; ?></div>
                                                    <?php else: ?>
                                                        <div class="staff-avatar-placeholder"><?php echo $initials; ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div style="font-weight:500;color:#e0e0e0;font-size:13px;">
                                                            <?php echo htmlspecialchars($name); ?>
                                                        </div>
                                                        <div style="font-size:11px;color:#606070;">
                                                            <?php echo htmlspecialchars($log['staff_id_number'] ?? ''); ?>
                                                            <?php if (!empty($log['department'])): ?>
                                                                <span class="mx-1">•</span>
                                                                <?php echo htmlspecialchars($log['department']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="log-uid"><?php echo htmlspecialchars($log['card_uid']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $log['access_type']; ?>">
                                                    <i class="fas fa-<?php echo $log['access_type'] == 'entry' ? 'sign-in-alt' : 'sign-out-alt'; ?> me-1"></i>
                                                    <?php echo ucfirst($log['access_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $log['access_status']; ?>">
                                                    <i class="fas fa-<?php echo $log['access_status'] == 'granted' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                    <?php echo ucfirst($log['access_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-power">
                                                    <i class="fas fa-<?php echo $log['power_source'] == 'main' ? 'plug' : ($log['power_source'] == 'battery' ? 'battery-three-quarters' : 'wifi'); ?> me-1"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['power_source'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['alert_triggered'] == 1): ?>
                                                    <span class="badge badge-alert">
                                                        <i class="fas fa-exclamation-triangle me-1"></i> Alert
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size:11px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:12px;color:#b0b0c0;">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                <?php echo date('M d, Y', strtotime($log['timestamp'])); ?>
                                                <br>
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo date('h:i A', strtotime($log['timestamp'])); ?>
                                                <?php if (!empty($log['reason'])): ?>
                                                    <div class="text-muted small mt-1">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        <?php echo htmlspecialchars($log['reason']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> logs
                                <span class="mx-1 text-muted">|</span>
                                <span class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                <div class="per-page-selector d-flex align-items-center gap-1">
                                    <label>Show:</label>
                                    <select onchange="changePerPage(this.value)">
                                        <?php foreach ($perPageOptions as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                                                <?php echo $option; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mb-0">
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=1<?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        if ($startPage > 1) {
                                            echo '<li class="page-item"><span class="page-link">...</span></li>';
                                        }
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <li class="page-item"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FOOTER -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span><?php echo $stats['total']; ?> total accesses</span>
                    <span class="mx-2">|</span>
                    <span class="text-success"><?php echo $stats['granted']; ?> granted</span>
                    <span class="mx-2">|</span>
                    <span class="text-danger"><?php echo $stats['denied']; ?> denied</span>
                </footer>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // CHANGE PER PAGE
        // ============================================================
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }

        // ============================================================
        // AUTO-SUBMIT FILTER ON CHANGE
        // ============================================================
        document.querySelectorAll('.filter-section select, .filter-section input[type="date"]').forEach(el => {
            el.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });

        // ============================================================
        // UPDATE TIME
        // ============================================================
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            const updateElement = document.getElementById('lastUpdate');
            if (updateElement) {
                updateElement.textContent = 'Updated: ' + timeString;
            }
            const serverTimeElement = document.getElementById('serverTime');
            if (serverTimeElement) {
                const dateString = now.toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
                serverTimeElement.textContent = 'Server Time: ' + dateString + ' ' + timeString;
            }
        }

        setInterval(updateLastUpdateTime, 10000);
        document.addEventListener('DOMContentLoaded', updateLastUpdateTime);
        
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
