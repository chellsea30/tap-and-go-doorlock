<?php
/**
 * Tap-and-Go Doorlock - Student Access History
 * DARK MODE - FULLY READABLE
 */

session_start();

require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

if (!isset($_SESSION['student_id']) || !isStudentSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

$error = '';
$success = '';

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
    header('Location: dashboard.php');
    exit();
}

// ============================================================
// FIND USER IN users TABLE
// ============================================================
$user_id = null;
$studentIdNumber = $_SESSION['student_id_number'] ?? '';

// Try to find by student_id
$stmt = $conn->prepare("SELECT user_id FROM users WHERE student_id = ?");
$stmt->bind_param("s", $studentIdNumber);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_id = $row['user_id'];
}
$stmt->close();

// If not found, try by email
if (!$user_id && !empty($studentInfo['email'])) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $studentInfo['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
    }
    $stmt->close();
}

// If still not found, try by full_name
if (!$user_id && !empty($_SESSION['full_name'])) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE full_name LIKE ?");
    $name = '%' . $_SESSION['full_name'] . '%';
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
    }
    $stmt->close();
}

// ============================================================
// GET ACCESS HISTORY
// ============================================================
$accessHistory = [];
$totalRecords = 0;

if ($user_id) {
    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM access_logs WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalRecords = $row['total'];
    }
    $stmt->close();

    // Get access logs
    $stmt = $conn->prepare("
        SELECT 
            al.*,
            c.card_uid,
            c.card_type,
            u.full_name as user_name,
            u.room_number
        FROM access_logs al
        LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.user_id = ?
        ORDER BY al.timestamp DESC 
        LIMIT 100
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
    'total' => $totalRecords,
    'granted' => 0,
    'denied' => 0,
    'entries' => 0,
    'exits' => 0
];

if ($user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM access_logs WHERE user_id = ? AND access_status = 'granted'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['granted'] = $row['count'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM access_logs WHERE user_id = ? AND access_status = 'denied'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['denied'] = $row['count'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM access_logs WHERE user_id = ? AND access_type = 'entry'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['entries'] = $row['count'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM access_logs WHERE user_id = ? AND access_type = 'exit'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['exits'] = $row['count'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access History - Student</title>
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
        
        /* ===== CARDS ===== */
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
            padding: 15px 20px;
        }
        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #ffd700 !important;
        }
        .card-body {
            padding: 20px;
            background: transparent !important;
        }
        .card .text-muted {
            color: #6b7280 !important;
        }
        .card .text-warning {
            color: #fbbf24 !important;
        }
        
        /* ===== STAT CARDS ===== */
        .stat-card {
            background: #131926 !important;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #1e2a3a;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }
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
        
        /* ===== TABLE ===== */
        .log-table {
            font-size: 13px;
            color: #e5e7eb !important;
            background: transparent !important;
        }
        .log-table thead th {
            font-weight: 600;
            color: #9ca3af !important;
            border-bottom: 2px solid #1e2a3a !important;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: transparent !important;
            padding: 10px 12px;
        }
        .log-table tbody td {
            vertical-align: middle;
            padding: 8px 12px;
            border-bottom: 1px solid #1e2a3a !important;
            color: #d1d5db !important;
            background: transparent !important;
        }
        .log-table tbody tr {
            background: transparent !important;
            transition: all 0.3s ease;
        }
        .log-table tbody tr:hover {
            background: rgba(255, 215, 0, 0.05) !important;
        }
        .log-table tbody tr:hover td {
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
        
        /* ===== BADGES ===== */
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
        .badge.bg-warning {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
        }
        .badge.bg-info {
            background: rgba(6, 182, 212, 0.2) !important;
            color: #67e8f9 !important;
        }
        
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
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
        .text-warning {
            color: #fbbf24 !important;
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
            .stat-card {
                padding: 12px 15px;
            }
            .stat-number {
                font-size: 18px;
            }
            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
            .log-table thead th,
            .log-table tbody td {
                padding: 6px 8px;
                font-size: 12px;
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
                        <a class="nav-link active" href="access-history.php"><i class="fas fa-clock"></i> Access History</a>
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
            <h1 class="h2" style="font-size:24px; font-weight:700;"><i class="fas fa-clock me-2" style="color: #ffd700;"></i>Access History</h1>
            <span class="badge bg-info"><?php echo count($accessHistory); ?> records</span>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #667eea;"><i class="fas fa-list"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $stats['granted']; ?></div>
                        <div class="stat-label">Granted</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-times-circle"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $stats['denied']; ?></div>
                        <div class="stat-label">Denied</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-sign-in-alt"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $stats['entries'] + $stats['exits']; ?></div>
                        <div class="stat-label">Entry/Exit</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Access Logs Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list me-2"></i>Access Logs</h5>
                <?php if ($user_id): ?>
                    <span class="text-muted small">User ID: <?php echo $user_id; ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover log-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>RFID UID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Power Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($accessHistory)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
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
                                        <td>
                                            <span class="badge <?php echo $log['power_source'] == 'main' ? 'bg-success' : 'bg-warning'; ?>">
                                                <i class="fas <?php echo $log['power_source'] == 'main' ? 'fa-bolt' : 'fa-battery-quarter'; ?> me-1"></i>
                                                <?php echo ucfirst($log['power_source']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($totalRecords > 100): ?>
            <div class="text-center text-muted small mt-3">
                <i class="fas fa-info-circle me-1"></i>
                Showing last 100 records. Total: <?php echo $totalRecords; ?> records.
            </div>
        <?php endif; ?>
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