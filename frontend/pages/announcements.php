<?php
/**
 * Tap-and-Go Doorlock - Announcements
 * DARK MODE - FULLY READABLE - FIXED LAYOUT SAME AS DASHBOARD
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication (Admin only)
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
// CREATE ANNOUNCEMENTS TABLE IF NOT EXISTS
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS announcements (
        announcement_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
        INDEX idx_admin_id (admin_id),
        INDEX idx_created_at (created_at),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ============================================================
// HANDLE CRUD OPERATIONS
// ============================================================

// Delete announcement
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success = "Announcement deleted successfully!";
        logAudit($_SESSION['admin_id'], 'Delete Announcement', "Deleted announcement ID: $delete_id");
    } else {
        $error = "Failed to delete announcement.";
    }
    $stmt->close();
}

// Toggle announcement status (active/inactive)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggle_id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE announcement_id = ?");
    $stmt->bind_param("i", $toggle_id);
    if ($stmt->execute()) {
        $success = "Announcement status updated!";
        logAudit($_SESSION['admin_id'], 'Toggle Announcement', "Toggled announcement ID: $toggle_id");
    } else {
        $error = "Failed to update announcement.";
    }
    $stmt->close();
}

// Add/Edit announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    
    if (empty($title) || empty($content)) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ? WHERE announcement_id = ?");
            $stmt->bind_param("ssi", $title, $content, $edit_id);
            if ($stmt->execute()) {
                $success = "Announcement updated successfully!";
                logAudit($_SESSION['admin_id'], 'Edit Announcement', "Updated announcement ID: $edit_id");
            } else {
                $error = "Failed to update announcement.";
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO announcements (admin_id, title, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $_SESSION['admin_id'], $title, $content);
            if ($stmt->execute()) {
                $success = "Announcement added successfully!";
                logAudit($_SESSION['admin_id'], 'Add Announcement', "Added announcement: $title");
            } else {
                $error = "Failed to add announcement.";
            }
            $stmt->close();
        }
    }
}

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

$announcementCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
if ($result && $row = $result->fetch_assoc()) {
    $announcementCount = $row['count'];
}

$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editData = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Tap-and-Go Doorlock</title>
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
           ANNOUNCEMENT CARD - DARK
           ============================================================ */
        .announcement-card {
            background: #111827 !important;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            border-left: 4px solid #ffd700;
            border: 1px solid #1a2a4a;
            transition: all 0.3s ease;
        }
        .announcement-card:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important;
        }
        .announcement-card.inactive {
            opacity: 0.6;
            border-left-color: #4a5568;
        }
        .announcement-card.inactive .title {
            color: #6b7280 !important;
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
        
        /* ============================================================
           DARK BUTTONS
           ============================================================ */
        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            color: #0a0e1a !important;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: #0a0e1a !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        .btn-outline-secondary {
            color: #9ca3af !important;
            border-color: #1a2a4a !important;
        }
        .btn-outline-secondary:hover {
            background: #1a2a4a !important;
            color: #e5e7eb !important;
        }
        
        .btn-outline-primary {
            color: #ffd700 !important;
            border-color: rgba(255, 215, 0, 0.3) !important;
        }
        .btn-outline-primary:hover {
            background: rgba(255, 215, 0, 0.15) !important;
            color: #ffd700 !important;
        }
        
        .btn-outline-success {
            color: #6ee7b7 !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
        }
        .btn-outline-success:hover {
            background: rgba(16, 185, 129, 0.15) !important;
            color: #6ee7b7 !important;
        }
        
        .btn-outline-danger {
            color: #fca5a5 !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
        }
        .btn-outline-danger:hover {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #fca5a5 !important;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: #0a0e1a !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3) !important;
            color: #0a0e1a !important;
        }
        
        .btn-secondary {
            background: #1a2a4a !important;
            border: none !important;
            color: #e5e7eb !important;
        }
        .btn-secondary:hover {
            background: #2d3548 !important;
            color: #e5e7eb !important;
        }
        
        /* ============================================================
           DARK ALERTS
           ============================================================ */
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
        
        /* ============================================================
           DARK FORM ELEMENTS
           ============================================================ */
        .form-control,
        .form-select {
            background: #0d1220 !important;
            border: 1px solid #1a2a4a !important;
            color: #e5e7eb !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        .form-control::placeholder { color: #6b7280 !important; }
        .form-select option { background: #131926 !important; color: #e5e7eb !important; }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #d1d5db !important;
        }
        .required { color: #ef4444 !important; }
        textarea.form-control {
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        textarea.form-control:focus {
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        
        /* ============================================================
           DARK MODAL
           ============================================================ */
        .modal-content {
            background: #131926 !important;
            border-radius: 16px;
            border: 1px solid #1a2a4a;
        }
        .modal-header { border-bottom: 1px solid #1a2a4a; }
        .modal-footer { border-top: 1px solid #1a2a4a; }
        .modal-title { color: #ffd700 !important; }
        .modal-title i { color: #ffd700 !important; }
        
        /* ============================================================
           DARK CARD
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .card .card-body { background: transparent !important; }
        .card .text-muted { color: #6b7280 !important; }
        .card h5 { color: #9ca3af !important; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #6b7280 !important; }
        
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
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1a2a4a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
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
            
            .announcement-card {
                padding: 15px;
            }
            .announcement-card .title {
                font-size: 16px;
            }
        }
        
        @media (max-width: 576px) {
            .announcement-card .d-flex {
                flex-direction: column;
                gap: 10px;
            }
            .announcement-card .d-flex .flex-shrink-0 {
                align-self: flex-start;
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
                        <i class="fas fa-bullhorn me-2" style="color: #ffd700;"></i>
                        Announcements
                        <?php if ($announcementCount > 0): ?>
                            <span class="badge bg-success ms-2"><?php echo $announcementCount; ?> active</span>
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
                        <button type="button" class="btn btn-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#announcementModal">
                            <i class="fas fa-plus me-1"></i> New
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
                ANNOUNCEMENTS LIST
                ============================================================ -->
                <?php if (empty($announcements)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No announcements yet</h5>
                            <p class="text-muted">Click "New" to create an announcement</p>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#announcementModal">
                                <i class="fas fa-plus me-1"></i> Create Announcement
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-card <?php echo !$announcement['is_active'] ? 'inactive' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="title">
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
                                            <?php echo htmlspecialchars($announcement['admin_name'] ?? 'Admin'); ?>
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
                                <div class="d-flex gap-1 flex-shrink-0 ms-3">
                                    <a href="?toggle=<?php echo $announcement['announcement_id']; ?>" 
                                       class="btn btn-sm <?php echo $announcement['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"
                                       title="<?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas <?php echo $announcement['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                    </a>
                                    <a href="?edit=<?php echo $announcement['announcement_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#announcementModal"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $announcement['announcement_id']; ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this announcement?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- ============================================================
                ANNOUNCEMENT MODAL (Add/Edit)
                ============================================================ -->
                <div class="modal fade" id="announcementModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-bullhorn me-2"></i>
                                    <?php echo $editData ? 'Edit Announcement' : 'New Announcement'; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <?php if ($editData): ?>
                                        <input type="hidden" name="edit_id" value="<?php echo $editData['announcement_id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Title <span class="required">*</span></label>
                                        <input type="text" class="form-control" name="title" placeholder="Enter announcement title" value="<?php echo htmlspecialchars($editData['title'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Content <span class="required">*</span></label>
                                        <textarea class="form-control" name="content" rows="5" placeholder="Enter announcement content" required><?php echo htmlspecialchars($editData['content'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="submit" class="btn btn-submit">
                                        <i class="fas fa-save me-1"></i> 
                                        <?php echo $editData ? 'Update Announcement' : 'Publish Announcement'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                FOOTER - SAME AS DASHBOARD
                ============================================================ -->
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                    <span class="mx-2">|</span>
                    <span>Total: <?php echo count($announcements); ?> announcements</span>
                    <span class="mx-2">|</span>
                    <span class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo $announcementCount; ?> active</span>
                </footer>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // AUTO-OPEN MODAL WHEN EDITING
        // ============================================================
        <?php if ($editData): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('announcementModal'));
                modal.show();
            });
        <?php endif; ?>
        
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