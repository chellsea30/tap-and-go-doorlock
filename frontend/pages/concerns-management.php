<?php
/**
 * Tap-and-Go Doorlock - Concerns Management (Admin/Staff)
 * COMPLETE WITH ROOM NUMBER
 * PURE DARK MODE - FIXED LAYOUT SAME AS DASHBOARD
 */

session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication (Admin or Staff)
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
        header('Location: login.php');
        exit();
    }
}
// Include header
include '../includes/header.php'; 
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
$roomFilter = isset($_GET['room']) ? trim($_GET['room']) : '';

// ============================================================
// GET STATS
// ============================================================
$stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'high_priority' => 0,
    'rooms' => []
];

$result = $conn->query("SELECT COUNT(*) as count FROM student_concerns");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM student_concerns WHERE status = 'pending'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM student_concerns WHERE status = 'in_progress'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['in_progress'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM student_concerns WHERE status = 'resolved'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['resolved'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM student_concerns WHERE priority = 'high' AND status != 'resolved' AND status != 'closed'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['high_priority'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT room_number, COUNT(*) as count 
    FROM student_concerns 
    WHERE room_number IS NOT NULL AND room_number != ''
    GROUP BY room_number 
    ORDER BY count DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['rooms'][] = $row;
    }
}

// ============================================================
// HANDLE RESPONSE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond'])) {
    $concern_id = (int)$_POST['concern_id'];
    $response = trim($_POST['response'] ?? '');
    $status = $_POST['status'] ?? 'resolved';
    
    if (empty($response)) {
        $error = 'Please enter a response.';
    } else {
        $stmt = $conn->prepare("UPDATE student_concerns SET admin_response = ?, status = ? WHERE concern_id = ?");
        $stmt->bind_param("ssi", $response, $status, $concern_id);
        if ($stmt->execute()) {
            $success = "Response sent successfully!";
            if (isset($_SESSION['admin_id'])) {
                logAudit($_SESSION['admin_id'], 'Concern Response', "Responded to concern ID: $concern_id");
            } else {
                logStaffAudit($_SESSION['staff_id'], 'Concern Response', "Responded to concern ID: $concern_id");
            }
        } else {
            $error = "Failed to send response.";
        }
        $stmt->close();
    }
}

// ============================================================
// HANDLE BULK ACTION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_status = $_POST['bulk_status'] ?? '';
    $selected = isset($_POST['selected']) ? $_POST['selected'] : [];
    
    if (!empty($selected) && !empty($bulk_status)) {
        $ids = implode(',', array_map('intval', $selected));
        $stmt = $conn->prepare("UPDATE student_concerns SET status = ? WHERE concern_id IN ($ids)");
        $stmt->bind_param("s", $bulk_status);
        if ($stmt->execute()) {
            $success = count($selected) . " concerns updated to " . ucfirst($bulk_status);
        } else {
            $error = "Failed to update concerns.";
        }
        $stmt->close();
    }
}

// ============================================================
// GET CONCERNS WITH FILTERS AND PAGINATION
// ============================================================
$query = "SELECT * FROM student_concerns WHERE 1=1";
$params = [];
$types = "";

if (!empty($statusFilter)) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($roomFilter)) {
    $query .= " AND room_number LIKE ?";
    $params[] = "%$roomFilter%";
    $types .= "s";
}

$countQuery = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalConcerns = 0;
if ($row = $countResult->fetch_assoc()) {
    $totalConcerns = (int)$row['total'];
}
$stmt->close();

$totalPages = ceil($totalConcerns / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$query .= " ORDER BY 
    FIELD(status, 'pending', 'in_progress', 'resolved', 'closed'),
    priority = 'high' DESC,
    created_at DESC
    LIMIT $perPage OFFSET $offset";

$concerns = [];
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $concerns[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concerns Management - Tap-and-Go Doorlock</title>
    
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
        
        /* ============================================================
           FIX: MAIN CONTENT OFFSET FOR FIXED NAVBAR
           ============================================================ */
        .container-fluid {
            padding-top: 10px !important;
        }
        
        main {
            padding-top: 10px !important;
            margin-top: 0 !important;
        }
        
        /* ============================================================
           DARK NAVBAR OVERRIDE - SAME AS DASHBOARD
           ============================================================ */
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
        
        /* ============================================================
           DARK SIDEBAR - SAME AS DASHBOARD
           ============================================================ */
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
           DARK STAT CARDS - SAME AS DASHBOARD
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
           DARK CONCERN CARDS
           ============================================================ */
        .concern-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .concern-card:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .concern-card.status-pending { border-left-color: #f59e0b; }
        .concern-card.status-in_progress { border-left-color: #3b82f6; }
        .concern-card.status-resolved { border-left-color: #10b981; }
        .concern-card.status-closed { border-left-color: #6b7280; opacity: 0.7; }
        
        .concern-card .subject {
            font-weight: 700;
            color: #e0e0e0 !important;
            font-size: 16px;
        }
        .concern-card .student {
            font-size: 13px;
            color: #808090 !important;
        }
        .concern-card .room-badge {
            background: #1a2a4a !important;
            color: #93c5fd !important;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .concern-card .message {
            color: #b0b0c0 !important;
            margin: 8px 0;
        }
        .concern-card .response-box {
            background: #15152a !important;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            border-left: 3px solid #10b981;
        }
        .concern-card .response-box strong {
            color: #93c5fd !important;
        }
        .concern-card .response-box div { color: #b0b0c0 !important; }
        
        /* ============================================================
           DARK CARDS - SAME AS DASHBOARD
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
        .card .text-muted { color: #808090 !important; }
        
        /* ============================================================
           DARK FORM ELEMENTS
           ============================================================ */
        .form-control, .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #e0e0e0 !important;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .form-control::placeholder { color: #606070 !important; }
        .form-label { color: #b0b0c0 !important; font-weight: 500; font-size: 13px; }
        
        /* ============================================================
           DARK BUTTONS
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 8px 25px;
            border-radius: 10px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,106,0.4);
            color: white !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            color: white !important;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           DARK FILTERS & BULK ACTIONS
           ============================================================ */
        .filter-box {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .filter-box .form-control, .filter-box .form-select {
            background: #1a1a2e !important;
            border-color: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .bulk-actions {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .bulk-actions .form-select {
            width: 180px;
            background: #1a1a2e !important;
            border-color: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .bulk-actions .fw-bold { color: #e0e0e0 !important; }
        .bulk-actions .text-muted { color: #808090 !important; }
        
        /* ============================================================
           DARK ROOM STATS
           ============================================================ */
        .room-stat {
            display: inline-block;
            background: #1a2a4a !important;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 500;
            color: #b0b0c0 !important;
            margin: 3px;
        }
        .room-stat .count {
            background: #667eea !important;
            color: white;
            border-radius: 50%;
            padding: 0 8px;
            margin-left: 5px;
            font-size: 10px;
        }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge.bg-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge.bg-info { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge.bg-success { background: #065f46 !important; color: #34d399 !important; }
        .badge.bg-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge.bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge.bg-light { background: #1a2a4a !important; color: #b0b0c0 !important; }
        .badge.bg-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        
        /* ============================================================
           DARK ALERTS
           ============================================================ */
        .alert-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
        }
        .alert .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           DARK PAGINATION - SAME AS DASHBOARD
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
        .text-danger { color: #f87171 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-success { color: #34d399 !important; }
        
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
        
        .pulse-badge {
            animation: pulseBadge 1s infinite;
        }
        @keyframes pulseBadge {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        /* ============================================================
           RESPONSIVE - SAME AS DASHBOARD
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
            
            .concern-card { padding: 15px; }
            .bulk-actions { flex-direction: column; align-items: stretch; }
            .bulk-actions .form-select { width: 100%; }
            
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
        }
        
        @media (max-width: 576px) {
            .concern-card .d-flex {
                flex-direction: column;
                gap: 8px;
            }
            .concern-card .form .row {
                flex-direction: column;
            }
            .concern-card .form .row .col-md-3,
            .concern-card .form .row .col-md-6 {
                width: 100%;
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ============================================================
                HEADER - SAME AS DASHBOARD
                ============================================================ -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-exclamation-circle me-2" style="color: #1a3a6a;"></i>
                        Student Concerns
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge bg-danger ms-2 pulse-badge">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?php echo $stats['pending']; ?> pending
                            </span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
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

                <!-- ============================================================
                STATS CARDS - SAME AS DASHBOARD
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-list"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['pending'] > 0 ? 'text-warning' : ''; ?>">
                                    <?php echo $stats['pending']; ?>
                                </div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <?php if ($stats['pending'] > 0): ?>
                                <span class="badge bg-warning pulse-badge" style="position:absolute; top:8px; right:8px;"><?php echo $stats['pending']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-spinner"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                                <div class="stat-label">In Progress</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['resolved']; ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <div class="stat-number <?php echo $stats['high_priority'] > 0 ? 'text-danger' : ''; ?>">
                                    <?php echo $stats['high_priority']; ?>
                                </div>
                                <div class="stat-label">High Priority</div>
                            </div>
                            <?php if ($stats['high_priority'] > 0): ?>
                                <span class="badge bg-danger pulse-badge" style="position:absolute; top:8px; right:8px;">🚨</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($concerns); ?></div>
                                <div class="stat-label">Showing</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                ROOM STATS
                ============================================================ -->
                <?php if (!empty($stats['rooms'])): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h5><i class="fas fa-door-open me-2"></i>Concerns by Room</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($stats['rooms'] as $room): ?>
                            <span class="room-stat">
                                Room <?php echo htmlspecialchars($room['room_number']); ?>
                                <span class="count"><?php echo $room['count']; ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                FILTERS
                ============================================================ -->
                <div class="filter-box">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Filter by Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $statusFilter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $statusFilter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $statusFilter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Room</label>
                            <input type="text" class="form-control" name="room" placeholder="Room number" value="<?php echo htmlspecialchars($roomFilter); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="concerns-management.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-1"></i> Clear
                            </a>
                        </div>
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- ============================================================
                BULK ACTIONS
                ============================================================ -->
                <?php if (!empty($concerns)): ?>
                <form method="POST" class="bulk-actions" id="bulkForm">
                    <span class="fw-bold me-2">Bulk Action:</span>
                    <select class="form-select" name="bulk_status">
                        <option value="">Select Status</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button type="submit" name="bulk_action" class="btn btn-primary btn-sm">
                        <i class="fas fa-check-double me-1"></i> Apply
                    </button>
                    <span class="text-muted small ms-2">Select concerns using checkboxes</span>
                </form>
                <?php endif; ?>

                <!-- ============================================================
                CONCERNS LIST
                ============================================================ -->
                <?php if (empty($concerns)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No concerns found</h5>
                            <p class="text-muted">
                                <?php if (!empty($statusFilter) || !empty($roomFilter)): ?>
                                    Try adjusting your filters
                                <?php else: ?>
                                    Students can submit concerns from their portal
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($concerns as $concern): ?>
                        <div class="concern-card status-<?php echo $concern['status']; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="subject">
                                        <input type="checkbox" name="selected[]" value="<?php echo $concern['concern_id']; ?>" form="bulkForm" class="me-2">
                                        <?php echo htmlspecialchars($concern['subject']); ?>
                                        <span class="badge <?php echo $concern['status'] == 'pending' ? 'bg-warning' : ($concern['status'] == 'in_progress' ? 'bg-info' : ($concern['status'] == 'resolved' ? 'bg-success' : 'bg-secondary')); ?> ms-1">
                                            <?php echo ucfirst(str_replace('_', ' ', $concern['status'])); ?>
                                        </span>
                                        <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($concern['category']); ?></span>
                                        <span class="badge <?php echo $concern['priority'] == 'high' ? 'bg-danger' : ($concern['priority'] == 'medium' ? 'bg-warning' : 'bg-secondary'); ?> ms-1">
                                            <?php echo ucfirst($concern['priority']); ?>
                                        </span>
                                    </div>
                                    <div class="student">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($concern['student_name']); ?>
                                        (<?php echo htmlspecialchars($concern['student_id_number']); ?>)
                                        <span class="mx-1">•</span>
                                        <i class="fas fa-door-open me-1"></i>
                                        <span class="room-badge"><i class="fas fa-bed me-1"></i> Room <?php echo htmlspecialchars($concern['room_number'] ?? 'N/A'); ?></span>
                                        <span class="mx-1">•</span>
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($concern['created_at'])); ?>
                                    </div>
                                    <div class="message"><?php echo nl2br(htmlspecialchars($concern['message'])); ?></div>
                                    
                                    <?php if (!empty($concern['admin_response'])): ?>
                                        <div class="response-box">
                                            <strong><i class="fas fa-reply me-1"></i> Admin Response:</strong>
                                            <div class="mt-1"><?php echo nl2br(htmlspecialchars($concern['admin_response'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Response Form -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="concern_id" value="<?php echo $concern['concern_id']; ?>">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <textarea class="form-control" name="response" rows="2" placeholder="Type your response here..."></textarea>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="status">
                                            <option value="in_progress" <?php echo $concern['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $concern['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="closed" <?php echo $concern['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" name="respond" class="btn btn-submit w-100">
                                            <i class="fas fa-reply me-1"></i> Respond
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- ============================================================
                PAGINATION WITH SHOW ENTRIES - SAME AS DASHBOARD
                ============================================================ -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalConcerns); ?> of <?php echo $totalConcerns; ?> concerns
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
                                            <a class="page-link" href="?page=1<?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($roomFilter) ? '&room=' . urlencode($roomFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($roomFilter) ? '&room=' . urlencode($roomFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($roomFilter) ? '&room=' . urlencode($roomFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <li class="page-item"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($roomFilter) ? '&room=' . urlencode($roomFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($roomFilter) ? '&room=' . urlencode($roomFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                
                <!-- ============================================================
                FOOTER - SAME AS DASHBOARD
                ============================================================ -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span>Total: <?php echo $stats['total']; ?> concerns</span>
                    <span class="mx-2">|</span>
                    <span class="text-warning"><i class="fas fa-clock me-1"></i><?php echo $stats['pending']; ?> pending</span>
                    <span class="mx-2">|</span>
                    <span class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo $stats['resolved']; ?> resolved</span>
                    <?php if ($stats['high_priority'] > 0): ?>
                        <span class="text-danger ms-3">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo $stats['high_priority']; ?> high priority
                        </span>
                    <?php endif; ?>
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
        // SELECT ALL CHECKBOXES
        // ============================================================
        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = this.checked);
        });
        
        // ============================================================
        // UPDATE TIME - SAME AS DASHBOARD
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