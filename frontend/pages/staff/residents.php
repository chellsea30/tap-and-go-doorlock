<?php
/**
 * Tap-and-Go Doorlock - Staff Residents List
 * VIEW ONLY - SAME DESIGN AS ADMIN RESIDENTS PAGE
 * PURE DARK MODE - No white backgrounds
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check staff authentication
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include 'includes/header.php';

// ============================================================
// INITIALIZE VARIABLES
// ============================================================
$residents = [];
$totalResidents = 0;
$totalPages = 1;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$error = '';

// Valid per page options
$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

// ============================================================
// GET RESIDENTS LIST
// ============================================================
try {
    $conn = getDBConnection();
    
    // Count total residents
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE status != 'deleted'";
    $countParams = [];
    $types = "";
    
    if (!empty($search)) {
        $countQuery .= " AND (full_name LIKE ? OR student_id LIKE ? OR room_number LIKE ?)";
        $searchTerm = "%$search%";
        $countParams = [$searchTerm, $searchTerm, $searchTerm];
        $types = "sss";
    }
    
    $stmt = $conn->prepare($countQuery);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$countParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRow = $result->fetch_assoc();
    $totalResidents = (int)($totalRow['total'] ?? 0);
    $stmt->close();
    
    $totalPages = ceil($totalResidents / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages) $page = $totalPages;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $perPage;
    
    // Get residents
    $query = "
        SELECT 
            u.*,
            u.profile_photo,
            rp.course,
            rp.year_level,
            rp.gender,
            rp.birth_date,
            rp.age,
            rp.religion,
            rp.dialect,
            rp.emergency_name,
            rp.emergency_relationship,
            rp.emergency_address,
            rp.date_registered,
            c.card_uid,
            c.status as card_status,
            ar.status as admission_status,
            ar.room_assignment
        FROM users u
        LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
        LEFT JOIN rfid_cards c ON u.user_id = c.user_id AND c.status = 'active'
        LEFT JOIN admission_records ar ON u.user_id = ar.user_id
        WHERE u.status != 'deleted'
    ";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.room_number LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
        $types = "sss";
    }
    
    $query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $residents = [];
    while ($row = $result->fetch_assoc()) {
        $residents[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    $error = 'Error loading residents: ' . $e->getMessage();
    $residents = [];
}

// ============================================================
// HELPER FUNCTION: GET INITIALS
// ============================================================
function getInitials($name) {
    if (empty($name)) return '?';
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper($part[0]);
        }
    }
    return substr($initials, 0, 2) ?: '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents - Staff View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           FIXED NAVBAR
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 56px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           SIDEBAR - FIXED POSITION
           ============================================================ */
        .sidebar {
            position: fixed !important;
            top: 56px !important;
            left: 0 !important;
            bottom: 0 !important;
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
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar .nav-link i {
            width: 18px;
            text-align: center;
        }
        .sidebar-footer { 
            border-top-color: #1a2a4a !important;
            padding: 12px 16px !important;
            margin-top: 10px !important;
        }
        .sidebar-footer .text-muted { color: #606070 !important; font-size: 11px !important; }
        
        /* ============================================================
           WRAPPER - FULL HEIGHT
           ============================================================ */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* ============================================================
           MAIN CONTENT - OFFSET FOR FIXED NAVBAR & SIDEBAR
           ============================================================ */
        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 15px 25px !important;
            flex: 1;
            min-height: calc(100vh - 56px) !important;
            background: #0a0e1a !important;
        }
        
        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            margin-left: 220px !important;
            margin-top: 0 !important;
            padding: 10px 25px !important;
            background: #0d1528 !important;
            border-top: 1px solid #1a2a4a !important;
            color: #606070 !important;
            font-size: 12px !important;
            text-align: center !important;
        }
        
        /* ============================================================
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-body { background: #111827 !important; }
        .card .text-muted { color: #808090 !important; }
        .card h5 { color: #e0e0e0 !important; }
        
        /* ============================================================
           RESIDENT CARD - DARK
           ============================================================ */
        .resident-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 14px 18px;
            margin-bottom: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.3) !important;
            transition: all 0.3s ease;
            border-left: 3px solid #1a3a6a;
        }
        .resident-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.5) !important;
        }
        .resident-card .text-muted { color: #808090 !important; }
        .resident-card strong { color: #e0e0e0 !important; }
        
        /* ============================================================
           RESIDENT AVATAR
           ============================================================ */
        .resident-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid #2a2a4a;
            position: relative;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            cursor: pointer;
        }
        .resident-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .resident-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 18px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
        }
        .resident-info h5 { color: #e0e0e0 !important; margin: 0; font-size: 14px; font-weight: 600; }
        .resident-info .text-muted { color: #808090 !important; font-size: 11px; }
        
        /* ============================================================
           BUTTON STYLES - DARK (SMALLER)
           ============================================================ */
        .btn-action {
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            margin: 1px;
        }
        .btn-action:hover { transform: translateY(-1px); }
        
        .btn-admission { 
            background: #1a2a4a !important; 
            color: #93c5fd !important; 
            border-color: #1a3a6a !important; 
        }
        .btn-admission:hover { background: #1a3a6a !important; color: #bfdbfe !important; }
        
        .btn-profile { 
            background: #1a2a3a !important; 
            color: #6ee7b7 !important; 
            border-color: #065f46 !important; 
        }
        .btn-profile:hover { background: #065f46 !important; color: #a7f3d0 !important; }
        
        .btn-view { 
            background: #1a1a3a !important; 
            color: #a5b4fc !important; 
            border-color: #3730a3 !important; 
        }
        .btn-view:hover { background: #3730a3 !important; color: #c7d2fe !important; }
        
        .btn-primary {
            background: #1a3a6a !important;
            border-color: #1a3a6a !important;
            color: white !important;
            padding: 5px 14px !important;
            font-size: 12px !important;
            border-radius: 8px !important;
        }
        .btn-primary:hover {
            background: #2a5a9a !important;
            border-color: #2a5a9a !important;
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
            font-size: 12px !important;
            padding: 5px 12px !important;
            border-radius: 8px !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           VIEW ONLY BADGE
           ============================================================ */
        .view-only-badge {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        /* ============================================================
           BADGES - DARK
           ============================================================ */
        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 500; }
        .badge-active { background: #065f46 !important; color: #6ee7b7 !important; }
        .badge-pending { background: #92400e !important; color: #fcd34d !important; }
        .badge-inactive { background: #2a2a3a !important; color: #808090 !important; }
        .badge-no-card { background: #2a2a3a !important; color: #808090 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-info { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        
        /* ============================================================
           SEARCH BOX - DARK
           ============================================================ */
        .search-box { max-width: 380px; }
        .search-box .form-control {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 10px 0 0 10px;
            padding: 7px 14px;
            font-size: 13px;
            height: 36px;
        }
        .search-box .form-control::placeholder { color: #606070 !important; }
        .search-box .form-control:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .search-box .btn {
            background: #1a3a6a !important;
            border: 1px solid #1a3a6a !important;
            color: white !important;
            border-radius: 0 10px 10px 0;
            padding: 7px 14px;
            height: 36px;
        }
        .search-box .btn:hover { background: #2a5a9a !important; }
        .search-box .btn-outline-secondary {
            background: transparent !important;
            border-color: #2a2a4a !important;
            color: #808090 !important;
            border-radius: 10px !important;
            height: 36px;
            padding: 7px 12px !important;
            font-size: 12px !important;
        }
        .search-box .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           PAGINATION - DARK
           ============================================================ */
        .pagination-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 10px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-top: 12px;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: none;
            color: #9090a0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 5px 12px;
            font-size: 12px;
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
        .page-info { color: #808090 !important; font-size: 12px; }
        .page-info strong { color: #93c5fd !important; }
        
        /* ============================================================
           PER PAGE SELECTOR - DARK
           ============================================================ */
        .per-page-selector select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 12px;
        }
        .per-page-selector select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .per-page-selector label { color: #808090 !important; font-size: 12px; margin: 0; }
        
        /* ============================================================
           PAGE HEADER STYLES
           ============================================================ */
        .page-header {
            padding-bottom: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1a2a4a;
        }
        .page-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #e0e0e0;
        }
        .page-header h1 i {
            color: #1a3a6a;
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed !important;
                top: 56px !important;
                bottom: 0 !important;
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
            .footer {
                margin-left: 0 !important;
                padding: 8px 15px !important;
            }
            .resident-card { padding: 12px; }
            .resident-actions { margin-top: 8px; }
            .resident-actions .btn-action {
                margin-bottom: 3px;
                font-size: 10px;
                padding: 3px 8px;
            }
            .pagination .page-link { padding: 4px 10px; font-size: 11px; }
            .pagination-container { padding: 8px 12px; }
            .page-info { font-size: 11px; }
            
            .btn-new-resident, .btn-admission-form {
                font-size: 11px !important;
                padding: 6px 12px !important;
            }
        }
        
        /* ============================================================
           MISC DARK
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .small { font-size: 11px !important; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
                <h1><i class="fas fa-users me-2"></i>Residents</h1>
                <span class="view-only-badge">
                    <i class="fas fa-eye me-1"></i> View Only
                </span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <form method="GET" action="" class="search-box d-flex">
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Search by name, ID, course, or room..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="residents.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <span class="text-muted small">
                        <i class="fas fa-users me-1"></i>
                        Showing <?php echo count($residents); ?> of <?php echo $totalResidents; ?> residents
                        <?php if (!empty($search)): ?>
                            <br><span class="text-muted">(filtered)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Residents List -->
            <?php if (empty($residents)): ?>
                <div class="card">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-2"></i>
                        <h5 class="text-muted">No residents found</h5>
                        <?php if (!empty($search)): ?>
                            <p class="text-muted small">Try adjusting your search criteria</p>
                            <a href="residents.php" class="btn btn-outline-secondary btn-sm">Clear Search</a>
                        <?php else: ?>
                            <p class="text-muted small">No residents in the system yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($residents as $resident): 
                    $photoPath = $resident['profile_photo'] ?? '';
                    $hasPhoto = false;
                    $fullPhotoPath = '';
                    
                    if (!empty($photoPath)) {
                        if (strpos($photoPath, 'uploads/') === 0) {
                            $fullPhotoPath = '../../' . $photoPath;
                        } else {
                            $fullPhotoPath = '../../uploads/resident_photos/' . $photoPath;
                        }
                        
                        if (file_exists($fullPhotoPath)) {
                            $hasPhoto = true;
                        }
                    }
                    
                    $initials = getInitials($resident['full_name'] ?? '');
                ?>
                    <div class="resident-card">
                        <div class="row align-items-center">
                            <!-- Avatar & Name -->
                            <div class="col-md-4 col-lg-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="resident-avatar">
                                        <?php if ($hasPhoto): ?>
                                            <img src="<?php echo $fullPhotoPath; ?>" 
                                                 alt="Photo of <?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>"
                                                 onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                                        <?php else: ?>
                                            <div class="no-photo"><?php echo $initials; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="resident-info">
                                        <h5><?php echo htmlspecialchars($resident['full_name'] ?? 'Unknown'); ?></h5>
                                        <span class="text-muted">
                                            <i class="fas fa-id-card me-1"></i>
                                            <?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>
                                        </span>
                                        <br>
                                        <span class="text-muted">
                                            <i class="fas fa-graduation-cap me-1"></i>
                                            <?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?>
                                            - <?php echo htmlspecialchars($resident['year_level'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Room & Status -->
                            <div class="col-md-3 col-lg-3">
                                <div>
                                    <span class="text-muted small">Room</span>
                                    <br>
                                    <strong><?php echo htmlspecialchars($resident['room_number'] ?? 'Not Assigned'); ?></strong>
                                </div>
                                <div class="mt-1">
                                    <span class="text-muted small">Card Status</span>
                                    <br>
                                    <?php if (!empty($resident['card_uid'])): ?>
                                        <span class="badge-status badge-active">
                                            <i class="fas fa-check-circle me-1"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status badge-no-card">
                                            <i class="fas fa-times-circle me-1"></i> No Card
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Admission Status -->
                            <div class="col-md-2 col-lg-2">
                                <div>
                                    <span class="text-muted small">Admission</span>
                                    <br>
                                    <?php 
                                        $admStatus = $resident['admission_status'] ?? 'pending';
                                        if ($admStatus == 'active'): ?>
                                            <span class="badge-status badge-active">
                                                <i class="fas fa-check-circle me-1"></i> Active
                                            </span>
                                        <?php elseif ($admStatus == 'pending'): ?>
                                            <span class="badge-status badge-pending">
                                                <i class="fas fa-clock me-1"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status badge-inactive">
                                                <i class="fas fa-minus-circle me-1"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    ?>
                                </div>
                            </div>

                            <!-- Action Buttons (VIEW ONLY) -->
                            <div class="col-md-3 col-lg-4">
                                <div class="resident-actions d-flex flex-wrap gap-1">
                                    <a href="view-resident.php?id=<?php echo $resident['user_id']; ?>" 
                                       class="btn btn-action btn-view">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    
                                    <?php if (!empty($resident['admission_status'])): ?>
                                        <a href="view-admission.php?id=<?php echo $resident['user_id']; ?>" 
                                           class="btn btn-action btn-admission">
                                            <i class="fas fa-clipboard-list me-1"></i> Admission
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($resident['course'])): ?>
                                        <a href="view-profile.php?id=<?php echo $resident['user_id']; ?>" 
                                           class="btn btn-action btn-profile">
                                            <i class="fas fa-user me-1"></i> Profile
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1 || $totalResidents > 0): ?>
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
                            <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                <!-- Per Page Selector -->
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
                                
                                <!-- Pagination -->
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mb-0">
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <li class="page-item"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
            <?php endif; ?>
        </main>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <i class="fas fa-eye me-1"></i> View Only Access
        <span class="mx-2">|</span>
        &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System - ISU-Echague Dormitory. All rights reserved.
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // CHANGE PER PAGE
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // SIDEBAR TOGGLE (mobile)
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
