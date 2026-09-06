<?php
/**
 * Tap-and-Go Doorlock - Residents View
 * VIEW-ONLY - WITH PROFILE PHOTO - WITH CARD STATUS - WITH ADMISSION STATUS
 * PURE DARK MODE - WITH PAGINATION - WITH SEARCH
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
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
// INITIALIZE VARIABLES
// ============================================================
$residents = [];
$totalResidents = 0;
$totalPages = 1;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 12;

// Valid per page options
$perPageOptions = [12, 24, 48, 96];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 12;
}

// ============================================================
// GET RESIDENTS LIST - VIEW ONLY
// ============================================================
try {
    // Count total residents
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE status != 'deleted'";
    $countParams = [];
    $types = "";
    
    if (!empty($search)) {
        $countQuery .= " AND (full_name LIKE ? OR student_id LIKE ? OR room_number LIKE ? OR course LIKE ?)";
        $searchTerm = "%$search%";
        $countParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        $types = "ssss";
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
            u.user_id,
            u.full_name,
            u.student_id,
            u.room_number,
            u.contact_number,
            u.email,
            u.profile_photo,
            u.created_at,
            rp.course,
            rp.year_level,
            rp.gender,
            rp.age,
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
        $query .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.room_number LIKE ? OR rp.course LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        $types = "ssss";
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

// Count statistics
$hasCardCount = 0;
$noCardCount = 0;
$activeCount = 0;
$pendingCount = 0;

foreach ($residents as $resident) {
    if (!empty($resident['card_uid'])) {
        $hasCardCount++;
    } else {
        $noCardCount++;
    }
    
    $admStatus = $resident['admission_status'] ?? 'pending';
    if ($admStatus == 'active') {
        $activeCount++;
    } elseif ($admStatus == 'pending') {
        $pendingCount++;
    }
}

// ============================================================
// HELPER: GET INITIALS
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

// ============================================================
// HELPER: GET PROFILE PHOTO PATH
// ============================================================
function getProfilePhotoPath($photoPath) {
    if (empty($photoPath)) {
        return null;
    }
    
    if (strpos($photoPath, 'uploads/') === 0) {
        $fullPath = '../../' . $photoPath;
    } else {
        $fullPath = '../../uploads/resident_photos/' . $photoPath;
    }
    
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents - Tap-and-Go Doorlock</title>
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
        
        /* RESIDENT CARD */
        .resident-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: all 0.3s ease;
            text-align: center;
            height: 100%;
        }
        .resident-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important;
        }
        .resident-card .name {
            font-weight: 700;
            color: #ffd700 !important;
            font-size: 18px;
        }
        .resident-card .student-id {
            color: #6b7280 !important;
            font-size: 12px;
        }
        .resident-card .course-info {
            color: #9ca3af !important;
            font-size: 14px;
        }
        
        /* RESIDENT AVATAR */
        .resident-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin: 0 auto 15px;
            font-weight: 700;
            overflow: hidden;
            border: 3px solid #1a2a4a;
            background: linear-gradient(135deg, #667eea, #764ba2);
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
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        .resident-avatar .has-photo-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #111827;
        }
        .resident-avatar-wrapper {
            position: relative;
            display: inline-block;
        }
        
        /* BADGES */
        .card-uid-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        .card-uid-badge.has-card {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .card-uid-badge.no-card {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #f87171 !important;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.active {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.2) !important;
            color: #9ca3af !important;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }
        
        /* STAT CARDS */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            text-align: center;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #ffd700 !important;
        }
        .stat-card .label {
            font-size: 13px;
            color: #6b7280 !important;
        }
        
        /* BUTTONS - VIEW ONLY */
        .btn-view {
            background: rgba(139, 92, 246, 0.2) !important;
            color: #a78bfa !important;
            border: 1px solid rgba(139, 92, 246, 0.3) !important;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-view:hover {
            background: rgba(139, 92, 246, 0.3) !important;
            color: #a78bfa !important;
        }
        
        .btn-print-id {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print-id:hover {
            background: rgba(59, 130, 246, 0.3) !important;
            color: #93c5fd !important;
        }
        
        .resident-actions {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        /* SEARCH */
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
        
        /* PAGINATION */
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
        
        /* ALERTS */
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
        }
        .btn-close { filter: invert(1) !important; }
        
        /* MISC */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #6b7280 !important; }
        .text-success { color: #34d399 !important; }
        .text-danger { color: #f87171 !important; }
        .text-warning { color: #fbbf24 !important; }
        
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .card .card-body { background: transparent !important; }
        .card h5 { color: #9ca3af !important; }
        
        .section-header h5 {
            margin: 0;
            color: #ffd700 !important;
            font-weight: 700;
        }
        
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
        
        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1a2a4a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
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
            .resident-card { padding: 15px; }
            .resident-card .name { font-size: 16px; }
            .resident-avatar { width: 60px; height: 60px; font-size: 24px; }
        }
        
        @media (max-width: 576px) {
            .resident-actions {
                flex-direction: column;
                align-items: center;
            }
            .resident-actions .btn {
                width: 100%;
                text-align: center;
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
                
                <!-- HEADER -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users me-2" style="color: #ffd700;"></i>
                        Residents View
                        <span class="badge bg-secondary ms-2"><?php echo $totalResidents; ?> total</span>
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

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- STATISTICS -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="stat-card">
                            <div class="number"><?php echo $totalResidents; ?></div>
                            <div class="label">Total Residents</div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="stat-card">
                            <div class="number"><?php echo $hasCardCount; ?></div>
                            <div class="label">With Card</div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="stat-card">
                            <div class="number"><?php echo $noCardCount; ?></div>
                            <div class="label">No Card</div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="stat-card">
                            <div class="number"><?php echo $activeCount; ?></div>
                            <div class="label">Active Admission</div>
                        </div>
                    </div>
                </div>

                <!-- SEARCH -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="GET" action="" class="search-box d-flex">
                            <input type="text" 
                                   class="form-control" 
                                   name="search" 
                                   placeholder="Search by name, ID, room, course..." 
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
                    <div class="col-md-6 text-end">
                        <span class="text-muted small">
                            <i class="fas fa-users me-1"></i>
                            Showing <?php echo count($residents); ?> of <?php echo $totalResidents; ?> residents
                        </span>
                    </div>
                </div>

                <!-- RESIDENTS LIST -->
                <div class="section-header mb-3">
                    <h5><i class="fas fa-list me-2"></i>Residents List</h5>
                </div>

                <?php if (empty($residents)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No residents found</h5>
                            <?php if (!empty($search)): ?>
                                <p class="text-muted small">Try adjusting your search criteria</p>
                                <a href="residents.php" class="btn btn-outline-secondary btn-sm">Clear Search</a>
                            <?php else: ?>
                                <p class="text-muted small">No resident records available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($residents as $resident): 
                            $name = $resident['full_name'] ?? 'Unknown';
                            $initials = getInitials($name);
                            
                            $photoPath = $resident['profile_photo'] ?? '';
                            $hasPhoto = false;
                            $fullPhotoPath = '';
                            
                            if (!empty($photoPath)) {
                                $fullPhotoPath = getProfilePhotoPath($photoPath);
                                if ($fullPhotoPath) {
                                    $hasPhoto = true;
                                }
                            }
                            
                            $admStatus = $resident['admission_status'] ?? 'pending';
                            $statusClass = $admStatus == 'active' ? 'active' : ($admStatus == 'pending' ? 'pending' : 'inactive');
                        ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="resident-card">
                                    <!-- Resident Avatar -->
                                    <div class="resident-avatar-wrapper">
                                        <div class="resident-avatar">
                                            <?php if ($hasPhoto): ?>
                                                <img src="<?php echo $fullPhotoPath; ?>" 
                                                     alt="<?php echo htmlspecialchars($resident['full_name']); ?>"
                                                     onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                                                <span class="has-photo-badge">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            <?php else: ?>
                                                <div class="no-photo"><?php echo $initials; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="name"><?php echo htmlspecialchars($resident['full_name']); ?></div>
                                    <div class="student-id">
                                        <span class="text-muted small">
                                            <i class="fas fa-id-card me-1"></i>
                                            <?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                    <div class="course-info">
                                        <?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?>
                                        <?php if (!empty($resident['year_level'])): ?>
                                            - <?php echo htmlspecialchars($resident['year_level']); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Room -->
                                    <div class="mt-1">
                                        <span class="text-muted small">
                                            <i class="fas fa-door-open me-1"></i>
                                            Room: <?php echo htmlspecialchars($resident['room_number'] ?? 'Not Assigned'); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Card UID -->
                                    <div class="mt-2">
                                        <?php if (!empty($resident['card_uid'])): ?>
                                            <span class="card-uid-badge has-card">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($resident['card_uid']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="card-uid-badge no-card">
                                                <i class="fas fa-times-circle me-1"></i>
                                                No Card
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Admission Status -->
                                    <div class="mt-1">
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $admStatus == 'active' ? 'fa-check-circle' : ($admStatus == 'pending' ? 'fa-clock' : 'fa-minus-circle'); ?> me-1"></i>
                                            <?php echo ucfirst($admStatus); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Resident Actions - VIEW ONLY -->
                                    <div class="resident-actions">
                                        <?php if (!empty($resident['card_uid'])): ?>
                                            <a href="print-resident-id.php?uid=<?php echo $resident['card_uid']; ?>" 
                                               target="_blank" 
                                               class="btn-print-id">
                                                <i class="fas fa-print me-1"></i> Print ID
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="view-resident.php?id=<?php echo $resident['user_id']; ?>" 
                                           class="btn-view">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        
                                        <?php if (!empty($resident['admission_status'])): ?>
                                            <a href="view-admission.php?id=<?php echo $resident['user_id']; ?>" 
                                               class="btn-view">
                                                <i class="fas fa-clipboard-list me-1"></i> Admission
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

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

                <!-- FOOTER -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span><?php echo $hasCardCount; ?> with card, <?php echo $noCardCount; ?> without card</span>
                    <span class="mx-2">|</span>
                    <span><?php echo $activeCount; ?> active, <?php echo $pendingCount; ?> pending</span>
                </footer>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // CHANGE PER PAGE
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // UPDATE TIME
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
        
        // SIDEBAR TOGGLE
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
