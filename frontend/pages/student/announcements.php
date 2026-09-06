<?php
/**
 * Tap-and-Go Doorlock - Student Announcements View
 * DARK MODE - FIXED ERRORS - SEPARATE NAVBAR
 */

session_start();

require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

if (!isset($_SESSION['student_id']) || !isStudentSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

$announcements = [];
$result = $conn->query("
    SELECT a.*, u.full_name as admin_name 
    FROM announcements a
    LEFT JOIN admin_users u ON a.admin_id = u.admin_id
    WHERE a.is_active = 1
    ORDER BY a.created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* ================================================================
           DARK MODE STYLES
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
        
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: calc(100vh - 56px);
            background: #0a0e1a;
        }
        
        .announcement-card {
            background: #131926 !important;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            border-left: 4px solid #ffd700;
            border: 1px solid #1e2a3a;
            transition: all 0.3s ease;
        }
        .announcement-card:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }
        .announcement-card .title {
            font-weight: 700;
            color: #ffd700 !important;
            font-size: 18px;
        }
        .announcement-card .content {
            color: #d1d5db !important;
            margin: 8px 0;
        }
        .announcement-card .meta {
            font-size: 12px;
            color: #9ca3af !important;
        }
        .announcement-card .meta i {
            color: #6b7280;
        }
        
        .badge.bg-info {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
        }
        
        .badge.bg-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
        }
        .badge.bg-secondary {
            background: rgba(107, 114, 128, 0.3) !important;
            color: #9ca3af !important;
        }
        
        .card {
            background: #131926 !important;
            border: 1px solid #1e2a3a !important;
            border-radius: 16px !important;
        }
        .card .card-body {
            background: transparent !important;
        }
        .card .text-muted {
            color: #6b7280 !important;
        }
        .card h5 {
            color: #9ca3af !important;
        }
        
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 {
            color: #e5e7eb !important;
        }
        
        .border-bottom {
            border-color: #1e2a3a !important;
        }
        
        .text-muted {
            color: #6b7280 !important;
        }
        
        /* Sidebar styles */
        #sidebar {
            background: #131926 !important;
            border-right: 1px solid #1e2a3a !important;
            padding-top: 20px;
        }
        #sidebar .nav-link {
            color: #9ca3af !important;
            padding: 10px 20px;
            border-radius: 8px;
            margin: 2px 10px;
        }
        #sidebar .nav-link:hover {
            background: rgba(255, 215, 0, 0.08);
            color: #ffd700 !important;
        }
        #sidebar .nav-link.active {
            background: rgba(255, 215, 0, 0.12);
            color: #ffd700 !important;
        }
        #sidebar .nav-link i {
            width: 20px;
        }
        #sidebar .nav-header {
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 20px 8px;
        }
        
        /* Scrollbar */
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
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            #sidebar {
                position: fixed;
                top: 56px;
                left: -280px;
                width: 280px;
                height: calc(100vh - 56px);
                z-index: 999;
                transition: left 0.3s ease;
                overflow-y: auto;
            }
            #sidebar.show {
                left: 0;
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
        }
    </style>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- ===== NAVBAR (Separate file) ===== -->
    <?php include 'includes/navbar.php'; ?>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2" style="font-size:24px; font-weight:700;"><i class="fas fa-bullhorn me-2" style="color: #ffd700;"></i>Announcements</h1>
            <span class="badge bg-info"><?php echo count($announcements); ?> announcements</span>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No announcements available</h5>
                    <p class="text-muted">Check back later for updates</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-card">
                    <div class="title">
                        <?php echo htmlspecialchars($announcement['title']); ?>
                        <?php if ($announcement['is_active']): ?>
                            <span class="badge bg-success ms-1">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary ms-1">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div class="content">
                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                    </div>
                    <div class="meta">
                        <i class="far fa-user me-1"></i>
                        <?php echo htmlspecialchars($announcement['admin_name'] ?? 'Admin'); ?>
                        <span class="mx-1">•</span>
                        <i class="far fa-calendar-alt me-1"></i>
                        <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggler = document.querySelector('.navbar-toggler');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggler.contains(event.target) && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>
