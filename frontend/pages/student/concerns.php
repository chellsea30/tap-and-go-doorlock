<?php
/**
 * Tap-and-Go Doorlock - Student Concerns
 * DARK MODE - AUTO ROOM NUMBER - FIXED TIMESTAMPS
 */

session_start();

require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

if (!isset($_SESSION['student_id']) || !isStudentSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// ============================================================
// GET STUDENT ROOM NUMBER
// ============================================================
$room_number = '';
$stmt = $conn->prepare("SELECT room_number FROM student_users WHERE student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $room_number = $row['room_number'] ?? '';
}
$stmt->close();

// ============================================================
// CREATE TABLE IF NOT EXISTS - FIXED TIMESTAMPS
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS student_concerns (
        concern_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        student_id_number VARCHAR(50) NOT NULL,
        room_number VARCHAR(20),
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        category ENUM('maintenance', 'security', 'cleanliness', 'noise', 'other') DEFAULT 'other',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
        admin_response TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ============================================================
// HANDLE CONCERN SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_concern'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $priority = $_POST['priority'] ?? 'medium';
    $room = trim($_POST['room_number'] ?? '');
    
    if (empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } else {
        if (empty($room)) {
            $room = $room_number;
        }
        
        // Insert without created_at and updated_at - they will use DEFAULT values
        $stmt = $conn->prepare("
            INSERT INTO student_concerns (
                student_id, student_name, student_id_number, room_number, 
                subject, message, category, priority
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssssss", 
            $_SESSION['student_id'], 
            $_SESSION['full_name'], 
            $_SESSION['student_id_number'],
            $room,
            $subject, $message, $category, $priority
        );
        
        if ($stmt->execute()) {
            $success = "✅ Your concern has been submitted successfully!";
            $_POST = array();
        } else {
            $error = "Failed to submit concern: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================================
// GET CONCERNS
// ============================================================
$concerns = [];
$stmt = $conn->prepare("SELECT * FROM student_concerns WHERE student_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $concerns[] = $row;
}
$stmt->close();

// ============================================================
// CALCULATE STATS
// ============================================================
$pendingCount = 0;
$resolvedCount = 0;
foreach ($concerns as $c) {
    if ($c['status'] == 'pending' || $c['status'] == 'in_progress') {
        $pendingCount++;
    } else {
        $resolvedCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Concerns - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        
        /* ===== STAT BOXES ===== */
        .stat-box {
            background: #131926 !important;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            text-align: center;
            border: 1px solid #1e2a3a;
            transition: transform 0.3s ease;
        }
        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }
        .stat-box .number {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700 !important;
        }
        .stat-box .number[style*="color:#f59e0b"] {
            color: #fbbf24 !important;
        }
        .stat-box .number[style*="color:#10b981"] {
            color: #6ee7b7 !important;
        }
        .stat-box .number[style*="color:#3b82f6"] {
            color: #93c5fd !important;
        }
        .stat-box .label {
            font-size: 13px;
            color: #6b7280 !important;
        }
        
        /* ===== CONCERN CARDS ===== */
        .concern-card {
            background: #131926 !important;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            border: 1px solid #1e2a3a;
            transition: all 0.3s ease;
        }
        .concern-card:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }
        .concern-card .subject {
            font-weight: 700;
            color: #ffd700 !important;
            font-size: 16px;
        }
        .concern-card .message {
            color: #d1d5db !important;
            margin: 5px 0;
        }
        .concern-card .meta {
            font-size: 12px;
            color: #6b7280 !important;
        }
        .concern-card .meta .room-badge {
            background: rgba(255, 215, 0, 0.1) !important;
            color: #ffd700 !important;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .concern-card .response {
            background: #0d1220 !important;
            border-radius: 10px;
            padding: 12px 15px;
            margin-top: 10px;
            border-left: 3px solid #10b981;
            border: 1px solid #1e2a3a;
        }
        .concern-card .response .admin {
            font-weight: 600;
            color: #ffd700 !important;
            font-size: 13px;
        }
        .concern-card .response .text {
            font-size: 13px;
            color: #d1d5db !important;
        }
        .concern-card.status-pending { border-left-color: #f59e0b; }
        .concern-card.status-in_progress { border-left-color: #3b82f6; }
        .concern-card.status-resolved { border-left-color: #10b981; }
        .concern-card.status-closed { border-left-color: #6b7280; opacity: 0.7; }
        
        /* ===== BADGES ===== */
        .badge.bg-warning {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
        }
        .badge.bg-info {
            background: rgba(6, 182, 212, 0.2) !important;
            color: #67e8f9 !important;
        }
        .badge.bg-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
        }
        .badge.bg-secondary {
            background: rgba(107, 114, 128, 0.3) !important;
            color: #9ca3af !important;
        }
        .badge.bg-light {
            background: rgba(107, 114, 128, 0.2) !important;
            color: #9ca3af !important;
        }
        .badge-priority-low {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
        }
        .badge-priority-medium {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
        }
        .badge-priority-high {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
        }
        
        /* ===== FORM ===== */
        .form-label {
            color: #d1d5db !important;
            font-weight: 500;
            font-size: 13px;
        }
        .form-control, .form-select {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        .form-control::placeholder {
            color: #6b7280 !important;
        }
        .form-control:disabled {
            background: #0a0e1a !important;
            color: #6b7280 !important;
            cursor: not-allowed;
        }
        .form-select option {
            background: #131926 !important;
            color: #e5e7eb !important;
        }
        .required {
            color: #ef4444 !important;
        }
        
        /* ===== BUTTONS ===== */
        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            color: #0a0e1a !important;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: #0a0e1a !important;
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
            background: #1e2a3a !important;
            border: none !important;
            color: #e5e7eb !important;
        }
        .btn-secondary:hover {
            background: #2d3548 !important;
            color: #e5e7eb !important;
        }
        
        /* ===== ALERTS ===== */
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
        .btn-close {
            filter: invert(1) !important;
        }
        
        /* ===== CARD ===== */
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
        
        /* ===== MODAL ===== */
        .modal-content {
            background: #131926 !important;
            border-radius: 16px;
            border: 1px solid #1e2a3a;
        }
        .modal-header {
            border-bottom: 1px solid #1e2a3a;
        }
        .modal-footer {
            border-top: 1px solid #1e2a3a;
        }
        .modal-title {
            color: #ffd700 !important;
        }
        .modal-title i {
            color: #ffd700 !important;
        }
        
        /* ===== HEADINGS ===== */
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 {
            color: #e5e7eb !important;
        }
        .border-bottom {
            border-color: #1e2a3a !important;
        }
        .text-muted {
            color: #6b7280 !important;
        }
        .auto-fill-badge {
            background: rgba(255, 215, 0, 0.1) !important;
            color: #ffd700 !important;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
            margin-left: 6px;
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
            .stat-box {
                padding: 15px;
            }
            .stat-box .number {
                font-size: 22px;
            }
            .concern-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

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
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
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
                        <a class="nav-link active" href="concerns.php"><i class="fas fa-exclamation-circle"></i> Concerns</a>
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
                    <a href="../../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- ===== SIDEBAR ===== -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2" style="font-size:24px; font-weight:700;"><i class="fas fa-exclamation-circle me-2" style="color: #ffd700;"></i>My Concerns</h1>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#concernModal">
                <i class="fas fa-plus me-1"></i> New Concern
            </button>
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

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="number"><?php echo count($concerns); ?></div>
                    <div class="label">Total Concerns</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="number" style="color:#f59e0b;"><?php echo $pendingCount; ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="number" style="color:#10b981;"><?php echo $resolvedCount; ?></div>
                    <div class="label">Resolved</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <div class="number" style="color:#3b82f6;"><?php echo date('M d'); ?></div>
                    <div class="label">Today</div>
                </div>
            </div>
        </div>

        <!-- Concerns List -->
        <?php if (empty($concerns)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No concerns submitted yet</h5>
                    <p class="text-muted">Click "New Concern" to report an issue</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#concernModal">
                        <i class="fas fa-plus me-1"></i> New Concern
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($concerns as $concern): ?>
                <div class="concern-card status-<?php echo $concern['status']; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="subject">
                                <?php echo htmlspecialchars($concern['subject']); ?>
                                <span class="badge <?php echo $concern['status'] == 'pending' ? 'bg-warning' : ($concern['status'] == 'in_progress' ? 'bg-info' : ($concern['status'] == 'resolved' ? 'bg-success' : 'bg-secondary')); ?> ms-1">
                                    <?php echo ucfirst(str_replace('_', ' ', $concern['status'])); ?>
                                </span>
                                <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($concern['category']); ?></span>
                                <span class="badge badge-priority-<?php echo $concern['priority']; ?> ms-1">
                                    <?php echo ucfirst($concern['priority']); ?>
                                </span>
                            </div>
                            <div class="message"><?php echo nl2br(htmlspecialchars($concern['message'])); ?></div>
                            <div class="meta">
                                <i class="fas fa-door-open me-1"></i>
                                <span class="room-badge"><i class="fas fa-bed me-1"></i> Room <?php echo htmlspecialchars($concern['room_number'] ?? 'N/A'); ?></span>
                                <span class="mx-1">•</span>
                                <i class="far fa-calendar-alt me-1"></i>
                                <?php echo date('M d, Y h:i A', strtotime($concern['created_at'])); ?>
                            </div>
                            <?php if (!empty($concern['admin_response'])): ?>
                                <div class="response">
                                    <div class="admin"><i class="fas fa-user-tie me-1"></i> Admin Response</div>
                                    <div class="text"><?php echo nl2br(htmlspecialchars($concern['admin_response'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Concern Modal -->
        <div class="modal fade" id="concernModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-circle me-2"></i>Submit Concern</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Room Number <span class="required">*</span></label>
                                <input type="text" class="form-control" name="room_number" value="<?php echo htmlspecialchars($room_number); ?>" disabled>
                                <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room_number); ?>">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Auto-filled from your profile
                                    <span class="auto-fill-badge"><i class="fas fa-check me-1"></i>Auto</span>
                                </small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subject <span class="required">*</span></label>
                                <input type="text" class="form-control" name="subject" placeholder="Brief subject" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <option value="maintenance">Maintenance</option>
                                    <option value="security">Security</option>
                                    <option value="cleanliness">Cleanliness</option>
                                    <option value="noise">Noise</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message <span class="required">*</span></label>
                                <textarea class="form-control" name="message" rows="5" placeholder="Describe your concern in detail" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="submit_concern" class="btn btn-submit">
                                <i class="fas fa-paper-plane me-1"></i> Submit Concern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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