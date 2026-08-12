<?php
/**
 * Tap-and-Go Doorlock - Student Dashboard
 * DARK MODE - NO WHITE BACKGROUNDS
 */

session_start();

// Load config and functions
require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

// Check if logged in as student
if (!isset($_SESSION['student_id']) || !isStudentSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$error = '';

// ============================================================
// GET STUDENT INFO
// ============================================================
$studentInfo = null;
$stmt = $conn->prepare("SELECT * FROM student_users WHERE student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$studentInfo = $result->fetch_assoc();
$stmt->close();

if (!$studentInfo) {
    header('Location: login.php');
    exit();
}

// ============================================================
// FIND USER IN users TABLE FOR PROFILE PHOTO
// ============================================================
$user_id = null;
$profilePhoto = '';
$studentIdNumber = $_SESSION['student_id_number'] ?? '';

// Try to find by student_id
$stmt = $conn->prepare("SELECT user_id, profile_photo FROM users WHERE student_id = ?");
$stmt->bind_param("s", $studentIdNumber);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_id = $row['user_id'];
    $profilePhoto = $row['profile_photo'];
}
$stmt->close();

// If not found, try by email
if (!$user_id && !empty($studentInfo['email'])) {
    $stmt = $conn->prepare("SELECT user_id, profile_photo FROM users WHERE email = ?");
    $stmt->bind_param("s", $studentInfo['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $profilePhoto = $row['profile_photo'];
    }
    $stmt->close();
}

// If still not found, try by full_name
if (!$user_id && !empty($_SESSION['full_name'])) {
    $stmt = $conn->prepare("SELECT user_id, profile_photo FROM users WHERE full_name LIKE ?");
    $name = '%' . $_SESSION['full_name'] . '%';
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $profilePhoto = $row['profile_photo'];
    }
    $stmt->close();
}

$fullPhotoPath = '../../../' . $profilePhoto;
$hasPhoto = !empty($profilePhoto) && file_exists($fullPhotoPath);

// ============================================================
// GET ACCESS HISTORY
// ============================================================
$accessHistory = [];
if ($user_id) {
    $stmt = $conn->prepare("
        SELECT 
            al.*,
            c.card_uid,
            c.card_type
        FROM access_logs al
        LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
        WHERE al.user_id = ?
        ORDER BY al.timestamp DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $accessHistory[] = $row;
    }
    $stmt->close();
}

// ============================================================
// GET STATS
// ============================================================
$stats = [
    'total_entries' => 0,
    'last_entry' => 'N/A',
    'today_entries' => 0,
    'card_status' => 'No Card'
];

if ($user_id) {
    // Total entries
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM access_logs WHERE user_id = ? AND access_status = 'granted'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_entries'] = (int)$row['count'];
    }
    $stmt->close();

    // Last entry
    $stmt = $conn->prepare("SELECT timestamp FROM access_logs WHERE user_id = ? AND access_status = 'granted' ORDER BY timestamp DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['last_entry'] = date('h:i A', strtotime($row['timestamp']));
    }
    $stmt->close();

    // Today's entries
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM access_logs WHERE user_id = ? AND DATE(timestamp) = CURDATE() AND access_status = 'granted'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['today_entries'] = (int)$row['count'];
    }
    $stmt->close();

    // Card status
    $stmt = $conn->prepare("SELECT status, card_uid FROM rfid_cards WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['card_status'] = 'Active';
        $stats['card_uid'] = $row['card_uid'];
    } else {
        $stats['card_status'] = 'No Card';
    }
    $stmt->close();
}

// ============================================================
// GET ANNOUNCEMENTS
// ============================================================
$announcements = [];
$result = $conn->query("
    SELECT * FROM announcements 
    WHERE is_active = 1 
    ORDER BY created_at DESC 
    LIMIT 3
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// ============================================================
// GET INITIALS FOR AVATAR
// ============================================================
$name = $_SESSION['full_name'] ?? 'Student';
$initials = '';
$parts = explode(' ', $name);
foreach ($parts as $p) {
    if (!empty($p)) $initials .= strtoupper($p[0]);
}
$initials = substr($initials, 0, 2) ?: 'S';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ================================================================
           DARK MODE STYLES - NO WHITE BACKGROUNDS
           ================================================================ */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e5e7eb !important;
            padding-top: 56px;
            min-height: 100vh;
        }
        
        /* ===== NAVBAR ===== */
        .navbar {
            background: linear-gradient(135deg, #0a1628, #1a2a4a) !important;
            border-bottom: 1px solid #1e2a3a;
            height: 56px;
            padding: 0 20px;
            z-index: 1050;
        }
        .navbar-brand { color: #ffd700 !important; font-weight: 700; font-size: 18px; }
        .navbar-brand i { color: #ffd700; }
        .navbar .nav-link {
            color: rgba(255,255,255,0.7) !important;
            font-size: 14px;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .navbar .nav-link:hover { color: white !important; background: rgba(255,255,255,0.08); }
        .navbar .nav-link.active { color: white !important; background: rgba(255,215,0,0.15); }
        .navbar .nav-link i { margin-right: 6px; }
        .logout-btn {
            color: rgba(255,255,255,0.7) !important;
            padding: 6px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.1); color: white !important; }
        .student-badge {
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .navbar-text { color: rgba(255,255,255,0.8) !important; font-size: 14px; }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            bottom: 0;
            width: 260px;
            background: #131926 !important;
            border-right: 1px solid #1e2a3a;
            overflow-y: auto;
            z-index: 999;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link {
            color: #9ca3af !important;
            padding: 10px 18px;
            border-radius: 8px;
            margin: 2px 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
        }
        .sidebar .nav-link:hover {
            background: rgba(255, 215, 0, 0.08);
            color: #ffd700 !important;
        }
        .sidebar .nav-link.active {
            background: rgba(255, 215, 0, 0.12);
            color: #ffd700 !important;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        .sidebar-footer {
            padding: 12px 18px;
            border-top: 1px solid #1e2a3a;
            margin-top: 5px;
        }
        .sidebar-footer .text-muted {
            color: #6b7280 !important;
            font-size: 11px;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: calc(100vh - 56px);
            background: #0a0e1a;
        }
        
        /* ===== TITLE WITH PROFILE PHOTO ===== */
        .page-title-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #1e2a3a;
        }
        .page-title-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .page-title-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: white;
            overflow: hidden;
            border: 3px solid #1e2a3a;
            flex-shrink: 0;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
        }
        .page-title-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .page-title-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 20px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
        }
        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: #ffd700 !important;
            margin: 0;
        }
        .page-title .sub {
            font-size: 13px;
            color: #9ca3af !important;
        }
        .page-title .sub i {
            margin-right: 4px;
            color: #6b7280;
        }
        .page-title-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* ===== CARDS - DARK ===== */
        .card {
            border: 1px solid #1e2a3a !important;
            border-radius: 16px !important;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            margin-bottom: 20px;
            background: #131926 !important;
        }
        .card-header {
            background: #131926 !important;
            border-bottom: 1px solid #1e2a3a !important;
            border-radius: 16px 16px 0 0 !important;
            padding: 14px 20px;
        }
        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #ffd700 !important;
            font-size: 16px;
        }
        .card-body {
            padding: 20px;
            background: transparent !important;
        }
        .card h4 {
            color: #ffd700 !important;
        }
        .card .text-muted {
            color: #6b7280 !important;
        }
        
        /* ===== STAT CARDS ===== */
        .stat-card {
            background: #131926 !important;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            height: 100%;
            border: 1px solid #1e2a3a;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.5); }
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
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #ffd700 !important;
            margin: 0;
        }
        .stat-label {
            font-size: 12px;
            color: #6b7280 !important;
            margin: 0;
        }
        
        /* ===== TABLE - NO WHITE BACKGROUND ===== */
        .table {
            color: #e5e7eb !important;
            background: transparent !important;
        }
        .table thead th {
            color: #9ca3af !important;
            border-bottom: 2px solid #1e2a3a !important;
            background: transparent !important;
            padding: 10px 12px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table tbody td {
            border-bottom: 1px solid #1e2a3a !important;
            color: #d1d5db !important;
            background: transparent !important;
            padding: 10px 12px;
            vertical-align: middle;
        }
        .table .text-muted {
            color: #6b7280 !important;
        }
        .table-hover tbody tr {
            background: transparent !important;
            transition: all 0.3s ease;
        }
        .table-hover tbody tr:hover {
            background: rgba(255, 215, 0, 0.05) !important;
        }
        .table-hover tbody tr:hover td {
            background: transparent !important;
        }
        .uid-cell {
            font-family: monospace;
            font-weight: 600;
            color: #ffd700 !important;
            background: rgba(255, 215, 0, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            display: inline-block;
        }
        
        /* ===== BADGES - DARK ===== */
        .badge.bg-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
        }
        .badge.bg-danger {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
        }
        .badge.bg-secondary {
            background: rgba(107, 114, 128, 0.3) !important;
            color: #9ca3af !important;
        }
        .badge.bg-primary {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
        }
        .badge.bg-warning {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
        }
        .badge.bg-info {
            background: rgba(6, 182, 212, 0.2) !important;
            color: #67e8f9 !important;
        }
        
        .btn-outline-primary {
            color: #ffd700 !important;
            border-color: rgba(255, 215, 0, 0.3) !important;
        }
        .btn-outline-primary:hover {
            background: rgba(255, 215, 0, 0.15) !important;
            color: #ffd700 !important;
        }
        
        .text-success {
            color: #6ee7b7 !important;
        }
        .text-danger {
            color: #fca5a5 !important;
        }
        .text-warning {
            color: #fbbf24 !important;
        }
        
        /* ===== ACCESS HISTORY ===== */
        .access-history {
            max-height: 350px;
            overflow-y: auto;
        }
        .access-history::-webkit-scrollbar {
            width: 6px;
        }
        .access-history::-webkit-scrollbar-track {
            background: #0a0e1a;
        }
        .access-history::-webkit-scrollbar-thumb {
            background: #1e2a3a;
            border-radius: 5px;
        }
        .access-history::-webkit-scrollbar-thumb:hover {
            background: #ffd700;
        }
        
        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0e1a;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e2a3a;
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #ffd700;
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 56px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 998;
            }
            .sidebar-overlay.show { display: block; }
            .navbar-toggler {
                border-color: rgba(255,255,255,0.1) !important;
            }
            .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
            }
            .stat-card { padding: 12px 15px; }
            .stat-number { font-size: 18px; }
            .stat-icon { width: 36px; height: 36px; font-size: 16px; }
            .page-title-container {
                flex-direction: column;
                align-items: flex-start;
            }
            .page-title-right {
                width: 100%;
                justify-content: flex-start;
            }
            .table thead th,
            .table tbody td {
                padding: 6px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

   <!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <button class="navbar-toggler me-2" type="button" onclick="toggleSidebar()">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-door-open me-2"></i> Tap-and-Go
        </a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access-history.php"><i class="fas fa-clock"></i> Access History</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-rfid.php"><i class="fas fa-id-card"></i> My RFID Card</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="concerns.php"><i class="fas fa-exclamation-circle"></i> Concerns</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="request-reset.php"><i class="fas fa-key"></i> Reset Password</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-2">
                    <i class="fas fa-user-graduate me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                    <span class="student-badge ms-1">Student</span>
                </span>
                <a href="#" class="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ===== LOGOUT CONFIRMATION MODAL ===== -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sign-out-alt me-2" style="color: #ef4444;"></i>
                    Confirm Logout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-question-circle fa-4x mb-3" style="color: #f59e0b;"></i>
                <h5 class="mb-2">Are you sure you want to logout?</h5>
                <p class="text-muted mb-0">You will be redirected to the login page.</p>
                <div class="mt-3">
                    <span class="badge bg-secondary">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                    </span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <a href="../../logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-1"></i> Yes, Logout
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* ===== LOGOUT MODAL DARK MODE ===== */
    body.dark-mode .modal-content {
        background: #131926 !important;
        border: 1px solid #1e2a3a;
    }
    body.dark-mode .modal-header {
        border-bottom: 1px solid #1e2a3a;
    }
    body.dark-mode .modal-footer {
        border-top: 1px solid #1e2a3a;
    }
    body.dark-mode .modal-title {
        color: #ffd700 !important;
    }
    body.dark-mode .modal-body h5 {
        color: #e5e7eb !important;
    }
    body.dark-mode .modal-body .text-muted {
        color: #6b7280 !important;
    }
    body.dark-mode .modal-body .badge.bg-secondary {
        background: rgba(107, 114, 128, 0.3) !important;
        color: #9ca3af !important;
    }
    body.dark-mode .btn-secondary {
        background: #1e2a3a !important;
        border: none !important;
        color: #e5e7eb !important;
    }
    body.dark-mode .btn-secondary:hover {
        background: #2d3548 !important;
        color: #e5e7eb !important;
    }
    body.dark-mode .btn-close {
        filter: invert(1) !important;
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .logout-btn {
            margin-top: 10px;
            display: inline-block;
        }
        .navbar-text {
            margin-bottom: 5px;
        }
    }
</style>

<script>
    // Optional: Keyboard shortcut for logout (Ctrl+Shift+L)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'L') {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        }
    });
</script>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <!-- ============================================================
        TITLE WITH PROFILE PHOTO
        ============================================================ -->
        <div class="page-title-container">
            <div class="page-title-left">
                <!-- Profile Photo -->
                <div class="page-title-avatar">
                    <?php if ($hasPhoto): ?>
                        <img src="<?php echo $fullPhotoPath; ?>" alt="Profile Photo" onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                        <div class="no-photo" style="display:none;">
                            <?php echo $initials; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-photo"><?php echo $initials; ?></div>
                    <?php endif; ?>
                </div>
                <div class="page-title">
                    <h1>Student Dashboard</h1>
                    <div class="sub">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                        <span class="mx-1">|</span>
                        <i class="fas fa-id-card me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['student_id_number'] ?? 'N/A'); ?>
                        <span class="mx-1">|</span>
                        <i class="fas fa-bed me-1"></i>
                        Room <?php echo htmlspecialchars($studentInfo['room_number'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>
            <div class="page-title-right">
                <span class="badge bg-success"><i class="fas fa-circle me-1"></i> System Online</span>
                <span class="badge bg-primary"><i class="fas fa-clock me-1"></i> <?php echo date('h:i A'); ?></span>
                <span class="badge bg-secondary"><i class="fas fa-calendar-day me-1"></i> <?php echo date('M d, Y'); ?></span>
            </div>
        </div>

        <!-- Welcome Card -->
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <h4 style="color:#ffd700; font-weight:700;">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>! 🎉</h4>
                        <p class="text-muted mb-0">
                            <i class="fas fa-graduation-cap me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['course'] ?? 'N/A'); ?>
                            - <?php echo htmlspecialchars($_SESSION['year_level'] ?? 'N/A'); ?>
                            <?php if (isset($stats['card_uid'])): ?>
                                <span class="mx-2">|</span>
                                <i class="fas fa-id-card me-1"></i>
                                RFID: <span class="uid-cell"><?php echo htmlspecialchars($stats['card_uid']); ?></span>
                            <?php endif; ?>
                            <?php if ($stats['card_status'] == 'Active'): ?>
                                <span class="badge bg-success ms-1"><i class="fas fa-check-circle me-1"></i> Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-1"><i class="fas fa-times-circle me-1"></i> No Card</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $stats['total_entries']; ?></div>
                        <div class="stat-label">Total Entries</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #667eea;"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $stats['last_entry']; ?></div>
                        <div class="stat-label">Last Entry</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-id-card"></i></div>
                    <div>
                        <div class="stat-number">
                            <?php if ($stats['card_status'] == 'Active'): ?>
                                <span class="text-success">✓</span>
                            <?php else: ?>
                                <span class="text-danger">✗</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label"><?php echo $stats['card_status']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-sign-in-alt"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $stats['today_entries']; ?></div>
                        <div class="stat-label">Today's Entries</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Access History Table - NO WHITE BACKGROUND -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-history me-2"></i>Recent Access Logs</h5>
                <a href="access-history.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="access-history">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>RFID UID</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($accessHistory)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                        <?php if (!$user_id): ?>
                                            <p>No user account linked to your student profile.</p>
                                            <p class="small text-warning">Please contact the administrator to link your account.</p>
                                        <?php else: ?>
                                            <p>No access history found</p>
                                            <p class="small">Your access logs will appear here when you use your RFID card.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($accessHistory as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo date('M d, Y', strtotime($log['timestamp'])); ?></div>
                                            <span class="text-muted small"><?php echo date('h:i A', strtotime($log['timestamp'])); ?></span>
                                        </td>
                                        <td>
                                            <span class="uid-cell"><?php echo htmlspecialchars($log['card_uid'] ?? 'N/A'); ?></span>
                                            <?php if (!empty($log['card_type'])): ?>
                                                <br>
                                                <span class="badge bg-secondary"><?php echo ucfirst($log['card_type']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $log['access_type'] == 'entry' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <i class="fas <?php echo $log['access_type'] == 'entry' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?> me-1"></i>
                                                <?php echo ucfirst($log['access_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $log['access_status'] == 'granted' ? 'bg-success' : 'bg-danger'; ?>">
                                                <i class="fas <?php echo $log['access_status'] == 'granted' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                                <?php echo ucfirst($log['access_status']); ?>
                                            </span>
                                            <?php if ($log['access_status'] == 'denied' && !empty($log['reason'])): ?>
                                                <br>
                                                <span class="text-muted small"><?php echo htmlspecialchars($log['reason']); ?></span>
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

        <div class="text-center text-muted small mt-3">
            <i class="fas fa-lock me-1"></i> Student Access - Limited View
            <span class="mx-1">|</span>
            <i class="fas fa-user-graduate me-1"></i>
            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
            <span class="mx-1">|</span>
            <i class="fas fa-clock me-1"></i>
            Last login: <?php echo date('M d, Y h:i A'); ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('studentSidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
</body>
</html>