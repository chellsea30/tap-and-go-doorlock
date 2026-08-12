<?php
/**
 * Tap-and-Go Doorlock - Staff Announcements
 * VIEW ONLY - Fully Readable
 * PURE DARK MODE - Walang puti
 */

// Start session
session_start();

// Load config and functions
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// Check authentication - Staff only
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: ../login.php');
    exit();
}

// Include navbar
include __DIR__ . '/../../includes/navbar_staff.php';

$conn = getDBConnection();

// ============================================================
// GET ANNOUNCEMENTS
// ============================================================
$announcements = [];
$result = $conn->query("
    SELECT a.*, u.full_name as admin_name 
    FROM announcements a
    LEFT JOIN admin_users u ON a.admin_id = u.admin_id
    ORDER BY a.created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Get announcement count
$announcementCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
if ($result && $row = $result->fetch_assoc()) {
    $announcementCount = $row['count'];
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
    <title>Staff Announcements - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME - PURE DARK
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
           ANNOUNCEMENT CARD - PURE DARK
           ============================================================ */
        .announcement-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            border-left: 4px solid #ffd700;
            transition: all 0.3s ease;
        }
        .announcement-card:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .announcement-card.inactive {
            opacity: 0.5;
            border-left-color: #4a5568;
        }
        .announcement-card .title {
            font-weight: 700;
            color: #ffd700 !important;
            font-size: 18px;
        }
        .announcement-card .title.inactive-title {
            color: #606070 !important;
        }
        .announcement-card .content {
            color: #d1d5db !important;
            margin: 8px 0;
        }
        .announcement-card .meta {
            font-size: 12px;
            color: #808090 !important;
        }
        .announcement-card .meta i {
            color: #606070;
        }
        
        .badge.bg-secondary {
            background: #2a2a3a !important;
            color: #808090 !important;
        }
        .badge.bg-success {
            background: #065f46 !important;
            color: #34d399 !important;
        }
        
        /* ============================================================
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
        }
        .card .card-body {
            background: transparent !important;
        }
        .card .text-muted {
            color: #808090 !important;
        }
        .card h5 {
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-success { color: #34d399 !important; }
        
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
            .announcement-card {
                padding: 15px;
            }
            .announcement-card .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }
            .announcement-card .d-flex .ms-3 {
                margin-left: 0 !important;
                margin-top: 10px;
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
                        <i class="fas fa-bullhorn me-1" style="color: #1a3a6a;"></i>
                        Announcements
                    </h1>
                    <div>
                        <span class="view-only-badge me-2">
                            <i class="fas fa-eye me-1"></i> View Only
                        </span>
                        <span class="badge bg-secondary">Active: <?php echo $announcementCount; ?></span>
                    </div>
                </div>

                <!-- Announcement Count -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <span class="text-muted small">
                            <i class="fas fa-bullhorn me-1"></i>
                            <?php echo $announcementCount; ?> active announcements
                        </span>
                    </div>
                </div>

                <!-- Announcements List -->
                <?php if (empty($announcements)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No announcements yet</h5>
                            <p class="text-muted">Check back later for updates</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-card <?php echo !$announcement['is_active'] ? 'inactive' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="title <?php echo !$announcement['is_active'] ? 'inactive-title' : ''; ?>">
                                        <?php if (!$announcement['is_active']): ?>
                                            <span class="badge bg-secondary me-2">Inactive</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </div>
                                    <div class="content">
                                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                    </div>
                                    <div class="meta">
                                        <span class="me-2">
                                            <i class="far fa-user me-1"></i>
                                            <?php echo htmlspecialchars($announcement['admin_name'] ?? 'Administrator'); ?>
                                        </span>
                                        <span>
                                            <i class="far fa-calendar-alt me-1"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?>
                                        </span>
                                        <?php if ($announcement['is_active']): ?>
                                            <span class="badge bg-success ms-2">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-2">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- No action buttons for staff -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="text-center text-muted small mt-3">
                    <i class="fas fa-eye me-1"></i> View Only Access
                    <span class="mx-2">|</span>
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo count($announcements); ?> announcements
                    <span class="mx-1">|</span>
                    <i class="fas fa-check-circle me-1 text-success"></i>
                    <?php echo $announcementCount; ?> active
                </div>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer_staff.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>