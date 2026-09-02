<?php
/**
 * Tap-and-Go Doorlock - Residents Report
 * PERMANENT STORAGE - NO DELETE OPTION
 * PURE DARK MODE - WITH PROFILE PHOTO
 * ALL RESIDENTS (ACTIVE & INACTIVE)
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$error = '';
$success = '';

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
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';
$roomFilter = isset($_GET['room']) ? (int)$_GET['room'] : 0;
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// ============================================================
// GET TOTAL RESIDENTS FOR PAGINATION (ALL USERS - PERMANENT)
// ============================================================
$countQuery = "
    SELECT COUNT(*) as total
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status != 'deleted'
";

if (!empty($statusFilter)) {
    $countQuery .= " AND u.status = '$statusFilter'";
}
if (!empty($searchFilter)) {
    $countQuery .= " AND (u.full_name LIKE '%$searchFilter%' OR u.student_id LIKE '%$searchFilter%' OR u.email LIKE '%$searchFilter%')";
}
if ($roomFilter > 0) {
    $countQuery .= " AND u.room_number = $roomFilter";
}
if ($yearFilter > 0) {
    $countQuery .= " AND rp.year_level = $yearFilter";
}

$countResult = $conn->query($countQuery);
$totalResidents = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalResidents = (int)$row['total'];
}

$totalPages = ceil($totalResidents / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// ============================================================
// GET RESIDENTS DATA - PERMANENT (ALL STATUS)
// ============================================================
$residents = [];
$query = "
    SELECT 
        u.*,
        rp.course,
        rp.year_level,
        rp.gender,
        rf.card_uid,
        rf.status as card_status,
        rf.issued_date,
        rf.expiry_date
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    LEFT JOIN rfid_cards rf ON u.user_id = rf.user_id AND rf.status = 'active'
    WHERE u.status != 'deleted'
";

if (!empty($statusFilter)) {
    $query .= " AND u.status = '$statusFilter'";
}
if (!empty($searchFilter)) {
    $query .= " AND (u.full_name LIKE '%$searchFilter%' OR u.student_id LIKE '%$searchFilter%' OR u.email LIKE '%$searchFilter%')";
}
if ($roomFilter > 0) {
    $query .= " AND u.room_number = $roomFilter";
}
if ($yearFilter > 0) {
    $query .= " AND rp.year_level = $yearFilter";
}

$query .= " ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residents[] = $row;
    }
}

// ============================================================
// GET STATS - PERMANENT COUNT
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'archived' => 0,
    'with_card' => 0,
    'no_card' => 0,
    'male' => 0,
    'female' => 0,
    'rooms_used' => 0,
    'total_rooms' => 5,
    'year1' => 0,
    'year2' => 0,
    'year3' => 0,
    'year4' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status != 'deleted'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['active'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['inactive'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'archived'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['archived'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users u 
    INNER JOIN rfid_cards rf ON u.user_id = rf.user_id 
    WHERE rf.status = 'active' AND u.status != 'deleted'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['with_card'] = (int)$row['count'];
}
$stats['no_card'] = $stats['total'] - $stats['with_card'];

// Gender stats - check if column exists
$genderCheck = $conn->query("SHOW COLUMNS FROM resident_profiles LIKE 'gender'");
if ($genderCheck && $genderCheck->num_rows > 0) {
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM users u
        INNER JOIN resident_profiles rp ON u.user_id = rp.user_id
        WHERE rp.gender = 'Male' AND u.status != 'deleted'
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['male'] = (int)$row['count'];
    }

    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM users u
        INNER JOIN resident_profiles rp ON u.user_id = rp.user_id
        WHERE rp.gender = 'Female' AND u.status != 'deleted'
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['female'] = (int)$row['count'];
    }
}

$result = $conn->query("SELECT COUNT(DISTINCT room_number) as count FROM users WHERE room_number IS NOT NULL AND room_number != '' AND status != 'deleted'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['rooms_used'] = (int)$row['count'];
}

// Year level stats - check if column exists
$yearCheck = $conn->query("SHOW COLUMNS FROM resident_profiles LIKE 'year_level'");
if ($yearCheck && $yearCheck->num_rows > 0) {
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM users u
        INNER JOIN resident_profiles rp ON u.user_id = rp.user_id
        WHERE rp.year_level = 1 AND u.status != 'deleted'
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['year1'] = (int)$row['count'];
    }

    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM users u
        INNER JOIN resident_profiles rp ON u.user_id = rp.user_id
        WHERE rp.year_level = 2 AND u.status != 'deleted'
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['year2'] = (int)$row['count'];
    }

    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM users u
        INNER JOIN resident_profiles rp ON u.user_id = rp.user_id
        WHERE rp.year_level = 3 AND u.status != 'deleted'
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['year3'] = (int)$row['count'];
    }

    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM users u
        INNER JOIN resident_profiles rp ON u.user_id = rp.user_id
        WHERE rp.year_level = 4 AND u.status != 'deleted'
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['year4'] = (int)$row['count'];
    }
}

// ============================================================
// GET ROOM OCCUPANCY FOR FILTER
// ============================================================
$rooms = [];
for ($i = 1; $i <= 5; $i++) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE room_number = ? AND status != 'deleted'");
    $stmt->bind_param("i", $i);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $rooms[$i] = $row['count'] ?? 0;
    $stmt->close();
}

// Get dark mode
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['admin_id']);
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
    <title>Residents Report - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME - SAME AS DASHBOARD
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        
        .container-fluid {
            padding-top: 10px !important;
        }
        
        main {
            padding-top: 10px !important;
            margin-top: 0 !important;
        }
        
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
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white; flex-shrink: 0;
        }
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        .stat-number.text-danger { color: #f87171 !important; }
        .stat-number.text-warning { color: #fbbf24 !important; }
        .stat-number.text-success { color: #34d399 !important; }
        
        /* ============================================================
           DARK CARDS
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
            border-radius: 16px 16px 0 0 !important;
            padding: 14px 20px;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #e0e0e0; font-size: 16px; }
        .card-body { padding: 20px; background: #111827 !important; }
        
        /* ============================================================
           DARK TABLE - PURE DARK
           ============================================================ */
        .resident-table { font-size: 13px; }
        .resident-table th {
            font-weight: 600;
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            background: transparent !important;
        }
        .resident-table td {
            vertical-align: middle;
            padding: 8px 12px;
            color: #e0e0e0;
            border-bottom: 1px solid #1a2a4a;
            background: transparent !important;
        }
        .resident-table tr {
            background: transparent !important;
        }
        .resident-table tr:hover td {
            background: rgba(255,255,255,0.02) !important;
        }
        .resident-table .user-cell { display: flex; align-items: center; gap: 10px; }
        .resident-table .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
            overflow: hidden;
            background: linear-gradient(135deg, #4a5a8a, #5a3a7a);
        }
        .resident-table .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .resident-table .user-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 12px;
            font-weight: 700;
            color: white;
        }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge-active { background: #065f46 !important; color: #34d399 !important; }
        .badge-inactive { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-archived { background: #2a2a3a !important; color: #808090 !important; }
        .badge-gender { background: #1a2a4a !important; color: #93c5fd !important; }
        .badge-room { background: #1a3a6a !important; color: #93c5fd !important; }
        
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
           PAGINATION
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
           NO DELETE BANNER
           ============================================================ */
        .no-delete-banner {
            background: rgba(16, 185, 129, 0.1) !important;
            border: 1px solid rgba(16, 185, 129, 0.2) !important;
            border-radius: 12px;
            padding: 10px 16px;
            color: #6ee7b7;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .no-delete-banner i {
            font-size: 18px;
            color: #34d399;
        }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        .text-primary { color: #93c5fd !important; }
        
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
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            body {
                padding-top: 60px !important;
            }
            
            .navbar {
                height: 60px !important;
            }
            
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
            .resident-table {
                font-size: 11px;
            }
            .resident-table .user-cell {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ============================================================
                HEADER
                ============================================================ -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users me-2" style="color: #1a3a6a;"></i>
                        Residents Report
                        <span class="badge bg-primary ms-2"><?php echo $stats['total']; ?> total</span>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator me-1"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- ============================================================
                NO DELETE BANNER
                ============================================================ -->
                <div class="no-delete-banner mb-3">
                    <i class="fas fa-database"></i>
                    <div>
                        <strong>Permanent Storage</strong>
                        <span class="text-muted ms-2">|</span>
                        <span class="text-muted ms-2">This report contains all residents (active, inactive, and archived). Data is permanent and cannot be deleted to maintain accurate records.</span>
                    </div>
                </div>

                <!-- ============================================================
                STATS CARDS
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Residents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['active']; ?></div>
                                <div class="stat-label">Active</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number text-warning"><?php echo $stats['inactive']; ?></div>
                                <div class="stat-label">Inactive</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-archive"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['archived']; ?></div>
                                <div class="stat-label">Archived</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['with_card']; ?></div>
                                <div class="stat-label">With Card</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-building"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['rooms_used']; ?>/<?php echo $stats['total_rooms']; ?></div>
                                <div class="stat-label">Rooms Used</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                FILTERS
                ============================================================ -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $statusFilter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="archived" <?php echo $statusFilter == 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Room</label>
                            <select class="form-select" name="room">
                                <option value="0">All Rooms</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $roomFilter == $i ? 'selected' : ''; ?>>
                                        Room <?php echo $i; ?> (<?php echo $rooms[$i] ?? 0; ?>)
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="year">
                                <option value="0">All Years</option>
                                <option value="1" <?php echo $yearFilter == 1 ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo $yearFilter == 2 ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo $yearFilter == 3 ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo $yearFilter == 4 ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name, Student ID, or Email" value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-filter w-100">
                                <i class="fas fa-filter me-1"></i> Apply
                            </button>
                        </div>
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- ============================================================
                RESIDENTS LIST - PERMANENT (NO DELETE)
                ============================================================ -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Residents List</h5>
                                <span class="text-muted small">Showing <?php echo count($residents); ?> residents</span>
                            </div>
                            <div>
                                <span class="badge bg-success me-1">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <?php echo $stats['active']; ?> Active
                                </span>
                                <span class="badge bg-warning me-1">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo $stats['inactive']; ?> Inactive
                                </span>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-archive me-1"></i>
                                    <?php echo $stats['archived']; ?> Archived
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover resident-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Resident</th>
                                        <th>Student ID</th>
                                        <th>Room</th>
                                        <th>Course</th>
                                        <th>Year</th>
                                        <th>Gender</th>
                                        <th>Card</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($residents)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No residents found
                                            </td>
                                        </tr>
                                    <?php else: 
                                        $counter = $offset + 1;
                                        foreach ($residents as $resident):
                                            $displayName = $resident['full_name'] ?? 'Unknown';
                                            $studentId = $resident['student_id'] ?? 'N/A';
                                            $room = $resident['room_number'] ?? 'N/A';
                                            $course = $resident['course'] ?? 'N/A';
                                            $year = $resident['year_level'] ?? 'N/A';
                                            $gender = $resident['gender'] ?? 'N/A';
                                            $status = $resident['status'] ?? 'unknown';
                                            $cardUid = $resident['card_uid'] ?? null;
                                            $hasCard = !empty($cardUid);
                                            
                                            // Get profile photo
                                            $photoPath = $resident['profile_photo'] ?? null;
                                            $hasPhoto = false;
                                            $photoUrl = '';
                                            
                                            if (!empty($photoPath)) {
                                                $fullPath = '../../' . $photoPath;
                                                if (strpos($photoPath, 'uploads/') !== 0) {
                                                    $fullPath = '../../uploads/resident_photos/' . $photoPath;
                                                }
                                                if (file_exists($fullPath)) {
                                                    $hasPhoto = true;
                                                    $photoUrl = $fullPath;
                                                }
                                            }
                                            
                                            $initials = '';
                                            $nameParts = explode(' ', $displayName);
                                            foreach ($nameParts as $p) {
                                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                            }
                                            $initials = substr($initials, 0, 2) ?: '?';
                                            
                                            $statusClass = $status == 'active' ? 'badge-active' : ($status == 'inactive' ? 'badge-inactive' : 'badge-archived');
                                        ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar">
                                                            <?php if ($hasPhoto): ?>
                                                                <img src="<?php echo $photoUrl; ?>" alt="<?php echo htmlspecialchars($displayName); ?>" 
                                                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'no-photo\'>' + '<?php echo $initials; ?>' + '</div>'">
                                                            <?php else: ?>
                                                                <div class="no-photo"><?php echo $initials; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div><?php echo htmlspecialchars($displayName); ?></div>
                                                            <div style="font-size: 10px; color: #808090;">
                                                                <?php echo htmlspecialchars($resident['email'] ?? ''); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($studentId); ?></span></td>
                                                <td>
                                                    <?php if ($room != 'N/A' && $room != ''): ?>
                                                        <span class="badge badge-room">
                                                            <i class="fas fa-bed me-1"></i>
                                                            Room <?php echo htmlspecialchars($room); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($course); ?></td>
                                                <td>
                                                    <?php if ($year != 'N/A' && $year > 0): ?>
                                                        <?php 
                                                            $yearLabels = ['', '1st', '2nd', '3rd', '4th'];
                                                            echo $yearLabels[$year] ?? $year . 'th';
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($gender) && $gender != 'N/A'): ?>
                                                        <span class="badge badge-gender">
                                                            <i class="fas <?php echo strtolower($gender) == 'male' ? 'fa-mars' : (strtolower($gender) == 'female' ? 'fa-venus' : 'fa-genderless'); ?> me-1"></i>
                                                            <?php echo htmlspecialchars($gender); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($hasCard): ?>
                                                        <span class="badge badge-active">
                                                            <i class="fas fa-id-card me-1"></i>
                                                            <?php echo htmlspecialchars(substr($cardUid, 0, 8)); ?>...
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-archived">
                                                            <i class="fas fa-times-circle me-1"></i>
                                                            No Card
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <i class="fas <?php echo $status == 'active' ? 'fa-check-circle' : ($status == 'inactive' ? 'fa-clock' : 'fa-archive'); ?> me-1"></i>
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalResidents); ?> of <?php echo $totalResidents; ?> residents
                                        <span class="mx-1 text-muted">|</span>
                                        <span class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
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
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end mb-0">
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=1<?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo $roomFilter > 0 ? '&room=' . $roomFilter : ''; ?><?php echo $yearFilter > 0 ? '&year=' . $yearFilter : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo $roomFilter > 0 ? '&room=' . $roomFilter : ''; ?><?php echo $yearFilter > 0 ? '&year=' . $yearFilter : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo $roomFilter > 0 ? '&room=' . $roomFilter : ''; ?><?php echo $yearFilter > 0 ? '&year=' . $yearFilter : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                <?php if ($endPage < $totalPages): ?>
                                                    <li class="page-item"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo $roomFilter > 0 ? '&room=' . $roomFilter : ''; ?><?php echo $yearFilter > 0 ? '&year=' . $yearFilter : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''; ?><?php echo $roomFilter > 0 ? '&room=' . $roomFilter : ''; ?><?php echo $yearFilter > 0 ? '&year=' . $yearFilter : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                    </div>
                </div>

                <!-- ============================================================
                SUMMARY
                ============================================================ -->
                <div class="text-center text-muted small mt-2">
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo $stats['total']; ?> residents recorded
                    <span class="mx-1">|</span>
                    <i class="fas fa-check-circle me-1 text-success"></i>
                    <?php echo $stats['active']; ?> active
                    <span class="mx-1">|</span>
                    <i class="fas fa-clock me-1 text-warning"></i>
                    <?php echo $stats['inactive']; ?> inactive
                    <span class="mx-1">|</span>
                    <i class="fas fa-archive me-1 text-muted"></i>
                    <?php echo $stats['archived']; ?> archived
                    <span class="mx-1">|</span>
                    <i class="fas fa-venus-mars me-1"></i>
                    <?php echo $stats['male']; ?> Male, <?php echo $stats['female']; ?> Female
                    <span class="mx-1">|</span>
                    <i class="fas fa-id-card me-1 text-primary"></i>
                    <?php echo $stats['with_card']; ?> with cards
                </div>
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
