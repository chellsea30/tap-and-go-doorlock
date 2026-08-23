<?php
/**
 * Tap-and-Go Doorlock - Staff Profile
 * PURE DARK MODE WITH PHOTO SUPPORT - FIXED VERSION
 */

// Start session
session_start();

// Load config and functions
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// Check if logged in as staff
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: ../login.php');
    exit();
}

// Get database connection
$conn = getDBConnection();

// Get staff data
$staff = null;
$stmt = $conn->prepare("SELECT * FROM staff_users WHERE staff_id = ?");
$stmt->bind_param("i", $_SESSION['staff_id']);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
$stmt->close();

// Get dark mode setting
$darkModeClass = '';
$darkModeFromDb = 'false';
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

// Get stats for profile
$stats = [
    'total_access' => 0,
    'total_alerts' => 0,
    'total_visitors' => 0
];

// Total access logs
$result = $conn->query("SELECT COUNT(*) as count FROM access_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_access'] = (int)$row['count'];
}

// Total alerts
$result = $conn->query("SELECT COUNT(*) as count FROM alert_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_alerts'] = (int)$row['count'];
}

// Total visitors
$result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_visitors'] = (int)$row['count'];
}

// ============================================================
// PHOTO PATH DETECTION - FIXED VERSION
// ============================================================
$photoPath = $staff['avatar'] ?? '';
$hasPhoto = false;
$photoUrl = '';

// Debug: Check what's in the database
// echo "<!-- DEBUG: photoPath = " . $photoPath . " -->";

if (!empty($photoPath)) {
    // Get the filename from the path
    $filename = basename($photoPath);
    
    // Define the upload directory (where files are actually stored)
    $upload_dir = __DIR__ . '/../../../uploads/staff_photos/';
    $upload_dir2 = $_SERVER['DOCUMENT_ROOT'] . '/tap-and-go/uploads/staff_photos/';
    $upload_dir3 = $_SERVER['DOCUMENT_ROOT'] . '/tap-and-go-doorlock/uploads/staff_photos/';
    
    // Check all possible locations
    $possible_files = [
        $upload_dir . $filename,
        $upload_dir2 . $filename,
        $upload_dir3 . $filename,
        '../../uploads/staff_photos/' . $filename,
        '../uploads/staff_photos/' . $filename,
        $_SERVER['DOCUMENT_ROOT'] . '/tap-and-go/' . $photoPath,
        $_SERVER['DOCUMENT_ROOT'] . '/tap-and-go-doorlock/' . $photoPath,
    ];
    
    foreach ($possible_files as $file_path) {
        if (file_exists($file_path)) {
            $hasPhoto = true;
            
            // Determine the correct URL
            if (strpos($file_path, 'tap-and-go-doorlock') !== false) {
                $photoUrl = '/tap-and-go-doorlock/uploads/staff_photos/' . $filename;
            } elseif (strpos($file_path, 'tap-and-go') !== false && strpos($file_path, 'tap-and-go-doorlock') === false) {
                $photoUrl = '/tap-and-go/uploads/staff_photos/' . $filename;
            } elseif (strpos($file_path, $_SERVER['DOCUMENT_ROOT']) === 0) {
                // Convert absolute path to URL
                $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file_path);
                $photoUrl = $relative_path;
            } else {
                // Fallback: use relative path
                $photoUrl = '../../uploads/staff_photos/' . $filename;
            }
            break;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .sidebar-footer {
            padding: 10px 0 20px 0;
            border-top: 1px solid #1a2a4a !important;
            margin-top: auto;
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
        }
        
        /* ============================================================
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-header {
            background: #111827 !important;
            border-bottom: 1px solid #1a2a4a !important;
        }
        .card-header h5 { color: #e0e0e0 !important; }
        .card-body { background: #111827 !important; }
        
        /* ============================================================
           PROFILE AVATAR - WITH PHOTO SUPPORT
           ============================================================ */
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #ffd700;
            margin: 0 auto 15px;
            border: 3px solid #1a2a4a;
            box-shadow: 0 8px 30px rgba(0,0,0,0.4);
            overflow: hidden;
            position: relative;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 48px;
            font-weight: 700;
            color: #ffd700;
        }
        .profile-avatar .photo-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #111827;
        }
        
        .profile-name {
            color: #e0e0e0;
            font-weight: 700;
            font-size: 24px;
            text-align: center;
        }
        .profile-role {
            color: #fbbf24;
            font-size: 14px;
            text-align: center;
        }
        .profile-id {
            color: #606070;
            font-size: 13px;
            text-align: center;
        }
        
        /* ============================================================
           PROFILE INFO
           ============================================================ */
        .info-item {
            padding: 12px 0;
            border-bottom: 1px solid #1a2a4a;
            display: flex;
            justify-content: space-between;
        }
        .info-item:last-child { border-bottom: none; }
        .info-item .label {
            color: #808090;
            font-size: 14px;
        }
        .info-item .value {
            color: #e0e0e0;
            font-size: 14px;
            font-weight: 500;
        }
        .info-item .value i {
            color: #ffd700;
            margin-right: 6px;
        }
        
        /* ============================================================
           STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white; flex-shrink: 0;
        }
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        
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
           LOGOUT MODAL - DARK THEME
           ============================================================ */
        .modal-content {
            background: rgba(20, 30, 50, 0.95) !important;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08) !important;
            border-radius: 20px !important;
            box-shadow: 0 40px 80px rgba(0,0,0,0.8) !important;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.06) !important;
            padding: 20px 25px !important;
        }
        .modal-header .modal-title {
            color: #fff !important;
            font-weight: 600 !important;
        }
        .modal-header .modal-title i {
            color: #ef4444 !important;
        }
        .modal-body {
            padding: 25px !important;
        }
        .modal-body .logout-confirm-text {
            color: rgba(255,255,255,0.7) !important;
            font-size: 15px;
            text-align: center;
            margin-bottom: 5px;
        }
        .modal-body .logout-sub-text {
            color: rgba(255,255,255,0.3) !important;
            font-size: 13px;
            text-align: center;
        }
        .modal-body .user-info {
            color: rgba(255,255,255,0.2) !important;
            font-size: 12px;
            text-align: center;
            margin-top: 15px;
        }
        .modal-body .user-info .badge-staff-logout {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #60a5fa !important;
            padding: 3px 12px !important;
            border-radius: 20px !important;
        }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.06) !important;
            padding: 15px 25px !important;
            justify-content: center !important;
            gap: 12px !important;
        }
        .modal-footer .btn-cancel {
            background: rgba(255,255,255,0.06) !important;
            color: rgba(255,255,255,0.6) !important;
            padding: 10px 35px !important;
            border-radius: 12px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            border: none !important;
        }
        .modal-footer .btn-cancel:hover {
            background: rgba(255,255,255,0.1) !important;
            color: rgba(255,255,255,0.8) !important;
        }
        .modal-footer .btn-logout-confirm {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            color: white !important;
            padding: 10px 35px !important;
            border-radius: 12px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            border: none !important;
        }
        .modal-footer .btn-logout-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 10px 30px rgba(239,68,68,0.3) !important;
        }
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%) !important;
        }
        
        /* ============================================================
           MISC
           ============================================================ */
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-primary { color: #93c5fd !important; }
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        .border { border-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 { color: #e0e0e0 !important; }
        a { color: #93c5fd !important; text-decoration: none; }
        a:hover { color: #bfdbfe !important; }
        
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; }
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        /* ============================================================
           PHOTO UPLOAD INDICATOR
           ============================================================ */
        .photo-upload-info {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            color: #34d399;
            display: inline-block;
            margin-top: 5px;
        }
        .photo-upload-info i {
            margin-right: 5px;
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <!-- ===== NAVBAR ===== -->
    <?php include __DIR__ . '/includes/navbar_staff.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- ===== SIDEBAR ===== -->
            <?php include __DIR__ . '/includes/sidebar_staff.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" style="color:#e0e0e0 !important;">
                        <i class="fas fa-user-circle me-2" style="color: #93c5fd;"></i>
                        My Profile
                    </h1>
                    <div>
                        <span class="view-only-badge me-2">
                            <i class="fas fa-eye me-1"></i> View Only
                        </span>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator me-1"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                    </div>
                </div>

                <div class="row">
                    <!-- Profile Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <!-- ===== PROFILE AVATAR WITH PHOTO SUPPORT ===== -->
                                <div class="profile-avatar">
                                    <?php if ($hasPhoto && !empty($photoUrl)): ?>
                                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" 
                                             alt="Profile Photo"
                                             onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex'; console.log('Image failed to load: <?php echo htmlspecialchars($photoUrl); ?>')">
                                        <span class="photo-badge">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    <?php else: 
                                        $name = $staff['full_name'] ?? 'Staff';
                                        $initials = '';
                                        $parts = explode(' ', $name);
                                        foreach ($parts as $p) {
                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                        }
                                        $initials = substr($initials, 0, 2);
                                    ?>
                                        <div class="no-photo"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Show photo status -->
                                <?php if ($hasPhoto): ?>
                                    <div class="photo-upload-info">
                                        <i class="fas fa-image me-1"></i> Photo Uploaded
                                    </div>
                                    <!-- Debug: Show the path being used -->
                                    <!-- <div style="font-size:10px;color:#666;margin-top:2px;">URL: <?php echo htmlspecialchars($photoUrl); ?></div> -->
                                <?php else: ?>
                                    <div class="photo-upload-info" style="color:#808090; border-color:#1a2a4a;">
                                        <i class="fas fa-camera me-1"></i> No photo uploaded
                                    </div>
                                <?php endif; ?>
                                
                                <div class="profile-name">
                                    <?php echo htmlspecialchars($staff['full_name'] ?? 'Staff'); ?>
                                </div>
                                <div class="profile-role">
                                    <i class="fas fa-user-shield me-1"></i> Staff
                                    <span class="badge bg-warning ms-1">View Only</span>
                                </div>
                                <div class="profile-id mt-1">
                                    <i class="fas fa-id-badge me-1"></i>
                                    <?php echo htmlspecialchars($staff['staff_id_number'] ?? 'N/A'); ?>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="small text-muted">Department</div>
                                        <div class="fw-bold" style="color:#e0e0e0;">
                                            <?php echo htmlspecialchars($staff['department'] ?? 'Dormitory Staff'); ?>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted">Status</div>
                                        <div>
                                            <span class="badge bg-success">
                                                <span class="live-indicator me-1" style="width:6px; height:6px;"></span>
                                                Active
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h6 class="text-muted mb-3"><i class="fas fa-chart-simple me-2"></i>Quick Stats</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Access Logs</span>
                                    <span class="fw-bold" style="color:#93c5fd;"><?php echo number_format($stats['total_access']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Alerts</span>
                                    <span class="fw-bold" style="color:#fbbf24;"><?php echo number_format($stats['total_alerts']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Total Visitors</span>
                                    <span class="fw-bold" style="color:#34d399;"><?php echo number_format($stats['total_visitors']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Details -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle me-2"></i>Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-user me-2"></i>Full Name</span>
                                    <span class="value"><?php echo htmlspecialchars($staff['full_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-id-badge me-2"></i>Staff ID</span>
                                    <span class="value"><?php echo htmlspecialchars($staff['staff_id_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-envelope me-2"></i>Email Address</span>
                                    <span class="value"><?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-building me-2"></i>Department</span>
                                    <span class="value"><?php echo htmlspecialchars($staff['department'] ?? 'Dormitory Staff'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-phone me-2"></i>Phone Number</span>
                                    <span class="value"><?php echo htmlspecialchars($staff['phone'] ?? 'Not provided'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-calendar-alt me-2"></i>Date Registered</span>
                                    <span class="value"><?php echo $staff['created_at'] ? date('F d, Y', strtotime($staff['created_at'])) : 'N/A'; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-clock me-2"></i>Last Login</span>
                                    <span class="value"><?php echo $staff['last_login'] ? date('F d, Y h:i A', strtotime($staff['last_login'])) : 'First time login'; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label"><i class="fas fa-shield-alt me-2"></i>Account Status</span>
                                    <span class="value">
                                        <?php if ($staff['is_active'] == 1): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="dashboard.php" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                                    </a>
                                    <a href="#" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    <i class="fas fa-eye me-1"></i> View Only Access
                    <span class="mx-2">|</span>
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                </footer>
            </main>
        </div>
    </div>

    <!-- ============================================================
    LOGOUT CONFIRMATION MODAL
    ============================================================ -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-sign-out-alt me-2"></i> Confirm Logout
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="logout-confirm-text">
                        Are you sure you want to logout?
                    </p>
                    <p class="logout-sub-text">
                        You will be redirected to the login page.
                    </p>
                    <div class="user-info">
                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>
                        <span style="margin: 0 8px;">•</span>
                        <span class="badge-staff-logout">Staff</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <a href="logout.php" class="btn-logout-confirm">
                        <i class="fas fa-sign-out-alt me-2"></i> Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
