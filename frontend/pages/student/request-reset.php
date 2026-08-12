<?php
/**
 * Tap-and-Go Doorlock - Student Password Reset Request
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

$studentInfo = null;
$stmt = $conn->prepare("SELECT * FROM student_users WHERE student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$studentInfo = $result->fetch_assoc();
$stmt->close();

$hasPending = false;
$stmt = $conn->prepare("SELECT request_id FROM password_reset_requests WHERE student_id = ? AND status = 'pending'");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $hasPending = true;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        $error = 'Please provide a reason for password reset request.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO password_reset_requests (
                student_id, student_name, student_id_number, username, email, reason
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssss", 
            $_SESSION['student_id'],
            $studentInfo['full_name'],
            $studentInfo['student_id_number'],
            $studentInfo['username'],
            $studentInfo['email'],
            $reason
        );
        
        if ($stmt->execute()) {
            $success = "✅ Password reset request submitted successfully!<br><small>The admin will review your request and notify you.</small>";
            $hasPending = true;
        } else {
            $error = "Failed to submit request: " . $stmt->error;
        }
        $stmt->close();
    }
}

$requests = [];
$stmt = $conn->prepare("SELECT * FROM password_reset_requests WHERE student_id = ? ORDER BY requested_at DESC");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Password Reset - Student</title>
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
        
        /* ===== FORM ===== */
        .form-label {
            color: #d1d5db !important;
            font-weight: 500;
            font-size: 13px;
        }
        .form-control {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus {
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
        .text-danger {
            color: #ef4444 !important;
        }
        
        /* ===== BUTTONS ===== */
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
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* ===== REQUEST CARDS ===== */
        .request-card {
            background: #0d1220 !important;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
            border: 1px solid #1e2a3a;
            box-shadow: 0 1px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .request-card:hover {
            border-color: #ffd700;
        }
        .request-card.status-pending { border-left-color: #f59e0b; }
        .request-card.status-approved { border-left-color: #10b981; }
        .request-card.status-denied { border-left-color: #ef4444; }
        .request-card .date {
            font-size: 12px;
            color: #6b7280 !important;
        }
        .request-card .reason {
            font-size: 14px;
            color: #d1d5db !important;
            margin: 5px 0;
        }
        .request-card .reason strong {
            color: #ffd700 !important;
        }
        .request-card .response {
            font-size: 13px;
            color: #d1d5db !important;
            background: #0a0e1a !important;
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: 5px;
            border: 1px solid #1e2a3a;
        }
        .request-card .response strong {
            color: #ffd700 !important;
        }
        .request-card .badge.bg-warning {
            background: rgba(245, 158, 11, 0.2) !important;
            color: #fbbf24 !important;
        }
        .request-card .badge.bg-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
        }
        .request-card .badge.bg-danger {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #fca5a5 !important;
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
        .alert-warning {
            background: rgba(245, 158, 11, 0.15) !important;
            border-color: #f59e0b !important;
            color: #fbbf24 !important;
        }
        .btn-close {
            filter: invert(1) !important;
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
            .request-card {
                padding: 12px 15px;
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
                        <a class="nav-link" href="concerns.php"><i class="fas fa-exclamation-circle"></i> Concerns</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="request-reset.php"><i class="fas fa-key"></i> Reset Password</a>
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
            <h1 class="h2" style="font-size:24px; font-weight:700;"><i class="fas fa-key me-2" style="color: #ffd700;"></i>Request Password Reset</h1>
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

        <!-- Submit Request Card -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-pen me-2"></i>Submit Password Reset Request</h5>
            </div>
            <div class="card-body">
                <?php if ($hasPending): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        <strong>You have a pending password reset request.</strong>
                        Please wait for the admin to review your request.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Reason for Password Reset <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reason" rows="4" placeholder="Please explain why you need to reset your password..." required <?php echo $hasPending ? 'disabled' : ''; ?>></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" name="request_reset" class="btn btn-submit" <?php echo $hasPending ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane me-1"></i> Submit Request
                        </button>
                        <?php if ($hasPending): ?>
                            <span class="text-muted ms-2"><i class="fas fa-info-circle me-1"></i> Request pending approval</span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Request History -->
        <?php if (!empty($requests)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>Request History</h5>
            </div>
            <div class="card-body">
                <?php foreach ($requests as $request): ?>
                    <div class="request-card status-<?php echo $request['status']; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <strong style="color: #ffd700;">Request #<?php echo $request['request_id']; ?></strong>
                                <span class="badge <?php echo $request['status'] == 'pending' ? 'bg-warning' : ($request['status'] == 'approved' ? 'bg-success' : 'bg-danger'); ?> ms-2">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </span>
                            <span class="date">
                                <i class="far fa-calendar-alt me-1"></i>
                                <?php echo date('M d, Y h:i A', strtotime($request['requested_at'])); ?>
                            </span>
                        </div>
                        <div class="reason">
                            <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                        </div>
                        <?php if (!empty($request['admin_response'])): ?>
                            <div class="response">
                                <strong><i class="fas fa-reply me-1"></i> Admin Response:</strong>
                                <?php echo nl2br(htmlspecialchars($request['admin_response'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-muted small text-center mt-3">
            <i class="fas fa-info-circle me-1"></i>
            Admin will review your request and reset your password if approved.
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