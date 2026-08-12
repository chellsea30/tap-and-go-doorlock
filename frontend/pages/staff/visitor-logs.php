<?php
/**
 * Tap-and-Go Doorlock - Staff Visitor Logs
 * VIEW ONLY - Complete with RFID Card Display
 * PURE DARK MODE - Walang puti sa table
 * WITH SHOW ENTRIES
 */

session_start();

// Load config and functions
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// Check authentication - Staff only
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: ../login.php');
    exit();
}

$conn = getDBConnection();

// ============================================================
// PAGINATION SETTINGS
// ============================================================
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

// ============================================================
// GET FILTERS
// ============================================================
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET TOTAL VISITOR LOGS FOR PAGINATION
// ============================================================
$countQuery = "
    SELECT COUNT(*) as total
    FROM visitor_logs v
    LEFT JOIN users u ON v.resident_visited = u.user_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $countQuery .= " AND v.access_status = '$statusFilter'";
}
if (!empty($dateFilter)) {
    $countQuery .= " AND DATE(v.created_at) = '$dateFilter'";
}
if (!empty($searchFilter)) {
    $countQuery .= " AND (v.visitor_name LIKE '%$searchFilter%' OR v.temporary_card_uid LIKE '%$searchFilter%')";
}

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

// ============================================================
// GET VISITOR LOGS
// ============================================================
$logs = [];
$query = "
    SELECT v.*, u.full_name as resident_name, u.room_number
    FROM visitor_logs v
    LEFT JOIN users u ON v.resident_visited = u.user_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $query .= " AND v.access_status = '$statusFilter'";
}
if (!empty($dateFilter)) {
    $query .= " AND DATE(v.created_at) = '$dateFilter'";
}
if (!empty($searchFilter)) {
    $query .= " AND (v.visitor_name LIKE '%$searchFilter%' OR v.temporary_card_uid LIKE '%$searchFilter%')";
}

$query .= " ORDER BY v.created_at DESC LIMIT $perPage OFFSET $offset";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// ============================================================
// GET STATS
// ============================================================
$stats = [
    'total' => 0,
    'pending' => 0,
    'granted' => 0,
    'denied' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending'] = (int)$row['count'];
}
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'granted'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['granted'] = (int)$row['count'];
}
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE access_status = 'denied'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['denied'] = (int)$row['count'];
}

// Get staff info
$staffInfo = null;
$stmt = $conn->prepare("SELECT * FROM staff_users WHERE staff_id = ?");
$stmt->bind_param("i", $_SESSION['staff_id']);
$stmt->execute();
$result = $stmt->get_result();
$staffInfo = $result->fetch_assoc();
$stmt->close();

// Get dark mode
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['staff_id'])) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE staff_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['staff_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $darkModeFromDb = $row['setting_value'];
            if ($darkModeFromDb == 'true') {
                $darkModeClass = 'dark-mode';
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Visitor Logs - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
            padding-top: 56px;
        }
        
        /* ============================================================
           DARK NAVBAR
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           DARK SIDEBAR
           ============================================================ */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            min-height: 100vh;
            box-shadow: 2px 0 15px rgba(0,0,0,0.3) !important;
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            width: 260px;
            z-index: 100;
            padding: 15px 0 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
            padding: 10px 16px;
            border-radius: 8px;
            margin: 2px 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(26,58,106,0.3) !important;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            color: #606070 !important;
            margin-right: 10px;
        }
        .sidebar .nav-link.active i {
            color: white !important;
        }
        .sidebar .nav-link .badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: auto;
        }
        .sidebar-footer {
            padding: 10px 0 20px 0;
            border-top: 1px solid #1a2a4a !important;
            margin-top: auto;
        }
        .sidebar-footer .text-muted {
            color: #606070 !important;
        }
        
        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: calc(100vh - 56px);
        }
        
        /* ============================================================
           VIEW ONLY BADGE
           ============================================================ */
        .view-only-badge {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        
        /* ============================================================
           DARK CARD - WALANG PUTI
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .card-header {
            background: #111827 !important;
            border-bottom: 1px solid #1a2a4a !important;
            border-radius: 16px 16px 0 0;
            padding: 14px 20px;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #e0e0e0 !important; }
        .card-body { background: #111827 !important; padding: 20px; }
        
        /* ============================================================
           DARK TABLE - WALANG PUTI
           ============================================================ */
        .log-table {
            font-size: 13px;
            color: #e0e0e0 !important;
            background: transparent !important;
        }
        .log-table th {
            font-weight: 600;
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            background: transparent !important;
        }
        .log-table td {
            vertical-align: middle;
            padding: 10px 12px;
            color: #e0e0e0 !important;
            border-bottom: 1px solid #1a2a4a !important;
            background: transparent !important;
        }
        .log-table tr:hover td {
            background: rgba(255,255,255,0.02) !important;
        }
        .log-table .text-muted { color: #808090 !important; }
        .log-table strong { color: #e0e0e0 !important; }
        
        /* ============================================================
           DARK FILTERS
           ============================================================ */
        .filter-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .filter-section .form-control, .filter-section .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #e0e0e0 !important;
        }
        .filter-section .form-control:focus, .filter-section .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .filter-section .form-control::placeholder { color: #606070 !important; }
        .filter-section .form-label { color: #b0b0c0 !important; font-size: 13px; }
        .filter-section .btn-filter {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .filter-section .btn-filter:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge-pending { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-granted { background: #065f46 !important; color: #34d399 !important; }
        .badge-denied { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        
        /* ============================================================
           DARK UID CELL
           ============================================================ */
        .uid-cell {
            font-family: monospace;
            font-weight: 600;
            color: #93c5fd !important;
            background: #1a2a4a !important;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        /* ============================================================
           DARK PAGINATION
           ============================================================ */
        .pagination-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-top: 20px;
        }
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 3px;
            border: none;
            color: #9090a0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 8px 16px;
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
        .page-info { color: #808090 !important; font-size: 14px; }
        .page-info strong { color: #93c5fd !important; }
        
        /* ============================================================
           PER PAGE SELECTOR - DARK
           ============================================================ */
        .per-page-selector select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
        }
        .per-page-selector select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .per-page-selector label { color: #808090 !important; font-size: 13px; margin: 0; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-danger { color: #f87171 !important; }
        .text-warning { color: #fbbf24 !important; }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
            }
            .sidebar.show { left: 0; }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            .pagination-container .row {
                flex-direction: column;
                gap: 10px;
            }
            .pagination-container .col-md-6 {
                width: 100%;
                text-align: center !important;
            }
            .pagination {
                justify-content: center !important;
            }
            .log-table th, .log-table td {
                padding: 6px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <!-- ===== NAVBAR ===== -->
    <?php include __DIR__ . '/../../includes/navbar_staff.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- ===== SIDEBAR ===== -->
            <?php include __DIR__ . '/includes/sidebar_staff.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-eye me-2" style="color: #fbbf24;"></i>
                        <i class="fas fa-history me-1" style="color: #1a3a6a;"></i>
                        Visitor Logs
                    </h1>
                    <div>
                        <span class="view-only-badge me-2">
                            <i class="fas fa-eye me-1"></i> View Only
                        </span>
                        <span class="badge bg-secondary">Total: <?php echo $stats['total']; ?></span>
                    </div>
                </div>

                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['granted']; ?></div>
                                <div class="stat-label">Granted</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-times-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['denied']; ?></div>
                                <div class="stat-label">Denied</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="granted" <?php echo $statusFilter == 'granted' ? 'selected' : ''; ?>>Granted</option>
                                <option value="denied" <?php echo $statusFilter == 'denied' ? 'selected' : ''; ?>>Denied</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name or Card UID" value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-filter w-100">
                                <i class="fas fa-filter me-1"></i> Apply
                            </button>
                        </div>
                        <!-- Hidden fields to preserve pagination -->
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- Logs Table - Pure Dark -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list me-2"></i>Visitor Logs</h5>
                        <span class="text-muted small">
                            <?php if ($totalLogs > 0): ?>
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> logs
                            <?php else: ?>
                                0 logs
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover log-table">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Visitor</th>
                                        <th>Resident</th>
                                        <th>Room</th>
                                        <th>Purpose</th>
                                        <th>RFID Card</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No visitor logs found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                                    <br>
                                                    <span class="text-muted small"><?php echo date('h:i A', strtotime($log['created_at'])); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($log['visitor_name']); ?></strong>
                                                    <?php if (!empty($log['contact_number'])): ?>
                                                        <br>
                                                        <span class="text-muted small">
                                                            <i class="fas fa-phone me-1"></i>
                                                            <?php echo htmlspecialchars($log['contact_number']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['resident_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($log['room_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($log['purpose_of_visit'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if (!empty($log['temporary_card_uid'])): ?>
                                                        <span class="uid-cell"><?php echo htmlspecialchars($log['temporary_card_uid']); ?></span>
                                                        <?php if (!empty($log['validity_end'])): ?>
                                                            <br>
                                                            <span class="text-muted small">
                                                                Valid until: <?php echo date('M d, Y', strtotime($log['validity_end'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No Card</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $log['access_status'] == 'pending' ? 'badge-pending' : ($log['access_status'] == 'granted' ? 'badge-granted' : 'badge-denied'); ?>">
                                                        <?php echo ucfirst($log['access_status']); ?>
                                                    </span>
                                                    <?php if ($log['access_status'] == 'denied' && !empty($log['denial_reason'])): ?>
                                                        <br>
                                                        <span class="text-muted small"><?php echo htmlspecialchars($log['denial_reason']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                PAGINATION WITH SHOW ENTRIES
                ============================================================ -->
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
                            <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
                                <!-- Per Page Selector -->
                                <div class="per-page-selector d-flex align-items-center gap-2">
                                    <label>Show:</label>
                                    <select onchange="changePerPage(this.value)">
                                        <?php foreach ($perPageOptions as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                                                <?php echo $option; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Pagination -->
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mb-0">
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=1<?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <li class="page-item"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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

                <div class="text-center text-muted small mt-3">
                    <i class="fas fa-eye me-1"></i> View Only Access
                    <span class="mx-2">|</span>
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo $stats['total']; ?> visitor records
                    <span class="mx-1">|</span>
                    <i class="fas fa-clock me-1"></i>
                    <?php echo $stats['pending']; ?> pending
                    <span class="mx-1">|</span>
                    <i class="fas fa-check-circle me-1 text-success"></i>
                    <?php echo $stats['granted']; ?> granted
                    <span class="mx-1">|</span>
                    <i class="fas fa-times-circle me-1 text-danger"></i>
                    <?php echo $stats['denied']; ?> denied
                </div>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer_staff.php'; ?>
    
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
                if (this.name !== 'search') {
                    this.closest('form').submit();
                }
            });
        });
        
        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>